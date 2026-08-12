<?php
declare(strict_types=1);

final class TransferController extends Controller
{
    protected ?string $requirePermission = 'view_balances';
    protected array $permissions = [
        'createForm' => 'manage_accounts',
        'store' => 'manage_accounts',
    ];

    public function index(): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        [$rows, $total, $page, $pages] = $this->paginate(
            "SELECT tr.*, c.code AS currency_code, src.name AS source_name, dst.name AS destination_name, u.full_name AS employee_name
             FROM transfers tr
             JOIN currencies c ON c.id = tr.currency_id
             JOIN accounts src ON src.id = tr.source_account_id
             JOIN accounts dst ON dst.id = tr.destination_account_id
             LEFT JOIN users u ON u.id = tr.employee_id
             ORDER BY tr.id DESC", [], $page, 20);
        $this->render('transfers/index', ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages]);
    }

    public function createForm(): void
    {
        $data = [
            'accounts' => Database::query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY name"),
            'currencies' => Database::query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY code"),
            'base' => SettingService::baseCurrency(),
        ];
        if ($this->isAjax()) {
            $this->renderBare('transfers/form', $data);
            return;
        }
        $this->render('transfers/form', $data);
    }

    public function store(): void
    {
        $this->csrfGuard();
        $v = new Validator($_POST, ['amount' => t('transfer.amount'), 'currency_id' => t('transfer.currency')]);
        $v->required('amount')->positive('amount')->required('currency_id');
        if (!$v->passes()) {
            $this->fail($v->firstError(), '/transfers/create', $v->errors());
        }
        $src = (int)($_POST['source_account_id'] ?? 0);
        $dst = (int)($_POST['destination_account_id'] ?? 0);
        $curId = (int)$_POST['currency_id'];
        $amount = Money::round((string)$_POST['amount'], 10);

        if ($src === $dst || $src === 0 || $dst === 0) {
            $this->fail(t('transfer.invalid_accounts'), '/transfers/create');
        }

        try {
            $result = Database::transaction(function () use ($src, $dst, $curId, $amount) {
                // Sufficient funds check
                $balance = LedgerService::accountBalance($src, $curId);
                if (Money::compare($balance, $amount) < 0) {
                    throw new DomainException(t('transfer.insufficient'));
                }

                $base = SettingService::baseCurrency();
                $rate = $curId === (int)$base['id'] ? '1' : LedgerService::currentRatePublic($curId);
                if (Money::isZero($rate)) throw new DomainException(t('tx.rate_missing'));
                $baseAmount = Money::round(Money::mul($amount, $rate), 10);

                $refNumber = TransactionService::nextRefNumber('TR');
                $refId = Database::insert('transfers', [
                    'ref_number' => $refNumber,
                    'source_account_id' => $src,
                    'destination_account_id' => $dst,
                    'currency_id' => $curId,
                    'amount' => $amount,
                    'base_amount' => $baseAmount,
                    'rate' => $rate,
                    'transfer_date' => $_POST['transfer_date'] ?? date('Y-m-d'),
                    'note' => $_POST['note'] ?? null,
                    'employee_id' => Auth::id(),
                ]);

                // Journal: Debit destination / Credit source — NO revenue or expense
                $entryId = LedgerService::post([
                    ['account_id' => $dst, 'currency_id' => $curId, 'debit' => $amount, 'rate' => $rate, 'note' => 'Transfer in'],
                    ['account_id' => $src, 'currency_id' => $curId, 'credit' => $amount, 'rate' => $rate, 'note' => 'Transfer out'],
                ], 'TRANSFER ' . $refNumber, null, null, LedgerService::nextEntryNo());

                AuditService::log('create_transfer', 'transfer', $refId, null,
                    ['from' => $src, 'to' => $dst, 'currency' => $curId, 'amount' => $amount, 'entry_id' => $entryId]);
                return $refId;
            });
            $this->succeed(t('transfer.done'), '/transfers', ['id' => (int)$result]);
        } catch (DomainException $e) {
            $this->fail($e->getMessage(), '/transfers');
        }
    }
}

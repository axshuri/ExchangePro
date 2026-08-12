<?php
declare(strict_types=1);

final class ReconciliationController extends Controller
{
    protected ?string $requirePermission = 'view_inventory';
    protected array $permissions = [
        'store' => 'perform_reconciliation',
        'approve' => 'adjust_balance',
    ];

    public function index(): void
    {
        $rows = Database::query(
            "SELECT r.*, a.name AS account_name, c.code AS currency_code,
                    cb.full_name AS created_by_name, ab.full_name AS approved_by_name
             FROM reconciliations r
             JOIN accounts a ON a.id = r.account_id
             JOIN currencies c ON c.id = r.currency_id
             JOIN users cb ON cb.id = r.created_by
             LEFT JOIN users ab ON ab.id = r.approved_by
             ORDER BY r.id DESC LIMIT 100");
        $this->render('reconciliation/index', [
            'rows' => $rows,
            'accounts' => Database::query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY name"),
            'currencies' => Database::query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY code"),
            'positions' => LedgerService::positions(),
        ]);
    }

    public function store(): void
    {
        Csrf::check();
        $accountId = (int)($_POST['account_id'] ?? 0);
        $currencyId = (int)($_POST['currency_id'] ?? 0);
        $physical = (string)($_POST['physical_balance'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        $v = new Validator($_POST, ['physical_balance' => t('recon.physical'), 'reason' => t('recon.reason')]);
        $v->required('physical_balance')->required('reason');
        if (!$v->passes() || !$accountId || !$currencyId || !is_numeric($physical)) {
            Session::flash('danger', t('recon.invalid'));
            redirect('/reconciliation');
        }

        $system = LedgerService::accountBalance($accountId, $currencyId);
        $difference = Money::sub(Money::round($physical, 10), $system);

        $recId = Database::insert('reconciliations', [
            'rec_number' => TransactionService::nextRefNumber('RC'),
            'account_id' => $accountId,
            'currency_id' => $currencyId,
            'system_balance' => $system,
            'physical_balance' => Money::round($physical, 10),
            'difference' => $difference,
            'reason' => $reason,
            'status' => Money::isZero($difference) ? 'approved' : 'pending',
            'created_by' => Auth::id(),
        ]);
        if (Money::isZero($difference)) {
            Database::update('reconciliations', ['approved_by' => Auth::id(), 'approved_at' => date('Y-m-d H:i:s')], 'id = ?', [$recId]);
        }
        AuditService::log('create_reconciliation', 'reconciliation', $recId, null,
            ['account' => $accountId, 'currency' => $currencyId, 'system' => $system, 'physical' => $physical, 'difference' => $difference]);
        Session::flash(Money::isZero($difference) ? 'success' : 'warning',
            t('recon.created') . ' ' . t('recon.difference') . ': ' . $difference);
        redirect('/reconciliation');
    }

    public function approve(string $id): void
    {
        Csrf::check();
        $rec = Database::fetch("SELECT * FROM reconciliations WHERE id = ?", [$id]);
        if (!$rec || $rec['status'] !== 'pending') {
            Session::flash('warning', t('recon.not_pending'));
            redirect('/reconciliation');
        }
        try {
            Database::transaction(function () use ($rec) {
                Database::update('reconciliations', [
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$rec['id']]);
                if (!Money::isZero((string)$rec['difference'])) {
                    TransactionService::adjustInventory(
                        (int)$rec['account_id'], (int)$rec['currency_id'],
                        (string)$rec['difference'], (string)$rec['reason'], (int)$rec['id']);
                }
            });
            AuditService::log('approve_reconciliation', 'reconciliation', (int)$id, ['status' => 'pending'], ['status' => 'approved']);
            Session::flash('success', t('recon.approved'));
        } catch (DomainException $e) {
            Session::flash('danger', $e->getMessage());
        }
        redirect('/reconciliation');
    }
}

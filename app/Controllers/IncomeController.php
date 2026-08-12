<?php
declare(strict_types=1);

final class IncomeController extends Controller
{
    protected ?string $requirePermission = 'view_reports';
    protected array $permissions = [
        'createForm' => 'manage_expenses',
        'store' => 'manage_expenses',
    ];

    public function index(): void
    {
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $where = ['1=1'];
        $params = [];
        if ($from) { $where[] = 'i.income_date >= ?'; $params[] = $from; }
        if ($to) { $where[] = 'i.income_date <= ?'; $params[] = $to; }
        [$rows, $total, $page, $pages] = $this->paginate(
            "SELECT i.*, c.code AS currency_code, a.name AS account_name, u.full_name AS employee_name
             FROM income i
             LEFT JOIN currencies c ON c.id = i.currency_id
             LEFT JOIN accounts a ON a.id = i.account_id
             LEFT JOIN users u ON u.id = i.employee_id
             WHERE " . implode(' AND ', $where) . " ORDER BY i.id DESC",
            $params, $page, 20);
        $totals = Database::fetch(
            "SELECT COALESCE(SUM(base_amount),0) AS total, COUNT(*) AS cnt FROM income i WHERE " . implode(' AND ', $where),
            $params);
        $this->render('income/index', ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'totals' => $totals, 'from' => $from, 'to' => $to]);
    }

    public function createForm(): void
    {
        $data = [
            'currencies' => Database::query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY code"),
            'accounts' => Database::query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY name"),
            'base' => SettingService::baseCurrency(),
        ];
        if ($this->isAjax()) {
            $this->renderBare('income/form', $data);
            return;
        }
        $this->render('income/form', $data);
    }

    public function store(): void
    {
        $this->csrfGuard();
        $v = new Validator($_POST, ['amount' => t('income.amount')]);
        $v->required('amount')->positive('amount')->date('income_date');
        if (!$v->passes()) {
            $this->fail($v->firstError(), '/income/create', $v->errors());
        }
        try {
            $result = Database::transaction(function () {
                $amount = Money::round((string)$_POST['amount'], 10);
                $base = SettingService::baseCurrency();
                $curId = (int)($_POST['currency_id'] ?? $base['id']);
                $rate = $curId === (int)$base['id'] ? '1' : LedgerService::currentRatePublic($curId);
                if (Money::isZero($rate)) throw new DomainException(t('tx.rate_missing'));
                $baseAmount = Money::round(Money::mul($amount, $rate), 10);
                $accountId = (int)($_POST['account_id'] ?? 0);

                $incId = Database::insert('income', [
                    'ref_number' => TransactionService::nextRefNumber('INC'),
                    'category' => $_POST['category'] ?? 'other',
                    'amount' => $amount,
                    'currency_id' => $curId,
                    'base_amount' => $baseAmount,
                    'rate' => $rate,
                    'account_id' => $accountId,
                    'income_date' => $_POST['income_date'] ?? date('Y-m-d'),
                    'description' => $_POST['description'] ?? null,
                    'employee_id' => Auth::id(),
                ]);

                $glId = Database::insert('gl_accounts', [
                    'code' => 'INC_' . strtoupper(preg_replace('/[^a-zA-Z0-9]/', '_', $_POST['category'] ?? 'other')),
                    'name' => ucfirst(str_replace('_', ' ', $_POST['category'] ?? 'other')),
                    'type' => 'income', 'is_system' => 1,
                ]);
                Database::update('income', ['gl_account_id' => $glId], 'id = ?', [$incId]);

                LedgerService::post([
                    ['account_id' => $accountId, 'currency_id' => $curId,
                        'debit' => $amount, 'rate' => $rate, 'note' => 'Other income'],
                    ['gl_account_id' => $glId, 'currency_id' => $base['id'],
                        'credit' => $baseAmount, 'rate' => '1', 'note' => 'Other income'],
                ], 'INCOME ' . ($_POST['category'] ?? 'other'), null, null, LedgerService::nextEntryNo());

                AuditService::log('create_income', 'income', $incId, null,
                    ['category' => $_POST['category'] ?? 'other', 'amount' => $amount, 'base_amount' => $baseAmount]);
                return $incId;
            });
            $this->succeed(t('income.done'), '/income', ['id' => (int)$result]);
        } catch (DomainException $e) {
            $this->fail($e->getMessage(), '/income');
        }
    }
}

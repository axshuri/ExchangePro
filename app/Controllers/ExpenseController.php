<?php
declare(strict_types=1);

final class ExpenseController extends Controller
{
    protected ?string $requirePermission = 'view_reports';
    protected array $permissions = [
        'createForm' => 'manage_expenses',
        'store' => 'manage_expenses',
    ];

    public const CATEGORIES = [
        'rent', 'salary', 'electricity', 'internet', 'transportation',
        'bank_fees', 'transfer_fees', 'office_supplies', 'maintenance',
        'taxes', 'other',
    ];

    public function index(): void
    {
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $category = $_GET['category'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));

        $where = ['1=1'];
        $params = [];
        if ($from) { $where[] = 'e.expense_date >= ?'; $params[] = $from; }
        if ($to) { $where[] = 'e.expense_date <= ?'; $params[] = $to; }
        if ($category !== '') { $where[] = 'e.category = ?'; $params[] = $category; }

        [$rows, $total, $page, $pages] = $this->paginate(
            "SELECT e.*, c.code AS currency_code, a.name AS account_name, u.full_name AS employee_name
             FROM expenses e
             LEFT JOIN currencies c ON c.id = e.currency_id
             LEFT JOIN accounts a ON a.id = e.account_id
             LEFT JOIN users u ON u.id = e.employee_id
             WHERE " . implode(' AND ', $where) . " ORDER BY e.id DESC",
            $params, $page, 20);

        $totals = Database::fetch(
            "SELECT COALESCE(SUM(e.base_amount),0) AS total,
                    COALESCE(SUM(CASE WHEN e.expense_date = ? THEN e.base_amount ELSE 0 END),0) AS today_total,
                    COUNT(*) AS cnt
             FROM expenses e WHERE " . implode(' AND ', $where), array_merge([date('Y-m-d')], $params));

        $this->render('expenses/index', [
            'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'totals' => $totals, 'from' => $from, 'to' => $to, 'category' => $category,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function createForm(): void
    {
        $data = [
            'categories' => self::CATEGORIES,
            'currencies' => Database::query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY code"),
            'accounts' => Database::query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY name"),
            'base' => SettingService::baseCurrency(),
        ];
        if ($this->isAjax()) {
            $this->renderBare('expenses/form', $data);
            return;
        }
        $this->render('expenses/form', $data);
    }

    public function store(): void
    {
        $this->csrfGuard();
        $v = new Validator($_POST, ['amount' => t('expense.amount'), 'category' => t('expense.category')]);
        $v->required('amount')->positive('amount')->required('category')->date('expense_date');
        if (!$v->passes()) {
            $this->fail($v->firstError(), '/expenses/create', $v->errors());
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

                // Check funds in payment account (base currency)
                if ($curId === (int)$base['id']) {
                    $bal = LedgerService::accountBalance($accountId, $curId);
                    if (Money::compare($bal, $amount) < 0) {
                        throw new DomainException(t('tx.insufficient'));
                    }
                }

                $expId = Database::insert('expenses', [
                    'ref_number' => TransactionService::nextRefNumber('EXP'),
                    'category' => $_POST['category'],
                    'amount' => $amount,
                    'currency_id' => $curId,
                    'base_amount' => $baseAmount,
                    'rate' => $rate,
                    'account_id' => $accountId,
                    'expense_date' => $_POST['expense_date'] ?? date('Y-m-d'),
                    'description' => $_POST['description'] ?? null,
                    'employee_id' => Auth::id(),
                    'reference_no' => $_POST['reference_no'] ?? null,
                    'gl_account_id' => self::expenseGlId($_POST['category']),
                ]);

                // Journal: Debit Expense / Credit Cash
                LedgerService::post([
                    ['gl_account_id' => self::expenseGlId($_POST['category']), 'currency_id' => $base['id'],
                        'debit' => $baseAmount, 'rate' => '1', 'note' => 'Expense ' . $_POST['category']],
                    ['account_id' => $accountId, 'currency_id' => $curId,
                        'credit' => $amount, 'rate' => $rate, 'note' => 'Expense payment'],
                ], 'EXPENSE ' . $_POST['category'], null, null, LedgerService::nextEntryNo());

                AuditService::log('create_expense', 'expense', $expId, null,
                    ['category' => $_POST['category'], 'amount' => $amount, 'base_amount' => $baseAmount]);
                return $expId;
            });
            $this->succeed(t('expense.done'), '/expenses', ['id' => (int)$result]);
        } catch (DomainException $e) {
            $this->fail($e->getMessage(), '/expenses');
        }
    }

    public static function expenseGlId(string $category): int
    {
        $code = 'EXP_' . strtoupper(preg_replace('/[^a-zA-Z0-9]/', '_', $category));
        $g = Database::fetch("SELECT id FROM gl_accounts WHERE code = ?", [$code]);
        if ($g) return (int)$g['id'];
        $id = Database::insert('gl_accounts', [
            'code' => $code, 'name' => ucfirst(str_replace('_', ' ', $category)), 'type' => 'expense', 'is_system' => 1,
        ]);
        return (int)$id;
    }
}

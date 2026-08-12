<?php
declare(strict_types=1);

final class AccountController extends Controller
{
    protected ?string $requirePermission = 'view_balances';
    protected array $permissions = [
        'createForm' => 'manage_accounts',
        'store' => 'manage_accounts',
        'editForm' => 'manage_accounts',
        'update' => 'manage_accounts',
    ];

    public function index(): void
    {
        $type = $_GET['type'] ?? '';
        $where = '';
        $params = [];
        if (in_array($type, ['cash_desk', 'vault', 'bank', 'wallet', 'other'], true)) {
            $where = 'WHERE a.type = ?';
            $params[] = $type;
        }
        $rows = Database::query(
            "SELECT a.*,
                    (SELECT COUNT(DISTINCT currency_id) FROM account_currencies ac WHERE ac.account_id = a.id AND ac.balance <> 0) AS currencies_held
             FROM accounts a $where ORDER BY a.type, a.name", $params);

        // balances per account
        foreach ($rows as &$r) {
            $r['balances'] = Database::query(
                "SELECT ac.*, c.code, c.symbol, c.amount_precision FROM account_currencies ac
                 JOIN currencies c ON c.id = ac.currency_id
                 WHERE ac.account_id = ? AND ac.balance <> 0 ORDER BY c.code", [$r['id']]);
        }

        $this->render('accounts/index', [
            'rows' => $rows, 'type' => $type,
            'base' => SettingService::baseCurrency(),
            'currencies' => Database::query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY code"),
        ]);
    }

    public function createForm(): void
    {
        if ($this->isAjax()) {
            $this->renderBare('accounts/form', ['account' => null]);
            return;
        }
        $this->render('accounts/form', ['account' => null]);
    }

    public function store(): void
    {
        $this->csrfGuard();
        $v = new Validator($_POST, ['name' => t('account.name'), 'code' => t('account.code')]);
        $v->required('name')->required('code')->in('type', ['cash_desk', 'vault', 'bank', 'wallet', 'other']);
        if (!$v->passes()) {
            $this->fail($v->firstError(), '/accounts/create', $v->errors());
        }
        $code = strtoupper(trim($_POST['code']));
        if (Database::fetch("SELECT id FROM accounts WHERE code = ?", [$code])) {
            $this->fail(t('account.code_exists'), '/accounts/create');
        }
        $id = Database::insert('accounts', [
            'code' => $code,
            'name' => trim($_POST['name']),
            'type' => $_POST['type'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'bank_name' => $_POST['bank_name'] ?? null,
            'account_number' => $_POST['account_number'] ?? null,
            'account_holder' => $_POST['account_holder'] ?? null,
            'notes' => $_POST['notes'] ?? null,
        ]);
        AuditService::log('create_account', 'account', $id, null, ['code' => $code, 'name' => $_POST['name']]);
        $this->succeed(t('account.created'), '/accounts');
    }

    public function show(string $id): void
    {
        $account = Database::fetch("SELECT * FROM accounts WHERE id = ?", [$id]);
        if (!$account) redirect('/accounts');
        $balances = Database::query(
            "SELECT ac.*, c.code, c.symbol, c.amount_precision FROM account_currencies ac
             JOIN currencies c ON c.id = ac.currency_id WHERE ac.account_id = ? ORDER BY c.code", [$id]);
        $movements = Database::query(
            "SELECT m.*, c.code AS currency_code, u.full_name AS user_name
             FROM inventory_movements m
             LEFT JOIN currencies c ON c.id = m.currency_id
             LEFT JOIN journal_entries je ON je.transaction_id = m.transaction_id
             LEFT JOIN users u ON u.id = je.created_by
             WHERE m.account_id = ? ORDER BY m.id DESC LIMIT 50", [$id]);
        $this->render('accounts/show', [
            'account' => $account, 'balances' => $balances, 'movements' => $movements,
            'base' => SettingService::baseCurrency(),
        ]);
    }

    public function editForm(string $id): void
    {
        $account = Database::fetch("SELECT * FROM accounts WHERE id = ?", [$id]);
        if (!$account) redirect('/accounts');
        $this->render('accounts/form', ['account' => $account]);
    }

    public function update(string $id): void
    {
        Csrf::check();
        $account = Database::fetch("SELECT * FROM accounts WHERE id = ?", [$id]);
        if (!$account) redirect('/accounts');
        $before = ['name' => $account['name'], 'is_active' => $account['is_active'], 'type' => $account['type']];
        Database::update('accounts', [
            'code' => strtoupper(trim($_POST['code'])),
            'name' => trim($_POST['name']),
            'type' => $_POST['type'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'bank_name' => $_POST['bank_name'] ?? null,
            'account_number' => $_POST['account_number'] ?? null,
            'account_holder' => $_POST['account_holder'] ?? null,
            'notes' => $_POST['notes'] ?? null,
        ], 'id = ?', [$id]);
        AuditService::log('update_account', 'account', (int)$id, $before,
            ['name' => $_POST['name'], 'type' => $_POST['type']]);
        Session::flash('success', t('account.updated'));
        redirect('/accounts');
    }
}

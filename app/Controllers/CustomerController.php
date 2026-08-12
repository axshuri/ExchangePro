<?php
declare(strict_types=1);

final class CustomerController extends Controller
{
    protected ?string $requirePermission = 'view_customers';
    protected array $permissions = [
        'createForm' => 'manage_customers',
        'store' => 'manage_customers',
        'storeAjax' => 'manage_customers',
        'editForm' => 'manage_customers',
        'update' => 'manage_customers',
    ];

    /** AJAX autocomplete: /customers/search?q=…  →  JSON list. */
    public function searchJson(): void
    {
        $q = trim($_GET['q'] ?? '');
        $limit = min(20, max(1, (int)($_GET['limit'] ?? 20)));
        $rows = CustomerService::search($q, $limit);
        $this->json(array_map(static fn(array $c): array => [
            'id' => (int)$c['id'],
            'full_name' => $c['full_name'],
            'code' => $c['code'],
            'phone' => $c['phone'] ?? '',
        ], $rows));
    }

    /** AJAX create: POST /customers/ajax  →  JSON {ok, id, full_name, code}. */
    public function storeAjax(): void
    {
        // Honor the same security.csrf config flag that Csrf::check() uses.
        $csrfOk = cfg('security.csrf') === false || Csrf::verify($_POST['_csrf'] ?? null);
        if (!$csrfOk) {
            $this->json(['ok' => false, 'error' => t('customer.picker_csrf')], 419);
        }
        $v = new Validator($_POST, ['full_name' => t('customer.full_name')]);
        $v->required('full_name')->maxLen('full_name', 160)->email('email');
        if (!$v->passes()) {
            $this->json(['ok' => false, 'error' => $v->firstError()], 422);
        }
        $id = CustomerService::create($_POST);
        AuditService::log('create_customer', 'customer', $id, null, ['full_name' => $_POST['full_name']]);
        $c = CustomerService::get($id);
        $this->json(['ok' => true, 'id' => (int)$id, 'full_name' => $c['full_name'], 'code' => $c['code']]);
    }

    public function index(): void
    {
        $q = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $where = '';
        $params = [];
        if ($q !== '') {
            $where = "WHERE c.full_name LIKE ? OR c.phone LIKE ? OR c.code LIKE ? OR c.email LIKE ?";
            $params = ["%$q%", "%$q%", "%$q%", "%$q%"];
        }
        [$rows, $total, $page, $pages] = $this->paginate(
            "SELECT c.*, COALESCE((SELECT COUNT(*) FROM transactions t WHERE t.customer_id = c.id AND t.status='completed'),0) AS tx_count,
                    COALESCE((SELECT SUM(balance) FROM customer_accounts ca WHERE ca.customer_id = c.id),0) AS net_balance
             FROM customers c $where ORDER BY c.full_name",
            $params, $page, 20);
        $this->render('customers/index', [
            'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'q' => $q,
        ]);
    }

    public function createForm(): void
    {
        if ($this->isAjax()) {
            $this->renderBare('customers/form', ['customer' => null]);
            return;
        }
        $this->render('customers/form', ['customer' => null]);
    }

    public function store(): void
    {
        $this->csrfGuard();
        $v = new Validator($_POST, ['full_name' => t('customer.full_name')]);
        $v->required('full_name')->maxLen('full_name', 160)->email('email');
        if (!$v->passes()) {
            $this->fail($v->firstError(), '/customers/create', $v->errors());
        }
        $id = CustomerService::create($_POST);
        AuditService::log('create_customer', 'customer', $id, null, ['full_name' => $_POST['full_name']]);
        $this->succeed(t('customer.created'), '/customers/' . $id, ['id' => (int)$id]);
    }

    public function show(string $id): void
    {
        $customer = CustomerService::get((int)$id);
        if (!$customer) redirect('/customers');
        $page = max(1, (int)($_GET['page'] ?? 1));
        [$txs, $total, $page, $pages] = $this->paginate(
            "SELECT t.*, c.code AS currency_code, c.symbol, u.full_name AS employee_name
             FROM transactions t
             LEFT JOIN currencies c ON c.id = t.currency_id
             LEFT JOIN users u ON u.id = t.employee_id
             WHERE t.customer_id = ? ORDER BY t.id DESC",
            [$customer['id']], $page, 15);
        $this->render('customers/show', [
            'customer' => $customer,
            'balances' => CustomerService::balances((int)$id),
            'stats' => CustomerService::stats((int)$id),
            'txs' => $txs, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'audit' => AuditService::recent(20, 'customer', $customer['id']),
        ]);
    }

    public function editForm(string $id): void
    {
        $customer = CustomerService::get((int)$id);
        if (!$customer) redirect('/customers');
        $this->render('customers/form', ['customer' => $customer]);
    }

    public function update(string $id): void
    {
        Csrf::check();
        $v = new Validator($_POST, ['full_name' => t('customer.full_name')]);
        $v->required('full_name')->email('email');
        if (!$v->passes()) {
            Session::flash('danger', $v->firstError());
            redirect('/customers/' . $id . '/edit');
        }
        CustomerService::update((int)$id, $_POST);
        Session::flash('success', t('customer.updated'));
        redirect('/customers/' . $id);
    }

    public function receivables(string $id): void
    {
        $customer = CustomerService::get((int)$id);
        if (!$customer) redirect('/customers');
        $balances = CustomerService::balances((int)$id);
        $this->render('customers/receivables', ['customer' => $customer, 'balances' => $balances]);
    }

    public function ledger(string $id): void
    {
        $customer = CustomerService::get((int)$id);
        if (!$customer) redirect('/customers');
        $f = $this->ledgerFilters();
        $this->render('customers/ledger', [
            'customer' => $customer,
            'data' => CustomerService::ledger((int)$id, $f),
            'balances' => CustomerService::balances((int)$id),
            'currencies' => Database::query(
                "SELECT id, code, name, localized_name FROM currencies WHERE is_active = 1 ORDER BY code"),
            'f' => $f,
            'base' => SettingService::baseCurrency(),
        ]);
    }

    public function ledgerExport(string $id): void
    {
        $customer = CustomerService::get((int)$id);
        if (!$customer) redirect('/customers');
        $rows = CustomerService::ledgerCsv((int)$id, $this->ledgerFilters());
        $this->csv($rows, 'ledger-' . $customer['code']);
    }

    private function ledgerFilters(): array
    {
        // Strict Y-m-d validation — these values are also interpolated into the
        // export filename, so anything else is dropped rather than trusted.
        $date = static function (string $v): string {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        };
        $type = trim($_GET['type'] ?? '');
        $allowedTypes = ['buy', 'sell', 'exchange', 'reversal', 'adjustment'];
        return [
            'from' => $date(trim($_GET['from'] ?? '')),
            'to' => $date(trim($_GET['to'] ?? '')),
            'currency_id' => (int)($_GET['currency_id'] ?? 0) ?: null,
            'type' => in_array($type, $allowedTypes, true) ? $type : '',
        ];
    }
}

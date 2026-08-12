<?php
declare(strict_types=1);

final class TransactionController extends Controller
{
    protected ?string $requirePermission = 'view_transactions';
    protected array $permissions = [
        'buyForm' => 'create_transaction',
        'buy' => 'create_transaction',
        'sellForm' => 'create_transaction',
        'sell' => 'create_transaction',
        'exchangeForm' => 'create_transaction',
        'exchange' => 'create_transaction',
        'cancel' => 'cancel_transaction',
        'approve' => 'cancel_transaction',
    ];

    public function history(): void
    {
        $q = trim($_GET['q'] ?? '');
        $type = $_GET['type'] ?? '';
        $currencyId = (int)($_GET['currency_id'] ?? 0);
        $customerId = (int)($_GET['customer_id'] ?? 0);
        $status = $_GET['status'] ?? '';
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));

        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(t.tx_number LIKE ? OR cu.full_name LIKE ? OR cu.phone LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        if ($type !== '') { $where[] = 't.type = ?'; $params[] = $type; }
        if ($currencyId) { $where[] = 't.currency_id = ?'; $params[] = $currencyId; }
        if ($customerId) { $where[] = 't.customer_id = ?'; $params[] = $customerId; }
        if ($status !== '') { $where[] = 't.status = ?'; $params[] = $status; }
        if ($from !== '') { $where[] = 't.tx_date >= ?'; $params[] = $from . ' 00:00:00'; }
        if ($to !== '') { $where[] = 't.tx_date <= ?'; $params[] = $to . ' 23:59:59'; }
        $whereSql = implode(' AND ', $where);

        [$rows, $total, $page, $pages] = $this->paginate(
            "SELECT t.*, c.code AS currency_code, c.symbol, cu.full_name AS customer_name, u.full_name AS employee_name
             FROM transactions t
             LEFT JOIN currencies c ON c.id = t.currency_id
             LEFT JOIN customers cu ON cu.id = t.customer_id
             LEFT JOIN users u ON u.id = t.employee_id
             WHERE $whereSql ORDER BY t.id DESC",
            $params, $page, 20);

        $this->render('transactions/history', [
            'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'q' => $q, 'type' => $type, 'currency_id' => $currencyId, 'customer_id' => $customerId,
            'status' => $status, 'from' => $from, 'to' => $to,
            'currencies' => Database::query("SELECT id, code FROM currencies WHERE is_active = 1 ORDER BY code"),
            'customers' => Database::query("SELECT id, full_name FROM customers ORDER BY full_name LIMIT 200"),
        ]);
    }

    public function buyForm(): void
    {
        $this->render('transactions/buy', [
            'rates' => RateService::all(),
            'base' => SettingService::baseCurrency(),
            'accounts' => Database::query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY name"),
            'customers' => CustomerService::search(),
            'large_threshold' => SettingService::largeTxThreshold(),
        ]);
    }

    public function buy(): void
    {
        Csrf::check();
        try {
            $tx = TransactionService::buy($_POST);
        } catch (LargeTransactionException $e) {
            Session::flash('warning', t('tx.large_confirm'));
            Session::set('large_pending', $_POST);
            redirect('/transactions/buy?large=1');
        } catch (DomainException $e) {
            Session::flash('danger', $e->getMessage());
            redirect('/transactions/buy');
        }
        Session::flash('success', t('tx.buy_success') . ' ' . $tx['tx_number']);
        redirect('/transactions/' . $tx['id'] . '/receipt');
    }

    public function sellForm(): void
    {
        $this->render('transactions/sell', [
            'rates' => RateService::all(),
            'base' => SettingService::baseCurrency(),
            'accounts' => Database::query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY name"),
            'customers' => CustomerService::search(),
            'inventory' => Database::query(
                "SELECT ic.*, c.code, c.name, c.localized_name, c.symbol, c.amount_precision
                 FROM inventory_costings ic JOIN currencies c ON c.id = ic.currency_id
                 WHERE ic.qty > 0 ORDER BY c.code"),
            'large_threshold' => SettingService::largeTxThreshold(),
        ]);
    }

    public function sell(): void
    {
        Csrf::check();
        try {
            $tx = TransactionService::sell($_POST);
        } catch (LargeTransactionException $e) {
            Session::flash('warning', t('tx.large_confirm'));
            Session::set('large_pending', $_POST);
            redirect('/transactions/sell?large=1');
        } catch (DomainException $e) {
            Session::flash('danger', $e->getMessage());
            redirect('/transactions/sell');
        }
        Session::flash('success', t('tx.sell_success') . ' ' . $tx['tx_number']);
        redirect('/transactions/' . $tx['id'] . '/receipt');
    }

    public function exchangeForm(): void
    {
        $this->render('transactions/exchange', [
            'rates' => RateService::all(),
            'base' => SettingService::baseCurrency(),
            'accounts' => Database::query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY name"),
            'customers' => CustomerService::search(),
            'inventory' => Database::query(
                "SELECT ic.*, c.code, c.symbol
                 FROM inventory_costings ic JOIN currencies c ON c.id = ic.currency_id
                 WHERE ic.qty > 0 ORDER BY c.code"),
            'large_threshold' => SettingService::largeTxThreshold(),
        ]);
    }

    public function exchange(): void
    {
        Csrf::check();
        try {
            $tx = TransactionService::exchange($_POST);
        } catch (LargeTransactionException $e) {
            Session::flash('warning', t('tx.large_confirm'));
            Session::set('large_pending', $_POST);
            redirect('/transactions/exchange?large=1');
        } catch (DomainException $e) {
            Session::flash('danger', $e->getMessage());
            redirect('/transactions/exchange');
        }
        Session::flash('success', t('tx.exchange_success') . ' ' . $tx['tx_number']);
        redirect('/transactions/' . $tx['id'] . '/receipt');
    }

    public function show(string $id): void
    {
        $tx = TransactionService::tx((int)$id);
        if (!$tx) redirect('/transactions');
        $entries = Database::query(
            "SELECT je.*, u.full_name AS user_name FROM journal_entries je
             LEFT JOIN users u ON u.id = je.created_by WHERE je.transaction_id = ? ORDER BY je.id", [$tx['id']]);
        foreach ($entries as &$en) {
            $en['lines'] = Database::query(
                "SELECT l.*, a.name AS account_name, a.type AS account_type, g.name AS gl_name, g.code AS gl_code, c.code AS currency_code
                 FROM journal_lines l
                 LEFT JOIN accounts a ON a.id = l.account_id
                 LEFT JOIN gl_accounts g ON g.id = l.gl_account_id
                 LEFT JOIN currencies c ON c.id = l.currency_id
                 WHERE l.entry_id = ? ORDER BY l.id", [$en['id']]);
        }
        $items = Database::query(
            "SELECT ti.*, sc.code AS source_code, tc.code AS target_code
             FROM transaction_items ti
             LEFT JOIN currencies sc ON sc.id = ti.source_currency_id
             LEFT JOIN currencies tc ON tc.id = ti.target_currency_id
             WHERE ti.transaction_id = ?", [$tx['id']]);
        $fees = Database::query(
            "SELECT f.*, c.code AS currency_code FROM transaction_fees f
             LEFT JOIN currencies c ON c.id = f.currency_id WHERE f.transaction_id = ?", [$tx['id']]);
        $movements = Database::query(
            "SELECT m.*, a.name AS account_name, c.code AS currency_code
             FROM inventory_movements m
             LEFT JOIN accounts a ON a.id = m.account_id
             LEFT JOIN currencies c ON c.id = m.currency_id
             WHERE m.transaction_id = ? ORDER BY m.id", [$tx['id']]);

        $this->render('transactions/show', [
            'tx' => $tx, 'entries' => $entries, 'items' => $items,
            'fees' => $fees, 'movements' => $movements,
            'audit' => AuditService::recent(30, 'transaction', $tx['id']),
        ]);
    }

    public function receipt(string $id): void
    {
        $tx = TransactionService::tx((int)$id);
        if (!$tx) redirect('/transactions');
        $items = Database::query(
            "SELECT ti.*, sc.code AS source_code, tc.code AS target_code
             FROM transaction_items ti
             LEFT JOIN currencies sc ON sc.id = ti.source_currency_id
             LEFT JOIN currencies tc ON tc.id = ti.target_currency_id
             WHERE ti.transaction_id = ?", [$tx['id']]);
        $fees = Database::query(
            "SELECT f.*, c.code AS currency_code FROM transaction_fees f
             LEFT JOIN currencies c ON c.id = f.currency_id WHERE f.transaction_id = ?", [$tx['id']]);
        $currency = $tx['currency_id'] ? Database::fetch("SELECT * FROM currencies WHERE id = ?", [$tx['currency_id']]) : null;
        $this->render('receipt/index', [
            'tx' => $tx, 'items' => $items, 'fees' => $fees, 'currency' => $currency,
            'business' => [
                'name' => SettingService::businessName(),
                'footer' => SettingService::get('receipt_footer', cfg('defaults.receipt_footer', '')),
            ],
        ]);
    }

    public function cancel(string $id): void
    {
        Csrf::check();
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            Session::flash('danger', t('tx.reason_required'));
            redirect('/transactions/' . $id);
        }
        try {
            $rev = TransactionService::reverse((int)$id, $reason);
            Session::flash('success', t('tx.cancelled') . ' ' . $rev['tx_number']);
        } catch (DomainException $e) {
            Session::flash('danger', $e->getMessage());
        }
        redirect('/transactions/' . $id);
    }

    public function approve(string $id): void
    {
        Csrf::check();
        // For future pending-approval flows; currently all completed at creation.
        redirect('/transactions/' . $id);
    }
}

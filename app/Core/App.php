<?php
declare(strict_types=1);

/**
 * Simple router + dispatcher.
 * Routes are registered as: METHOD path => [Controller::class, 'action']
 * Placeholders in path like {id} are passed as positional args.
 */
final class App
{
    private static array $routes = [];

    public static function route(string $method, string $path, array $handler): void
    {
        self::$routes[] = ['method' => strtoupper($method), 'path' => $path, 'handler' => $handler];
    }

    public static function registerRoutes(): void
    {
        // ---- Auth ----
        self::route('GET', '/login', [AuthController::class, 'loginForm']);
        self::route('POST', '/login', [AuthController::class, 'login']);
        self::route('GET', '/logout', [AuthController::class, 'logout']);
        self::route('GET', '/2fa', [AuthController::class, 'twoFactorForm']);
        self::route('POST', '/2fa', [AuthController::class, 'twoFactorVerify']);
        self::route('GET', '/lang/{lang}', [AuthController::class, 'setLang']);

        // ---- Dashboard ----
        self::route('GET', '/', [DashboardController::class, 'index']);
        self::route('GET', '/dashboard', [DashboardController::class, 'index']);

        // ---- Quick transaction (keyboard-first operator screen) ----
        self::route('GET', '/quick', [QuickController::class, 'form']);
        self::route('POST', '/quick', [QuickController::class, 'store']);

        // ---- Transaction calculator ----
        self::route('GET', '/calculator', [CalculatorController::class, 'form']);
        self::route('POST', '/calculator', [CalculatorController::class, 'store']);

        // ---- Price board ----
        self::route('GET', '/rates/board', [BoardController::class, 'index']);
        self::route('GET', '/rates/board/data', [BoardController::class, 'data']);

        // ---- Profit analytics ----
        self::route('GET', '/analytics/profit', [AnalyticsController::class, 'profit']);

        // ---- Inventory forecast ----
        self::route('GET', '/inventory/forecast', [ForecastController::class, 'index']);
        self::route('POST', '/inventory/forecast/targets', [ForecastController::class, 'saveTargets']);

        // ---- Transactions ----
        self::route('GET', '/transactions', [TransactionController::class, 'history']);
        self::route('GET', '/transactions/buy', [TransactionController::class, 'buyForm']);
        self::route('POST', '/transactions/buy', [TransactionController::class, 'buy']);
        self::route('GET', '/transactions/sell', [TransactionController::class, 'sellForm']);
        self::route('POST', '/transactions/sell', [TransactionController::class, 'sell']);
        self::route('GET', '/transactions/exchange', [TransactionController::class, 'exchangeForm']);
        self::route('POST', '/transactions/exchange', [TransactionController::class, 'exchange']);
        self::route('GET', '/transactions/{id}', [TransactionController::class, 'show']);
        self::route('GET', '/transactions/{id}/receipt', [TransactionController::class, 'receipt']);
        self::route('POST', '/transactions/{id}/cancel', [TransactionController::class, 'cancel']);
        self::route('POST', '/transactions/{id}/approve', [TransactionController::class, 'approve']);

        // ---- Customers ----
        self::route('GET', '/customers', [CustomerController::class, 'index']);
        self::route('GET', '/customers/create', [CustomerController::class, 'createForm']);
        self::route('POST', '/customers', [CustomerController::class, 'store']);
        self::route('GET', '/customers/search', [CustomerController::class, 'searchJson']);
        self::route('POST', '/customers/ajax', [CustomerController::class, 'storeAjax']);
        self::route('GET', '/customers/{id}', [CustomerController::class, 'show']);
        self::route('GET', '/customers/{id}/edit', [CustomerController::class, 'editForm']);
        self::route('POST', '/customers/{id}', [CustomerController::class, 'update']);
        self::route('GET', '/customers/{id}/receivables', [CustomerController::class, 'receivables']);
        self::route('GET', '/customers/{id}/ledger', [CustomerController::class, 'ledger']);
        self::route('GET', '/customers/{id}/ledger/export', [CustomerController::class, 'ledgerExport']);

        // ---- Rates ----
        self::route('GET', '/rates', [RateController::class, 'index']);
        self::route('POST', '/rates/update', [RateController::class, 'update']);
        self::route('POST', '/rates/sync', [RateController::class, 'sync']);
        self::route('POST', '/rates/settings', [RateController::class, 'saveSettings']);
        self::route('GET', '/rates/history', [RateController::class, 'history']);

        // ---- Currencies ----
        self::route('GET', '/currencies', [CurrencyController::class, 'index']);
        self::route('GET', '/currencies/create', [CurrencyController::class, 'createForm']);
        self::route('POST', '/currencies', [CurrencyController::class, 'store']);
        self::route('GET', '/currencies/{id}/edit', [CurrencyController::class, 'editForm']);
        self::route('POST', '/currencies/{id}', [CurrencyController::class, 'update']);

        // ---- Inventory / Accounts ----
        self::route('GET', '/inventory', [InventoryController::class, 'index']);
        self::route('GET', '/accounts', [AccountController::class, 'index']);
        self::route('GET', '/accounts/create', [AccountController::class, 'createForm']);
        self::route('POST', '/accounts', [AccountController::class, 'store']);
        self::route('GET', '/accounts/{id}', [AccountController::class, 'show']);
        self::route('GET', '/accounts/{id}/edit', [AccountController::class, 'editForm']);
        self::route('POST', '/accounts/{id}', [AccountController::class, 'update']);
        self::route('GET', '/transfers', [TransferController::class, 'index']);
        self::route('GET', '/transfers/create', [TransferController::class, 'createForm']);
        self::route('POST', '/transfers', [TransferController::class, 'store']);
        self::route('GET', '/reconciliation', [ReconciliationController::class, 'index']);
        self::route('POST', '/reconciliation', [ReconciliationController::class, 'store']);
        self::route('POST', '/reconciliation/{id}/approve', [ReconciliationController::class, 'approve']);

        // ---- Cash Count ----
        self::route('GET', '/cash-count', [CashCountController::class, 'index']);
        self::route('GET', '/cash-count/create', [CashCountController::class, 'createForm']);
        self::route('POST', '/cash-count', [CashCountController::class, 'store']);

        // ---- Daily Closing ----
        self::route('GET', '/closing', [ClosingController::class, 'index']);
        self::route('POST', '/closing/start', [ClosingController::class, 'start']);
        self::route('POST', '/closing/complete', [ClosingController::class, 'complete']);
        self::route('POST', '/closing/approve', [ClosingController::class, 'approve']);
        self::route('POST', '/closing/reopen', [ClosingController::class, 'reopen']);

        // ---- Expenses & Income ----
        self::route('GET', '/expenses', [ExpenseController::class, 'index']);
        self::route('GET', '/expenses/create', [ExpenseController::class, 'createForm']);
        self::route('POST', '/expenses', [ExpenseController::class, 'store']);
        self::route('GET', '/income', [IncomeController::class, 'index']);
        self::route('GET', '/income/create', [IncomeController::class, 'createForm']);
        self::route('POST', '/income', [IncomeController::class, 'store']);

        // ---- Accounting ----
        self::route('GET', '/ledger', [LedgerController::class, 'index']);
        self::route('GET', '/accounting/pnl', [ReportController::class, 'pnl']);
        self::route('GET', '/accounting/balance-sheet', [ReportController::class, 'balanceSheet']);
        self::route('GET', '/accounting/cash-flow', [ReportController::class, 'cashFlow']);

        // ---- Reports ----
        self::route('GET', '/reports', [ReportController::class, 'index']);
        self::route('GET', '/reports/daily', [ReportController::class, 'daily']);
        self::route('GET', '/reports/monthly', [ReportController::class, 'monthly']);
        self::route('GET', '/reports/currency', [ReportController::class, 'currency']);
        self::route('GET', '/reports/customer', [ReportController::class, 'customer']);
        self::route('GET', '/reports/inventory', [ReportController::class, 'inventoryReport']);

        // ---- Users / Roles ----
        self::route('GET', '/users', [UserController::class, 'index']);
        self::route('GET', '/users/create', [UserController::class, 'createForm']);
        self::route('POST', '/users', [UserController::class, 'store']);
        self::route('GET', '/users/{id}/edit', [UserController::class, 'editForm']);
        self::route('POST', '/users/{id}', [UserController::class, 'update']);
        self::route('POST', '/users/{id}/disable', [UserController::class, 'toggleStatus']);
        self::route('GET', '/roles', [UserController::class, 'roles']);
        self::route('POST', '/roles', [UserController::class, 'storeRole']);
        self::route('POST', '/roles/{id}', [UserController::class, 'updateRole']);

        // ---- Audit ----
        self::route('GET', '/audit', [AuditController::class, 'index']);

        // ---- Settings ----
        self::route('GET', '/settings', [SettingsController::class, 'index']);
        self::route('GET', '/settings/business', [SettingsController::class, 'business']);
        self::route('POST', '/settings/business', [SettingsController::class, 'saveBusiness']);
        self::route('GET', '/settings/system', [SettingsController::class, 'system']);
        self::route('POST', '/settings/system', [SettingsController::class, 'saveSystem']);
        self::route('GET', '/settings/backup', [SettingsController::class, 'backup']);
        self::route('POST', '/settings/backup', [SettingsController::class, 'createBackup']);
        self::route('POST', '/settings/restore', [SettingsController::class, 'restore']);
        self::route('POST', '/settings/backup/settings', [SettingsController::class, 'saveBackupSettings']);

        // ---- Export ----
        self::route('GET', '/export/{type}', [ExportController::class, 'export']);

        // ---- Data transfer (XLSX import/export) ----
        self::route('GET', '/settings/data', [DataController::class, 'index']);
        self::route('GET', '/settings/data/export', [DataController::class, 'exportXlsx']);
        self::route('POST', '/settings/data/import', [DataController::class, 'import']);

        // ---- Notifications ----
        self::route('POST', '/notifications/read', [DashboardController::class, 'markNotificationsRead']);
    }

    public static function dispatch(): void
    {
        self::registerRoutes();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/');
        if ($path === '') $path = '/';

        foreach (self::$routes as $route) {
            if ($route['method'] !== $method) continue;
            $pattern = preg_replace('/\{[a-zA-Z_]+\}/', '([^/]+)', $route['path']);
            if (preg_match('#^' . $pattern . '$#', $path, $m)) {
                array_shift($m);
                self::call($route['handler'], $m);
                return;
            }
        }
        http_response_code(404);
        View::render('errors/404');
    }

    private static function call(array $handler, array $args): void
    {
        [$class, $action] = $handler;
        $controller = new $class();
        $controller->call($action, $args);
    }
}

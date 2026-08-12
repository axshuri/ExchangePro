<?php
declare(strict_types=1);

final class ReportController extends Controller
{
    protected ?string $requirePermission = 'view_reports';

    public function index(): void
    {
        $this->render('reports/index', ['base' => SettingService::baseCurrency()]);
    }

    public function daily(): void
    {
        $date = $_GET['date'] ?? (new DateTime('now', new DateTimeZone(cfg('app.timezone', 'UTC'))))->format('Y-m-d');
        $this->render('reports/daily', [
            'report' => ReportService::daily($date),
            'date' => $date,
            'base' => SettingService::baseCurrency(),
        ]);
    }

    public function monthly(): void
    {
        $month = $_GET['month'] ?? date('Y-m');
        $this->render('reports/monthly', [
            'report' => ReportService::monthly($month),
            'month' => $month,
            'base' => SettingService::baseCurrency(),
        ]);
    }

    public function currency(): void
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $currencyId = (int)($_GET['currency_id'] ?? 0);

        $currencies = Database::query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY code");
        $report = null;
        if ($currencyId) {
            $report = [
                'currency' => Database::fetch("SELECT * FROM currencies WHERE id = ?", [$currencyId]),
                'costing' => InventoryService::costing($currencyId),
                'movements' => InventoryService::currencyMovement($currencyId, $from, $to),
                'transactions' => Database::query(
                    "SELECT t.*, c.code AS currency_code, cu.full_name AS customer_name
                     FROM transactions t
                     LEFT JOIN currencies c ON c.id = t.currency_id
                     LEFT JOIN customers cu ON cu.id = t.customer_id
                     WHERE t.currency_id = ? AND t.status = 'completed'
                       AND t.tx_date BETWEEN ? AND ?
                     ORDER BY t.id DESC LIMIT 200",
                    [$currencyId, $from . ' 00:00:00', $to . ' 23:59:59']),
            ];
        }
        $this->render('reports/currency', [
            'currencies' => $currencies, 'currency_id' => $currencyId,
            'from' => $from, 'to' => $to, 'report' => $report,
            'base' => SettingService::baseCurrency(),
        ]);
    }

    public function customer(): void
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $customerId = (int)($_GET['customer_id'] ?? 0);

        $customers = Database::query("SELECT id, full_name, code FROM customers ORDER BY full_name");
        $report = null;
        if ($customerId) {
            $c = Database::fetch("SELECT * FROM customers WHERE id = ?", [$customerId]);
            if ($c) {
                $report = [
                    'customer' => $c,
                    'stats' => CustomerService::stats($customerId),
                    'txs' => Database::query(
                        "SELECT t.*, c.code AS currency_code FROM transactions t
                         LEFT JOIN currencies c ON c.id = t.currency_id
                         WHERE t.customer_id = ? AND t.status = 'completed'
                           AND t.tx_date BETWEEN ? AND ? ORDER BY t.id DESC LIMIT 300",
                        [$customerId, $from . ' 00:00:00', $to . ' 23:59:59']),
                ];
            }
        }
        $this->render('reports/customer', [
            'customers' => $customers, 'customer_id' => $customerId,
            'from' => $from, 'to' => $to, 'report' => $report,
            'base' => SettingService::baseCurrency(),
        ]);
    }

    public function pnl(): void
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $this->render('reports/pnl', [
            'report' => ReportService::pnl($from, $to),
            'from' => $from, 'to' => $to,
            'base' => SettingService::baseCurrency(),
        ]);
    }

    public function balanceSheet(): void
    {
        $asOf = $_GET['as_of'] ?? date('Y-m-d');
        $this->render('reports/balance_sheet', [
            'report' => ReportService::balanceSheet($asOf),
            'as_of' => $asOf,
            'base' => SettingService::baseCurrency(),
        ]);
    }

    public function cashFlow(): void
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $this->render('reports/cash_flow', [
            'rows' => ReportService::cashFlow($from, $to),
            'from' => $from, 'to' => $to,
            'base' => SettingService::baseCurrency(),
        ]);
    }

    public function inventoryReport(): void
    {
        $this->render('reports/inventory', [
            'report' => ReportService::inventoryValuation(),
            'base' => SettingService::baseCurrency(),
        ]);
    }
}

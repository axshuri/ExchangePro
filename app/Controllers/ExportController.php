<?php
declare(strict_types=1);

final class ExportController extends Controller
{
    protected ?string $requirePermission = 'export_data';

    public function export(string $type): void
    {
        $format = $_GET['format'] ?? 'csv';
        if (!in_array($format, ['csv', 'json'], true)) $format = 'csv';

        $rows = match ($type) {
            'transactions' => Database::query(
                "SELECT t.tx_number, t.tx_date, t.type, t.status, t.rate, t.foreign_amount, t.base_amount,
                        t.fee_amount, t.discount_amount, t.total_amount, t.payment_method, t.notes,
                        c.code AS currency_code, cu.full_name AS customer_name, u.full_name AS employee_name
                 FROM transactions t
                 LEFT JOIN currencies c ON c.id = t.currency_id
                 LEFT JOIN customers cu ON cu.id = t.customer_id
                 LEFT JOIN users u ON u.id = t.employee_id
                 ORDER BY t.id DESC"),
            'customers' => Database::query(
                "SELECT c.code, c.full_name, c.phone, c.email, c.id_number, c.status, c.created_at,
                        (SELECT COALESCE(SUM(balance),0) FROM customer_accounts ca WHERE ca.customer_id = c.id) AS net_balance
                 FROM customers c ORDER BY c.id"),
            'ledger' => Database::query(
                "SELECT je.entry_no, je.created_at, je.description, l.currency_id, cur.code AS currency_code,
                        l.debit, l.credit, l.base_debit, l.base_credit,
                        COALESCE(a.name, g.name) AS account_name
                 FROM journal_lines l
                 JOIN journal_entries je ON je.id = l.entry_id
                 LEFT JOIN currencies cur ON cur.id = l.currency_id
                 LEFT JOIN accounts a ON a.id = l.account_id
                 LEFT JOIN gl_accounts g ON g.id = l.gl_account_id
                 ORDER BY je.id DESC"),
            'expenses' => Database::query(
                "SELECT e.ref_number, e.expense_date, e.category, e.amount, c.code AS currency_code,
                        e.base_amount, e.description, a.name AS account_name
                 FROM expenses e
                 LEFT JOIN currencies c ON c.id = e.currency_id
                 LEFT JOIN accounts a ON a.id = e.account_id
                 ORDER BY e.id DESC"),
            'inventory' => Database::query(
                "SELECT c.code, c.name, ic.qty, ic.avg_cost, ic.total_cost, r.mid_rate AS reference_rate
                 FROM inventory_costings ic
                 JOIN currencies c ON c.id = ic.currency_id
                 LEFT JOIN exchange_rates r ON r.currency_id = c.id
                 ORDER BY c.code"),
            'audit' => Database::query(
                "SELECT a.created_at, a.action, a.entity_type, a.entity_id, u.username, a.ip, a.reason
                 FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC"),
            'rates' => Database::query(
                "SELECT c.code, r.buy_rate, r.sell_rate, r.mid_rate, r.updated_at
                 FROM exchange_rates r JOIN currencies c ON c.id = r.currency_id ORDER BY c.code"),
            default => [],
        };

        $filename = strtolower($type) . '_export_' . date('Ymd_His') . '.' . $format;

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // CSV (with UTF-8 BOM for Excel compatibility)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        if ($rows) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn($v) => $v ?? '', $row));
            }
        }
        fclose($out);
        exit;
    }
}

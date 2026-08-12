<?php
declare(strict_types=1);

/**
 * Customer management + customer account balances.
 *
 * Balance semantics: balance > 0 = customer owes the exchange (receivable);
 * balance < 0 = the exchange owes the customer (payable).
 * Balances are per currency. UI always renders labeled Receivable/Payable.
 */
final class CustomerService
{
    public static function nextCode(): string
    {
        $max = (int)(Database::value("SELECT MAX(CAST(SUBSTRING(code, 3) AS UNSIGNED)) FROM customers") ?: 0);
        return 'C-' . str_pad((string)($max + 1), 5, '0', STR_PAD_LEFT);
    }

    public static function create(array $d): int
    {
        return Database::insert('customers', [
            'code' => self::nextCode(),
            'full_name' => trim($d['full_name']),
            'phone' => $d['phone'] ?? null,
            'email' => $d['email'] ?? null,
            'address' => $d['address'] ?? null,
            'id_type' => $d['id_type'] ?? null,
            'id_number' => $d['id_number'] ?? null,
            'notes' => $d['notes'] ?? null,
        ]);
    }

    public static function update(int $id, array $d): void
    {
        $before = Database::fetch("SELECT * FROM customers WHERE id = ?", [$id]);
        Database::update('customers', [
            'full_name' => trim($d['full_name']),
            'phone' => $d['phone'] ?? null,
            'email' => $d['email'] ?? null,
            'address' => $d['address'] ?? null,
            'id_type' => $d['id_type'] ?? null,
            'id_number' => $d['id_number'] ?? null,
            'notes' => $d['notes'] ?? null,
            'status' => $d['status'] ?? 'active',
        ], 'id = ?', [$id]);
        AuditService::log('update_customer', 'customer', $id,
            $before ? ['full_name' => $before['full_name'], 'phone' => $before['phone']] : null,
            ['full_name' => $d['full_name'], 'phone' => $d['phone'] ?? null]);
    }

    public static function get(int $id): ?array
    {
        return Database::fetch("SELECT * FROM customers WHERE id = ?", [$id]);
    }

    /** Rebuild all customer balances from transactions (paid via internal balance). */
    public static function rebuildBalance(int $customerId): void
    {
        $base = SettingService::baseCurrency();
        if (!$base) return;
        // buy with internal balance: customer receives money → exchange owes customer → payable (negative)
        // sell with internal balance: customer pays → receivable (positive)
        $total = (string)(Database::value(
            "SELECT COALESCE(SUM(CASE WHEN t.type = 'sell' THEN t.total_amount ELSE -t.total_amount END), 0)
             FROM transactions t
             WHERE t.customer_id = ? AND t.status = 'completed' AND t.payment_method = 'internal_balance'
               AND t.currency_id IS NOT NULL", [$customerId]) ?: '0');

        $exists = Database::fetch("SELECT currency_id FROM customer_accounts WHERE customer_id = ? AND currency_id = ?",
            [$customerId, $base['id']]);
        $data = ['balance' => Money::round($total, 10)];
        if ($exists) {
            Database::update('customer_accounts', $data, 'customer_id = ? AND currency_id = ?',
                [$customerId, $base['id']]);
        } else {
            Database::insert('customer_accounts', $data + ['customer_id' => $customerId, 'currency_id' => $base['id']]);
        }
    }

    public static function balances(int $customerId): array
    {
        return Database::query(
            "SELECT ca.*, c.code, c.symbol, c.amount_precision
             FROM customer_accounts ca JOIN currencies c ON c.id = ca.currency_id
             WHERE ca.customer_id = ? AND ca.balance <> 0", [$customerId]);
    }

    public static function stats(int $customerId): array
    {
        return [
            'total_buy' => (string)(Database::value(
                "SELECT COALESCE(SUM(base_amount),0) FROM transactions
                 WHERE customer_id = ? AND type = 'buy' AND status IN ('completed')", [$customerId]) ?: '0'),
            'total_sell' => (string)(Database::value(
                "SELECT COALESCE(SUM(base_amount),0) FROM transactions
                 WHERE customer_id = ? AND type = 'sell' AND status IN ('completed')", [$customerId]) ?: '0'),
            'total_fees' => (string)(Database::value(
                "SELECT COALESCE(SUM(f.base_amount),0) FROM transaction_fees f
                 JOIN transactions t ON t.id = f.transaction_id
                 WHERE t.customer_id = ?", [$customerId]) ?: '0'),
            'tx_count' => (int)(Database::value(
                "SELECT COUNT(*) FROM transactions WHERE customer_id = ? AND status IN ('completed')", [$customerId]) ?: 0),
        ];
    }

    /**
     * Customer ledger rows: complete financial history with filters.
     * Filters: from, to (dates), currency_id, type, q.
     */
    public static function ledger(int $customerId, array $f = []): array
    {
        $sql = "SELECT t.*, c.code AS currency_code, c.symbol, c.amount_precision,
                       u.full_name AS employee_name,
                       COALESCE((SELECT SUM(f.base_amount) FROM transaction_fees f WHERE f.transaction_id = t.id),0) AS fee_base
                FROM transactions t
                LEFT JOIN currencies c ON c.id = t.currency_id
                LEFT JOIN users u ON u.id = t.employee_id
                WHERE t.customer_id = ?";
        $params = [(int)$customerId];
        if (!empty($f['from'])) { $sql .= " AND t.tx_date >= ?"; $params[] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to'])) { $sql .= " AND t.tx_date <= ?"; $params[] = $f['to'] . ' 23:59:59'; }
        if (!empty($f['currency_id'])) { $sql .= " AND t.currency_id = ?"; $params[] = (int)$f['currency_id']; }
        if (!empty($f['type'])) { $sql .= " AND t.type = ?"; $params[] = $f['type']; }
        $sql .= " ORDER BY t.id DESC";
        $rows = Database::query($sql, $params);

        // Period totals per currency (completed only) — dates bound as parameters
        $totalsSql = "SELECT c.code, c.symbol, c.amount_precision,
                    COALESCE(SUM(CASE WHEN t.type = 'buy' THEN t.base_amount ELSE 0 END),0) AS buy_base,
                    COALESCE(SUM(CASE WHEN t.type = 'sell' THEN t.base_amount ELSE 0 END),0) AS sell_base
             FROM transactions t JOIN currencies c ON c.id = t.currency_id
             WHERE t.customer_id = ? AND t.status = 'completed'";
        $totalsParams = [(int)$customerId];
        if (!empty($f['from'])) { $totalsSql .= " AND t.tx_date >= ?"; $totalsParams[] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to'])) { $totalsSql .= " AND t.tx_date <= ?"; $totalsParams[] = $f['to'] . ' 23:59:59'; }
        $totalsSql .= " GROUP BY c.code, c.symbol, c.amount_precision";
        $totals = Database::query($totalsSql, $totalsParams);

        return ['rows' => $rows, 'totals' => $totals];
    }

    /** CSV rows for the customer ledger export. */
    public static function ledgerCsv(int $customerId, array $f = []): array
    {
        $data = self::ledger($customerId, $f);
        $base = SettingService::baseCurrency();
        $lines = [];
        $lines[] = ['Date', 'Type', 'Currency', 'Amount', 'Rate', 'Base ' . ($base['code'] ?? ''), 'Fee', 'Discount', 'Total', 'Status', 'Operator'];
        foreach ($data['rows'] as $t) {
            $lines[] = [
                substr((string)$t['tx_date'], 0, 16),
                $t['type'], $t['currency_code'] ?? '', $t['foreign_amount'] ?? '',
                $t['rate'] ?? '', $t['base_amount'] ?? '', $t['fee_base'] ?? '',
                $t['discount_amount'] ?? '', $t['total_amount'] ?? '',
                $t['status'], $t['employee_name'] ?? '',
            ];
        }
        return $lines;
    }

    public static function search(string $q = '', int $limit = 50): array
    {
        if ($q === '') {
            return Database::query("SELECT * FROM customers ORDER BY full_name LIMIT " . (int)$limit);
        }
        return Database::query(
            "SELECT * FROM customers WHERE full_name LIKE ? OR phone LIKE ? OR code LIKE ? OR email LIKE ?
             ORDER BY full_name LIMIT " . (int)$limit,
            ["%$q%", "%$q%", "%$q%", "%$q%"]);
    }
}

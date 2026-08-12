<?php
declare(strict_types=1);

/**
 * Inventory costing — Weighted Average Cost.
 *
 * Derived directly from journal lines, so it is deterministic and reversible:
 *   qty        = Σ(currency debit − credit) over location lines for the currency
 *   total_cost = Σ(base_debit − base_credit) over location lines (in base currency)
 *   avg_cost   = total_cost / qty
 *
 * A `inventory_costings` row is cached after every transaction; it can always
 * be rebuilt from the ledger, so it can never drift out of sync.
 */
final class InventoryService
{
    public static function recalculate(string|int $currencyId): void
    {
        $currencyId = (int)$currencyId;
        $row = Database::fetch(
            "SELECT COALESCE(SUM(l.debit - l.credit),0) AS qty,
                    COALESCE(SUM(l.base_debit - l.base_credit),0) AS total_cost
             FROM journal_lines l
             WHERE l.currency_id = ? AND l.account_id IS NOT NULL",
            [$currencyId]);
        $qty = (string)($row['qty'] ?? '0');
        $cost = (string)($row['total_cost'] ?? '0');
        $avg = Money::isZero($qty) ? Money::zero() : Money::div($cost, $qty, 10);

        $exists = Database::fetch("SELECT currency_id FROM inventory_costings WHERE currency_id = ?", [$currencyId]);
        $data = ['qty' => $qty, 'total_cost' => $cost, 'avg_cost' => $avg];
        if ($exists) {
            Database::update('inventory_costings', $data, 'currency_id = ?', [$currencyId]);
        } else {
            Database::insert('inventory_costings', $data + ['currency_id' => $currencyId]);
        }
    }

    public static function costing(string|int $currencyId): array
    {
        $currencyId = (int)$currencyId;
        $row = Database::fetch("SELECT * FROM inventory_costings WHERE currency_id = ?", [$currencyId]);
        if ($row) return $row;
        return ['currency_id' => $currencyId, 'qty' => '0', 'total_cost' => '0', 'avg_cost' => '0'];
    }

    /** Record an inventory movement row (audit + balance_after). */
    public static function movement(
        ?int $transactionId,
        int $accountId,
        int $currencyId,
        string $direction,
        string $amount,
        ?string $rate,
        string $baseAmount,
        string $balanceAfter,
        ?string $note = null
    ): void {
        Database::insert('inventory_movements', [
            'transaction_id' => $transactionId,
            'account_id' => $accountId,
            'currency_id' => $currencyId,
            'direction' => $direction,
            'amount' => $amount,
            'rate' => $rate,
            'base_amount' => $baseAmount,
            'balance_after' => $balanceAfter,
            'note' => $note,
        ]);
    }

    /** Currency report: opening/closing balances for a period from ledger. */
    public static function currencyMovement(int $currencyId, string $from, string $to): array
    {
        // Base value of position changes grouped by transaction date & type
        $rows = Database::query(
            "SELECT t.type, t.tx_date, l.currency_id,
                    COALESCE(SUM(CASE WHEN l.account_id IS NOT NULL THEN l.debit - l.credit END),0) AS amount,
                    COALESCE(SUM(CASE WHEN l.account_id IS NOT NULL THEN l.base_debit - l.base_credit END),0) AS base_amount
             FROM journal_lines l
             LEFT JOIN journal_entries je ON je.id = l.entry_id
             LEFT JOIN transactions t ON t.id = je.transaction_id
             WHERE l.currency_id = ? AND je.created_at BETWEEN ? AND ?
               AND l.account_id IS NOT NULL
             GROUP BY t.type, t.tx_date, l.currency_id
             ORDER BY t.tx_date",
            [$currencyId, $from . ' 00:00:00', $to . ' 23:59:59']);
        return $rows;
    }
}

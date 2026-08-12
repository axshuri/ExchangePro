<?php
declare(strict_types=1);

/**
 * Financial reports. Every number traces back to the ledger.
 */
final class ReportService
{
    /** Daily summary for a date (business timezone). */
    public static function daily(string $date): array
    {
        $from = $date . ' 00:00:00';
        $to = $date . ' 23:59:59';

        $buys = Database::query(
            "SELECT t.currency_id, c.code, c.symbol, c.amount_precision,
                    COUNT(*) AS tx_count,
                    COALESCE(SUM(t.foreign_amount),0) AS amount,
                    COALESCE(SUM(t.base_amount),0) AS base_amount,
                    COALESCE(SUM(f.base_amount),0) AS fee_base
             FROM transactions t
             JOIN currencies c ON c.id = t.currency_id
             LEFT JOIN transaction_fees f ON f.transaction_id = t.id
             WHERE t.type = 'buy' AND t.status = 'completed' AND t.tx_date BETWEEN ? AND ?
             GROUP BY t.currency_id, c.code, c.symbol, c.amount_precision", [$from, $to]);

        $sells = Database::query(
            "SELECT t.currency_id, c.code, c.symbol, c.amount_precision,
                    COUNT(*) AS tx_count,
                    COALESCE(SUM(t.foreign_amount),0) AS amount,
                    COALESCE(SUM(t.base_amount),0) AS base_amount
             FROM transactions t
             JOIN currencies c ON c.id = t.currency_id
             WHERE t.type = 'sell' AND t.status = 'completed' AND t.tx_date BETWEEN ? AND ?
             GROUP BY t.currency_id, c.code, c.symbol, c.amount_precision", [$from, $to]);

        $expenses = Database::query(
            "SELECT category, COUNT(*) AS cnt, COALESCE(SUM(base_amount),0) AS base_amount
             FROM expenses WHERE expense_date = ? GROUP BY category", [$date]);

        $income = Database::query(
            "SELECT category, COUNT(*) AS cnt, COALESCE(SUM(base_amount),0) AS base_amount
             FROM income WHERE income_date = ? GROUP BY category", [$date]);

        // Realized P/L from ledger GL account
        $pl = Database::fetch(
            "SELECT COALESCE(SUM(l.base_credit) - SUM(l.base_debit), 0) AS total
             FROM journal_lines l JOIN journal_entries je ON je.id = l.entry_id
             JOIN gl_accounts g ON g.id = l.gl_account_id
             WHERE g.code = 'REALIZED_PL' AND je.created_at BETWEEN ? AND ?", [$from, $to]);

        $fees = Database::fetch(
            "SELECT COALESCE(SUM(l.base_credit) - SUM(l.base_debit), 0) AS total
             FROM journal_lines l JOIN journal_entries je ON je.id = l.entry_id
             JOIN gl_accounts g ON g.id = l.gl_account_id
             WHERE g.code = 'FEE_INCOME' AND je.created_at BETWEEN ? AND ?", [$from, $to]);

        $expenseTotal = (string)(Database::value(
            "SELECT COALESCE(SUM(base_amount),0) FROM expenses WHERE expense_date = ?", [$date]) ?: '0');
        $incomeTotal = (string)(Database::value(
            "SELECT COALESCE(SUM(base_amount),0) FROM income WHERE income_date = ?", [$date]) ?: '0');

        $tradingProfit = Money::add((string)($pl['total'] ?? '0'), (string)($fees['total'] ?? '0'));
        $netProfit = Money::add(Money::add($tradingProfit, $incomeTotal), Money::sub('0', $expenseTotal));

        $txCount = (int)(Database::value(
            "SELECT COUNT(*) FROM transactions WHERE status = 'completed' AND tx_date BETWEEN ? AND ?",
            [$from, $to]) ?: 0);

        return [
            'date' => $date,
            'buys' => $buys, 'sells' => $sells,
            'expenses' => $expenses, 'income' => $income,
            'trading_profit' => $tradingProfit,
            'expense_total' => $expenseTotal,
            'income_total' => $incomeTotal,
            'net_profit' => $netProfit,
            'tx_count' => $txCount,
            'buy_total_base' => (string)(Database::value(
                "SELECT COALESCE(SUM(base_amount),0) FROM transactions WHERE type='buy' AND status='completed' AND tx_date BETWEEN ? AND ?", [$from, $to]) ?: '0'),
            'sell_total_base' => (string)(Database::value(
                "SELECT COALESCE(SUM(base_amount),0) FROM transactions WHERE type='sell' AND status='completed' AND tx_date BETWEEN ? AND ?", [$from, $to]) ?: '0'),
        ];
    }

    /** Summary for an arbitrary period (e.g. current year). */
    public static function dailyPeriod(string $fromDate, string $toDate): array
    {
        $from = $fromDate . ' 00:00:00';
        $to = $toDate . ' 23:59:59';
        $pl = (string)(Database::value(
            "SELECT COALESCE(SUM(l.base_credit) - SUM(l.base_debit), 0)
             FROM journal_lines l JOIN journal_entries je ON je.id = l.entry_id
             JOIN gl_accounts g ON g.id = l.gl_account_id
             WHERE g.code = 'REALIZED_PL' AND je.created_at BETWEEN ? AND ?", [$from, $to]) ?: '0');
        $fees = (string)(Database::value(
            "SELECT COALESCE(SUM(l.base_credit) - SUM(l.base_debit), 0)
             FROM journal_lines l JOIN journal_entries je ON je.id = l.entry_id
             JOIN gl_accounts g ON g.id = l.gl_account_id
             WHERE g.code = 'FEE_INCOME' AND je.created_at BETWEEN ? AND ?", [$from, $to]) ?: '0');
        $expenseTotal = (string)(Database::value(
            "SELECT COALESCE(SUM(base_amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?",
            [$fromDate, $toDate]) ?: '0');
        $incomeTotal = (string)(Database::value(
            "SELECT COALESCE(SUM(base_amount),0) FROM income WHERE income_date BETWEEN ? AND ?",
            [$fromDate, $toDate]) ?: '0');
        return [
            'trading_profit' => Money::add($pl, $fees),
            'expense_total' => $expenseTotal,
            'income_total' => $incomeTotal,
            'net_profit' => Money::add(Money::add($pl, $fees), Money::sub($incomeTotal, $expenseTotal)),
            'tx_count' => (int)(Database::value(
                "SELECT COUNT(*) FROM transactions WHERE status='completed' AND tx_date BETWEEN ? AND ?", [$from, $to]) ?: 0),
        ];
    }

    /** Monthly report. */
    public static function monthly(string $month): array
    {
        $from = $month . '-01 00:00:00';
        $to = date('Y-m-t', strtotime($month . '-01')) . ' 23:59:59';

        $pl = (string)(Database::value(
            "SELECT COALESCE(SUM(l.base_credit) - SUM(l.base_debit), 0)
             FROM journal_lines l JOIN journal_entries je ON je.id = l.entry_id
             JOIN gl_accounts g ON g.id = l.gl_account_id
             WHERE g.code = 'REALIZED_PL' AND je.created_at BETWEEN ? AND ?", [$from, $to]) ?: '0');
        $fees = (string)(Database::value(
            "SELECT COALESCE(SUM(l.base_credit) - SUM(l.base_debit), 0)
             FROM journal_lines l JOIN journal_entries je ON je.id = l.entry_id
             JOIN gl_accounts g ON g.id = l.gl_account_id
             WHERE g.code = 'FEE_INCOME' AND je.created_at BETWEEN ? AND ?", [$from, $to]) ?: '0');
        $expenseTotal = (string)(Database::value(
            "SELECT COALESCE(SUM(base_amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?",
            [substr($from, 0, 10), substr($to, 0, 10)]) ?: '0');
        $incomeTotal = (string)(Database::value(
            "SELECT COALESCE(SUM(base_amount),0) FROM income WHERE income_date BETWEEN ? AND ?",
            [substr($from, 0, 10), substr($to, 0, 10)]) ?: '0');

        $byCurrency = Database::query(
            "SELECT t.currency_id, c.code, c.symbol,
                    COALESCE(SUM(CASE WHEN t.type='buy' THEN t.foreign_amount ELSE 0 END),0) AS buy_amount,
                    COALESCE(SUM(CASE WHEN t.type='sell' THEN t.foreign_amount ELSE 0 END),0) AS sell_amount,
                    COALESCE(SUM(t.base_amount),0) AS base_amount
             FROM transactions t JOIN currencies c ON c.id = t.currency_id
             WHERE t.status = 'completed' AND t.tx_date BETWEEN ? AND ?
             GROUP BY t.currency_id, c.code, c.symbol", [$from, $to]);

        return [
            'month' => $month,
            'trading_profit' => Money::add($pl, $fees),
            'expense_total' => $expenseTotal,
            'income_total' => $incomeTotal,
            'net_profit' => Money::add(Money::add($pl, $fees), Money::sub($incomeTotal, $expenseTotal)),
            'by_currency' => $byCurrency,
            'tx_count' => (int)(Database::value(
                "SELECT COUNT(*) FROM transactions WHERE status='completed' AND tx_date BETWEEN ? AND ?", [$from, $to]) ?: 0),
        ];
    }

    /** P&L statement for a period. */
    public static function pnl(string $from, string $to): array
    {
        $gl = Database::query(
            "SELECT g.id, g.code, g.name, g.type,
                    COALESCE(SUM(l.base_credit) - SUM(l.base_debit), 0) AS balance
             FROM gl_accounts g
             LEFT JOIN journal_lines l ON l.gl_account_id = g.id
             LEFT JOIN journal_entries je ON je.id = l.entry_id AND je.created_at BETWEEN ? AND ?
             WHERE g.type IN ('income','expense')
             GROUP BY g.id, g.code, g.name, g.type
             ORDER BY g.type, g.name", [$from . ' 00:00:00', $to . ' 23:59:59']);

        $income = []; $expenses = [];
        $incomeTotal = Money::zero(); $expenseTotal = Money::zero();
        foreach ($gl as $g) {
            if ($g['type'] === 'income') {
                $income[] = $g;
                $incomeTotal = Money::add($incomeTotal, (string)$g['balance']);
            } else {
                $expenses[] = $g;
                $expenseTotal = Money::add($expenseTotal, (string)$g['balance']);
            }
        }

        // Non-ledger income/expense tables
        $otherIncome = (string)(Database::value(
            "SELECT COALESCE(SUM(base_amount),0) FROM income WHERE income_date BETWEEN ? AND ?",
            [$from, $to]) ?: '0');
        $otherExpenses = (string)(Database::value(
            "SELECT COALESCE(SUM(base_amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?",
            [$from, $to]) ?: '0');

        $gross = Money::add($incomeTotal, $otherIncome);
        $totalExpenses = Money::add($expenseTotal, $otherExpenses);
        return [
            'from' => $from, 'to' => $to,
            'income' => $income, 'expenses' => $expenses,
            'income_total' => $gross, 'expense_total' => $totalExpenses,
            'net_profit' => Money::sub($gross, $totalExpenses),
            'other_income' => $otherIncome, 'other_expenses' => $otherExpenses,
            'ledger_income' => $incomeTotal, 'ledger_expenses' => $expenseTotal,
        ];
    }

    /** Simplified balance sheet as of a date. */
    public static function balanceSheet(string $asOf): array
    {
        $base = SettingService::baseCurrency();
        $currencies = Database::query("SELECT * FROM currencies WHERE is_active = 1");

        // Assets: all location positions valued at current reference rates
        $assets = [];
        $assetTotal = Money::zero();
        foreach ($currencies as $c) {
            $pos = LedgerService::totalPosition((int)$c['id']);
            if (Money::isZero($pos)) continue;
            $rate = $c['id'] == $base['id'] ? '1' : self::midRate((int)$c['id']);
            $baseValue = Money::mul($pos, $rate);
            $assets[] = ['currency' => $c, 'amount' => $pos, 'rate' => $rate, 'base_value' => $baseValue];
            $assetTotal = Money::add($assetTotal, $baseValue);
        }

        // Receivables (customer owes us) & payables (we owe customer)
        $recv = (string)(Database::value(
            "SELECT COALESCE(SUM(balance),0) FROM customer_accounts WHERE balance > 0") ?: '0');
        $pay = (string)(Database::value(
            "SELECT COALESCE(SUM(-balance),0) FROM customer_accounts WHERE balance < 0") ?: '0');

        // Equity = assets + payables − receivables (assets = receivables + equity − payables)
        $equity = Money::add(Money::sub($assetTotal, $recv), $pay);

        return [
            'as_of' => $asOf,
            'assets' => $assets, 'asset_total' => $assetTotal,
            'receivables' => $recv, 'payables' => $pay,
            'equity' => $equity,
        ];
    }

    /** Cash flow: inflows/outflows per account in base for a period. */
    public static function cashFlow(string $from, string $to): array
    {
        $rows = Database::query(
            "SELECT a.id AS account_id, a.name AS account_name, a.type AS account_type,
                    l.currency_id, c.code AS currency_code,
                    COALESCE(SUM(l.base_debit),0) AS inflow,
                    COALESCE(SUM(l.base_credit),0) AS outflow
             FROM journal_lines l
             JOIN journal_entries je ON je.id = l.entry_id
             JOIN accounts a ON a.id = l.account_id
             JOIN currencies c ON c.id = l.currency_id
             WHERE je.created_at BETWEEN ? AND ?
             GROUP BY a.id, a.name, a.type, l.currency_id, c.code
             ORDER BY a.name", [$from . ' 00:00:00', $to . ' 23:59:59']);
        return $rows;
    }

    /** Inventory valuation report. */
    public static function inventoryValuation(): array
    {
        $base = SettingService::baseCurrency();
        $rows = [];
        $currencies = Database::query("SELECT * FROM currencies WHERE is_active = 1");
        $total = Money::zero();
        foreach ($currencies as $c) {
            $costing = InventoryService::costing((int)$c['id']);
            $qty = (string)$costing['qty'];
            $avg = (string)$costing['avg_cost'];
            $rate = $c['id'] == $base['id'] ? '1' : self::midRate((int)$c['id']);
            $marketValue = Money::mul($qty, $rate);
            $unrealized = Money::sub($marketValue, (string)$costing['total_cost']);
            $rows[] = [
                'currency' => $c, 'qty' => $qty, 'avg_cost' => $avg,
                'total_cost' => (string)$costing['total_cost'],
                'reference_rate' => $rate, 'market_value' => $marketValue,
                'unrealized_pl' => $unrealized,
            ];
            $total = Money::add($total, $marketValue);
        }
        return ['rows' => $rows, 'total' => $total];
    }

    private static function midRate(int $currencyId): string
    {
        $v = Database::value("SELECT mid_rate FROM exchange_rates WHERE currency_id = ?", [$currencyId]);
        return $v !== false && Money::isPositive((string)$v) ? (string)$v : '1';
    }
}

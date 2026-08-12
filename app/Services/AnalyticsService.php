<?php
declare(strict_types=1);

/**
 * Profit analytics for the owner.
 *
 * Every figure traces to stored records — transactions keep their own rate and
 * realized P/L is captured at transaction time (transactions.realized_pl), so
 * historical profit is never recalculated with today's rates.
 */
final class AnalyticsService
{
    /** Period metrics: profit, revenue, volume, fees, expenses, net. */
    public static function metrics(string $from, string $to): array
    {
        $realized = self::realized($from, $to);
        $fees = self::fees($from, $to);
        $expenses = (string)(Database::value(
            "SELECT COALESCE(SUM(base_amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?",
            [$from, $to]) ?: '0');
        $income = (string)(Database::value(
            "SELECT COALESCE(SUM(base_amount),0) FROM income WHERE income_date BETWEEN ? AND ?",
            [$from, $to]) ?: '0');

        $buyVol = (string)(Database::value(
            "SELECT COALESCE(SUM(base_amount),0) FROM transactions WHERE type='buy' AND status='completed' AND tx_date BETWEEN ? AND ?",
            [$from . ' 00:00:00', $to . ' 23:59:59']) ?: '0');
        $sellVol = (string)(Database::value(
            "SELECT COALESCE(SUM(base_amount),0) FROM transactions WHERE type='sell' AND status='completed' AND tx_date BETWEEN ? AND ?",
            [$from . ' 00:00:00', $to . ' 23:59:59']) ?: '0');

        $trading = Money::add($realized, $fees);
        $net = Money::add(Money::add($trading, $income), Money::sub('0', $expenses));

        return [
            'realized_pl' => $realized,
            'fees' => $fees,
            'trading_profit' => $trading,
            'revenue' => Money::add($sellVol, $income),
            'volume' => Money::add($buyVol, $sellVol),
            'buy_volume' => $buyVol,
            'sell_volume' => $sellVol,
            'expenses' => $expenses,
            'income' => $income,
            'net_profit' => $net,
            'tx_count' => (int)(Database::value(
                "SELECT COUNT(*) FROM transactions WHERE status='completed' AND tx_date BETWEEN ? AND ?",
                [$from . ' 00:00:00', $to . ' 23:59:59']) ?: 0),
        ];
    }

    /** Realized P/L per currency (attributed at transaction time). */
    public static function byCurrency(string $from, string $to): array
    {
        return Database::query(
            "SELECT t.currency_id, c.code, c.symbol, c.amount_precision,
                    COALESCE(SUM(CASE WHEN t.type='buy' THEN t.foreign_amount ELSE 0 END),0) AS buy_amount,
                    COALESCE(SUM(CASE WHEN t.type='sell' THEN t.foreign_amount ELSE 0 END),0) AS sell_amount,
                    COALESCE(SUM(CASE WHEN t.type='buy' THEN t.base_amount ELSE 0 END),0) AS buy_base,
                    COALESCE(SUM(CASE WHEN t.type='sell' THEN t.base_amount ELSE 0 END),0) AS sell_base,
                    COALESCE(SUM(realized_pl),0) AS realized,
                    COUNT(*) AS tx_count
             FROM transactions t JOIN currencies c ON c.id = t.currency_id
             WHERE t.status = 'completed' AND t.type IN ('buy','sell')
               AND t.tx_date BETWEEN ? AND ?
             GROUP BY t.currency_id, c.code, c.symbol, c.amount_precision
             ORDER BY realized DESC", [$from . ' 00:00:00', $to . ' 23:59:59']);
    }

    /** Profit/volume by transaction type (incl. fee income). */
    public static function byType(string $from, string $to): array
    {
        $rows = Database::query(
            "SELECT t.type, COUNT(*) AS tx_count,
                    COALESCE(SUM(t.base_amount),0) AS base_amount,
                    COALESCE(SUM(t.realized_pl),0) AS realized
             FROM transactions t
             WHERE t.status = 'completed' AND t.type IN ('buy','sell','exchange')
               AND t.tx_date BETWEEN ? AND ?
             GROUP BY t.type ORDER BY base_amount DESC", [$from . ' 00:00:00', $to . ' 23:59:59']);
        return [
            'rows' => $rows,
            'fees' => self::fees($from, $to),
        ];
    }

    /** Daily trend series (profit, volume, count, fees) for charts. */
    public static function trend(string $from, string $to): array
    {
        $fromDate = new DateTime($from);
        $toDate = new DateTime($to);
        $days = [];
        for ($d = clone $fromDate; $d <= $toDate; $d->modify('+1 day')) {
            $days[$d->format('Y-m-d')] = ['profit' => '0', 'volume' => '0', 'count' => 0, 'fees' => '0'];
        }

        $realized = Database::query(
            "SELECT DATE(tx_date) AS d, COALESCE(SUM(realized_pl),0) AS v
             FROM transactions WHERE status='completed' AND type IN ('sell','exchange')
               AND tx_date BETWEEN ? AND ? GROUP BY DATE(tx_date)",
            [$from . ' 00:00:00', $to . ' 23:59:59']);
        $volumes = Database::query(
            "SELECT DATE(tx_date) AS d, COALESCE(SUM(base_amount),0) AS v, COUNT(*) AS n
             FROM transactions WHERE status='completed' AND type IN ('buy','sell')
               AND tx_date BETWEEN ? AND ? GROUP BY DATE(tx_date)",
            [$from . ' 00:00:00', $to . ' 23:59:59']);
        $fees = Database::query(
            "SELECT DATE(je.created_at) AS d, COALESCE(SUM(l.base_credit - l.base_debit),0) AS v
             FROM journal_lines l JOIN journal_entries je ON je.id = l.entry_id
             JOIN gl_accounts g ON g.id = l.gl_account_id
             WHERE g.code = 'FEE_INCOME' AND je.created_at BETWEEN ? AND ? GROUP BY DATE(je.created_at)",
            [$from . ' 00:00:00', $to . ' 23:59:59']);

        foreach ($realized as $r) { $days[$r['d']]['profit'] = Money::add($days[$r['d']]['profit'], (string)$r['v']); }
        foreach ($fees as $r) { $days[$r['d']]['profit'] = Money::add($days[$r['d']]['profit'], (string)$r['v']); $days[$r['d']]['fees'] = (string)$r['v']; }
        foreach ($volumes as $r) {
            $days[$r['d']]['volume'] = (string)$r['v'];
            $days[$r['d']]['count'] = (int)$r['n'];
        }

        $out = [];
        foreach ($days as $date => $v) {
            $out[] = ['date' => $date, 'label' => date('M j', strtotime($date))] + $v;
        }
        return $out;
    }

    /** Per-currency performance: volume, counts, avg size, profit. */
    public static function currencyPerformance(string $from, string $to): array
    {
        $rows = self::byCurrency($from, $to);
        $base = SettingService::baseCurrency();
        foreach ($rows as &$r) {
            $vol = Money::add((string)$r['buy_base'], (string)$r['sell_base']);
            $r['volume'] = $vol;
            $r['avg_size'] = $r['tx_count'] > 0 ? Money::round(Money::div($vol, (string)$r['tx_count'], 10), (int)$base['amount_precision']) : '0';
        }
        unset($r);
        return $rows;
    }

    /** Top 5 currencies by volume, profit, and transaction count. */
    public static function topCurrencies(string $from, string $to): array
    {
        $rows = self::currencyPerformance($from, $to);
        $byVol = $rows; usort($byVol, fn($a, $b) => Money::compare((string)$b['volume'], (string)$a['volume']));
        $byProfit = $rows; usort($byProfit, fn($a, $b) => Money::compare((string)$b['realized'], (string)$a['realized']));
        $byCount = $rows; usort($byCount, fn($a, $b) => (int)$b['tx_count'] <=> (int)$a['tx_count']);
        return [
            'volume' => array_slice($byVol, 0, 5),
            'profit' => array_slice($byProfit, 0, 5),
            'count' => array_slice($byCount, 0, 5),
        ];
    }

    /** Convenience: resolve a preset into [from, to] dates. */
    public static function resolvePeriod(string $preset, ?string $from = null, ?string $to = null): array
    {
        $tz = new DateTimeZone(cfg('app.timezone', 'UTC'));
        $today = (new DateTime('now', $tz))->format('Y-m-d');
        return match ($preset) {
            'today' => [$today, $today],
            'yesterday' => [date('Y-m-d', strtotime('-1 day', strtotime($today))), date('Y-m-d', strtotime('-1 day', strtotime($today)))],
            '7d' => [date('Y-m-d', strtotime('-6 day', strtotime($today))), $today],
            '30d' => [date('Y-m-d', strtotime('-29 day', strtotime($today))), $today],
            'month' => [substr($today, 0, 7) . '-01', $today],
            'prev_month' => [date('Y-m-01', strtotime('first day of last month', strtotime($today))), date('Y-m-t', strtotime('first day of last month', strtotime($today)))],
            default => [$from ?: date('Y-m-01'), $to ?: $today],
        };
    }

    private static function realized(string $from, string $to): string
    {
        return (string)(Database::value(
            "SELECT COALESCE(SUM(realized_pl),0) FROM transactions
             WHERE status='completed' AND type IN ('sell','exchange') AND tx_date BETWEEN ? AND ?",
            [$from . ' 00:00:00', $to . ' 23:59:59']) ?: '0');
    }

    private static function fees(string $from, string $to): string
    {
        return (string)(Database::value(
            "SELECT COALESCE(SUM(l.base_credit - l.base_debit),0)
             FROM journal_lines l JOIN journal_entries je ON je.id = l.entry_id
             JOIN gl_accounts g ON g.id = l.gl_account_id
             WHERE g.code = 'FEE_INCOME' AND je.created_at BETWEEN ? AND ?",
            [$from . ' 00:00:00', $to . ' 23:59:59']) ?: '0');
    }
}

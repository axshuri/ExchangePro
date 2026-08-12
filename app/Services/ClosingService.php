<?php
declare(strict_types=1);

/**
 * Daily closing process.
 */
final class ClosingService
{
    public static function status(string $date): array
    {
        $row = Database::fetch("SELECT * FROM daily_closings WHERE closing_date = ?", [$date]);
        if (!$row) {
            return ['status' => 'open', 'opened' => false];
        }
        $row['opening'] = $row['opening_balances'] ? json_decode($row['opening_balances'], true) : [];
        $row['closing'] = $row['closing_balances'] ? json_decode($row['closing_balances'], true) : [];
        $row['diffs'] = $row['differences'] ? json_decode($row['differences'], true) : [];
        return ['status' => $row['status'], 'row' => $row, 'opened' => true];
    }

    /** Snapshot current positions per account+currency. */
    public static function snapshot(): array
    {
        $rows = LedgerService::positions();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['account_id'] . ':' . $r['currency_id']] = [
                'account_id' => (int)$r['account_id'],
                'currency_id' => (int)$r['currency_id'],
                'amount' => $r['amount'],
            ];
        }
        return $out;
    }

    /** Open the day (snapshot opening balances). */
    public static function open(string $date): void
    {
        $exists = Database::fetch("SELECT id FROM daily_closings WHERE closing_date = ?", [$date]);
        if ($exists) throw new DomainException('Day already opened.');
        Database::insert('daily_closings', [
            'closing_date' => $date,
            'status' => 'in_progress',
            'opened_by' => Auth::id(),
            'opening_balances' => json_encode(self::snapshot()),
        ]);
        AuditService::log('open_day', 'daily_closing', null, null, ['date' => $date]);
    }

    /** Complete the closing with physical balances + differences. */
    public static function complete(string $date, array $physical, string $notes = ''): array
    {
        $row = Database::fetch("SELECT * FROM daily_closings WHERE closing_date = ?", [$date]);
        if (!$row) throw new DomainException('Day is not open.');
        if ($row['status'] === 'closed') throw new DomainException('Day already closed.');

        $closing = self::snapshot();
        $diffs = [];
        foreach ($physical as $key => $qty) {
            $sys = $closing[$key]['amount'] ?? '0';
            $diff = Money::sub($qty, $sys);
            if (!Money::isZero($diff)) {
                $diffs[] = [
                    'account_id' => $closing[$key]['account_id'] ?? null,
                    'currency_id' => $closing[$key]['currency_id'] ?? null,
                    'system' => $sys, 'physical' => $qty, 'difference' => $diff,
                ];
            }
        }

        Database::update('daily_closings', [
            'status' => 'closed',
            'closed_by' => Auth::id(),
            'closed_at' => date('Y-m-d H:i:s'),
            'closing_balances' => json_encode($closing),
            'differences' => json_encode($diffs),
            'notes' => $notes,
        ], 'id = ?', [$row['id']]);

        foreach ($diffs as $d) {
            AuditService::log('daily_closing_difference', 'daily_closing', $row['id'],
                ['system' => $d['system']], ['physical' => $d['physical'], 'difference' => $d['difference']]);
        }
        AuditService::log('close_day', 'daily_closing', $row['id'], null, ['date' => $date, 'differences' => count($diffs)]);
        return $diffs;
    }

    /** True when the business day is closed or approved (final). */
    public static function isClosed(string $date): bool
    {
        $row = Database::fetch("SELECT status FROM daily_closings WHERE closing_date = ?", [$date]);
        return $row && in_array($row['status'], ['closed', 'approved'], true);
    }

    /**
     * Closed-day write guard: throws unless the operator holds closing_approve
     * (owner/manager) so a closed day can only be changed via an authorized
     * reopen or reversal process.
     */
    public static function guardWrite(string $date): void
    {
        if (!self::isClosed($date)) return;
        if (Auth::hasPermission('closing_approve')) return;
        throw new DomainException(t('closing.day_closed'));
    }

    /** Pre-close readiness checks (warnings, not blockers). */
    public static function checks(string $date): array
    {
        $checks = [];
        $add = function (string $key, bool $ok, string $message) use (&$checks): void {
            $checks[] = ['key' => $key, 'ok' => $ok, 'message' => $message];
        };

        // Pending / unconfirmed transactions
        $pending = (int)(Database::value(
            "SELECT COUNT(*) FROM transactions WHERE status NOT IN ('completed') AND DATE(tx_date) = ?", [$date]) ?: 0);
        $add('pending_tx', $pending === 0,
            $pending ? sprintf('%d %s', $pending, t('closing.check.pending_tx')) : t('closing.check.no_pending_tx'));

        // Unresolved reconciliation (cash discrepancies)
        $recon = (int)(Database::value(
            "SELECT COUNT(*) FROM reconciliations WHERE status = 'pending' AND DATE(created_at) = ?", [$date]) ?: 0);
        $add('reconciliation', $recon === 0,
            $recon ? sprintf('%d %s', $recon, t('closing.check.reconciliation')) : t('closing.check.no_reconciliation'));

        // Negative inventory balances
        $neg = Database::query(
            "SELECT c.code, ic.qty FROM inventory_costings ic
             JOIN currencies c ON c.id = ic.currency_id
             WHERE ic.qty < 0");
        $add('negative_inventory', count($neg) === 0,
            $neg ? implode(', ', array_map(fn($r) => $r['code'] . ' ' . Money::format((string)$r['qty'], 2), $neg)) : t('closing.check.no_negative_inventory'));

        // Outstanding receivables / payables
        $recv = (string)(Database::value(
            "SELECT COALESCE(SUM(balance),0) FROM customer_accounts WHERE balance > 0") ?: '0');
        $pay = (string)(Database::value(
            "SELECT COALESCE(SUM(-balance),0) FROM customer_accounts WHERE balance < 0") ?: '0');
        $add('receivables', Money::isZero($recv),
            Money::isZero($recv) ? t('closing.check.no_receivables') : t('closing.check.receivables') . ' ' . Money::format($recv, 2));
        $add('payables', Money::isZero($pay),
            Money::isZero($pay) ? t('closing.check.no_payables') : t('closing.check.payables') . ' ' . Money::format($pay, 2));

        // Recent backup status
        $lastBackup = Database::fetch("SELECT created_at, status, verified FROM backup_records ORDER BY id DESC LIMIT 1");
        $backupOk = $lastBackup && $lastBackup['status'] === 'ok';
        $add('backup', $backupOk,
            $backupOk ? t('closing.check.backup_ok') . ' ' . tz($lastBackup['created_at']) : t('closing.check.backup_missing'));

        // Stale exchange rates
        $stale = (int)(Database::value(
            "SELECT COUNT(*) FROM exchange_rates
             WHERE rate_status IN ('stale','offline')
                OR (retrieved_at IS NOT NULL AND retrieved_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))") ?: 0);
        $add('rates', $stale === 0,
            $stale ? t('closing.check.stale_rates') : t('closing.check.rates_ok'));

        return $checks;
    }

    /**
     * Per-currency daily summary: opening vs bought/sold vs expected vs current.
     * Opening comes from the stored opening snapshot; activity from the ledger
     * (transactions) for the date; current from the live position.
     */
    public static function currencySummary(string $date): array
    {
        $row = Database::fetch("SELECT * FROM daily_closings WHERE closing_date = ?", [$date]);
        $opening = $row && $row['opening_balances']
            ? (array)json_decode($row['opening_balances'], true) : [];
        $currencies = Database::query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY code");
        $current = self::snapshot();

        $from = $date . ' 00:00:00';
        $to = $date . ' 23:59:59';
        $activity = Database::query(
            "SELECT t.currency_id,
                    COALESCE(SUM(CASE WHEN t.type = 'buy' THEN t.foreign_amount ELSE 0 END),0) AS bought,
                    COALESCE(SUM(CASE WHEN t.type = 'sell' THEN t.foreign_amount ELSE 0 END),0) AS sold
             FROM transactions t
             WHERE t.status = 'completed' AND t.tx_date BETWEEN ? AND ?
               AND t.currency_id IS NOT NULL
             GROUP BY t.currency_id", [$from, $to]);
        $activityBy = [];
        foreach ($activity as $a) $activityBy[(int)$a['currency_id']] = $a;

        $rows = [];
        $totalExpected = Money::zero();
        $totalCurrent = Money::zero();
        $totalDiff = Money::zero();
        foreach ($currencies as $c) {
            $currencyId = (int)$c['id'];
            $open = Money::zero();
            foreach ($opening as $k => $v) {
                if (str_ends_with((string)$k, ':' . $currencyId)) {
                    $open = Money::add($open, (string)$v['amount']);
                }
            }
            $bought = (string)($activityBy[$currencyId]['bought'] ?? '0');
            $sold = (string)($activityBy[$currencyId]['sold'] ?? '0');
            $expected = Money::round(Money::add(Money::sub($open, $sold), $bought), 10);
            $cur = Money::zero();
            foreach ($current as $k => $v) {
                if (str_ends_with((string)$k, ':' . $currencyId)) {
                    $cur = Money::add($cur, (string)$v['amount']);
                }
            }
            $diff = Money::round(Money::sub($cur, $expected), 10);
            $rows[] = [
                'currency' => $c, 'opening' => $open, 'bought' => $bought, 'sold' => $sold,
                'expected' => $expected, 'current' => $cur, 'difference' => $diff,
            ];
            $totalExpected = Money::add($totalExpected, $expected);
            $totalCurrent = Money::add($totalCurrent, $cur);
            $totalDiff = Money::add($totalDiff, $diff);
        }
        return [
            'rows' => $rows,
            'total_expected' => $totalExpected,
            'total_current' => $totalCurrent,
            'total_difference' => $totalDiff,
        ];
    }

    /** Final approval of a closed day (manager/owner). */
    public static function approve(string $date): void
    {
        if (!Auth::hasPermission('closing_approve')) {
            throw new DomainException(t('errors.403_msg'));
        }
        $row = Database::fetch("SELECT * FROM daily_closings WHERE closing_date = ?", [$date]);
        if (!$row) throw new DomainException(t('closing.not_open'));
        if ($row['status'] !== 'closed') {
            throw new DomainException(t('closing.approve_requires_close'));
        }
        Database::update('daily_closings', [
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$row['id']]);
        AuditService::log('approve_day', 'daily_closing', $row['id'], ['status' => 'closed'],
            ['status' => 'approved', 'date' => $date]);
    }

    /** Authorized reopening of a closed/approved day (owner/manager only). */
    public static function reopen(string $date): void
    {
        if (!Auth::hasPermission('closing_approve')) {
            throw new DomainException(t('errors.403_msg'));
        }
        $row = Database::fetch("SELECT * FROM daily_closings WHERE closing_date = ?", [$date]);
        if (!$row) throw new DomainException(t('closing.not_open'));
        if (!in_array($row['status'], ['closed', 'approved'], true)) {
            throw new DomainException(t('closing.reopen_requires_close'));
        }
        Database::update('daily_closings', [
            'status' => 'in_progress',
            'approved_by' => null, 'approved_at' => null,
        ], 'id = ?', [$row['id']]);
        AuditService::log('reopen_day', 'daily_closing', $row['id'], ['status' => $row['status']],
            ['status' => 'in_progress', 'date' => $date]);
    }
}

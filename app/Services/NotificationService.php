<?php
declare(strict_types=1);

/**
 * Notifications & alerts. Low inventory, large transactions, outstanding balances,
 * pending reconciliation, missing daily closing.
 */
final class NotificationService
{
    public static function push(string $type, string $title, string $message, ?int $userId = null): void
    {
        Database::insert('notifications', [
            'user_id' => $userId, 'type' => $type, 'title' => $title, 'message' => $message,
        ]);
    }

    public static function unread(?int $userId = null): int
    {
        if ($userId === null) {
            return (int)(Database::value("SELECT COUNT(*) FROM notifications WHERE is_read = 0") ?: 0);
        }
        return (int)(Database::value(
            "SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND (user_id IS NULL OR user_id = ?)",
            [$userId]) ?: 0);
    }

    public static function recent(int $limit = 10, ?int $userId = null): array
    {
        $sql = "SELECT * FROM notifications";
        $params = [];
        if ($userId !== null) {
            $sql .= " WHERE user_id IS NULL OR user_id = ?";
            $params[] = $userId;
        }
        $sql .= " ORDER BY id DESC LIMIT " . (int)$limit;
        return Database::query($sql, $params);
    }

    public static function markAllRead(?int $userId = null): void
    {
        if ($userId === null) {
            Database::execute("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");
        } else {
            Database::execute(
                "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0 AND (user_id IS NULL OR user_id = ?)",
                [$userId]);
        }
    }

    /** Evaluate all alert rules; returns list of active alerts. */
    public static function evaluateAlerts(): array
    {
        $alerts = [];
        $base = SettingService::baseCurrency();

        // Low inventory thresholds (per currency setting: low_threshold_{CODE})
        $currencies = Database::query("SELECT * FROM currencies WHERE is_active = 1 AND is_base = 0");
        foreach ($currencies as $c) {
            $threshold = SettingService::get('low_inventory_' . $c['code'], '0');
            if (!is_numeric($threshold) || Money::isZero($threshold)) continue;
            $costing = InventoryService::costing((int)$c['id']);
            if (Money::compare((string)$costing['qty'], (string)$threshold) < 0) {
                $alerts[] = [
                    'type' => 'low_inventory',
                    'message' => $c['code'] . ' ' . t('alert.low_inventory') . ' '
                        . Money::format((string)$costing['qty'], 2) . ' (' . t('alert.threshold') . ' ' . Money::format((string)$threshold, 2) . ')',
                ];
            }
        }

        // Outstanding receivables / payables
        $recv = (int)(Database::value("SELECT COUNT(*) FROM customer_accounts WHERE balance > 0") ?: 0);
        if ($recv > 0) {
            $alerts[] = ['type' => 'receivables', 'message' => $recv . ' ' . t('alert.customers_owe')];
        }
        $pay = (int)(Database::value("SELECT COUNT(*) FROM customer_accounts WHERE balance < 0") ?: 0);
        if ($pay > 0) {
            $alerts[] = ['type' => 'payables', 'message' => $pay . ' ' . t('alert.we_owe_customers')];
        }

        // Pending reconciliation
        $pendingRec = (int)(Database::value("SELECT COUNT(*) FROM reconciliations WHERE status = 'pending'") ?: 0);
        if ($pendingRec > 0) {
            $alerts[] = ['type' => 'reconciliation', 'message' => $pendingRec . ' ' . t('alert.pending_reconciliation')];
        }

        // Daily closing incomplete (today in business tz)
        $today = (new DateTime('now', new DateTimeZone(cfg('app.timezone', 'UTC'))))->format('Y-m-d');
        $closing = Database::fetch("SELECT status FROM daily_closings WHERE closing_date = ?", [$today]);
        if (!$closing || $closing['status'] !== 'closed') {
            $alerts[] = ['type' => 'closing', 'message' => t('alert.closing_pending')];
        }

        return $alerts;
    }
}

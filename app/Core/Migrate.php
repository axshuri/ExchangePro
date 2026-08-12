<?php
declare(strict_types=1);

/**
 * Idempotent schema/data migrations for the advanced feature set.
 * Safe to run repeatedly (each step checks existence first).
 * Run via: php database/migrate.php   (or in tests after seeding)
 */
final class Migrate
{
    public static function run(): array
    {
        $out = [];

        // ---- currencies: inventory forecast targets ----
        foreach (['min_inventory', 'target_inventory', 'max_inventory'] as $col) {
            if (!self::hasColumn('currencies', $col)) {
                Database::execute(
                    "ALTER TABLE currencies ADD COLUMN `$col` DECIMAL(30,10) NULL AFTER max_amount"
                );
                $out[] = "currencies.$col added";
            }
        }

        // ---- transactions: realized P/L attribution (per-currency analytics) ----
        if (!self::hasColumn('transactions', 'realized_pl')) {
            Database::execute("ALTER TABLE transactions ADD COLUMN realized_pl DECIMAL(30,10) NULL AFTER total_amount");
            $out[] = 'transactions.realized_pl added';
        }
        if (!self::hasColumn('transactions', 'pl_currency_id')) {
            Database::execute("ALTER TABLE transactions ADD COLUMN pl_currency_id INT UNSIGNED NULL AFTER realized_pl");
            $out[] = 'transactions.pl_currency_id added';
        }

        // ---- backup_records: checksum / encryption / verification metadata ----
        if (!self::hasColumn('backup_records', 'checksum')) {
            Database::execute("ALTER TABLE backup_records ADD COLUMN checksum VARCHAR(64) NULL AFTER size");
            $out[] = 'backup_records.checksum added';
        }
        if (!self::hasColumn('backup_records', 'encrypted')) {
            Database::execute("ALTER TABLE backup_records ADD COLUMN encrypted TINYINT(1) NOT NULL DEFAULT 0 AFTER checksum");
            $out[] = 'backup_records.encrypted added';
        }
        if (!self::hasColumn('backup_records', 'verified')) {
            Database::execute("ALTER TABLE backup_records ADD COLUMN verified TINYINT(1) NOT NULL DEFAULT 0 AFTER encrypted");
            $out[] = 'backup_records.verified added';
        }

        // ---- daily_closings: approval flow ----
        $status = self::columnType('daily_closings', 'status');
        if ($status !== null && !str_contains($status, 'approved')) {
            Database::execute(
                "ALTER TABLE daily_closings MODIFY status ENUM('open','in_progress','closed','approved') NOT NULL DEFAULT 'open'"
            );
            $out[] = 'daily_closings.status extended (approved)';
        }
        if (!self::hasColumn('daily_closings', 'approved_by')) {
            Database::execute("ALTER TABLE daily_closings ADD COLUMN approved_by INT UNSIGNED NULL AFTER closed_at");
            $out[] = 'daily_closings.approved_by added';
        }
        if (!self::hasColumn('daily_closings', 'approved_at')) {
            Database::execute("ALTER TABLE daily_closings ADD COLUMN approved_at DATETIME NULL AFTER approved_by");
            $out[] = 'daily_closings.approved_at added';
        }

        // ---- new permissions + role grants ----
        $perms = [
            'view_analytics'   => 'View profit analytics',
            'view_price_board' => 'View the price board',
            'closing_approve'  => 'Approve / reopen daily closing',
        ];
        $ids = [];
        $stmt = Database::connection()->prepare("INSERT IGNORE INTO permissions (code, description) VALUES (?, ?)");
        foreach ($perms as $code => $desc) {
            $stmt->execute([$code, $desc]);
            // INSERT IGNORE returns 0 on a duplicate — always re-select the real id
            // so repeated runs grant against the existing row (idempotent).
            $ids[$code] = (int)(Database::value("SELECT id FROM permissions WHERE code = ?", [$code]) ?: 0);
        }
        if ($ids) {
            $out[] = 'permissions: ' . implode(', ', array_keys($perms));
        }
        $grant = function (string $role, array $codes) use ($ids): void {
            $roleId = (int)(Database::value("SELECT id FROM roles WHERE name = ?", [$role]) ?: 0);
            if (!$roleId) return;
            $stmt = Database::connection()->prepare(
                "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)"
            );
            foreach ($codes as $code) {
                if (isset($ids[$code])) $stmt->execute([$roleId, $ids[$code]]);
            }
        };
        $grant('owner', array_keys($perms));
        $grant('manager', array_keys($perms));
        $grant('accountant', ['view_analytics', 'view_price_board', 'closing_approve']);
        $grant('cashier', ['view_price_board']);
        $grant('viewer', ['view_analytics', 'view_price_board']);

        // ---- settings defaults ----
        $settings = [
            'price_board_refresh'      => '30',   // seconds between auto-refresh
            'backup_enabled'           => '0',
            'backup_time'              => '02:00',
            'backup_retention_daily'   => '30',
            'backup_retention_weekly'  => '12',
            'backup_retention_monthly' => '12',
            'inventory_min_default'    => '0',
            'inventory_target_default' => '0',
            'inventory_max_default'    => '0',
        ];
        foreach ($settings as $k => $v) {
            $exists = (bool)Database::value("SELECT id FROM settings WHERE setting_key = ?", [$k]);
            if (!$exists) {
                Database::insert('settings', ['setting_key' => $k, 'setting_value' => $v]);
                $out[] = "setting $k = $v";
            }
        }

        return $out;
    }

    private static function hasColumn(string $table, string $col): bool
    {
        $db = cfg('db');
        return (bool)Database::value(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$db['name'], $table, $col]
        );
    }

    private static function columnType(string $table, string $col): ?string
    {
        $db = cfg('db');
        $v = Database::value(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$db['name'], $table, $col]
        );
        return $v === null ? null : (string)$v;
    }
}

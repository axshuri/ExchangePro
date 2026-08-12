<?php
declare(strict_types=1);

/**
 * Idempotent schema migration for the rate-sync feature.
 *
 * schema.sql carries the full definition for fresh installs and the test
 * suite; this class lets an EXISTING database adopt the new columns/tables
 * without a manual migration step (checked via information_schema, so it is
 * safe to call on every request path that needs the new structure).
 */
final class RateSyncSchema
{
    private static bool $done = false;

    public static function ensure(): void
    {
        if (self::$done) return;
        $pdo = Database::connection();

        $cols = [];
        foreach ($pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exchange_rates'"
        )->fetchAll() as $r) {
            $cols[$r['COLUMN_NAME']] = true;
        }

        if (!isset($cols['reference_rate'])) {
            $pdo->exec("ALTER TABLE exchange_rates
                ADD COLUMN reference_rate     DECIMAL(30,10) NULL AFTER mid_rate,
                ADD COLUMN previous_reference DECIMAL(30,10) NULL AFTER reference_rate,
                ADD COLUMN buy_spread_type    ENUM('fixed','percent') NULL AFTER previous_reference,
                ADD COLUMN buy_spread_value   DECIMAL(30,10) NULL AFTER buy_spread_type,
                ADD COLUMN sell_spread_type   ENUM('fixed','percent') NULL AFTER buy_spread_value,
                ADD COLUMN sell_spread_value  DECIMAL(30,10) NULL AFTER sell_spread_type,
                ADD COLUMN buy_override       DECIMAL(30,10) NULL AFTER sell_spread_value,
                ADD COLUMN sell_override      DECIMAL(30,10) NULL AFTER buy_override,
                ADD COLUMN override_persistent TINYINT(1) NOT NULL DEFAULT 0 AFTER sell_override,
                ADD COLUMN provider           VARCHAR(64) NULL AFTER source,
                ADD COLUMN provider_timestamp DATETIME NULL AFTER provider,
                ADD COLUMN retrieved_at       DATETIME NULL AFTER provider_timestamp,
                ADD COLUMN rate_status        ENUM('online','cached','stale','manual') NOT NULL DEFAULT 'manual' AFTER retrieved_at");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS exchange_rate_history (
            id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            currency_id        INT UNSIGNED NOT NULL,
            base_currency_id   INT UNSIGNED NOT NULL,
            reference_rate     DECIMAL(30,10) NOT NULL,
            provider           VARCHAR(64) NULL,
            provider_timestamp DATETIME NULL,
            retrieved_at       DATETIME NOT NULL,
            sync_id            BIGINT UNSIGNED NULL,
            created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_erh_currency FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE CASCADE,
            KEY idx_erh_cur_time (currency_id, retrieved_at)
        ) ENGINE=InnoDB");

        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_sync_logs (
            id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider           VARCHAR(64) NOT NULL,
            status             ENUM('success','failed','partial','skipped') NOT NULL DEFAULT 'success',
            triggered_by       ENUM('login','manual','cron') NOT NULL DEFAULT 'manual',
            started_at         DATETIME NOT NULL,
            completed_at       DATETIME NULL,
            currencies_updated INT UNSIGNED NOT NULL DEFAULT 0,
            currencies_skipped INT UNSIGNED NOT NULL DEFAULT 0,
            currencies_failed  INT UNSIGNED NOT NULL DEFAULT 0,
            error_message      TEXT NULL,
            created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_rsl_time (started_at),
            KEY idx_rsl_status (status)
        ) ENGINE=InnoDB");

        self::$done = true;
    }
}

<?php
declare(strict_types=1);

/**
 * Database-backed settings (override config defaults).
 */
final class SettingService
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            $rows = Database::query("SELECT setting_key, setting_value FROM settings");
            self::$cache = [];
            foreach ($rows as $r) {
                self::$cache[$r['setting_key']] = $r['setting_value'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        self::all();
        $exists = array_key_exists($key, self::$cache);
        if ($exists) {
            Database::update('settings', ['setting_value' => (string)$value], 'setting_key = ?', [$key]);
        } else {
            Database::insert('settings', ['setting_key' => $key, 'setting_value' => (string)$value]);
        }
        self::$cache[$key] = (string)$value;
    }

    /** Business settings that affect the whole app. */
    public static function businessName(): string
    {
        return (string)self::get('business_name', cfg('app.name', 'ExchangePro'));
    }

    public static function baseCurrencyId(): int
    {
        static $id = null;
        if ($id === null) {
            $code = (string)self::get('base_currency', cfg('app.base_currency', 'CAD'));
            $row = Database::fetch("SELECT id FROM currencies WHERE code = ?", [$code]);
            $id = $row ? (int)$row['id'] : 0;
        }
        return $id;
    }

    public static function baseCurrency(): ?array
    {
        $row = Database::fetch("SELECT * FROM currencies WHERE id = ?", [self::baseCurrencyId()]);
        return $row ?: null;
    }

    public static function txPrefix(): string
    {
        return (string)self::get('tx_prefix', cfg('defaults.tx_prefix', 'EX'));
    }

    public static function largeTxThreshold(): string
    {
        return (string)self::get('large_tx_threshold', cfg('defaults.large_tx_threshold', '25000'));
    }

    public static function profitMethod(): string
    {
        return (string)self::get('profit_method', cfg('defaults.profit_method', 'weighted_average'));
    }
}

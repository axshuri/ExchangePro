<?php
/**
 * Central configuration for the Currency Exchange Management System.
 * Override any value via environment variables (Windows: setx VAR value).
 */

return [
    // --- Database ---------------------------------------------------------
    'db' => [
        'host'     => getenv('EXCHANGE_DB_HOST') ?: '127.0.0.1',
        'port'     => (int)(getenv('EXCHANGE_DB_PORT') ?: 3306),
        'name'     => getenv('EXCHANGE_DB_NAME') ?: 'exchange_cms',
        'user'     => getenv('EXCHANGE_DB_USER') ?: 'root',
        'pass'     => getenv('EXCHANGE_DB_PASS') ?: '',
        'charset'  => 'utf8mb4',
    ],

    // --- Application ------------------------------------------------------
    'app' => [
        'name'       => 'ExchangePro',
        'version'    => '1.0.0',
        'base_currency' => 'CAD',   // configurable in Settings afterwards
        'timezone'   => 'America/Toronto', // business timezone
        'language'   => 'en',            // en | fa
        'default_locale' => 'en',
        'env'        => 'development',   // development | production
        'debug'      => true,
    ],

    // --- Security ---------------------------------------------------------
    'security' => [
        'session_name'      => 'exch_sess',
        'session_lifetime'  => 60 * 60 * 8,      // 8 hours inactivity timeout
        'cookie_secure'     => false,            // set true when HTTPS enabled
        'password_algo'     => PASSWORD_BCRYPT,  // bcrypt (Argon2id also fine)
        'login_max_attempts'=> 5,
        'login_lock_minutes'=> 15,
        'csrf'              => true,
        'backup_encrypt_key'=> getenv('EXCHANGE_BACKUP_KEY') ?: 'change-this-key-before-production',
    ],

    // --- Paths ------------------------------------------------------------
    'paths' => [
        'storage'   => dirname(__DIR__) . '/storage',
        'uploads'   => dirname(__DIR__) . '/storage/uploads',
        'backups'   => dirname(__DIR__) . '/storage/backups',
        'logs'      => dirname(__DIR__) . '/storage/logs',
    ],

    // --- Business defaults (overridable in Settings UI) -------------------
    'defaults' => [
        'tx_prefix'        => 'EX',          // e.g. EX-20260811-000001
        'tx_number_format' => '{PREFIX}-{YYYYMMDD}-{SEQ:06}',
        'large_tx_threshold' => 25000,       // base currency, configurable
        'low_inventory_threshold' => 0,      // per currency, 0 = disabled
        'profit_method'    => 'weighted_average', // weighted_average | fifo (later)
        'receipt_footer'   => '',
    ],

    // --- Automatic online rate synchronization (Rates page) --------------
    // External rates are market REFERENCE rates only. Actual Buy/Sell rates are
    // always business-controlled via spreads or manual overrides.
    'rate_sync' => [
        'enabled'            => true,                     // auto-sync on login/cron
        'provider'           => 'frankfurter',            // provider identifier
        'base_currency'      => 'EUR',                    // provider base currency (Frankfurter = EUR)
        'api_timeout'        => 8,                        // seconds per HTTP request
        'cache_ttl'          => 3600,                     // seconds between syncs (1h)
        'stale_threshold'    => 86400,                    // after this, rates are shown as outdated (24h)
        'max_change_percent' => '10',                     // reject auto-apply above this % change
        'retry_attempts'     => 3,                        // retries for manual/cron syncs (login uses 1)
        'retry_delays'       => [2, 5, 15],               // seconds between retries
        // Default Buy/Sell spread applied to the reference rate when a currency
        // has no per-currency spread configured. Percent scales with rate size.
        // Buy value is negative (below reference), sell positive (above).
        'buy_spread_type'    => 'percent',
        'buy_spread_value'   => '-0.5',
        'sell_spread_type'   => 'percent',
        'sell_spread_value'  => '0.5',
    ],

    // --- Bootstrap dirs ----------------------------------------------------
    'autoload_dirs' => [
        dirname(__DIR__) . '/app/Core',
        dirname(__DIR__) . '/app/Services',
        dirname(__DIR__) . '/app/Services/ExchangeRate',
        dirname(__DIR__) . '/app/Controllers',
        dirname(__DIR__) . '/app/Models',
    ],
];

<?php
/**
 * Application bootstrap.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('UTC'); // stored UTC, converted per business timezone on display

define('BASE_PATH', dirname(__DIR__, 2));

// Deep-merge the generated config/config.local.php (written by the web/CLI
// installer) over the defaults, then let environment variables win (used by
// the test suite and by operators who prefer env-based configuration).
$config = require BASE_PATH . '/config/config.php';
$localConfigPath = BASE_PATH . '/config/config.local.php';
if (is_file($localConfigPath)) {
    $local = require $localConfigPath;
    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}
$envOverrides = [
    'EXCHANGE_DB_HOST' => ['db', 'host'],
    'EXCHANGE_DB_PORT' => ['db', 'port'],
    'EXCHANGE_DB_NAME' => ['db', 'name'],
    'EXCHANGE_DB_USER' => ['db', 'user'],
    'EXCHANGE_DB_PASS' => ['db', 'pass'],
    'EXCHANGE_BACKUP_KEY' => ['security', 'backup_encrypt_key'],
    // Rate synchronization (ops-level overrides; Settings UI values win over these)
    'EXCHANGE_RATE_PROVIDER' => ['rate_sync', 'provider'],
    'EXCHANGE_RATE_BASE_CURRENCY' => ['rate_sync', 'base_currency'],
    'EXCHANGE_RATE_API_TIMEOUT' => ['rate_sync', 'api_timeout'],
    'EXCHANGE_RATE_CACHE_TTL' => ['rate_sync', 'cache_ttl'],
    'EXCHANGE_RATE_AUTO_SYNC' => ['rate_sync', 'enabled'],
    // XE.com provider credentials (ops-level; the Settings UI values win)
    'EXCHANGE_RATE_XE_ACCOUNT_ID' => ['rate_sync', 'xe_account_id'],
    'EXCHANGE_RATE_XE_API_KEY' => ['rate_sync', 'xe_api_key'],
];
foreach ($envOverrides as $envKey => [$group, $key]) {
    $envValue = getenv($envKey);
    if ($envValue === false) continue;
    if ($key === 'port') {
        $config[$group][$key] = (int)$envValue;
    } elseif ($key === 'enabled') {
        $config[$group][$key] = filter_var($envValue, FILTER_VALIDATE_BOOL);
    } elseif (in_array($key, ['api_timeout', 'cache_ttl'], true)) {
        $config[$group][$key] = (int)$envValue;
    } else {
        $config[$group][$key] = $envValue;
    }
}
define('CONFIG', $config);

spl_autoload_register(function (string $class): void {
    foreach (CONFIG['autoload_dirs'] as $dir) {
        $file = $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

// Global config accessor
function cfg(string $key, $default = null) {
    $parts = explode('.', $key);
    $node = CONFIG;
    foreach ($parts as $p) {
        if (!is_array($node) || !array_key_exists($p, $node)) return $default;
        $node = $node[$p];
    }
    return $node;
}

require_once __DIR__ . '/helpers.php';

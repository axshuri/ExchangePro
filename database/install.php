<?php
/**
 * CLI installer — run with: php database/install.php [--with-demo] [--admin-pass=...] [--force]
 *
 * Creates the database, applies schema, seeds roles/permissions/settings,
 * creates the admin user, optionally seeds demo data, and writes
 * config/installed.lock (which unlocks the app and disables the web installer).
 *
 * Database credentials come from config/config.php (or config/config.local.php,
 * or the EXCHANGE_DB_* environment variables).
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../app/Core/bootstrap.php';

$opts = getopt('', ['with-demo', 'admin-pass::', 'admin-user::', 'admin-email::', 'force', 'reinstall']);
$withDemo = isset($opts['with-demo']);
$adminPass = $opts['admin-pass'] ?? 'Admin@12345';
$adminUser = $opts['admin-user'] ?? 'admin';
$adminEmail = $opts['admin-email'] ?? 'admin@example.com';
$force = isset($opts['force']) || isset($opts['reinstall']);

$db = cfg('db');

echo "== ExchangePro Installer ==\n";
echo "Database: {$db['user']}@{$db['host']}:{$db['port']}/{$db['name']}\n\n";

$result = Installer::run([
    'force'     => $force,
    'with_demo' => $withDemo,
    'admin'     => [
        'username' => $adminUser,
        'email'    => $adminEmail,
        'password' => $adminPass,
        'full_name' => 'System Owner',
    ],
]);

foreach ($result['messages'] as $m) {
    echo "  * $m\n";
}
if ($result['errors']) {
    echo "\nErrors:\n";
    foreach ($result['errors'] as $e) {
        echo "  ! $e\n";
    }
    exit(1);
}

echo "\nAdmin login: {$adminUser} / {$adminPass}  →  change it immediately after first login.\n";
echo "Serve the app by pointing your document root at the 'public' folder, or run:\n";
echo "  php -S 127.0.0.1:8000 -t public\n";
echo "Then open http://127.0.0.1:8000/\n";

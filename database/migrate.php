<?php
/**
 * CLI migrations — applies any pending schema/permission/setting changes.
 *
 *   php database/migrate.php
 *
 * Idempotent: safe to run any number of times.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';

echo "== ExchangePro migrations ==\n";
$applied = Migrate::run();
if (!$applied) {
    echo "  Nothing to migrate — database is up to date.\n";
} else {
    foreach ($applied as $m) {
        echo "  ✓ $m\n";
    }
}
echo "Done.\n";

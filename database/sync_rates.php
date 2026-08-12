<?php
/**
 * CLI trigger for automatic rate synchronization (scheduled job).
 *
 * Primary trigger (recommended via cron, hourly):
 *   * * * * * php /path/to/database/sync_rates.php
 *
 * Login-triggered sync remains as a fallback; the service skips work when
 * the cache is still fresh, so running this frequently is harmless.
 *
 * Exit codes: 0 = ok/skipped, 1 = failed.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';
Session::start();

$result = RateSyncService::sync(false, 'cron');

$line = 'Rate sync [' . date('Y-m-d H:i:s') . ']: ' . $result['status']
    . ' — updated ' . (int)($result['updated'] ?? 0)
    . ', skipped ' . (int)($result['skipped'] ?? 0)
    . ', failed ' . (int)($result['failed'] ?? 0);
if (!empty($result['error']) && is_array($result['error'])) {
    $line .= ' — ' . implode('; ', array_slice($result['error'], 0, 3));
} elseif (!empty($result['message'])) {
    $line .= ' — ' . $result['message'];
}
echo $line . PHP_EOL;

exit(in_array($result['status'], ['success', 'partial', 'cached', 'disabled', 'in_progress'], true) ? 0 : 1);

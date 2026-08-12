<?php
/**
 * Scheduled backup runner — for cron / Windows Task Scheduler:
 *
 *   php database/backup.php            # creates a scheduled backup if enabled
 *   php database/backup.php --force    # always create, ignoring backup_enabled
 *
 * Backups are encrypted + checksum-verified and pruned per the configured
 * retention policy (backup_retention_daily/weekly/monthly).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';

$force = in_array('--force', $argv ?? [], true);

if (!$force && (string)SettingService::get('backup_enabled', '0') !== '1') {
    echo "Scheduled backups are disabled (setting backup_enabled). Use --force to override.\n";
    exit(0);
}

try {
    $result = BackupService::create(true, 'scheduled');
    echo "Backup created: {$result['file']} (" . BackupService::humanSize($result['size']) . ") — " . ($result['verified'] ? 'VERIFIED' : 'NOT VERIFIED') . "\n";
    BackupService::prune('scheduled');
    echo "Retention pruned.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Backup FAILED: " . $e->getMessage() . "\n");
    // Record the failure so the dashboard's backup status surfaces it
    // (backup failures must never be silently ignored).
    try {
        Database::insert('backup_records', [
            'file_name' => 'FAILED-' . date('Ymd_His') . '.sql',
            'file_path' => '', 'size' => 0, 'kind' => 'scheduled',
            'status' => 'failed', 'checksum' => null, 'encrypted' => 0,
            'verified' => 0, 'created_by' => Auth::effectiveUserId(),
        ]);
    } catch (Throwable $ignore) {
        // The DB itself may be the thing that failed — nothing more we can do.
    }
    exit(1);
}

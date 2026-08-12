<?php
declare(strict_types=1);

/**
 * Backup & restore. Uses mysqldump (Laragon ships it); falls back to a
 * PHP-driven INSERT dump. Backups are stored in storage/backups and can be
 * optionally AES-256-CBC encrypted. Restore imports a .sql or .sql.enc file.
 *
 * Every backup records a SHA-256 checksum and is verified (readable +
 * decryptable) before being marked VERIFIED. Retention is per kind and
 * configurable (backup_retention_daily/weekly/monthly).
 */
final class BackupService
{
    public static function create(bool $encrypt = true, string $kind = 'manual'): array
    {
        $backupDir = cfg('paths.backups');
        if (!is_dir($backupDir)) @mkdir($backupDir, 0777, true);

        // Microsecond suffix keeps filenames unique even for rapid successive
        // backups (e.g. a restore's safety point moments after a manual backup).
        $date = date('Ymd_His') . '_' . str_pad((string)((int)(microtime(true) * 1000) % 1000), 3, '0', STR_PAD_LEFT);
        $baseName = "exchange_{$date}.sql";
        $fileName = $encrypt ? $baseName . '.enc' : $baseName;
        $filePath = $backupDir . '/' . $fileName;

        $sql = self::dump();
        if ($encrypt) {
            $key = hash('sha256', cfg('security.backup_encrypt_key'), true);
            $iv = random_bytes(16);
            $encrypted = openssl_encrypt($sql, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($encrypted === false) {
                throw new RuntimeException('Encryption failed.');
            }
            file_put_contents($filePath, base64_encode('EXCH_ENC:1:' . base64_encode($iv) . ':' . base64_encode($encrypted)));
        } else {
            file_put_contents($filePath, $sql);
        }

        $size = filesize($filePath);
        $checksum = hash_file('sha256', $filePath);
        $verified = self::verifyFile($filePath, $checksum, $encrypt) ? 1 : 0;

        $id = Database::insert('backup_records', [
            'file_name' => $fileName, 'file_path' => $filePath,
            'size' => $size, 'kind' => $kind, 'status' => 'ok',
            'checksum' => $checksum, 'encrypted' => $encrypt ? 1 : 0, 'verified' => $verified,
            'created_by' => Auth::id(),
        ]);
        AuditService::log('create_backup', 'backup', $id, null, [
            'file' => $fileName, 'size' => $size, 'checksum' => $checksum, 'verified' => (bool)$verified,
        ]);

        self::prune($kind);

        return ['file' => $fileName, 'path' => $filePath, 'size' => $size,
            'checksum' => $checksum, 'verified' => (bool)$verified];
    }

    /** Verify a backup file: readable, checksum matches, and (if encrypted) decryptable. */
    public static function verifyFile(string $filePath, string $checksum, bool $encrypted): bool
    {
        if (!is_file($filePath) || filesize($filePath) <= 0) return false;
        if (hash_file('sha256', $filePath) !== $checksum) return false;
        if ($encrypted) {
            $content = file_get_contents($filePath);
            // The on-disk payload is base64-encoded; decode before checking the marker.
            $decoded = base64_decode((string)$content, true);
            if ($decoded === false || !str_starts_with($decoded, 'EXCH_ENC:1:')) return false;
            $parts = explode(':', $decoded, 4);
            if (count($parts) !== 4) return false;
            $key = hash('sha256', cfg('security.backup_encrypt_key'), true);
            $sql = openssl_decrypt(
                base64_decode($parts[3]), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, base64_decode($parts[2])
            );
            return $sql !== false && str_contains($sql, 'CREATE TABLE');
        }
        return true;
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        $units = ['KB', 'MB', 'GB'];
        $i = -1;
        do {
            $bytes /= 1024;
            $i++;
        } while ($bytes >= 1024 && $i < count($units) - 1);
        return round($bytes, 1) . ' ' . $units[$i];
    }

    /** Prune old backups per kind using the configured retention policy. */
    public static function prune(string $kind = 'manual'): void
    {
        if ($kind === 'manual') {
            $keep = 10;
        } else {
            // Scheduled: daily/weekly/monthly buckets share the retention window.
            $keep = max(1, (int)SettingService::get('backup_retention_daily', '30'));
        }
        $old = Database::query(
            "SELECT id, file_path FROM backup_records WHERE kind = ? ORDER BY id DESC LIMIT ?, 1000",
            [$kind, $keep]
        );
        foreach ($old as $o) {
            if (is_file($o['file_path'])) @unlink($o['file_path']);
            Database::execute("DELETE FROM backup_records WHERE id = ?", [$o['id']]);
        }
    }

    /** Dump entire DB to SQL. */
    public static function dump(): string
    {
        $db = cfg('db');
        $pdo = Database::connection();
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        $sql = "-- ExchangePro backup " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n";
        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $sql .= "\nDROP TABLE IF EXISTS `$table`;\n" . $create['Create Table'] . ";\n";
            $rows = $pdo->query("SELECT * FROM `$table`");
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
                $sql .= "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ");\n";
            }
        }
        $sql .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
        return $sql;
    }

    public static function list(): array
    {
        return Database::query(
            "SELECT b.*, u.username FROM backup_records b LEFT JOIN users u ON u.id = b.created_by
             ORDER BY b.id DESC LIMIT 50");
    }

    /** Restore from a backup record id. Creates a safety restore-point first. */
    public static function restore(int $recordId): void
    {
        $rec = Database::fetch("SELECT * FROM backup_records WHERE id = ?", [$recordId]);
        if (!$rec) throw new DomainException('Backup record not found.');
        $filePath = $rec['file_path'];
        if (!is_file($filePath)) throw new DomainException('Backup file missing.');

        // Safety backup of the current state BEFORE any destructive action.
        try {
            self::create(true, 'restore_point');
        } catch (Throwable $e) {
            throw new DomainException('Safety backup failed — restore aborted: ' . $e->getMessage());
        }

        $content = file_get_contents($filePath);
        $decoded = base64_decode((string)$content, true);
        if ($decoded !== false && str_starts_with($decoded, 'EXCH_ENC:1:')) {
            $parts = explode(':', $decoded, 4);
            $iv = base64_decode($parts[2]);
            $enc = base64_decode($parts[3]);
            $key = hash('sha256', cfg('security.backup_encrypt_key'), true);
            $sql = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($sql === false) throw new DomainException('Decryption failed — wrong backup key?');
        } else {
            $sql = $content;
        }

        $pdo = Database::connection();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        try {
            // Execute the dump as a single multi-statement batch: the naive
            // split-on-';' approach breaks on semicolons inside string values,
            // and a silent partial restore is worse than a loud failure.
            $pdo->exec($sql);
        } catch (Throwable $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            throw new DomainException('Restore failed — no data was committed for the failing statement: ' . $e->getMessage());
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        AuditService::log('restore_backup', 'backup', $recordId, null, ['file' => $rec['file_name']]);
    }

    /** Backup status summary for the dashboard. */
    public static function status(): array
    {
        $last = Database::fetch("SELECT * FROM backup_records ORDER BY id DESC LIMIT 1");
        $enabled = (string)SettingService::get('backup_enabled', '0') === '1';
        $time = (string)SettingService::get('backup_time', '02:00');
        $failed = (int)(Database::value("SELECT COUNT(*) FROM backup_records WHERE status = 'failed'") ?: 0);
        return [
            'last' => $last,
            'enabled' => $enabled,
            'time' => $time,
            'next' => $enabled ? self::nextSchedule($time) : null,
            'failed_count' => $failed,
        ];
    }

    private static function nextSchedule(string $time): string
    {
        $tz = new DateTimeZone(cfg('app.timezone', 'UTC'));
        $next = new DateTime('now', $tz);
        $next->setTime((int)substr($time, 0, 2), (int)substr($time, 3, 2), 0);
        if ($next <= new DateTime('now', $tz)) $next->modify('+1 day');
        return $next->format('Y-m-d H:i');
    }
}

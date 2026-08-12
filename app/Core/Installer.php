<?php
declare(strict_types=1);

/**
 * Shared installation logic — used by both the CLI installer
 * (database/install.php) and the web installer (public/install.php).
 *
 * Flow: connect to MySQL → (re)create the database → apply schema →
 * seed base data (roles/permissions/currencies/accounts/settings) →
 * create the owner/admin user → optionally load demo data → write
 * config/installed.lock (which disables the installer and unlocks the app).
 */
final class Installer
{
    public static function lockFile(): string
    {
        return BASE_PATH . '/config/installed.lock';
    }

    public static function isInstalled(): bool
    {
        return is_file(self::lockFile());
    }

    /**
     * @param array{
     *   force?: bool,
     *   with_demo?: bool,
     *   admin?: array{username:string,email:string,password:string,full_name:string}
     * } $opts
     * @return array{ok:bool,messages:array,errors:array,created:bool}
     */
    public static function run(array $opts = []): array
    {
        $messages = [];
        $errors = [];
        $db = cfg('db');

        try {
            // 1. Connect to MySQL without selecting a database.
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']),
                $db['user'], $db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // 2. Create the database (or reuse / force-recreate it).
            $exists = $pdo->query(
                "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . $pdo->quote($db['name'])
            )->fetchColumn();
            $created = false;
            if ($exists && !empty($opts['force'])) {
                $pdo->exec("DROP DATABASE `{$db['name']}`");
                $messages[] = "Dropped existing database '{$db['name']}'.";
                $exists = false;
            }
            if (!$exists) {
                try {
                    $pdo->exec("CREATE DATABASE `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                } catch (PDOException $e) {
                    // 1044 = CREATE denied, 1045 = bad credentials (common on shared hosts
                    // where the DB must be created through the control panel first).
                    $errno = (int)($e->errorInfo[1] ?? 0);
                    $hint = in_array($errno, [1044, 1045], true)
                        ? " — create the database and grant this user ALL PRIVILEGES first"
                          . " (e.g. cPanel → MySQL\u{00ae} Databases), then re-submit."
                        : '';
                    throw new RuntimeException("Could not create database '{$db['name']}': " . $e->getMessage() . $hint);
                }
                $messages[] = "Created database '{$db['name']}'.";
                $created = true;
            } else {
                $messages[] = "Using existing database '{$db['name']}'.";
            }

            // 3. Apply the schema through the app connection (points at cfg('db')).
            $app = Database::connection();
            $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
            if ($schema === false) {
                throw new RuntimeException('Could not read database/schema.sql.');
            }
            foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
                try {
                    $app->exec($stmt);
                } catch (PDOException $e) {
                    $errors[] = 'Schema statement: ' . $e->getMessage();
                }
            }
            $messages[] = 'Schema applied.';

            // 4. Seed base data (idempotent — INSERT IGNORE everywhere).
            require BASE_PATH . '/database/seed.php';
            $messages[] = 'Base data seeded (roles, permissions, currencies, GL accounts, settings).';

            // 5. Create the owner/admin user.
            $admin = array_merge([
                'username' => 'admin', 'email' => 'admin@example.com',
                'password' => 'Admin@12345', 'full_name' => 'System Owner',
            ], $opts['admin'] ?? []);
            $roleId = $app->query("SELECT id FROM roles WHERE name = 'owner'")->fetchColumn();
            if (!$roleId) {
                throw new RuntimeException('Owner role not found after seeding.');
            }
            $existing = $app->query(
                "SELECT id FROM users WHERE username = " . $app->quote($admin['username'])
            )->fetchColumn();
            if ($existing) {
                $messages[] = "Admin user '{$admin['username']}' already exists (password unchanged).";
            } else {
                $stmt = $app->prepare(
                    "INSERT INTO users (username, email, password_hash, full_name, role_id, status)
                     VALUES (?, ?, ?, ?, ?, 'active')"
                );
                $stmt->execute([
                    $admin['username'], $admin['email'],
                    password_hash($admin['password'], PASSWORD_BCRYPT),
                    $admin['full_name'], $roleId,
                ]);
                $messages[] = "Admin user '{$admin['username']}' created.";
            }

            // 6. Demo data (only on a freshly created database).
            if (!empty($opts['with_demo']) && $created) {
                require BASE_PATH . '/database/demo_seed.php';
                $messages[] = 'Demo data loaded.';
            }

            // 7. Lock the installer — but ONLY on a fully clean run, so a partial
            // install keeps redirecting to /install and can be retried.
            if ($errors) {
                $messages[] = 'Installation finished with errors — the installer was NOT locked.'
                    . ' Review the errors above and re-submit (check "force" to rebuild the database).';
                return ['ok' => false, 'messages' => $messages, 'errors' => $errors, 'created' => $created];
            }
            file_put_contents(self::lockFile(), 'Installed at ' . date('c') . "\n");
            $messages[] = 'Installation complete.';

            return ['ok' => true, 'messages' => $messages, 'errors' => $errors, 'created' => $created];
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
            return ['ok' => false, 'messages' => $messages, 'errors' => $errors, 'created' => false];
        }
    }
}

<?php
declare(strict_types=1);

/**
 * PDO wrapper: prepared statements everywhere, transaction helpers.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $db = cfg('db');
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'], $db['port'], $db['name'], $db['charset']
            );
            try {
                self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    /** Run a query, return rows. */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = [], $default = null)
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return $v === false ? $default : $v;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES ("
            . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::execute($sql, array_values($data));
        return (int)self::connection()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[] = "`$col` = ?";
            $params[] = $val;
        }
        $params = array_merge($params, $whereParams);
        return self::execute("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE $where", $params);
    }

    private static int $txDepth = 0;

    /**
     * Nested-transaction-safe wrapper using savepoints.
     * Inner transactions roll back to their savepoint on failure; outer commit
     * or rollback applies to the whole batch — guaranteeing atomicity.
     */
    public static function transaction(callable $fn)
    {
        $pdo = self::connection();
        $isRoot = !$pdo->inTransaction();
        if ($isRoot) {
            $pdo->beginTransaction();
        } else {
            self::$txDepth++;
            $pdo->exec('SAVEPOINT sp' . self::$txDepth);
        }
        try {
            $result = $fn($pdo);
            if ($isRoot) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT sp' . self::$txDepth);
                self::$txDepth--;
            }
            return $result;
        } catch (Throwable $e) {
            if ($isRoot) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } else {
                $pdo->exec('ROLLBACK TO SAVEPOINT sp' . self::$txDepth);
                self::$txDepth--;
            }
            throw $e;
        }
    }

    /** Lock a row inside a transaction (prevents race conditions). */
    public static function lockRow(string $sql, array $params = []): ?array
    {
        $stmt = self::connection()->prepare($sql . ' FOR UPDATE');
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Named advisory lock (MySQL GET_LOCK). Used to serialize one-off jobs
     * (e.g. rate synchronization) across users/processes.
     */
    public static function namedLock(string $name, int $timeout = 1): bool
    {
        $stmt = self::connection()->prepare('SELECT GET_LOCK(?, ?)');
        $stmt->execute([$name, $timeout]);
        $result = (int)$stmt->fetchColumn();
        $stmt->closeCursor(); // native prepares are unbuffered — free the result
        return $result === 1;
    }

    public static function namedUnlock(string $name): void
    {
        $stmt = self::connection()->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([$name]);
        $stmt->closeCursor();
    }
}

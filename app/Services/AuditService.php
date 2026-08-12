<?php
declare(strict_types=1);

/**
 * Audit logging. Immutable by design — normal users cannot edit audit_logs.
 */
final class AuditService
{
    public static function log(
        string $action,
        string $entityType,
        $entityId = null,
        ?array $previous = null,
        ?array $new = null,
        ?string $reason = null,
        ?int $userId = null
    ): void {
        Database::insert('audit_logs', [
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? (string)$entityId : null,
            'previous_value' => $previous !== null ? json_encode($previous) : null,
            'new_value' => $new !== null ? json_encode($new) : null,
            'ip' => clientIp(),
            'user_agent' => clientUA(),
            'reason' => $reason,
        ]);
    }

    public static function recent(int $limit = 50, ?string $entityType = null, $entityId = null): array
    {
        $sql = "SELECT a.*, u.username, u.full_name AS user_name
                FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id";
        $params = [];
        $where = [];
        if ($entityType) {
            $where[] = "a.entity_type = ?";
            $params[] = $entityType;
        }
        if ($entityId !== null) {
            $where[] = "a.entity_id = ?";
            $params[] = (string)$entityId;
        }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " ORDER BY a.id DESC LIMIT " . (int)$limit;
        return Database::query($sql, $params);
    }
}

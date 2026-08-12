<?php
declare(strict_types=1);

final class AuditController extends Controller
{
    protected ?string $requirePermission = 'view_audit_log';

    public function index(): void
    {
        $q = trim($_GET['q'] ?? '');
        $action = $_GET['action'] ?? '';
        $entityType = $_GET['entity_type'] ?? '';
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));

        $where = ['1=1'];
        $params = [];
        if ($q) {
            $where[] = '(a.action LIKE ? OR a.entity_type LIKE ? OR a.entity_id LIKE ? OR u.username LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        if ($action) { $where[] = 'a.action = ?'; $params[] = $action; }
        if ($entityType) { $where[] = 'a.entity_type = ?'; $params[] = $entityType; }
        if ($from) { $where[] = 'a.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
        if ($to) { $where[] = 'a.created_at <= ?'; $params[] = $to . ' 23:59:59'; }

        [$rows, $total, $page, $pages] = $this->paginate(
            "SELECT a.*, u.username, u.full_name AS user_name
             FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
             WHERE " . implode(' AND ', $where) . " ORDER BY a.id DESC",
            $params, $page, 30);

        $actions = array_column(Database::query("SELECT DISTINCT action FROM audit_logs ORDER BY action"), 'action');
        $entities = array_column(Database::query("SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type"), 'entity_type');

        $this->render('audit/index', [
            'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'q' => $q, 'action' => $action, 'entity_type' => $entityType, 'from' => $from, 'to' => $to,
            'actions' => $actions, 'entities' => $entities,
        ]);
    }
}

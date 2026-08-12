<?php
declare(strict_types=1);

final class LedgerController extends Controller
{
    protected ?string $requirePermission = 'view_ledger';

    public function index(): void
    {
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $accountId = (int)($_GET['account_id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));

        $where = ['1=1'];
        $params = [];
        if ($from) { $where[] = 'je.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
        if ($to) { $where[] = 'je.created_at <= ?'; $params[] = $to . ' 23:59:59'; }
        if ($accountId) {
            $where[] = 'EXISTS (SELECT 1 FROM journal_lines l2 WHERE l2.entry_id = je.id AND l2.account_id = ?)';
            $params[] = $accountId;
        }
        $whereSql = implode(' AND ', $where);

        [$rows, $total, $page, $pages] = $this->paginate(
            "SELECT je.*, u.full_name AS user_name, t.tx_number,
                    (SELECT COUNT(*) FROM journal_lines l WHERE l.entry_id = je.id) AS line_count
             FROM journal_entries je
             LEFT JOIN users u ON u.id = je.created_by
             LEFT JOIN transactions t ON t.id = je.transaction_id
             WHERE $whereSql ORDER BY je.id DESC",
            $params, $page, 25);

        foreach ($rows as &$r) {
            $r['lines'] = Database::query(
                "SELECT l.*, a.name AS account_name, g.name AS gl_name, c.code AS currency_code
                 FROM journal_lines l
                 LEFT JOIN accounts a ON a.id = l.account_id
                 LEFT JOIN gl_accounts g ON g.id = l.gl_account_id
                 LEFT JOIN currencies c ON c.id = l.currency_id
                 WHERE l.entry_id = ? ORDER BY l.id", [$r['id']]);
        }

        $this->render('ledger/index', [
            'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'from' => $from, 'to' => $to, 'account_id' => $accountId,
            'accounts' => Database::query("SELECT * FROM accounts ORDER BY name"),
        ]);
    }
}

<?php
declare(strict_types=1);

final class CashCountController extends Controller
{
    protected ?string $requirePermission = 'view_inventory';

    public function index(): void
    {
        $rows = Database::query(
            "SELECT cc.*, a.name AS account_name, u.full_name AS employee_name
             FROM cash_counts cc
             JOIN accounts a ON a.id = cc.account_id
             JOIN users u ON u.id = cc.employee_id
             ORDER BY cc.id DESC LIMIT 100");
        foreach ($rows as &$r) {
            $r['total'] = (string)(Database::value(
                "SELECT COALESCE(SUM(total),0) FROM cash_count_items WHERE cash_count_id = ?", [$r['id']]) ?: '0');
        }
        $this->render('cashcount/index', ['rows' => $rows]);
    }

    public function createForm(): void
    {
        $data = [
            'accounts' => Database::query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY name"),
            'currencies' => Database::query(
                "SELECT c.* FROM currencies c WHERE c.is_active = 1 ORDER BY c.is_base DESC, c.code"),
            'denominations' => Database::query(
                "SELECT d.*, c.code AS currency_code FROM currency_denominations d
                 JOIN currencies c ON c.id = d.currency_id WHERE d.is_active = 1 ORDER BY c.code, d.value DESC"),
        ];
        if ($this->isAjax()) {
            $this->renderBare('cashcount/form', $data);
            return;
        }
        $this->render('cashcount/form', $data);
    }

    public function store(): void
    {
        $this->csrfGuard();
        $accountId = (int)($_POST['account_id'] ?? 0);
        $counts = $_POST['counts'] ?? []; // currency_id => [denom_id => qty]

        $totalItems = 0;
        foreach ($counts as $curId => $denoms) {
            foreach ($denoms as $qty) {
                if (is_numeric($qty) && $qty > 0) $totalItems++;
            }
        }
        if ($totalItems === 0) {
            $this->fail(t('cashcount.enter_count'), '/cash-count/create');
        }

        $countId = Database::insert('cash_counts', [
            'count_number' => TransactionService::nextRefNumber('CC'),
            'account_id' => $accountId,
            'count_date' => $_POST['count_date'] ?? date('Y-m-d'),
            'status' => 'confirmed',
            'employee_id' => Auth::id(),
            'notes' => $_POST['notes'] ?? null,
        ]);

        foreach ($counts as $curId => $denoms) {
            $currencyId = (int)$curId;
            $total = Money::zero();
            foreach ($denoms as $denomId => $qty) {
                if (!is_numeric($qty) || $qty <= 0) continue;
                $denom = Database::fetch("SELECT * FROM currency_denominations WHERE id = ?", [$denomId]);
                if (!$denom || (int)$denom['currency_id'] !== $currencyId) continue;
                $lineTotal = Money::mul((string)$qty, (string)$denom['value']);
                $total = Money::add($total, $lineTotal);
                Database::insert('cash_count_items', [
                    'cash_count_id' => $countId,
                    'currency_id' => $currencyId,
                    'denomination_id' => (int)$denomId,
                    'quantity' => (string)$qty,
                    'total' => Money::round($lineTotal, 10),
                ]);
            }
            if (!Money::isZero($total)) {
                // Compare with system balance
                $system = LedgerService::accountBalance($accountId, $currencyId);
                $diff = Money::sub($total, $system);
                AuditService::log('cash_count', 'cash_count', $countId, null,
                    ['account' => $accountId, 'currency' => $currencyId, 'counted' => $total, 'system' => $system, 'difference' => $diff]);
            }
        }

        AuditService::log('confirm_cash_count', 'cash_count', $countId);
        $this->succeed(t('cashcount.done'), '/cash-count');
    }
}

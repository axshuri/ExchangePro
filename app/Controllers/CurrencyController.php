<?php
declare(strict_types=1);

final class CurrencyController extends Controller
{
    protected ?string $requirePermission = 'manage_currencies';

    public function index(): void
    {
        $rows = Database::query(
            "SELECT c.*, r.buy_rate, r.sell_rate, r.mid_rate,
                    ic.qty AS inventory_qty, ic.avg_cost
             FROM currencies c
             LEFT JOIN exchange_rates r ON r.currency_id = c.id
             LEFT JOIN inventory_costings ic ON ic.currency_id = c.id
             ORDER BY c.is_base DESC, c.code");
        $this->render('currencies/index', ['rows' => $rows, 'base' => SettingService::baseCurrency()]);
    }

    public function createForm(): void
    {
        if ($this->isAjax()) {
            $this->renderBare('currencies/form', ['currency' => null]);
            return;
        }
        $this->render('currencies/form', ['currency' => null]);
    }

    public function store(): void
    {
        $this->csrfGuard();
        $v = new Validator($_POST, ['code' => t('currency.code'), 'name' => t('currency.name')]);
        $v->required('code')->required('name')->maxLen('code', 8)->maxLen('name', 80);
        if (!$v->passes()) {
            $this->fail($v->firstError(), '/currencies/create', $v->errors());
        }
        $code = strtoupper(trim($_POST['code']));
        if (Database::fetch("SELECT id FROM currencies WHERE code = ?", [$code])) {
            $this->fail(t('currency.code_exists'), '/currencies/create');
        }
        $id = Database::insert('currencies', [
            'code' => $code,
            'name' => trim($_POST['name']),
            'localized_name' => $_POST['localized_name'] ?? null,
            'symbol' => $_POST['symbol'] ?? null,
            'amount_precision' => (int)($_POST['amount_precision'] ?? 2),
            'rate_precision' => (int)($_POST['rate_precision'] ?? 4),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'min_amount' => ($_POST['min_amount'] ?? '') !== '' ? $_POST['min_amount'] : null,
            'max_amount' => ($_POST['max_amount'] ?? '') !== '' ? $_POST['max_amount'] : null,
            'notes' => $_POST['notes'] ?? null,
        ]);
        AuditService::log('create_currency', 'currency', $id, null, ['code' => $code]);
        $this->succeed(t('currency.created'), '/currencies');
    }

    public function editForm(string $id): void
    {
        $currency = Database::fetch("SELECT * FROM currencies WHERE id = ?", [$id]);
        if (!$currency) redirect('/currencies');
        $this->render('currencies/form', ['currency' => $currency]);
    }

    public function update(string $id): void
    {
        Csrf::check();
        $currency = Database::fetch("SELECT * FROM currencies WHERE id = ?", [$id]);
        if (!$currency) redirect('/currencies');
        $before = ['name' => $currency['name'], 'symbol' => $currency['symbol'], 'is_active' => $currency['is_active']];
        Database::update('currencies', [
            'name' => trim($_POST['name']),
            'localized_name' => $_POST['localized_name'] ?? null,
            'symbol' => $_POST['symbol'] ?? null,
            'amount_precision' => (int)($_POST['amount_precision'] ?? 2),
            'rate_precision' => (int)($_POST['rate_precision'] ?? 4),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'min_amount' => ($_POST['min_amount'] ?? '') !== '' ? $_POST['min_amount'] : null,
            'max_amount' => ($_POST['max_amount'] ?? '') !== '' ? $_POST['max_amount'] : null,
            'notes' => $_POST['notes'] ?? null,
        ], 'id = ?', [$id]);
        AuditService::log('update_currency', 'currency', (int)$id, $before,
            ['name' => $_POST['name'], 'is_active' => isset($_POST['is_active']) ? 1 : 0]);
        Session::flash('success', t('currency.updated'));
        redirect('/currencies');
    }
}

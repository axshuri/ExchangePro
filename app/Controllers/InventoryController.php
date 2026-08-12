<?php
declare(strict_types=1);

final class InventoryController extends Controller
{
    protected ?string $requirePermission = 'view_inventory';

    public function index(): void
    {
        $base = SettingService::baseCurrency();
        $valuation = ReportService::inventoryValuation();

        // Per-account positions
        $positions = LedgerService::positions();
        $byAccount = [];
        foreach ($positions as $p) {
            $byAccount[$p['account_id']][$p['currency_id']] = $p;
        }
        $accounts = Database::query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY name");
        $currencies = Database::query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY code");

        $this->render('inventory/index', [
            'valuation' => $valuation,
            'base' => $base,
            'positions' => $positions,
            'by_account' => $byAccount,
            'accounts' => $accounts,
            'currencies' => $currencies,
        ]);
    }
}

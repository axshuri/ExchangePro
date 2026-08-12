<?php
declare(strict_types=1);

final class QuickController extends Controller
{
    protected ?string $requirePermission = 'create_transaction';

    public function form(): void
    {
        $base = SettingService::baseCurrency();
        $rates = RateService::all();
        $accounts = Database::query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY type, name");

        // Smart defaults: remember the operator's last currency + direction,
        // otherwise fall back to the most-used currency.
        $currencyId = (int)(Session::get('quick_currency_id') ?: 0);
        $direction = Session::get('quick_direction') === 'sell' ? 'sell' : 'buy';
        if (!$currencyId) {
            $currencyId = (int)(Database::value(
                "SELECT currency_id FROM transactions
                 WHERE type IN ('buy','sell') AND status = 'completed' AND currency_id IS NOT NULL
                 GROUP BY currency_id ORDER BY COUNT(*) DESC, MAX(id) DESC LIMIT 1"
            ) ?: 0);
        }
        // Default cash account = first cash desk.
        $cashAccount = null;
        foreach ($accounts as $a) {
            if ($a['type'] === 'cash_desk') { $cashAccount = (int)$a['id']; break; }
        }
        if (!$cashAccount && $accounts) $cashAccount = (int)$accounts[0]['id'];

        $this->render('quick/index', [
            'rates' => $rates, 'base' => $base, 'accounts' => $accounts,
            'default_currency_id' => $currencyId,
            'default_direction' => $direction,
            'default_cash_account' => $cashAccount,
        ]);
    }

    public function store(): void
    {
        $this->csrfGuard();
        $direction = ($_POST['direction'] ?? '') === 'sell' ? 'sell' : 'buy';
        $currencyId = (int)($_POST['currency_id'] ?? 0);
        if ($currencyId) Session::set('quick_currency_id', $currencyId);
        Session::set('quick_direction', $direction);

        try {
            $tx = $direction === 'sell'
                ? TransactionService::sell($this->txData($_POST, 'sell'))
                : TransactionService::buy($this->txData($_POST, 'buy'));
            $this->succeed(t('quick.done'), '/transactions/' . (int)$tx['id'], ['id' => (int)$tx['id']]);
        } catch (DomainException $e) {
            $this->fail($e->getMessage(), '/quick');
        }
    }

    private function txData(array $p, string $direction): array
    {
        $data = [
            'customer_id' => (int)($p['customer_id'] ?? 0) ?: null,
            'currency_id' => (int)($p['currency_id'] ?? 0),
            'foreign_amount' => trim((string)($p['foreign_amount'] ?? '')),
            'rate' => trim((string)($p['rate'] ?? '')),
            'fee_amount' => trim((string)($p['fee_amount'] ?? '0')),
            'fee_type' => ($p['fee_type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed',
            'discount_amount' => trim((string)($p['discount_amount'] ?? '0')),
            'discount_type' => ($p['discount_type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed',
            'payment_method' => trim((string)($p['payment_method'] ?? 'cash')) ?: 'cash',
            'notes' => trim((string)($p['notes'] ?? '')) ?: null,
            'large_confirmed' => !empty($p['large_confirmed']),
        ];
        $currencyAccount = (int)($p['account_id'] ?? 0);
        $cashAccount = (int)($p['cash_account_id'] ?? 0);
        if ($direction === 'sell') {
            $data['account_id'] = $currencyAccount;
            $data['destination_account_id'] = $cashAccount;
        } else {
            $data['account_id'] = $cashAccount;
            $data['source_account_id'] = $currencyAccount;
        }
        return $data;
    }
}

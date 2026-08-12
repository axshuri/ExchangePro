<?php
declare(strict_types=1);

/**
 * Transaction Calculator — a dedicated pre-transaction calculator.
 *
 * The calculator only prepares numbers client-side. Nothing is committed
 * until the operator explicitly hits "Create transaction", which posts to
 * store() where the backend recomputes every financial value via
 * TransactionService (frontend values are never trusted).
 */
final class CalculatorController extends Controller
{
    protected ?string $requirePermission = 'create_transaction';

    public function form(): void
    {
        $base = SettingService::baseCurrency();
        $rates = RateService::all();
        $accounts = Database::query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY type, name");

        // Smart defaults: most-used currency; first cash desk; first inventory account.
        $currencyId = (int)(Database::value(
            "SELECT currency_id FROM transactions
             WHERE type IN ('buy','sell') AND status = 'completed' AND currency_id IS NOT NULL
             GROUP BY currency_id ORDER BY COUNT(*) DESC, MAX(id) DESC LIMIT 1"
        ) ?: 0);
        $cashAccount = null;
        $currencyAccount = null;
        foreach ($accounts as $a) {
            if ($a['type'] === 'cash_desk' && $cashAccount === null) $cashAccount = (int)$a['id'];
            elseif ($a['type'] !== 'cash_desk' && $currencyAccount === null) $currencyAccount = (int)$a['id'];
        }
        if ($currencyAccount === null) $currencyAccount = $cashAccount;

        // Live stock per currency for the sell pre-check (mirrors InventoryService::recalculate).
        $invRows = Database::query(
            "SELECT l.currency_id, COALESCE(SUM(l.debit - l.credit),0) AS qty
             FROM journal_lines l
             WHERE l.account_id IS NOT NULL AND l.currency_id IS NOT NULL
             GROUP BY l.currency_id");
        $inventory = [];
        foreach ($invRows as $row) {
            $inventory[(int)$row['currency_id']] = (string)$row['qty'];
        }

        $this->render('calculator/index', [
            'rates' => $rates, 'base' => $base, 'accounts' => $accounts,
            'default_currency_id' => $currencyId,
            'default_cash_account' => $cashAccount,
            'default_currency_account' => $currencyAccount,
            'large_threshold' => SettingService::largeTxThreshold(),
            'inventory' => $inventory,
        ]);
    }

    public function store(): void
    {
        $this->csrfGuard();
        $direction = ($_POST['direction'] ?? '') === 'sell' ? 'sell' : 'buy';
        try {
            $tx = $direction === 'sell'
                ? TransactionService::sell($this->txData($_POST, 'sell'))
                : TransactionService::buy($this->txData($_POST, 'buy'));
            Session::flash('success', t($direction === 'sell' ? 'tx.sell_success' : 'tx.buy_success') . ' ' . $tx['tx_number']);
            redirect('/transactions/' . (int)$tx['id'] . '/receipt');
        } catch (LargeTransactionException $e) {
            Session::flash('warning', t('tx.large_confirm'));
            Session::set('large_pending', $_POST);
            redirect('/calculator?large=1');
        } catch (DomainException $e) {
            Session::flash('danger', $e->getMessage());
            redirect('/calculator');
        }
    }

    /** Same field contract as QuickController — the backend recomputes everything. */
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
            $data['account_id'] = $currencyAccount;          // foreign currency leaves here
            $data['destination_account_id'] = $cashAccount;  // base cash arrives here
        } else {
            $data['account_id'] = $cashAccount;              // base cash leaves here
            $data['source_account_id'] = $currencyAccount;   // foreign currency arrives here
        }
        return $data;
    }
}

<?php
/**
 * Demo data — created through the real financial engine (ledger + services)
 * so everything reconciles and every number is traceable.
 *
 * Base-currency agnostic: a static table gives each currency a value in CAD;
 * rates and opening balances are then expressed relative to whichever base
 * currency the business configured, so the books balance for any base.
 */
declare(strict_types=1);

$adminId = Auth::effectiveUserId();
$base = SettingService::baseCurrency();
$baseCode = (string)$base['code'];
$baseId = (int)$base['id'];

function demoCurId(string $code): int {
    return (int)Database::value("SELECT id FROM currencies WHERE code = ?", [$code]);
}

// ---- Value of each demo currency per 1 unit (CAD terms, midpoints) ----
$cadValue = [
    'CAD' => '1.0000', 'USD' => '1.3650', 'EUR' => '1.4850', 'GBP' => '1.7400',
    'CHF' => '1.5300', 'AUD' => '0.8950', 'NZD' => '0.8250', 'JPY' => '0.0091',
    'CNY' => '0.1900', 'HKD' => '0.1750', 'SGD' => '1.0200', 'KRW' => '0.0010',
    'INR' => '0.0163', 'AED' => '0.3715', 'SAR' => '0.3640', 'QAR' => '0.3750',
    'KWD' => '4.4400', 'BHD' => '3.6200', 'OMR' => '3.5500', 'JOD' => '1.9250',
    'TRY' => '0.0410', 'RUB' => '0.0151', 'AZN' => '0.8020', 'GEL' => '0.5080',
    'AMD' => '0.0035', 'IQD' => '0.00104', 'PKR' => '0.0049', 'AFN' => '0.0193',
    'KZT' => '0.0029', 'TMT' => '0.3890', 'UZS' => '0.000108', 'TJS' => '0.1260',
    'MYR' => '0.2890', 'THB' => '0.0375', 'IDR' => '0.000086', 'ILS' => '0.3650',
    'EGP' => '0.0279', 'ZAR' => '0.0755', 'NOK' => '0.1260', 'SEK' => '0.1280',
    'IRR' => '0.0000325', 'IRT' => '0.000325',
];

// ---- Base-relative mid rates: value(cur) / value(base) ----
$baseValue = $cadValue[$baseCode] ?? '1.0000';
$demoMid = [];
foreach ($cadValue as $code => $v) {
    $demoMid[$code] = $code === $baseCode
        ? '1'
        : Money::div($v, $baseValue, 10);
}

// ---- Insert rates for every demo currency (base currency itself = 1) ----
foreach ($cadValue as $code => $v) {
    $curId = demoCurId($code);
    $mid = $code === $baseCode ? '1' : $demoMid[$code];
    $buy = $code === $baseCode ? '1' : Money::round(Money::mul($mid, '0.995'), 6);
    $sell = $code === $baseCode ? '1' : Money::round(Money::mul($mid, '1.005'), 6);
    $exists = Database::fetch("SELECT currency_id FROM exchange_rates WHERE currency_id = ?", [$curId]);
    if (!$exists) {
        Database::insert('exchange_rates', [
            'currency_id' => $curId, 'buy_rate' => $buy, 'sell_rate' => $sell,
            'mid_rate' => $mid, 'is_manual' => 1, 'updated_by' => $adminId,
        ]);
        Database::insert('rate_history', [
            'currency_id' => $curId, 'buy_rate' => $buy, 'sell_rate' => $sell,
            'mid_rate' => $mid, 'is_manual' => 1, 'changed_by' => $adminId,
        ]);
    }
}

// ---- Customers ----
$customers = [
    ['John Smith', '+1 416 555 0101', 'john@example.com', 'passport', 'AB123456'],
    ['Sarah Johnson', '+1 647 555 0142', 'sarah@example.com', 'passport', 'CD789012'],
    ['Ahmed Al-Farsi', '+1 905 555 0177', 'ahmed@example.com', 'driver_license', 'E3456789'],
    ['Maria Garcia', '+1 437 555 0155', 'maria@example.com', 'passport', 'FG112233'],
    ['David Chen', '+1 289 555 0188', 'david@example.com', 'driver_license', 'H4455667'],
];
$customerIds = [];
foreach ($customers as $i => [$name, $phone, $email, $idType, $idNum]) {
    $existing = Database::value("SELECT id FROM customers WHERE phone = ?", [$phone]);
    if ($existing) { $customerIds[] = (int)$existing; continue; }
    $customerIds[] = CustomerService::create([
        'full_name' => $name, 'phone' => $phone, 'email' => $email,
        'id_type' => $idType, 'id_number' => $idNum,
        'notes' => 'Demo customer ' . ($i + 1),
    ]);
}

// ---- Opening balances via ledger (equity) ----
$deskId = (int)Database::value("SELECT id FROM accounts WHERE code = 'DESK-1'");
$vaultId = (int)Database::value("SELECT id FROM accounts WHERE code = 'VAULT-1'");

// Pick a "main" foreign currency (most traded) and a "second" one, always ≠ base.
$priority = ['USD', 'CAD', 'EUR', 'GBP'];
$rest = array_values(array_diff($priority, [$baseCode]));
$main = $rest[0] ?? 'EUR';
$rest2 = array_values(array_diff($rest, [$main]));
$second = $rest2[0] ?? ($main === 'EUR' ? 'GBP' : 'EUR');

// Opening: Desk holds 50,000 base; Vault holds 25,000 of the main foreign
// currency and 8,000 of the second, acquired at their demo mid rates.
$openings = [
    [$deskId, $baseId, '50000', '1'],
    [$vaultId, demoCurId($main), '25000', $demoMid[$main]],
    [$vaultId, demoCurId($second), '8000', $demoMid[$second]],
];
$openingExists = Database::value("SELECT COUNT(*) FROM journal_entries WHERE description LIKE 'OPENING BALANCE%'");
if (!$openingExists) {
    $lines = [];
    foreach ($openings as [$acct, $cur, $amt, $rate]) {
        $lines[] = ['account_id' => $acct, 'currency_id' => $cur, 'debit' => $amt, 'rate' => $rate, 'note' => 'Opening balance'];
    }
    $equityId = Database::insert('gl_accounts', [
        'code' => 'OPENING_EQUITY', 'name' => 'Opening Balance Equity', 'type' => 'equity', 'is_system' => 1,
    ]);
    // Equity = sum of the ledger-computed base amounts (base-currency legs carry
    // their amount unchanged — exactly like LedgerService converts lines).
    $totalBase = Money::zero();
    foreach ($openings as [$acct, $cur, $amt, $rate]) {
        $totalBase = (int)$cur === $baseId
            ? Money::add($totalBase, $amt)
            : Money::add($totalBase, Money::mul($amt, $rate));
    }
    $lines[] = ['gl_account_id' => $equityId, 'currency_id' => $baseId,
        'credit' => Money::round($totalBase, 10), 'rate' => '1', 'note' => 'Opening equity'];
    LedgerService::post($lines, 'OPENING BALANCE (demo seed)', null, $adminId);
    InventoryService::recalculate(demoCurId($main));
    InventoryService::recalculate(demoCurId($second));
}

// Demo transactions are recorded as if the owner made them (the installer
// runs before any login, so act as the first active user explicitly).
Session::start();
Session::set('user_id', (int)$adminId);

// ---- Sample transactions over the past 10 days (trading the main currency) ----
$days = 10;
for ($d = $days; $d >= 1; $d--) {
    $date = date('Y-m-d', strtotime("-$d days"));
    $t = strtotime($date . ' 10:30:00');
    $rand = random_int(0, 5);
    $mid = $demoMid[$main];
    $jitter = Money::div((string)random_int(1, 4), '1000', 10);
    try {
        if ($rand <= 2 && $d % 2 === 0) {
            // BUY main currency (we buy below mid)
            $amt = (string)(random_int(500, 3000));
            $rate = Money::round(Money::mul($mid, Money::sub('1', $jitter)), 6);
            TransactionService::buy([
                'customer_id' => $customerIds[array_rand($customerIds)],
                'currency_id' => demoCurId($main),
                'foreign_amount' => $amt,
                'rate' => $rate,
                'account_id' => $vaultId,
                'source_account_id' => $deskId,
                'payment_method' => 'cash',
                'tx_date' => date('Y-m-d H:i:s', $t),
                'notes' => 'Demo buy',
            ]);
        } elseif ($rand <= 4) {
            // SELL main currency (we sell above mid)
            $amt = (string)(random_int(200, 1500));
            $rate = Money::round(Money::mul($mid, Money::add('1', $jitter)), 6);
            TransactionService::sell([
                'customer_id' => $customerIds[array_rand($customerIds)],
                'currency_id' => demoCurId($main),
                'foreign_amount' => $amt,
                'rate' => $rate,
                'account_id' => $vaultId,
                'destination_account_id' => $deskId,
                'payment_method' => 'cash',
                'tx_date' => date('Y-m-d H:i:s', $t),
                'notes' => 'Demo sell',
            ]);
        } elseif ($d % 3 === 0) {
            // EXCHANGE main → second
            $amt = (string)(random_int(300, 1500));
            TransactionService::exchange([
                'customer_id' => $customerIds[array_rand($customerIds)],
                'source_currency_id' => demoCurId($main),
                'target_currency_id' => demoCurId($second),
                'source_amount' => $amt,
                'source_account_id' => $vaultId,
                'destination_account_id' => $vaultId,
                'tx_date' => date('Y-m-d H:i:s', $t),
                'notes' => 'Demo exchange',
            ]);
        }
    } catch (Throwable $e) {
        // inventory limits — skip gracefully
    }
}

Session::remove('user_id');

// ---- Expenses ----
$expenseCats = ['rent', 'salary', 'internet', 'office_supplies', 'electricity'];
$expCount = (int)Database::value("SELECT COUNT(*) FROM expenses WHERE description LIKE 'Demo%'");
if ($expCount === 0) {
    for ($i = 0; $i < 5; $i++) {
        $date = date('Y-m-d', strtotime('-' . random_int(1, $days) . ' days'));
        $cat = $expenseCats[$i % count($expenseCats)];
        $amt = (string)(random_int(50, 900));
        try {
            Database::insert('expenses', [
                'ref_number' => 'EXP-DEMO' . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
                'category' => $cat,
                'amount' => $amt,
                'currency_id' => $baseId,
                'base_amount' => $amt,
                'rate' => '1',
                'account_id' => $deskId,
                'expense_date' => $date,
                'description' => 'Demo ' . $cat,
                'employee_id' => $adminId,
                'gl_account_id' => ExpenseController::expenseGlId($cat),
            ]);
            LedgerService::post([
                ['gl_account_id' => ExpenseController::expenseGlId($cat), 'currency_id' => $baseId, 'debit' => $amt, 'rate' => '1', 'note' => 'Demo expense'],
                ['account_id' => $deskId, 'currency_id' => $baseId, 'credit' => $amt, 'rate' => '1', 'note' => 'Demo expense'],
            ], 'DEMO EXPENSE ' . $cat, null, $adminId);
        } catch (Throwable $e) {
            // skip
        }
    }
}

echo "Demo data loaded: customers, rates, opening balances, sample transactions, expenses.\n";

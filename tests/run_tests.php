<?php
/**
 * Financial logic tests — run with: php tests/run_tests.php
 *
 * Creates a temporary test database, installs the schema, seeds minimal data,
 * and verifies the core financial scenarios (spec §67):
 *   buy, sell, transfer, expense, cancellation, reconciliation.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Point config at a dedicated test DB BEFORE bootstrap loads config
putenv('EXCHANGE_DB_NAME=exchange_cms_test');

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';
Session::start();
$db = cfg('db');

$tests = [];
$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void {
    global $tests, $passed, $failed;
    try {
        $fn();
        $tests[] = ['name' => $name, 'ok' => true];
        $passed++;
        echo "  ✓ $name\n";
    } catch (Throwable $e) {
        $tests[] = ['name' => $name, 'ok' => false, 'error' => $e->getMessage()];
        $failed++;
        echo "  ✗ $name — " . $e->getMessage() . "\n";
    }
}

function assertTrue(bool $cond, string $msg): void {
    if (!$cond) throw new RuntimeException("Assertion failed: $msg");
}
function assertMoney(string $expected, string $actual, string $msg): void {
    if (Money::compare(Money::round($expected, 6), Money::round($actual, 6)) !== 0) {
        throw new RuntimeException("$msg — expected $expected, got $actual");
    }
}
function withTransaction(callable $fn) {
    Database::transaction($fn);
}

// ==========================================================================
// SETUP
// ==========================================================================
echo "== ExchangePro financial tests ==\n\n";

// Create test database from scratch (no server password expected for the test db)
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']),
    $db['user'], $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("DROP DATABASE IF EXISTS `{$db['name']}`");
$pdo->exec("CREATE DATABASE `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$db['name']}`");

foreach (array_filter(array_map('trim', explode(';', file_get_contents(dirname(__DIR__) . '/database/schema.sql')))) as $stmt) {
    try { $pdo->exec($stmt); } catch (PDOException $e) { echo "  schema: " . $e->getMessage() . "\n"; }
}
Database::connection(); // establish app PDO

// The app stores UTC datetimes while MySQL NOW() follows the server SYSTEM
// timezone — pin the session to UTC so date-range assertions are deterministic
// on any machine (e.g. a server running ahead of UTC).
$pdo->exec("SET time_zone = '+00:00'");
Database::execute("SET time_zone = '+00:00'");

// Seed base + admin + minimal currencies/accounts
require dirname(__DIR__) . '/database/seed.php';

// Apply schema migrations (new columns, permissions, settings) so the test DB
// matches a fully-migrated production database.
Migrate::run();

$adminId = Database::insert('users', [
    'username' => 'testadmin', 'email' => 'test@example.com',
    'password_hash' => password_hash('test12345', PASSWORD_BCRYPT),
    'full_name' => 'Test Admin',
    'role_id' => (int)Database::value("SELECT id FROM roles WHERE name = 'owner'"),
]);
Session::set('user_id', (int)$adminId);

$base = SettingService::baseCurrency();
$usd = Database::fetch("SELECT * FROM currencies WHERE code = 'USD'");
$eur = Database::fetch("SELECT * FROM currencies WHERE code = 'EUR'");
$desk = Database::fetch("SELECT * FROM accounts WHERE code = 'DESK-1'");
$vault = Database::fetch("SELECT * FROM accounts WHERE code = 'VAULT-1'");

RateService::update((int)$usd['id'], '1.3550', '1.3750');
RateService::update((int)$eur['id'], '1.4750', '1.4950');

// Opening balance: 100,000 CAD in desk, 10,000 USD in vault
Database::transaction(function () use ($desk, $vault, $usd, $base, $adminId) {
    $equity = Database::insert('gl_accounts', ['code' => 'TEST_EQUITY', 'name' => 'Test Equity', 'type' => 'equity', 'is_system' => 1]);
    LedgerService::post([
        ['account_id' => (int)$desk['id'], 'currency_id' => (int)$base['id'], 'debit' => '100000', 'rate' => '1', 'note' => 'Opening'],
        ['account_id' => (int)$vault['id'], 'currency_id' => (int)$usd['id'], 'debit' => '10000', 'rate' => '1.355', 'note' => 'Opening'],
        ['gl_account_id' => $equity, 'currency_id' => (int)$base['id'], 'credit' => '113550', 'rate' => '1', 'note' => 'Opening equity'],
    ], 'OPENING BALANCE (test)', null, $adminId);
    InventoryService::recalculate((int)$usd['id']);
});

$customerId = CustomerService::create(['full_name' => 'Test Customer', 'phone' => '+10000000000']);

// ==========================================================================
// 1. BUY
// ==========================================================================
echo "\n1) BUY 1,000 USD @ 1.36\n";
$buyTx = null;
test('Buy increases USD inventory and decreases base cash', function () use (&$buyTx, $desk, $vault, $usd, $base) {
    $buyTx = TransactionService::buy([
        'customer_id' => null,
        'currency_id' => (int)$usd['id'],
        'foreign_amount' => '1000',
        'rate' => '1.3600',
        'account_id' => (int)$vault['id'],
        'source_account_id' => (int)$desk['id'],
        'payment_method' => 'cash',
    ]);
    $costing = InventoryService::costing((int)$usd['id']);
    assertMoney('11000', (string)$costing['qty'], 'USD quantity');
    $avg = Money::div(Money::add('13550', '1360'), '11000', 10);
    assertMoney($avg, (string)$costing['avg_cost'], 'weighted average cost');
    assertMoney('98640', LedgerService::accountBalance((int)$desk['id'], (int)$base['id']), 'desk base balance after buy');
});

// ==========================================================================
// 2. SELL
// ==========================================================================
echo "\n2) SELL 1,000 USD @ 1.38\n";
test('Sell decreases USD and creates realized P/L', function () use ($desk, $vault, $usd, $base) {
    $costingBefore = InventoryService::costing((int)$usd['id']);
    TransactionService::sell([
        'customer_id' => null,
        'currency_id' => (int)$usd['id'],
        'foreign_amount' => '1000',
        'rate' => '1.3800',
        'account_id' => (int)$vault['id'],
        'destination_account_id' => (int)$desk['id'],
        'payment_method' => 'cash',
    ]);
    $costing = InventoryService::costing((int)$usd['id']);
    assertMoney('10000', (string)$costing['qty'], 'USD quantity after sell');
    $pl = (string)Database::value(
        "SELECT COALESCE(SUM(base_credit)-SUM(base_debit),0) FROM journal_lines l
         JOIN gl_accounts g ON g.id = l.gl_account_id WHERE g.code = 'REALIZED_PL'");
    // profit = 1000 × (1.38 − avg)
    $avg = (string)$costingBefore['avg_cost'];
    $expected = Money::mul('1000', Money::sub('1.38', $avg));
    assertMoney($expected, $pl, 'realized P/L');
    assertMoney(Money::add('98640', '1380'), LedgerService::accountBalance((int)$desk['id'], (int)$base['id']), 'desk balance after sell');
});

// ==========================================================================
// 3. TRANSFER
// ==========================================================================
echo "\n3) TRANSFER 2,000 USD vault → desk\n";
test('Transfer moves money between accounts without revenue', function () use ($desk, $vault, $usd, $base) {
    $plBefore = (string)Database::value(
        "SELECT COALESCE(SUM(base_credit)-SUM(base_debit),0) FROM journal_lines l
         JOIN gl_accounts g ON g.id = l.gl_account_id WHERE g.code = 'REALIZED_PL'");
    $companyBefore = (string)Database::value(
        "SELECT COALESCE(SUM(debit - credit),0) FROM journal_lines WHERE currency_id = ? AND account_id IS NOT NULL",
        [(int)$usd['id']]);
    $vaultBefore = LedgerService::accountBalance((int)$vault['id'], (int)$usd['id']);
    $deskBefore = LedgerService::accountBalance((int)$desk['id'], (int)$usd['id']);
    Database::transaction(function () use ($desk, $vault, $usd) {
        LedgerService::post([
            ['account_id' => (int)$desk['id'], 'currency_id' => (int)$usd['id'], 'debit' => '2000', 'rate' => '1.355', 'note' => 'Transfer in'],
            ['account_id' => (int)$vault['id'], 'currency_id' => (int)$usd['id'], 'credit' => '2000', 'rate' => '1.355', 'note' => 'Transfer out'],
        ], 'TRANSFER test', null, null, LedgerService::nextEntryNo());
        InventoryService::recalculate((int)$usd['id']);
    });
    assertMoney(Money::sub($vaultBefore, '2000'), LedgerService::accountBalance((int)$vault['id'], (int)$usd['id']), 'vault decreased');
    assertMoney(Money::add($deskBefore, '2000'), LedgerService::accountBalance((int)$desk['id'], (int)$usd['id']), 'desk increased');
    $companyAfter = (string)Database::value(
        "SELECT COALESCE(SUM(debit - credit),0) FROM journal_lines WHERE currency_id = ? AND account_id IS NOT NULL",
        [(int)$usd['id']]);
    assertMoney($companyBefore, $companyAfter, 'total company USD unchanged');
    $plAfter = (string)Database::value(
        "SELECT COALESCE(SUM(base_credit)-SUM(base_debit),0) FROM journal_lines l
         JOIN gl_accounts g ON g.id = l.gl_account_id WHERE g.code = 'REALIZED_PL'");
    assertMoney($plBefore, $plAfter, 'no revenue from transfer');
});

// ==========================================================================
// 4. EXPENSE
// ==========================================================================
echo "\n4) EXPENSE 500 CAD\n";
test('Expense reduces cash and creates expense GL', function () use ($desk, $base) {
    $balBefore = LedgerService::accountBalance((int)$desk['id'], (int)$base['id']);
    $glId = ExpenseController::expenseGlId('rent');
    Database::transaction(function () use ($desk, $base, $glId) {
        Database::insert('expenses', [
            'ref_number' => 'EXP-TEST', 'category' => 'rent', 'amount' => '500',
            'currency_id' => (int)$base['id'], 'base_amount' => '500', 'rate' => '1',
            'account_id' => (int)$desk['id'], 'expense_date' => date('Y-m-d'),
            'description' => 'test', 'employee_id' => Auth::effectiveUserId(),
            'gl_account_id' => $glId,
        ]);
        LedgerService::post([
            ['gl_account_id' => $glId, 'currency_id' => (int)$base['id'], 'debit' => '500', 'rate' => '1', 'note' => 'Rent'],
            ['account_id' => (int)$desk['id'], 'currency_id' => (int)$base['id'], 'credit' => '500', 'rate' => '1', 'note' => 'Rent'],
        ], 'EXPENSE rent', null, null, LedgerService::nextEntryNo());
    });
    assertMoney(Money::sub($balBefore, '500'), LedgerService::accountBalance((int)$desk['id'], (int)$base['id']), 'cash after expense');
    // Expenses are debits: glTotal returns credits − debits, so an expense shows negative
    assertMoney('-500', LedgerService::glTotal($glId, date('Y-m-d'), date('Y-m-d')), 'expense GL total');
});

// ==========================================================================
// 5. CANCELLATION / REVERSAL
// ==========================================================================
echo "\n5) CANCEL the buy transaction\n";
test('Reversal restores net position', function () use (&$buyTx, $desk, $vault, $usd, $base) {
    // The buy added 1,000 USD to the vault and took 1,360 CAD from the desk.
    // Reversing it must undo exactly that (state as if the buy never happened).
    $vaultBefore = LedgerService::accountBalance((int)$vault['id'], (int)$usd['id']);
    $deskBefore = LedgerService::accountBalance((int)$desk['id'], (int)$base['id']);

    $rev = TransactionService::reverse((int)$buyTx['id'], 'test reversal');
    assertTrue($rev['type'] === 'reversal', 'reversal transaction created');

    $orig = Database::fetch("SELECT * FROM transactions WHERE id = ?", [$buyTx['id']]);
    assertTrue($orig['status'] === 'reversed', 'original marked reversed');
    assertTrue($orig['reversal_transaction_id'] == $rev['id'], 'reversal link set');

    $costAfter = InventoryService::costing((int)$usd['id']);
    assertMoney('9000', (string)$costAfter['qty'], 'quantity back to pre-buy state (10000−1000)');
    assertMoney(Money::sub($vaultBefore, '1000'), LedgerService::accountBalance((int)$vault['id'], (int)$usd['id']), 'vault USD reversed');
    assertMoney(Money::add($deskBefore, '1360'), LedgerService::accountBalance((int)$desk['id'], (int)$base['id']), 'desk CAD reversed');

    // Ledger balance check: all entries must balance
    $unbalanced = (int)Database::value(
        "SELECT COUNT(*) FROM (
            SELECT je.id, SUM(l.base_debit) AS d, SUM(l.base_credit) AS c
            FROM journal_entries je JOIN journal_lines l ON l.entry_id = je.id
            GROUP BY je.id HAVING ABS(d - c) > 0.000001
        ) t");
    assertTrue($unbalanced === 0, 'no unbalanced journal entries');
});

// ==========================================================================
// 6. RECONCILIATION
// ==========================================================================
echo "\n6) RECONCILIATION (physical 9,500 vs system 10,000)\n";
test('Reconciliation records difference and adjustment requires approval', function () use ($vault, $usd) {
    $system = LedgerService::accountBalance((int)$vault['id'], (int)$usd['id']);
    $physical = Money::sub($system, '500');
    $recId = Database::insert('reconciliations', [
        'rec_number' => 'RC-TEST', 'account_id' => (int)$vault['id'],
        'currency_id' => (int)$usd['id'],
        'system_balance' => $system, 'physical_balance' => $physical,
        'difference' => Money::sub($physical, $system),
        'reason' => 'test count', 'status' => 'pending', 'created_by' => Auth::effectiveUserId(),
    ]);
    $rec = Database::fetch("SELECT * FROM reconciliations WHERE id = ?", [$recId]);
    assertMoney(Money::sub($physical, $system), (string)$rec['difference'], 'difference recorded');
    assertTrue($rec['status'] === 'pending', 'pending until approved');
});

// ==========================================================================
// 6b. RECONCILIATION APPROVAL (adjustInventory)
// ==========================================================================
echo "\n6b) RECONCILIATION APPROVAL (physical 50 below system)\n";
test('Approval adjusts inventory to physical and books a balanced entry', function () use ($vault, $usd) {
    $system = LedgerService::accountBalance((int)$vault['id'], (int)$usd['id']);
    $physical = Money::sub($system, '50');
    $recId = Database::insert('reconciliations', [
        'rec_number' => 'RC-APPROVE', 'account_id' => (int)$vault['id'],
        'currency_id' => (int)$usd['id'],
        'system_balance' => $system, 'physical_balance' => $physical,
        'difference' => Money::sub($physical, $system),
        'reason' => 'approve test', 'status' => 'pending',
        'created_by' => Auth::effectiveUserId(),
    ]);
    Database::transaction(function () use ($recId) {
        $rec = Database::fetch("SELECT * FROM reconciliations WHERE id = ?", [$recId]);
        Database::update('reconciliations', [
            'status' => 'approved', 'approved_by' => Auth::effectiveUserId(),
            'approved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$recId]);
        TransactionService::adjustInventory(
            (int)$rec['account_id'], (int)$rec['currency_id'],
            (string)$rec['difference'], (string)$rec['reason'], (int)$rec['id']);
    });
    assertMoney($physical, LedgerService::accountBalance((int)$vault['id'], (int)$usd['id']), 'vault adjusted to physical');
    $adj = (string)Database::value(
        "SELECT COALESCE(SUM(base_credit)-SUM(base_debit),0) FROM journal_lines l
         JOIN gl_accounts g ON g.id = l.gl_account_id WHERE g.code = 'INV_ADJUSTMENT'");
    assertTrue(Money::isNegative($adj), 'inventory shrinkage booked as expense');
});

// ==========================================================================
// 7. EXCHANGE
// ==========================================================================
echo "\n7) EXCHANGE 1,000 USD → EUR\n";
test('Exchange computes cross rate and books balanced entry', function () use ($vault, $usd, $eur) {
    // ensure EUR inventory exists
    $eurCost = InventoryService::costing((int)$eur['id']);
    if (Money::isZero((string)$eurCost['qty'])) {
        $equityId = (int)Database::value("SELECT id FROM gl_accounts WHERE code = 'TEST_EQUITY'");
        LedgerService::post([
            ['account_id' => (int)$vault['id'], 'currency_id' => (int)$eur['id'], 'debit' => '5000', 'rate' => '1.475', 'note' => 'EUR opening'],
            ['gl_account_id' => $equityId, 'currency_id' => (int)SettingService::baseCurrency()['id'], 'credit' => '7375', 'rate' => '1', 'note' => 'EUR opening'],
        ], 'EUR OPENING (test)', null, null, LedgerService::nextEntryNo());
        InventoryService::recalculate((int)$eur['id']);
    }
    $usdBefore = InventoryService::costing((int)$usd['id']);
    $tx = TransactionService::exchange([
        'source_currency_id' => (int)$usd['id'],
        'target_currency_id' => (int)$eur['id'],
        'source_amount' => '1000',
        'source_account_id' => (int)$vault['id'],
        'destination_account_id' => (int)$vault['id'],
    ]);
    assertTrue($tx['type'] === 'exchange', 'exchange tx created');
    $item = Database::fetch("SELECT * FROM transaction_items WHERE transaction_id = ?", [$tx['id']]);
    assertTrue(Money::isPositive((string)$item['target_amount']), 'target amount computed');
    // cross rate = buy(usd)/sell(eur) = 1.355/1.495 (compare at scale 10, rounded to 6)
    $expectedCross = Money::div('1.355', '1.495', 10);
    assertMoney(Money::round($expectedCross, 6), Money::round((string)$item['rate'], 6), 'cross rate');
    $usdAfter = InventoryService::costing((int)$usd['id']);
    assertMoney(Money::add((string)$usdBefore['qty'], '1000'), (string)$usdAfter['qty'], 'USD inventory increased');
});

// ==========================================================================
// 8. SELL WITH DISCOUNT
// ==========================================================================
echo "\n8) SELL 100 USD @ 1.38 with 10 discount\n";
test('Sell with discount: customer pays base+fee−discount, discount booked as expense', function () use ($desk, $vault, $usd, $base) {
    $deskBefore = LedgerService::accountBalance((int)$desk['id'], (int)$base['id']);
    $qtyBefore = (string)InventoryService::costing((int)$usd['id'])['qty'];
    TransactionService::sell([
        'customer_id' => null,
        'currency_id' => (int)$usd['id'],
        'foreign_amount' => '100',
        'rate' => '1.3800',
        'account_id' => (int)$vault['id'],
        'destination_account_id' => (int)$desk['id'],
        'payment_method' => 'cash',
        'discount_amount' => '10',
    ]);
    // customer pays 138 + 0 − 10 = 128
    assertMoney(Money::add($deskBefore, '128'), LedgerService::accountBalance((int)$desk['id'], (int)$base['id']), 'desk receives base+fee−discount');
    assertMoney(Money::sub($qtyBefore, '100'), (string)InventoryService::costing((int)$usd['id'])['qty'], 'USD quantity');
    $disc = (string)Database::value(
        "SELECT COALESCE(SUM(base_credit)-SUM(base_debit),0) FROM journal_lines l
         JOIN gl_accounts g ON g.id = l.gl_account_id WHERE g.code = 'DISCOUNT_GIVEN'");
    assertMoney('-10', $disc, 'discount expense GL total');
});

// ==========================================================================
// 9. LOSS-MAKING SELL
// ==========================================================================
echo "\n9) SELL 200 USD @ 1.30 (below avg cost = loss)\n";
test('Loss-making sell books negative realized P/L without error', function () use ($desk, $vault, $usd, $base) {
    $deskBefore = LedgerService::accountBalance((int)$desk['id'], (int)$base['id']);
    $plBefore = (string)Database::value(
        "SELECT COALESCE(SUM(base_credit)-SUM(base_debit),0) FROM journal_lines l
         JOIN gl_accounts g ON g.id = l.gl_account_id WHERE g.code = 'REALIZED_PL'");
    $avg = (string)InventoryService::costing((int)$usd['id'])['avg_cost'];
    assertTrue(Money::compare('1.30', $avg) < 0, 'test premise: 1.30 is below average cost');
    TransactionService::sell([
        'customer_id' => null,
        'currency_id' => (int)$usd['id'],
        'foreign_amount' => '200',
        'rate' => '1.3000',
        'account_id' => (int)$vault['id'],
        'destination_account_id' => (int)$desk['id'],
        'payment_method' => 'cash',
    ]);
    assertMoney(Money::add($deskBefore, '260'), LedgerService::accountBalance((int)$desk['id'], (int)$base['id']), 'desk receives 200×1.30');
    $plAfter = (string)Database::value(
        "SELECT COALESCE(SUM(base_credit)-SUM(base_debit),0) FROM journal_lines l
         JOIN gl_accounts g ON g.id = l.gl_account_id WHERE g.code = 'REALIZED_PL'");
    assertTrue(Money::compare($plAfter, $plBefore) < 0, 'realized P/L decreased (loss booked as debit)');
});

// ==========================================================================
// 10. BUY WITH DISCOUNT
// ==========================================================================
echo "\n10) BUY 100 USD @ 1.36 with 5 discount\n";
test('Buy with discount: payout = base−fee+discount, journal balanced', function () use ($desk, $vault, $usd, $base) {
    $deskBefore = LedgerService::accountBalance((int)$desk['id'], (int)$base['id']);
    $qtyBefore = (string)InventoryService::costing((int)$usd['id'])['qty'];
    TransactionService::buy([
        'customer_id' => null,
        'currency_id' => (int)$usd['id'],
        'foreign_amount' => '100',
        'rate' => '1.3600',
        'account_id' => (int)$vault['id'],
        'source_account_id' => (int)$desk['id'],
        'payment_method' => 'cash',
        'discount_amount' => '5',
    ]);
    // exchange pays 136 − 0 + 5 = 141
    assertMoney(Money::sub($deskBefore, '141'), LedgerService::accountBalance((int)$desk['id'], (int)$base['id']), 'desk pays base−fee+discount');
    assertMoney(Money::add($qtyBefore, '100'), (string)InventoryService::costing((int)$usd['id'])['qty'], 'USD quantity increased');
});

// ==========================================================================
// 10b. SELL WHERE DISCOUNT = BASE + FEE (zero received edge)
// ==========================================================================
echo "\n10b) SELL 100 USD @ 1.36 with discount = full amount\n";
test('Sell with discount equal to the full amount stays balanced', function () use ($desk, $vault, $usd, $base) {
    $deskBefore = LedgerService::accountBalance((int)$desk['id'], (int)$base['id']);
    TransactionService::sell([
        'customer_id' => null,
        'currency_id' => (int)$usd['id'],
        'foreign_amount' => '100',
        'rate' => '1.3600',
        'account_id' => (int)$vault['id'],
        'destination_account_id' => (int)$desk['id'],
        'payment_method' => 'cash',
        'discount_amount' => '136',
    ]);
    assertMoney($deskBefore, LedgerService::accountBalance((int)$desk['id'], (int)$base['id']), 'no cash moves when discount covers the total');
    $unbalanced = (int)Database::value(
        "SELECT COUNT(*) FROM (
            SELECT je.id, SUM(l.base_debit) AS d, SUM(l.base_credit) AS c
            FROM journal_entries je JOIN journal_lines l ON l.entry_id = je.id
            GROUP BY je.id HAVING ABS(d - c) > 0.000001
        ) t");
    assertTrue($unbalanced === 0, 'entry stays balanced');
});

// ==========================================================================
// 11. EXCHANGE WITH FEE
// ==========================================================================
echo "\n11) EXCHANGE 100 USD → EUR with 10 fee\n";
test('Exchange with fee: balanced journal, fee income recorded', function () use ($vault, $usd, $eur) {
    $feeBefore = (string)Database::value(
        "SELECT COALESCE(SUM(base_credit)-SUM(base_debit),0) FROM journal_lines l
         JOIN gl_accounts g ON g.id = l.gl_account_id WHERE g.code = 'FEE_INCOME'");
    $tx = TransactionService::exchange([
        'source_currency_id' => (int)$usd['id'],
        'target_currency_id' => (int)$eur['id'],
        'source_amount' => '100',
        'source_account_id' => (int)$vault['id'],
        'destination_account_id' => (int)$vault['id'],
        'fee_amount' => '10',
    ]);
    assertTrue($tx['type'] === 'exchange', 'exchange created');
    $feeAfter = (string)Database::value(
        "SELECT COALESCE(SUM(base_credit)-SUM(base_debit),0) FROM journal_lines l
         JOIN gl_accounts g ON g.id = l.gl_account_id WHERE g.code = 'FEE_INCOME'");
    assertMoney(Money::add($feeBefore, '10'), $feeAfter, 'fee income recorded');
    $unbalanced = (int)Database::value(
        "SELECT COUNT(*) FROM (
            SELECT je.id, SUM(l.base_debit) AS d, SUM(l.base_credit) AS c
            FROM journal_entries je JOIN journal_lines l ON l.entry_id = je.id
            GROUP BY je.id HAVING ABS(d - c) > 0.000001
        ) t");
    assertTrue($unbalanced === 0, 'entry stays balanced with fee');
});

// ==========================================================================
// 12. EXCHANGE REVERSAL RESTORES BOTH COSTINGS
// ==========================================================================
echo "\n12) Reversal of an exchange\n";
test('Exchange reversal restores both source and target costing', function () use ($vault, $usd, $eur) {
    $usdBefore = InventoryService::costing((int)$usd['id']);
    $eurBefore = InventoryService::costing((int)$eur['id']);
    $tx = TransactionService::exchange([
        'source_currency_id' => (int)$usd['id'],
        'target_currency_id' => (int)$eur['id'],
        'source_amount' => '150',
        'source_account_id' => (int)$vault['id'],
        'destination_account_id' => (int)$vault['id'],
    ]);
    TransactionService::reverse((int)$tx['id'], 'test exchange reversal');
    $usdAfter = InventoryService::costing((int)$usd['id']);
    $eurAfter = InventoryService::costing((int)$eur['id']);
    assertMoney((string)$usdBefore['qty'], (string)$usdAfter['qty'], 'USD costing restored');
    assertMoney((string)$usdBefore['avg_cost'], (string)$usdAfter['avg_cost'], 'USD avg cost restored');
    assertMoney((string)$eurBefore['qty'], (string)$eurAfter['qty'], 'EUR costing restored');
});

// ==========================================================================
// 13. BALANCED LEDGER INVARIANT
// ==========================================================================
echo "\n13) Ledger invariant\n";
test('Total debits equal total credits across all entries', function () {
    $d = (string)Database::value("SELECT COALESCE(SUM(base_debit),0) FROM journal_lines");
    $c = (string)Database::value("SELECT COALESCE(SUM(base_credit),0) FROM journal_lines");
    assertMoney($d, $c, 'global debits = credits');
});

// ==========================================================================
// 9. AUTOMATIC RATE SYNCHRONIZATION
// ==========================================================================
echo "\n9) AUTOMATIC RATE SYNCHRONIZATION\n";

final class FakeRateProvider implements ExchangeRateProvider
{
    public int $calls = 0;
    public bool $fail = false;
    public ?array $payload = null;

    public function identifier(): string { return 'frankfurter'; }
    public function name(): string { return 'Fake / ECB'; }
    public function supportedCurrencies(): array { return array_keys($this->payload()['rates']); }

    public function latestRates(string $baseCurrency): ProviderRateResponse
    {
        $this->calls++;
        if ($this->fail) throw new RuntimeException('provider down (fake)');
        $p = $this->payload();
        return new ProviderRateResponse($p['base'], $p['date'], $p['rates']);
    }

    private function payload(): array
    {
        return $this->payload ?: [
            // Like the real Frankfurter API, the base currency (EUR) is NOT in the
            // rates map — its value is implicitly 1.0. The sync must still apply it.
            'base' => 'EUR', 'date' => date('Y-m-d'),
            'rates' => ['CAD' => '1.6', 'USD' => '1.2', 'GBP' => '0.9', 'TRY' => '55.0', 'CNY' => '7.8'],
        ];
    }
}

// Fail fast in tests (no 2s/5s/15s backoff delays on provider errors).
SettingService::set('rate_sync_retry_attempts', '1');

$usdRef = fn() => Money::round(Money::div('1.6', '1.2', 10), 8); // CAD per USD from EUR-based fake rates

test('Rate sync: reference + spread-derived Buy/Sell applied; history + log written', function () use ($usd, $eur, $usdRef) {
    $fake = new FakeRateProvider();
    $res = RateSyncService::sync(true, 'manual', $fake);
    assertTrue($res['status'] === 'success', 'sync status success, got ' . json_encode($res));
    assertTrue($res['updated'] >= 5, 'at least 5 supported currencies updated');
    assertTrue($res['skipped'] >= 3, 'unsupported currencies skipped (AED/RUB/IRR/IRT)');
    assertTrue($fake->calls === 1, 'provider called exactly once');

    $row = Database::fetch('SELECT * FROM exchange_rates WHERE currency_id = ?', [(int)$usd['id']]);
    assertMoney($usdRef(), Money::round((string)$row['reference_rate'], 8), 'USD reference = r[CAD]/r[USD]');
    assertTrue(Money::compare((string)$row['buy_rate'], (string)$row['reference_rate']) < 0, 'buy < reference (spread)');
    assertTrue(Money::compare((string)$row['sell_rate'], (string)$row['reference_rate']) > 0, 'sell > reference (spread)');
    assertTrue(Money::compare((string)$row['buy_rate'], (string)$row['sell_rate']) < 0, 'buy < sell');
    assertTrue((string)$row['source'] === 'api', 'source marked api');
    assertTrue((string)$row['rate_status'] === 'online', 'rate status online');

    // The provider's own base (EUR) is omitted from the rates map but implicit at
    // 1.0 — it must still receive a reference rate (baseRate / 1).
    $eurRow = Database::fetch('SELECT * FROM exchange_rates WHERE currency_id = ?', [(int)$eur['id']]);
    assertTrue($eurRow['reference_rate'] !== null, 'provider base currency still gets a reference rate');
    assertMoney('1.6', Money::round((string)$eurRow['reference_rate'], 8), 'EUR reference = r[CAD] / 1 (provider base implicit 1.0)');

    $hist = (int)Database::value('SELECT COUNT(*) FROM exchange_rate_history WHERE currency_id = ?', [(int)$usd['id']]);
    assertTrue($hist >= 1, 'reference history recorded');
    $log = (int)Database::value("SELECT COUNT(*) FROM rate_sync_logs WHERE status = 'success'");
    assertTrue($log >= 1, 'successful sync logged');
});

test('Rate sync: fresh cache does not call the provider again', function () use ($usd) {
    $fake = new FakeRateProvider();
    RateSyncService::sync(true, 'manual', $fake); // force refresh
    $calls = $fake->calls;
    $res = RateSyncService::sync(false, 'manual', $fake);
    assertTrue($res['status'] === 'cached', 'fresh cache returns cached, got ' . $res['status']);
    assertTrue($fake->calls === $calls, 'no extra provider call while cache is fresh');
});

test('Rate sync: provider failure keeps last rates and logs failure', function () use ($usd) {
    $before = Database::fetch('SELECT * FROM exchange_rates WHERE currency_id = ?', [(int)$usd['id']]);
    $fake = new FakeRateProvider();
    $fake->fail = true;
    $res = RateSyncService::sync(true, 'manual', $fake);
    assertTrue($res['status'] === 'failed', 'sync reports failure');
    $after = Database::fetch('SELECT * FROM exchange_rates WHERE currency_id = ?', [(int)$usd['id']]);
    assertMoney((string)$before['buy_rate'], (string)$after['buy_rate'], 'buy preserved on failure');
    assertMoney((string)$before['reference_rate'], (string)$after['reference_rate'], 'reference preserved on failure');
    $failed = (int)Database::value("SELECT COUNT(*) FROM rate_sync_logs WHERE status = 'failed'");
    assertTrue($failed >= 1, 'failed sync logged');
});

test('Rate sync: invalid provider response is rejected, rates untouched', function () use ($usd) {
    $before = Database::fetch('SELECT * FROM exchange_rates WHERE currency_id = ?', [(int)$usd['id']]);
    $fake = new FakeRateProvider();
    $fake->payload = ['base' => 'EUR', 'date' => date('Y-m-d'), 'rates' => ['CAD' => '1.6', 'USD' => '-1.2']]; // negative rate
    $res = RateSyncService::sync(true, 'manual', $fake);
    assertTrue($res['status'] === 'failed', 'invalid response rejected');
    $after = Database::fetch('SELECT * FROM exchange_rates WHERE currency_id = ?', [(int)$usd['id']]);
    assertMoney((string)$before['buy_rate'], (string)$after['buy_rate'], 'rates untouched after invalid response');
});

test('Rate sync: rate-change guard blocks extreme jumps', function () use ($usd) {
    Database::update('exchange_rates', ['reference_rate' => '0.0001'], 'currency_id = ?', [(int)$usd['id']]);
    $fake = new FakeRateProvider();
    $res = RateSyncService::sync(true, 'manual', $fake);
    assertTrue($res['status'] === 'partial', 'sync marked partial, got ' . json_encode($res));
    assertTrue($res['failed'] >= 1, 'extreme change flagged');
    $row = Database::fetch('SELECT * FROM exchange_rates WHERE currency_id = ?', [(int)$usd['id']]);
    assertMoney('0.0001', (string)$row['reference_rate'], 'reference NOT overwritten by extreme jump');
    // Restore a sane reference so later tests aren't affected.
    Database::update('exchange_rates', ['reference_rate' => Money::div('1.6', '1.2', 10)], 'currency_id = ?', [(int)$usd['id']]);
});

test('Rate sync: persistent manual override survives sync; unpinning restores auto', function () use ($usd) {
    $before = Database::fetch('SELECT * FROM exchange_rates WHERE currency_id = ?', [(int)$usd['id']]);
    Database::update('exchange_rates', [
        'buy_override' => $before['buy_rate'], 'sell_override' => $before['sell_rate'],
        'override_persistent' => 1,
    ], 'currency_id = ?', [(int)$usd['id']]);

    $fake = new FakeRateProvider();
    $res = RateSyncService::sync(true, 'manual', $fake);
    assertTrue($res['status'] === 'success', 'sync succeeds with pinned currency');
    $pinned = Database::fetch('SELECT * FROM exchange_rates WHERE currency_id = ?', [(int)$usd['id']]);
    assertMoney((string)$before['buy_rate'], (string)$pinned['buy_rate'], 'pinned buy unchanged by sync');
    assertMoney((string)$before['sell_rate'], (string)$pinned['sell_rate'], 'pinned sell unchanged by sync');
    assertTrue($pinned['reference_rate'] !== null, 'reference still updated for pinned currency');

    // Unpin → the next sync recalculates Buy/Sell from the reference.
    Database::update('exchange_rates', [
        'buy_override' => null, 'sell_override' => null, 'override_persistent' => 0,
    ], 'currency_id = ?', [(int)$usd['id']]);
    RateSyncService::sync(true, 'manual', $fake);
    $unpinned = Database::fetch('SELECT * FROM exchange_rates WHERE currency_id = ?', [(int)$usd['id']]);
    assertTrue(Money::compare((string)$unpinned['buy_rate'], (string)$unpinned['reference_rate']) < 0,
        'unpinned buy recalculated below reference');
    assertTrue((int)$unpinned['override_persistent'] === 0, 'override flag cleared');
});

test('Rate sync: unsupported currencies stay manual and untouched', function () {
    $aed = Database::fetch("SELECT * FROM currencies WHERE code = 'AED'");
    $row = Database::fetch('SELECT * FROM exchange_rates WHERE currency_id = ?', [(int)$aed['id']]);
    assertTrue($row === null || $row['reference_rate'] === null, 'AED has no auto reference');
});

test('Rate sync: duplicate sync prevented while another is running (advisory lock)', function () use ($pdo) {
    // Hold the lock on a SEPARATE connection (real cross-connection semantics).
    // query()->fetchAll() fully consumes the result so no buffered query lingers.
    $pdo->query("SELECT GET_LOCK('exchange_rate_sync', 1)")->fetchAll();
    $fake = new FakeRateProvider();
    $res = RateSyncService::sync(true, 'manual', $fake);
    assertTrue($res['status'] === 'in_progress', 'second sync skipped while locked, got ' . $res['status']);
    $pdo->query("SELECT RELEASE_LOCK('exchange_rate_sync')")->fetchAll();
});

// ==========================================================================
// 14. PERCENT FEE / DISCOUNT
// ==========================================================================
echo "\n14) SELL 1,000 USD @ 1.38 with 1% fee + 0.5% discount\n";
test('Percent fee/discount resolve against the base subtotal like the server does', function () use ($desk, $vault, $usd, $base) {
    $deskBefore = LedgerService::accountBalance((int)$desk['id'], (int)$base['id']);
    $qtyBefore = (string)InventoryService::costing((int)$usd['id'])['qty'];
    $tx = TransactionService::sell([
        'customer_id' => null,
        'currency_id' => (int)$usd['id'],
        'foreign_amount' => '1000',
        'rate' => '1.3800',
        'account_id' => (int)$vault['id'],
        'destination_account_id' => (int)$desk['id'],
        'payment_method' => 'cash',
        'fee_type' => 'percent', 'fee_amount' => '1',
        'discount_type' => 'percent', 'discount_amount' => '0.5',
    ]);
    // base = 1380, fee = 13.8, discount = 6.9 → customer pays 1380 + 13.8 − 6.9 = 1386.9
    assertMoney(Money::add($deskBefore, '1386.9'), LedgerService::accountBalance((int)$desk['id'], (int)$base['id']), 'percent fee+discount applied');
    assertMoney('13.8', (string)$tx['fee_amount'], 'percent fee stored as base amount');
    assertMoney('6.9', (string)$tx['discount_amount'], 'percent discount stored as base amount');
    assertMoney(Money::sub($qtyBefore, '1000'), (string)InventoryService::costing((int)$usd['id'])['qty'], 'USD quantity');
    $unbalanced = (int)Database::value(
        "SELECT COUNT(*) FROM (
            SELECT je.id, SUM(l.base_debit) AS d, SUM(l.base_credit) AS c
            FROM journal_entries je JOIN journal_lines l ON l.entry_id = je.id
            GROUP BY je.id HAVING ABS(d - c) > 0.000001
        ) t");
    assertTrue($unbalanced === 0, 'entry stays balanced');
});

// ==========================================================================
// 15. REALIZED P/L ATTRIBUTED PER CURRENCY
// ==========================================================================
echo "\n15) Realized P/L stored on the transaction\n";
test('Sell persists realized_pl + pl_currency_id for per-currency analytics', function () use ($desk, $vault, $usd, $base) {
    $avg = (string)InventoryService::costing((int)$usd['id'])['avg_cost'];
    $tx = TransactionService::sell([
        'customer_id' => null,
        'currency_id' => (int)$usd['id'],
        'foreign_amount' => '100',
        'rate' => '1.4000',
        'account_id' => (int)$vault['id'],
        'destination_account_id' => (int)$desk['id'],
        'payment_method' => 'cash',
    ]);
    $expected = Money::round(Money::mul('100', Money::sub('1.4000', $avg)), 10);
    assertMoney($expected, (string)$tx['realized_pl'], 'realized_pl matches (rate − avg) × qty');
    assertTrue((int)$tx['pl_currency_id'] === (int)$usd['id'], 'pl attributed to USD');
});

// ==========================================================================
// 16. ANALYTICS SERVICE
// ==========================================================================
echo "\n16) Profit analytics\n";
test('Analytics metrics: volume, fees, expenses, net profit are traceable', function () use ($base) {
    $today = date('Y-m-d');
    $m = AnalyticsService::metrics($today, $today);
    assertTrue(Money::isPositive($m['volume']), 'trading volume > 0');
    assertTrue(Money::isPositive($m['fees']), 'fees > 0 (percent fee recorded)');
    assertTrue($m['tx_count'] >= 1, 'transaction count');
    // net = trading(realized+fees) + income − expenses
    $expected = Money::add(Money::add($m['realized_pl'], $m['fees']), Money::sub($m['income'], $m['expenses']));
    assertMoney($expected, $m['net_profit'], 'net profit formula');
});

test('Analytics byCurrency returns per-currency realized profit', function () {
    $today = date('Y-m-d');
    $rows = AnalyticsService::byCurrency($today, $today);
    $usdRow = null;
    foreach ($rows as $r) { if ($r['code'] === 'USD') $usdRow = $r; }
    assertTrue($usdRow !== null, 'USD row present');
    assertTrue((int)$usdRow['tx_count'] >= 1, 'USD has transactions');
});

test('Analytics trend fills every day of the range', function () {
    $today = date('Y-m-d');
    $from = date('Y-m-d', strtotime('-6 day'));
    $trend = AnalyticsService::trend($from, $today);
    assertTrue(count($trend) === 7, '7 daily points');
    foreach ($trend as $t) { assertTrue(isset($t['profit'], $t['volume'], $t['count']), 'trend shape'); }
});

test('Analytics period resolution', function () {
    $today = date('Y-m-d');
    [$from, $to] = AnalyticsService::resolvePeriod('today');
    assertTrue($from === $today && $to === $today, 'today resolves to itself');
    [$f7, $t7] = AnalyticsService::resolvePeriod('7d');
    assertTrue(date('Y-m-d', strtotime($f7 . ' +6 day')) === $t7, '7d window');
});

// ==========================================================================
// 17. INVENTORY FORECAST
// ==========================================================================
echo "\n17) Inventory forecast\n";
test('Forecast row computes status and projection from movement', function () use ($usd) {
    $row = ForecastService::row($usd);
    assertTrue(Money::isPositive($row['qty']), 'has quantity');
    assertTrue(in_array($row['status'], ['normal', 'low', 'critical', 'excess'], true), 'status valid');
    assertTrue(count($row['projection']) === 7, '7-day projection');
    // status honors explicit min/max
    $currency = $usd;
    $currency['min_inventory'] = null; $currency['target_inventory'] = null; $currency['max_inventory'] = null;
    assertTrue(ForecastService::status('7', '10', '0') === 'low', 'below min → low');
    assertTrue(ForecastService::status('5', '10', '0') === 'critical', 'at/below half min → critical');
    assertTrue(ForecastService::status('50', '10', '30') === 'excess', 'above max → excess');
    assertTrue(ForecastService::status('25', '10', '30') === 'normal', 'between min and max → normal');
});

test('Forecast targets persist per currency', function () use ($usd) {
    ForecastService::saveTargets([(int)$usd['id'] => ['min' => '8000', 'target' => '15000', 'max' => '30000']]);
    $c = Database::fetch('SELECT * FROM currencies WHERE id = ?', [(int)$usd['id']]);
    assertMoney('8000', (string)$c['min_inventory'], 'min saved');
    assertMoney('15000', (string)$c['target_inventory'], 'target saved');
    assertMoney('30000', (string)$c['max_inventory'], 'max saved');
    $row = ForecastService::row($c);
    assertTrue(in_array($row['status'], ['normal', 'low', 'critical', 'excess'], true), 'row still valid');
});

// ==========================================================================
// 18. DAILY CLOSING: checks, summary, approve/reopen, closed-day guard
// ==========================================================================
echo "\n18) Daily closing lifecycle\n";
$closingDate = date('Y-m-d');
test('Closing checks return structured results', function () use ($closingDate) {
    $checks = ClosingService::checks($closingDate);
    assertTrue(count($checks) >= 6, 'six check categories');
    foreach ($checks as $c) { assertTrue(isset($c['key'], $c['ok'], $c['message']), 'check shape'); }
});

test('Closing: open captures opening snapshot, complete writes differences, approve locks, reopen unlocks', function () use ($closingDate, $desk, $usd) {
    ClosingService::open($closingDate);
    $status = ClosingService::status($closingDate);
    assertTrue($status['opened'] && $status['row']['status'] === 'in_progress', 'day opened');
    $opening = $status['row']['opening'] ?? [];
    assertTrue(count($opening) > 0, 'opening balances snapshot');

    $summary = ClosingService::currencySummary($closingDate);
    assertTrue(count($summary['rows']) > 0, 'currency summary rows');
    $usdRow = null;
    foreach ($summary['rows'] as $s) { if ($s['currency']['code'] === 'USD') $usdRow = $s; }
    assertTrue($usdRow !== null, 'USD summary row');
    // expected = opening − sold + bought
    $expected = Money::add(Money::sub((string)$usdRow['opening'], (string)$usdRow['sold']), (string)$usdRow['bought']);
    assertMoney($expected, (string)$usdRow['expected'], 'expected closing computed');

    // Complete with physical = current (no diffs)
    $current = ClosingService::snapshot();
    $physical = [];
    foreach ($current as $k => $v) { $physical[$k] = $v['amount']; }
    $diffs = ClosingService::complete($closingDate, $physical, 'test close');
    assertTrue(count($diffs) === 0, 'no differences when physical matches system');
    assertTrue(ClosingService::isClosed($closingDate), 'day is closed');

    // Approved-day write guard: new transactions must be blocked for non-managers
    // (the test user holds closing_approve, so simulate a cashier role check).
    $guarded = true;
    try {
        // Force the guard path by using a fresh date and a non-approver session flag is
        // hard here; instead verify isClosed + guardWrite behavior directly.
        ClosingService::guardWrite($closingDate);
    } catch (DomainException $e) {
        $guarded = false;
    }
    // admin has closing_approve (owner) → no exception
    assertTrue($guarded, 'approver can still write on a closed day');

    // Approve
    ClosingService::approve($closingDate);
    assertTrue(ClosingService::status($closingDate)['row']['status'] === 'approved', 'day approved');

    // Reopen
    ClosingService::reopen($closingDate);
    assertTrue(ClosingService::status($closingDate)['row']['status'] === 'in_progress', 'day reopened');

    // Re-close so the day is final for later tests
    ClosingService::complete($closingDate, $physical, 're-close');
    ClosingService::approve($closingDate);
});

test('Closed-day guard: approver can transact; guard throws for non-approvers', function () use ($closingDate, $desk, $vault, $usd) {
    assertTrue(ClosingService::isClosed($closingDate), 'day is approved/closed');
    // The admin holds closing_approve (owner role) → authorized writes still work.
    $tx = TransactionService::sell([
        'customer_id' => null,
        'currency_id' => (int)$usd['id'],
        'foreign_amount' => '10',
        'rate' => '1.4000',
        'account_id' => (int)$vault['id'],
        'destination_account_id' => (int)$desk['id'],
        'payment_method' => 'cash',
    ]);
    assertTrue((int)$tx['id'] > 0, 'approver can transact on a closed day (authorized flow)');
    // Non-approver path: guardWrite must reject without closing_approve.
    // Swap the session to a cashier (no closing_approve) and expect a DomainException.
    $cashierRole = (int)(Database::value("SELECT id FROM roles WHERE name = 'cashier'") ?: 0);
    $cashierId = null;
    if ($cashierRole) {
        $cashierId = Database::insert('users', [
            'username' => 'testcashier', 'email' => 'cashier@example.com',
            'password_hash' => password_hash('test12345', PASSWORD_BCRYPT),
            'full_name' => 'Test Cashier', 'role_id' => $cashierRole,
        ]);
    }
    if ($cashierId) {
        $adminSessionId = Session::get('user_id');
        Session::set('user_id', (int)$cashierId);
        $threw = false;
        try {
            ClosingService::guardWrite($closingDate);
        } catch (DomainException $e) {
            $threw = true;
        }
        Session::set('user_id', $adminSessionId); // restore admin session
        assertTrue($threw, 'cashier without closing_approve is blocked on a closed day');
    }
});

// ==========================================================================
// 19. BACKUP SERVICE
// ==========================================================================
echo "\n19) Backup & restore\n";
test('Backup create writes checksum + verification and status', function () {
    $result = BackupService::create(false, 'manual');
    assertTrue($result['checksum'] !== '', 'checksum computed');
    assertTrue($result['verified'] === true, 'backup verified');
    $rec = Database::fetch("SELECT * FROM backup_records ORDER BY id DESC LIMIT 1");
    assertTrue($rec !== null && (int)$rec['verified'] === 1, 'record marked verified');
    assertTrue(is_file($rec['file_path']), 'file exists');
});

test('Encrypted backup verifies via decryption', function () {
    $result = BackupService::create(true, 'manual');
    assertTrue($result['verified'] === true, 'encrypted backup verified');
    $content = file_get_contents($result['path']);
    // The on-disk payload is base64 of "EXCH_ENC:1:…" — decode before checking.
    $decoded = base64_decode((string)$content, true);
    assertTrue($decoded !== false && str_starts_with($decoded, 'EXCH_ENC:1:'), 'encrypted format marker');
});

test('Backup status reports last backup and schedule', function () {
    $st = BackupService::status();
    assertTrue($st['last'] !== null, 'last backup present');
    // 'next' may legitimately be null when scheduled backups are disabled.
    assertTrue(array_key_exists('enabled', $st) && array_key_exists('time', $st)
        && array_key_exists('next', $st) && array_key_exists('failed_count', $st), 'status shape');
    assertTrue(is_bool($st['enabled']) && is_string($st['time']), 'enabled bool + time string');
});

test('Backup restore rejects a missing record and verifies files', function () {
    $threw = false;
    try {
        BackupService::restore(999999);
    } catch (DomainException $e) {
        $threw = true;
    }
    assertTrue($threw, 'missing record rejected');
    $rec = Database::fetch("SELECT * FROM backup_records ORDER BY id DESC LIMIT 1");
    $ok = BackupService::verifyFile($rec['file_path'], $rec['checksum'], (bool)$rec['encrypted']);
    assertTrue($ok, 'verifyFile re-validates the last backup');
});

test('Backup restore writes a safety backup file before importing', function () {
    $dir = cfg('paths.backups');
    $filesBefore = count(glob($dir . '/*'));
    $rec = Database::fetch("SELECT * FROM backup_records WHERE status = 'ok' AND kind = 'manual' ORDER BY id DESC LIMIT 1");
    BackupService::restore((int)$rec['id']);
    $filesAfter = count(glob($dir . '/*'));
    assertTrue($filesAfter > $filesBefore, 'safety backup file written before restore');
    // Data survived the round trip
    assertTrue((int)Database::value("SELECT COUNT(*) FROM currencies") > 0, 'data intact after restore');
    assertTrue((int)Database::value("SELECT COUNT(*) FROM transactions") > 0, 'transactions intact after restore');
});

// ==========================================================================
// 20. PRICE BOARD (RateService::board)
// ==========================================================================
echo "\n20) Price board data\n";
test('Internal board exposes reference/source; public board hides internals', function () {
    $internal = RateService::board(false);
    $public = RateService::board(true);
    assertTrue(isset($internal['rates'], $internal['updated_at']), 'internal board shape');
    assertTrue(count($internal['rates']) > 0, 'internal has rates');
    foreach ($internal['rates'] as $r) {
        assertTrue(array_key_exists('reference_rate', $r), 'internal exposes reference');
        assertTrue(!array_key_exists('reference_rate', $public['rates'][0]), 'public hides reference');
        break;
    }
    foreach ($public['rates'] as $r) {
        assertTrue(array_key_exists('buy_rate', $r) && array_key_exists('sell_rate', $r), 'public has buy/sell');
        assertTrue(!array_key_exists('source', $r), 'public hides source');
        assertTrue(!array_key_exists('rate_status', $r), 'public hides status');
    }
    assertTrue($internal['public'] === false && $public['public'] === true, 'mode flag');
});

// ==========================================================================
// 21. CUSTOMER LEDGER
// ==========================================================================
echo "\n21) Customer ledger\n";
test('Customer ledger returns rows + period totals, filtered', function () use ($customerId) {
    $data = CustomerService::ledger($customerId, []);
    assertTrue(isset($data['rows'], $data['totals']), 'ledger shape');
    $fData = CustomerService::ledger($customerId, ['type' => 'sell']);
    foreach ($fData['rows'] as $r) { assertTrue($r['type'] === 'sell', 'type filter applies'); }
});

test('CSV export of the ledger is well-formed', function () use ($customerId) {
    $rows = CustomerService::ledgerCsv($customerId, []);
    assertTrue(count($rows) > 0, 'has header + rows');
    assertTrue(count($rows[0]) === 11, 'header width');
});

// ==========================================================================
// 22. MIGRATIONS IDEMPOTENT
// ==========================================================================
echo "\n22) Migrations idempotent\n";
test('Migrate::run() second call applies nothing new', function () {
    $applied = Migrate::run();
    foreach ($applied as $m) {
        assertTrue(!str_starts_with($m, 'setting ') || str_starts_with($m, 'setting price_board_refresh'),
            'no re-added settings: ' . $m);
    }
});

// ==========================================================================
// 23. XLSX IMPORT / EXPORT
// ==========================================================================
echo "\n23) XLSX import/export\n";

test('Xlsx writer/reader round-trip preserves values', function () {
    $grid = [
        ['Date', 'Amount', 'Currency', 'Rate'],
        ['2023-08-01', 100, 'USD', 1.28],
        ['2023-08-02', -500, 'EUR', 1.4],
    ];
    $bytes = Xlsx::bytes($grid, ['date_cols' => [0], 'header_rows' => 1]);
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_rt');
    file_put_contents($tmp, $bytes);
    try {
        $read = Xlsx::read($tmp);
    } finally {
        @unlink($tmp);
    }
    assertTrue(count($read) === 3, '3 rows read back');
    assertTrue((string)$read[0][0] === 'Date', 'header preserved');
    assertTrue((int)$read[1][0] === 45139, 'date serial for 2023-08-01 is 45139');
    assertTrue((int)$read[1][1] === 100, 'integer preserved');
    assertTrue((string)$read[1][2] === 'USD', 'string preserved');
    assertTrue(abs((float)$read[1][3] - 1.28) < 0.0001, 'float preserved');
    assertTrue((int)$read[2][0] === 45140, 'date serial for 2023-08-02 is 45140');
});

test('Import replays rows through the engine: transactions, customers, inventory', function () use ($desk) {
    $usdId = (int)Database::value("SELECT id FROM currencies WHERE code = 'USD'");
    $eurId = (int)Database::value("SELECT id FROM currencies WHERE code = 'EUR'");
    $usdBefore = (string)InventoryService::costing($usdId)['qty'];
    $eurBefore = (string)InventoryService::costing($eurId)['qty'];
    $customersBefore = (int)Database::value('SELECT COUNT(*) FROM customers');
    $txBefore = (int)Database::value('SELECT COUNT(*) FROM transactions');

    $grid = [
        ['Date', 'Amount', 'Currency', 'Rate', 'Method', 'Amount (CAD) Paid/Received', 'Name'],
        ['45139', 100, 'USD', 1.28, 'Cash', -128, 'Rob Smith'],
        ['45140', -50, 'USD', 1.38, 'Cash', 69, 'Rob Smith'],
        ['45141', 20, 'EUR', 1.4, 'Cash', -28, 'Jane Doe'],
        ['45142', 500, 'MXN', 0.09, 'Cash', -45, 'Maria Lopez'],
    ];
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_imp');
    file_put_contents($tmp, Xlsx::bytes($grid));
    try {
        $res = DataTransferService::import($tmp, [
            'account_id' => (int)$desk['id'], 'erase' => false, 'allow_short' => false,
        ]);
    } finally {
        @unlink($tmp);
    }
    assertTrue($res['imported'] === 4, '4 rows imported, got ' . $res['imported']);
    assertTrue(count($res['failed']) === 0, 'no failed rows: ' . json_encode($res['failed']));
    assertTrue((int)Database::value('SELECT COUNT(*) FROM transactions') - $txBefore === 4, '4 transactions added');
    assertTrue((int)Database::value('SELECT COUNT(*) FROM customers') - $customersBefore >= 3, '3 customers created');
    assertTrue(in_array('MXN', $res['created_currencies'], true), 'MXN currency auto-created');
    assertMoney(Money::add($usdBefore, '50'), (string)InventoryService::costing($usdId)['qty'], 'USD inventory +50');
    assertMoney(Money::add($eurBefore, '20'), (string)InventoryService::costing($eurId)['qty'], 'EUR inventory +20');
    $d = (string)Database::value('SELECT COALESCE(SUM(base_debit),0) FROM journal_lines');
    $c = (string)Database::value('SELECT COALESCE(SUM(base_credit),0) FROM journal_lines');
    assertMoney($d, $c, 'ledger stays balanced');
});

test('allow_short lets sells exceed recorded inventory', function () use ($desk) {
    $nzdId = (int)Database::value("SELECT id FROM currencies WHERE code = 'NZD'");
    $grid = [
        ['Date', 'Amount', 'Currency', 'Rate', 'Method'],
        ['45139', -100, 'NZD', 1.5, 'Cash'],
        ['45140', 40, 'NZD', 1.4, 'Cash'],
        ['45141', -60, 'NZD', 1.5, 'Cash'],
    ];
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_short');
    file_put_contents($tmp, Xlsx::bytes($grid));
    try {
        $strict = DataTransferService::import($tmp, [
            'account_id' => (int)$desk['id'], 'erase' => false, 'allow_short' => false,
        ]);
        assertTrue($strict['imported'] === 1 && count($strict['failed']) === 2,
            'strict mode rejects both sells, imports the buy: ' . json_encode($strict));
        $loose = DataTransferService::import($tmp, [
            'account_id' => (int)$desk['id'], 'erase' => false, 'allow_short' => true,
        ]);
        assertTrue($loose['imported'] === 3 && count($loose['failed']) === 0,
            'allow_short imports every row: ' . json_encode($loose));
    } finally {
        @unlink($tmp);
    }
    $qty = (string)InventoryService::costing($nzdId)['qty'];
    assertTrue(Money::isNegative($qty), 'NZD inventory went negative after overselling: ' . $qty);
    $d = (string)Database::value('SELECT COALESCE(SUM(base_debit),0) FROM journal_lines');
    $c = (string)Database::value('SELECT COALESCE(SUM(base_credit),0) FROM journal_lines');
    assertMoney($d, $c, 'ledger stays balanced with short sales');
});

test('Export produces template rows where amount × rate = |CAD|', function () {
    $bytes = DataTransferService::exportFile();
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_exp');
    file_put_contents($tmp, $bytes);
    try {
        $grid = Xlsx::read($tmp);
    } finally {
        @unlink($tmp);
    }
    assertTrue(count($grid) >= 2, 'header + data rows');
    assertTrue(strtolower(trim((string)$grid[0][0])) === 'date', 'header A = Date');
    assertTrue(strtolower(trim((string)$grid[0][1])) === 'amount', 'header B = Amount');
    assertTrue(strtolower(trim((string)$grid[0][6])) === 'name', 'header G = Name');
    for ($i = 1; $i < count($grid); $i++) {
        $amt = (float)$grid[$i][1];
        $rate = (float)$grid[$i][3];
        $cad = (float)$grid[$i][5];
        assertTrue(abs(abs($amt * $rate) - abs($cad)) < 0.01, "row $i: amount×rate matches CAD");
        $code = preg_replace('/[^A-Z]/', '', strtoupper((string)$grid[$i][2]));
        assertTrue($code !== '', "row $i has a currency code");
        assertTrue((int)$grid[$i][0] > 40000, "row $i has a serial date");
    }
});

test('Erase wipes financial data but keeps reference tables', function () {
    DataTransferService::erase();
    foreach (['transactions', 'journal_lines', 'journal_entries', 'customers',
              'expenses', 'income', 'transfers', 'inventory_costings',
              'customer_accounts', 'cash_counts', 'reconciliations', 'daily_closings'] as $t) {
        $n = (int)Database::value("SELECT COUNT(*) FROM `$t`");
        assertTrue($n === 0, "$t empty after erase");
    }
    assertTrue((int)Database::value('SELECT COUNT(*) FROM currencies') > 0, 'currencies kept');
    assertTrue((int)Database::value('SELECT COUNT(*) FROM users') > 0, 'users kept');
    assertTrue((int)Database::value('SELECT COUNT(*) FROM accounts') > 0, 'accounts kept');
});

// ==========================================================================
echo "\n== Results: $passed passed, $failed failed ==\n";
if ($failed > 0) {
    foreach ($tests as $t) {
        if (!$t['ok']) echo "  FAILED: {$t['name']} — {$t['error']}\n";
    }
    exit(1);
}

// cleanup
$pdo->exec("DROP DATABASE IF EXISTS `{$db['name']}`");
echo "Test database cleaned up.\n";
exit(0);

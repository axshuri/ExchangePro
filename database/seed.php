<?php
/** Base data: permissions, roles, role_permissions, GL accounts, settings, base currency. */
declare(strict_types=1);

$seedDb = Database::connection(); // NOT $pdo — seed.php runs in the caller's scope, so a generic name would clobber the caller's connection variable

// ---- Permissions ----
$permissions = [
    'view_dashboard', 'view_transactions', 'create_transaction', 'edit_transaction', 'cancel_transaction',
    'view_customers', 'manage_customers', 'view_balances', 'adjust_balance',
    'view_reports', 'manage_rates', 'manage_currencies', 'manage_accounts',
    'manage_expenses', 'view_ledger', 'view_inventory', 'manage_users',
    'manage_settings', 'view_audit_log', 'perform_reconciliation', 'export_data',
];
$permIds = [];
$stmt = $seedDb->prepare("INSERT IGNORE INTO permissions (code, description) VALUES (?, ?)");
foreach ($permissions as $p) {
    $stmt->execute([$p, ucwords(str_replace('_', ' ', $p))]);
    $permIds[$p] = (int)$seedDb->lastInsertId();
}

// ---- Roles ----
$roles = [
    'owner' => 'Full access',
    'manager' => 'Almost full access',
    'cashier' => 'Create transactions, manage assigned desk',
    'accountant' => 'Financial reports, ledgers, expenses, reconciliation',
    'viewer' => 'Read-only access',
];
$roleIds = [];
$stmt = $seedDb->prepare("INSERT IGNORE INTO roles (name, description, is_system) VALUES (?, ?, 1)");
foreach ($roles as $name => $desc) {
    $stmt->execute([$name, $desc]);
    $roleIds[$name] = (int)$seedDb->lastInsertId();
}

// ---- Role permissions ----
$assign = function (string $role, array $codes) use ($seedDb, $roleIds, $permIds) {
    if (!isset($roleIds[$role])) return;
    $stmt = $seedDb->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    foreach ($codes as $code) {
        if (isset($permIds[$code])) {
            $stmt->execute([$roleIds[$role], $permIds[$code]]);
        }
    }
};

$all = array_keys($permIds);
$assign('owner', $all);
$assign('manager', array_diff($all, ['manage_users']));
$assign('cashier', [
    'view_dashboard', 'view_transactions', 'create_transaction', 'edit_transaction',
    'view_customers', 'view_balances', 'view_inventory', 'view_reports', 'export_data',
]);
$assign('accountant', [
    'view_dashboard', 'view_transactions', 'view_customers', 'view_balances', 'view_reports',
    'view_ledger', 'view_inventory', 'manage_expenses', 'perform_reconciliation', 'adjust_balance', 'export_data',
]);
$assign('viewer', [
    'view_dashboard', 'view_transactions', 'view_customers', 'view_balances', 'view_reports', 'view_inventory', 'view_ledger',
]);

// ---- Base currency (config default) ----
$baseCode = cfg('app.base_currency', 'CAD');
$currencies = [
    // [code, English name, Persian (localized) name, symbol, amount_precision, rate_precision, is_base]
    ['USD', 'United States Dollar', 'دلار آمریکا', '$', 2, 4, false],
    ['EUR', 'Euro', 'یورو', '€', 2, 4, false],
    ['GBP', 'British Pound', 'پوند بریتانیا', '£', 2, 4, false],
    ['CHF', 'Swiss Franc', 'فرانک سوئیس', 'CHF', 2, 4, false],
    ['CAD', 'Canadian Dollar', 'دلار کانادا', '$', 2, 4, true],
    ['AUD', 'Australian Dollar', 'دلار استرالیا', 'A$', 2, 4, false],
    ['NZD', 'New Zealand Dollar', 'دلار نیوزیلند', 'NZ$', 2, 4, false],
    ['JPY', 'Japanese Yen', 'ین ژاپن', '¥', 0, 4, false],
    ['CNY', 'Chinese Yuan', 'یوان چین', '¥', 2, 4, false],
    ['HKD', 'Hong Kong Dollar', 'دلار هنگ‌کنگ', 'HK$', 2, 4, false],
    ['SGD', 'Singapore Dollar', 'دلار سنگاپور', 'S$', 2, 4, false],
    ['KRW', 'South Korean Won', 'وون کره جنوبی', '₩', 0, 4, false],
    ['INR', 'Indian Rupee', 'روپیه هند', '₹', 2, 4, false],
    ['AED', 'UAE Dirham', 'درهم امارات', 'AED', 2, 4, false],
    ['SAR', 'Saudi Riyal', 'ریال عربستان', 'SAR', 2, 4, false],
    ['QAR', 'Qatari Riyal', 'ریال قطر', 'QAR', 2, 4, false],
    ['KWD', 'Kuwaiti Dinar', 'دینار کویت', 'KWD', 3, 4, false],
    ['BHD', 'Bahraini Dinar', 'دینار بحرین', 'BHD', 3, 4, false],
    ['OMR', 'Omani Rial', 'ریال عمان', 'OMR', 3, 4, false],
    ['JOD', 'Jordanian Dinar', 'دینار اردن', 'JOD', 3, 4, false],
    ['TRY', 'Turkish Lira', 'لیر ترکیه', '₺', 2, 4, false],
    ['RUB', 'Russian Ruble', 'روبل روسیه', '₽', 2, 4, false],
    ['AZN', 'Azerbaijani Manat', 'منات آذربایجان', 'AZN', 2, 4, false],
    ['GEL', 'Georgian Lari', 'لاری گرجستان', 'GEL', 2, 4, false],
    ['AMD', 'Armenian Dram', 'درام ارمنستان', 'AMD', 2, 4, false],
    ['IQD', 'Iraqi Dinar', 'دینار عراق', 'IQD', 0, 4, false],
    ['PKR', 'Pakistani Rupee', 'روپیه پاکستان', 'PKR', 2, 4, false],
    ['AFN', 'Afghan Afghani', 'افغانی افغانستان', 'AFN', 2, 4, false],
    ['KZT', 'Kazakhstani Tenge', 'تنگه قزاقستان', 'KZT', 2, 4, false],
    ['TMT', 'Turkmenistan Manat', 'منات ترکمنستان', 'TMT', 2, 4, false],
    ['UZS', 'Uzbekistani Som', 'سوم ازبکستان', 'UZS', 0, 4, false],
    ['TJS', 'Tajikistani Somoni', 'سامانی تاجیکستان', 'TJS', 2, 4, false],
    ['MYR', 'Malaysian Ringgit', 'رینگیت مالزی', 'RM', 2, 4, false],
    ['THB', 'Thai Baht', 'بات تایلند', '฿', 2, 4, false],
    ['IDR', 'Indonesian Rupiah', 'روپیه اندونزی', 'Rp', 0, 4, false],
    ['ILS', 'Israeli New Shekel', 'شِکِل اسرائیل', '₪', 2, 4, false],
    ['EGP', 'Egyptian Pound', 'پوند مصر', 'EGP', 2, 4, false],
    ['ZAR', 'South African Rand', 'رَند آفریقای جنوبی', 'R', 2, 4, false],
    ['NOK', 'Norwegian Krone', 'کرون نروژ', 'kr', 2, 4, false],
    ['SEK', 'Swedish Krona', 'کرون سوئد', 'kr', 2, 4, false],
    // Local Iranian currencies (kept for domestic market use)
    ['IRR', 'Iranian Rial', 'ریال ایران', 'IRR', 0, 2, false],
    ['IRT', 'Iranian Toman', 'تومان ایران', 'IRT', 0, 2, false],
];
$stmt = $seedDb->prepare(
    "INSERT IGNORE INTO currencies (code, name, localized_name, symbol, amount_precision, rate_precision, is_base, is_active)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
);
foreach ($currencies as [$code, $name, $local, $sym, $ap, $rp, $isBase]) {
    $stmt->execute([$code, $name, $local, $sym, $ap, $rp, $isBase ? 1 : 0]);
}

// Ensure exactly one base currency
$seedDb->exec("UPDATE currencies SET is_base = 0");
$seedDb->exec("UPDATE currencies SET is_base = 1 WHERE code = " . $seedDb->quote($baseCode));

// ---- GL accounts ----
$gl = [
    ['REALIZED_PL', 'Realized Profit/Loss', 'income'],
    ['FEE_INCOME', 'Exchange Fee Income', 'income'],
    ['DISCOUNT_GIVEN', 'Discounts Given', 'expense'],
    ['INV_ADJUSTMENT', 'Inventory Adjustment', 'expense'],
    ['EXP_RENT', 'Rent', 'expense'],
    ['EXP_SALARY', 'Salaries', 'expense'],
    ['EXP_UTILITIES', 'Utilities', 'expense'],
    ['EXP_OTHER', 'Other Operating Expenses', 'expense'],
];
$stmt = $seedDb->prepare("INSERT IGNORE INTO gl_accounts (code, name, type, is_system) VALUES (?, ?, ?, 1)");
foreach ($gl as [$code, $name, $type]) {
    $stmt->execute([$code, $name, $type]);
}

// ---- Settings ----
$settings = [
    'business_name' => cfg('app.name', 'ExchangePro'),
    'base_currency' => $baseCode,
    'timezone' => cfg('app.timezone', 'America/Toronto'),
    'language' => cfg('app.language', 'en'),
    'tx_prefix' => cfg('defaults.tx_prefix', 'EX'),
    'large_tx_threshold' => cfg('defaults.large_tx_threshold', '25000'),
    'profit_method' => 'weighted_average',
    'receipt_footer' => 'Thank you for your business. Amounts subject to confirmation.',
];
$stmt = $seedDb->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
foreach ($settings as $k => $v) {
    $stmt->execute([$k, $v]);
}

// ---- Default accounts (cash desk + vault) ----
$seedDb->prepare("INSERT IGNORE INTO accounts (code, name, type, is_active) VALUES ('DESK-1', 'Main Cash Desk', 'cash_desk', 1)")->execute();
$seedDb->prepare("INSERT IGNORE INTO accounts (code, name, type, is_active) VALUES ('VAULT-1', 'Main Vault', 'vault', 1)")->execute();

// ---- Currency denominations for cash counting (CAD & USD) ----
$denoms = [
    ['CAD', 100, 'banknote'], ['CAD', 50, 'banknote'], ['CAD', 20, 'banknote'], ['CAD', 10, 'banknote'],
    ['CAD', 5, 'banknote'], ['CAD', 2, 'coin'], ['CAD', 1, 'coin'], ['CAD', 0.25, 'coin'],
    ['USD', 100, 'banknote'], ['USD', 50, 'banknote'], ['USD', 20, 'banknote'], ['USD', 10, 'banknote'],
    ['USD', 5, 'banknote'], ['USD', 1, 'banknote'],
];
$stmt = $seedDb->prepare("INSERT IGNORE INTO currency_denominations (currency_id, kind, value, label) SELECT id, ?, ?, ? FROM currencies WHERE code = ?");
foreach ($denoms as [$code, $val, $kind]) {
    $stmt->execute([$kind, (string)$val, (string)$val, $code]);
}

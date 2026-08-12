<?php
/**
 * ExchangePro — Web Installer
 * ---------------------------
 * Point your browser at /install (or /install.php) on a fresh copy.
 *
 *  - Step 1 (form): checks PHP requirements and collects database + admin details.
 *  - Step 2 (run):  writes config/config.local.php, creates the database,
 *                   applies the schema, seeds base data, creates the admin
 *                   user, optionally loads demo data, and writes
 *                   config/installed.lock.
 *
 * Once installed.lock exists the installer is disabled and the app redirects
 * to /login. To reinstall, delete config/installed.lock (and, if you want a
 * fresh database, drop it too).
 *
 * This file is intentionally self-contained (no app bootstrap) so it works
 * before the database exists. It uses only PDO, which the app already needs.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$basePath = dirname(__DIR__);
$lockFile = $basePath . '/config/installed.lock';
$localFile = $basePath . '/config/config.local.php';

/** Escape for HTML output. */
function iEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------------
// Already installed? Lock the installer.
// ---------------------------------------------------------------------------
$action = $_GET['action'] ?? 'form';
// Once installed, the installer is fully disabled (prevents remote re-installs).
// To reinstall, delete config/installed.lock first.
if (is_file($lockFile)) {
    header('Location: /login');
    exit;
}

// Lightweight anti-CSRF token for the pre-auth install form.
if (empty($_COOKIE['exch_install_nonce'])) {
    $nonce = bin2hex(random_bytes(16));
    setcookie('exch_install_nonce', $nonce, ['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
} else {
    $nonce = $_COOKIE['exch_install_nonce'];
}

// Current form values (prefilled after a failed attempt; passwords never prefilled).
$form = [
    'db_host' => trim((string)($_POST['db_host'] ?? '127.0.0.1')),
    'db_port' => (int)($_POST['db_port'] ?? 3306),
    'db_name' => trim((string)($_POST['db_name'] ?? 'exchange_cms')),
    'db_user' => trim((string)($_POST['db_user'] ?? '')),
    'business_name' => trim((string)($_POST['business_name'] ?? 'ExchangePro')),
    'base_currency' => strtoupper(trim((string)($_POST['base_currency'] ?? 'CAD'))),
    'admin_username' => trim((string)($_POST['admin_username'] ?? 'admin')),
    'admin_email' => trim((string)($_POST['admin_email'] ?? '')),
    'with_demo' => !empty($_POST['with_demo']) || ($_SERVER['REQUEST_METHOD'] !== 'POST' && $action !== 'run'),
    'force' => !empty($_POST['force']),
];

// ---------------------------------------------------------------------------
// Currency list (base currency choice).
// ---------------------------------------------------------------------------
$currencies = [
    'CAD' => 'Canadian Dollar', 'USD' => 'US Dollar', 'EUR' => 'Euro',
    'GBP' => 'British Pound', 'AED' => 'UAE Dirham', 'TRY' => 'Turkish Lira',
    'CNY' => 'Chinese Yuan', 'RUB' => 'Russian Ruble',
    'IRR' => 'Iranian Rial', 'IRT' => 'Iranian Toman',
];

// ---------------------------------------------------------------------------
// Requirements check.
// ---------------------------------------------------------------------------
$requiredExts = ['pdo_mysql', 'bcmath', 'mbstring', 'openssl'];
$recommendedExts = ['intl', 'gd', 'zip', 'curl'];
$requirements = [];
foreach ($requiredExts as $ext) {
    $requirements[] = ['name' => $ext, 'ok' => extension_loaded($ext), 'required' => true];
}
foreach ($recommendedExts as $ext) {
    $requirements[] = ['name' => $ext, 'ok' => extension_loaded($ext), 'required' => false];
}
$requirements[] = ['name' => 'PHP ' . PHP_VERSION . ' (needs 8.1+)', 'ok' => version_compare(PHP_VERSION, '8.1.0', '>='), 'required' => true];
$requirements[] = ['name' => 'config/ directory writable', 'ok' => is_writable($basePath . '/config'), 'required' => true];
foreach (['storage', 'storage/backups', 'storage/logs', 'storage/uploads'] as $dir) {
    $path = $basePath . '/' . $dir;
    if (!is_dir($path)) @mkdir($path, 0777, true);
    $requirements[] = ['name' => $dir . ' directory writable', 'ok' => is_dir($path) && is_writable($path), 'required' => true];
}
$blockers = array_filter($requirements, fn($r) => $r['required'] && !$r['ok']);

// ---------------------------------------------------------------------------
// Handle the install POST.
// ---------------------------------------------------------------------------
$errors = [];
$installed = null;
if ($action === 'run' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- validate ---
    if (!hash_equals($nonce, (string)($_POST['install_nonce'] ?? ''))) {
        $errors[] = 'Security token missing or expired — reload the page and try again.';
    }
    $dbHost = $form['db_host'];
    $dbPort = $form['db_port'];
    $dbName = $form['db_name'];
    $dbUser = $form['db_user'];
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $businessName = $form['business_name'];
    $baseCurrency = $form['base_currency'];
    $adminUser = $form['admin_username'];
    $adminEmail = $form['admin_email'];
    $adminPass = (string)($_POST['admin_password'] ?? '');
    $adminConfirm = (string)($_POST['admin_password_confirm'] ?? '');
    $withDemo = !empty($_POST['with_demo']);
    $force = !empty($_POST['force']);

    if ($dbPort < 1 || $dbPort > 65535) $errors[] = 'Invalid database port.';
    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) $errors[] = 'Database name may only contain letters, numbers and underscores.';
    if ($dbUser === '') $errors[] = 'Database user is required.';
    if ($businessName === '') $errors[] = 'Business name is required.';
    if (!isset($currencies[$baseCurrency])) $errors[] = 'Choose a valid base currency.';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $adminUser)) $errors[] = 'Admin username must be 3–32 letters/numbers (._- allowed).';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Admin email is not valid.';
    if (strlen($adminPass) < 8) $errors[] = 'Admin password must be at least 8 characters.';
    if ($adminPass !== $adminConfirm) $errors[] = 'Admin passwords do not match.';

    // --- test the MySQL connection before doing anything destructive ---
    if (!$errors) {
        try {
            $test = new PDO(
                sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort),
                $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            $test = null;
        } catch (PDOException $e) {
            $errors[] = 'Could not connect to MySQL: ' . $e->getMessage();
        }
    }

    if (!$errors && $blockers) {
        $errors[] = 'Fix the missing requirements above before installing.';
    }

    // --- write config/config.local.php ---
    if (!$errors) {
        $local = "<?php\n"
            . "/** Generated by the web installer at " . date('c') . ". Edit or delete to reconfigure. */\n"
            . "return [\n"
            . "    'db' => [\n"
            . "        'host' => " . var_export($dbHost, true) . ",\n"
            . "        'port' => " . (int)$dbPort . ",\n"
            . "        'name' => " . var_export($dbName, true) . ",\n"
            . "        'user' => " . var_export($dbUser, true) . ",\n"
            . "        'pass' => " . var_export($dbPass, true) . ",\n"
            . "    ],\n"
            . "    'app' => [\n"
            . "        'name' => " . var_export($businessName, true) . ",\n"
            . "        'base_currency' => " . var_export($baseCurrency, true) . ",\n"
            . "    ],\n"
            . "];\n";
        if (@file_put_contents($localFile, $local) === false) {
            $errors[] = "Could not write config/config.local.php. Check that the config/ folder is writable by PHP.";
        } else {
            @chmod($localFile, 0600);
        }
    }

    // --- run the install ---
    if (!$errors) {
        require $basePath . '/app/Core/bootstrap.php'; // now reads config.local.php
        $installed = Installer::run([
            'force'     => $force,
            'with_demo' => $withDemo,
            'admin'     => [
                'username' => $adminUser,
                'email'    => $adminEmail,
                'password' => $adminPass,
                'full_name' => 'System Owner',
            ],
        ]);
        if (!$installed['ok']) {
            $errors = $installed['errors'];
        }
    }
}

// ---------------------------------------------------------------------------
// Render the page.
// ---------------------------------------------------------------------------
$formVisible = ($action === 'form' || !$installed || !$installed['ok']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ExchangePro — Installer</title>
<style>
    :root {
        --bg: #f1f4f9; --card: #ffffff; --ink: #1c2733; --muted: #6b7a8c;
        --line: #e2e8f0; --accent: #2563eb; --accent-2: #1d4ed8;
        --ok: #16a34a; --bad: #dc2626; --warn: #d97706;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0; padding: 32px 16px; background: var(--bg); color: var(--ink);
        font: 15px/1.55 -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    .wrap { max-width: 640px; margin: 0 auto; }
    .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
    .logo {
        width: 42px; height: 42px; border-radius: 10px; display: grid; place-items: center;
        background: linear-gradient(135deg, var(--accent), #7c3aed); color: #fff;
        font-weight: 800; font-size: 18px; letter-spacing: .5px;
    }
    .brand h1 { margin: 0; font-size: 20px; }
    .brand p { margin: 0; color: var(--muted); font-size: 13px; }
    .card {
        background: var(--card); border: 1px solid var(--line); border-radius: 14px;
        padding: 26px 28px; box-shadow: 0 10px 30px rgba(28,39,51,.06);
    }
    h2 { margin: 0 0 6px; font-size: 17px; }
    .sub { color: var(--muted); font-size: 13px; margin: 0 0 18px; }
    .req { display: grid; grid-template-columns: auto 1fr auto; gap: 10px; align-items: center;
        padding: 7px 0; border-bottom: 1px dashed var(--line); font-size: 14px; }
    .req:last-child { border-bottom: 0; }
    .pill { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
    .pill.ok { background: #dcfce7; color: var(--ok); }
    .pill.bad { background: #fee2e2; color: var(--bad); }
    .pill.warn { background: #fef3c7; color: var(--warn); }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .field { display: flex; flex-direction: column; gap: 5px; }
    .field.full { grid-column: 1 / -1; }
    label { font-size: 13px; font-weight: 600; color: var(--ink); }
    input, select {
        border: 1px solid var(--line); border-radius: 8px; padding: 9px 11px;
        font: inherit; color: var(--ink); background: #fff;
    }
    input:focus, select:focus { outline: 2px solid rgba(37,99,235,.35); border-color: var(--accent); }
    .hint { font-size: 12px; color: var(--muted); }
    .check { display: flex; align-items: center; gap: 8px; font-size: 13.5px; }
    .check input { width: auto; }
    .btn {
        display: inline-block; border: 0; border-radius: 9px; padding: 11px 20px;
        background: var(--accent); color: #fff; font: 600 14.5px inherit; cursor: pointer;
        transition: background .15s ease, transform .05s ease;
    }
    .btn:hover { background: var(--accent-2); }
    .btn:active { transform: translateY(1px); }
    .btn:disabled { background: #94a3b8; cursor: not-allowed; }
    .err { background: #fef2f2; border: 1px solid #fecaca; color: var(--bad);
        border-radius: 10px; padding: 12px 14px; font-size: 13.5px; margin-bottom: 16px; }
    .err li { margin: 2px 0; }
    .ok-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;
        padding: 16px; font-size: 14px; margin-bottom: 16px; }
    .ok-box ul { margin: 8px 0 0; padding-left: 18px; }
    .warn-box { background: #fffbeb; border: 1px solid #fde68a; color: #92400e;
        border-radius: 10px; padding: 12px 14px; font-size: 13px; margin-bottom: 16px; }
    code { background: #f1f5f9; padding: 1px 6px; border-radius: 5px; font-size: 12.5px; }
    .foot { text-align: center; color: var(--muted); font-size: 12px; margin-top: 18px; }
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <div class="logo">EX</div>
        <div>
            <h1>ExchangePro Installer</h1>
            <p>Currency Exchange Management &amp; Accounting System</p>
        </div>
    </div>

    <?php if ($installed && $installed['ok']): ?>
        <div class="card">
            <h2>Installation complete 🎉</h2>
            <p class="sub">Your system is ready. Log in with the admin credentials you just created.</p>
            <div class="ok-box">
                <strong>What was done:</strong>
                <ul>
                    <?php foreach ($installed['messages'] as $m): ?>
                        <li><?= iEscape($m) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php if ($installed['errors']): ?>
                <div class="err"><ul>
                    <?php foreach ($installed['errors'] as $e): ?><li><?= iEscape($e) ?></li><?php endforeach; ?>
                </ul></div>
            <?php endif; ?>
            <div class="warn-box">⚠ The installer is now locked. To reinstall later, delete
                <code>config/installed.lock</code>.</div>
            <a class="btn" href="/login">Go to login →</a>
        </div>
    <?php elseif ($formVisible): ?>
        <div class="card">
            <h2>Before you start</h2>
            <p class="sub">These checks determine whether the system can run on this server.</p>
            <?php foreach ($requirements as $r): ?>
                <div class="req">
                    <span style="color:<?= $r['ok'] ? 'var(--ok)' : 'var(--bad)' ?>"><?= $r['ok'] ? '✓' : '✗' ?></span>
                    <span><?= iEscape($r['name']) ?></span>
                    <?php if (!$r['required'] && !$r['ok']): ?>
                        <span class="pill warn">optional</span>
                    <?php elseif (!$r['ok']): ?>
                        <span class="pill bad">required</span>
                    <?php else: ?>
                        <span class="pill ok">ok</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card" style="margin-top:18px">
            <h2>Database &amp; admin account</h2>
            <p class="sub">Fill in your MySQL details. On cPanel, create the database and user in
                <strong>MySQL® Databases</strong> first (or let the installer create it if your user has permission).</p>

            <?php if ($errors): ?>
                <div class="err"><ul>
                    <?php foreach ($errors as $e): ?><li><?= iEscape($e) ?></li><?php endforeach; ?>
                </ul></div>
            <?php endif; ?>

            <form method="post" action="/install?action=run" autocomplete="off">
                <input type="hidden" name="install_nonce" value="<?= iEscape($nonce) ?>">
                <h2 style="font-size:14px; margin-bottom:12px">Database</h2>
                <div class="grid">
                    <div class="field">
                        <label for="db_host">MySQL host</label>
                        <input id="db_host" name="db_host" value="<?= iEscape($form['db_host']) ?>" required>
                    </div>
                    <div class="field">
                        <label for="db_port">Port</label>
                        <input id="db_port" name="db_port" type="number" value="<?= (int)$form['db_port'] ?>" min="1" max="65535" required>
                    </div>
                    <div class="field">
                        <label for="db_name">Database name</label>
                        <input id="db_name" name="db_name" value="<?= iEscape($form['db_name']) ?>" pattern="[A-Za-z0-9_]+" required>
                        <span class="hint">Letters, numbers, underscores only.</span>
                    </div>
                    <div class="field">
                        <label for="db_user">Database user</label>
                        <input id="db_user" name="db_user" value="<?= iEscape($form['db_user']) ?>" required>
                    </div>
                    <div class="field full">
                        <label for="db_pass">Database password</label>
                        <input id="db_pass" name="db_pass" type="password" autocomplete="new-password">
                        <span class="hint">Leave empty if your local MySQL root has no password (e.g. Laragon default).</span>
                    </div>
                </div>

                <h2 style="font-size:14px; margin:20px 0 12px">Business</h2>
                <div class="grid">
                    <div class="field">
                        <label for="business_name">Business name</label>
                        <input id="business_name" name="business_name" value="<?= iEscape($form['business_name']) ?>" required>
                    </div>
                    <div class="field">
                        <label for="base_currency">Base (accounting) currency</label>
                        <select id="base_currency" name="base_currency" required>
                            <?php foreach ($currencies as $code => $name): ?>
                                <option value="<?= $code ?>" <?= $code === $form['base_currency'] ? 'selected' : '' ?>><?= $code ?> — <?= iEscape($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint">Used for reporting, profit and asset valuation.</span>
                    </div>
                </div>

                <h2 style="font-size:14px; margin:20px 0 12px">Owner / admin login</h2>
                <div class="grid">
                    <div class="field">
                        <label for="admin_username">Username</label>
                        <input id="admin_username" name="admin_username" value="<?= iEscape($form['admin_username']) ?>" required>
                    </div>
                    <div class="field">
                        <label for="admin_email">Email</label>
                        <input id="admin_email" name="admin_email" type="email" value="<?= iEscape($form['admin_email']) ?>" required>
                    </div>
                    <div class="field">
                        <label for="admin_password">Password</label>
                        <input id="admin_password" name="admin_password" type="password" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label for="admin_password_confirm">Confirm password</label>
                        <input id="admin_password_confirm" name="admin_password_confirm" type="password" minlength="8" required autocomplete="new-password">
                    </div>
                </div>

                <div style="margin:18px 0; display:flex; flex-direction:column; gap:10px">
                    <label class="check">
                        <input type="checkbox" name="with_demo" value="1" <?= $form['with_demo'] ? 'checked' : '' ?>>
                        Load <strong>&nbsp;demo data</strong> (sample currencies, rates, customers and transactions)
                    </label>
                    <label class="check">
                        <input type="checkbox" name="force" value="1" <?= $form['force'] ? 'checked' : '' ?>>
                        <span>Drop &amp; recreate the database if it already exists (destructive — reinstall)</span>
                    </label>
                </div>

                <button class="btn" type="submit" <?= $blockers ? 'disabled title="Fix the required checks above first"' : '' ?>>
                    Install ExchangePro
                </button>
            </form>
        </div>
    <?php endif; ?>

    <div class="foot">ExchangePro v1.0 · PHP + MySQL · offline-ready</div>
</div>
</body>
</html>

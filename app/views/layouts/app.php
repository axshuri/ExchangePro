<?php
/** @var array $user @var array $flash @var string $content */
$lang = I18n::lang();
$isRtl = I18n::isRtl();
$base = \SettingService::baseCurrency();
$unread = \NotificationService::unread(Auth::id());

// Derive the active nav item from the request path (controllers never set $current)
$current = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

$nav = [
    ['href' => '/', 'icon' => 'grid', 'label' => t('app.dashboard')],
    ['section' => t('app.transactions'), 'items' => [
        ['href' => '/quick', 'icon' => 'plus-circle', 'label' => t('quick.title')],
        ['href' => '/calculator', 'icon' => 'percent', 'label' => t('calc.title')],
        ['href' => '/transactions/buy', 'icon' => 'arrow-down-circle', 'label' => t('tx.buy')],
        ['href' => '/transactions/sell', 'icon' => 'arrow-up-circle', 'label' => t('tx.sell')],
        ['href' => '/transactions/exchange', 'icon' => 'repeat', 'label' => t('tx.exchange')],
        ['href' => '/transactions', 'icon' => 'list', 'label' => t('tx.history')],
    ]],
    ['section' => t('app.customers'), 'items' => [
        ['href' => '/customers', 'icon' => 'users', 'label' => t('customer.customers')],
    ]],
    ['section' => t('app.inventory'), 'items' => [
        ['href' => '/inventory', 'icon' => 'briefcase', 'label' => t('dashboard.currency_inventory')],
        ['href' => '/inventory/forecast', 'icon' => 'trending-up', 'label' => t('forecast.title')],
        ['href' => '/accounts', 'icon' => 'wallet', 'label' => t('account.accounts')],
        ['href' => '/transfers', 'icon' => 'repeat', 'label' => t('transfer.transfers')],
        ['href' => '/reconciliation', 'icon' => 'check-square', 'label' => t('recon.title')],
    ]],
    ['section' => t('app.rates'), 'items' => [
        ['href' => '/rates', 'icon' => 'trending-up', 'label' => t('rates.title')],
        ['href' => '/rates/board', 'icon' => 'activity', 'label' => t('board.title')],
        ['href' => '/rates/history', 'icon' => 'clock', 'label' => t('rates.history')],
    ]],
    ['section' => t('app.accounting'), 'items' => [
        ['href' => '/ledger', 'icon' => 'book', 'label' => t('ledger.title')],
        ['href' => '/expenses', 'icon' => 'minus-circle', 'label' => t('expense.expenses')],
        ['href' => '/income', 'icon' => 'plus-circle', 'label' => t('income.income')],
        ['href' => '/accounting/pnl', 'icon' => 'percent', 'label' => t('reports.pnl')],
        ['href' => '/accounting/balance-sheet', 'icon' => 'scale', 'label' => t('reports.balance_sheet')],
    ]],
    ['section' => t('app.reports'), 'items' => [
        ['href' => '/analytics/profit', 'icon' => 'percent', 'label' => t('analytics.profit_title')],
        ['href' => '/reports/daily', 'icon' => 'calendar', 'label' => t('reports.daily')],
        ['href' => '/reports/monthly', 'icon' => 'calendar', 'label' => t('reports.monthly')],
        ['href' => '/reports/currency', 'icon' => 'coins', 'label' => t('reports.currency')],
        ['href' => '/reports/customer', 'icon' => 'users', 'label' => t('reports.customer')],
        ['href' => '/reports/inventory', 'icon' => 'briefcase', 'label' => t('reports.inventory')],
        ['href' => '/accounting/cash-flow', 'icon' => 'activity', 'label' => t('reports.cash_flow')],
    ]],
    ['section' => t('app.operations'), 'items' => [
        ['href' => '/cash-count', 'icon' => 'hash', 'label' => t('cashcount.title')],
        ['href' => '/closing', 'icon' => 'lock', 'label' => t('closing.title')],
    ]],
    ['section' => t('app.settings'), 'items' => [
        ['href' => '/currencies', 'icon' => 'coins', 'label' => t('currency.currencies')],
        ['href' => '/users', 'icon' => 'user', 'label' => t('user.users')],
        ['href' => '/roles', 'icon' => 'shield', 'label' => t('role.roles')],
        ['href' => '/settings/business', 'icon' => 'settings', 'label' => t('settings.business')],
        ['href' => '/settings/backup', 'icon' => 'database', 'label' => t('settings.backup')],
        ['href' => '/settings/data', 'icon' => 'download', 'label' => t('data.transfer')],
    ]],
    ['section' => t('app.audit'), 'items' => [
        ['href' => '/audit', 'icon' => 'file-text', 'label' => t('audit.title')],
    ]],
];
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#f6f7f9">
<meta name="color-scheme" content="light dark">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(SettingService::businessName()) ?>">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" type="image/png" href="/assets/img/favicon-32.png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<title><?= e(SettingService::businessName()) ?> — <?= e($title ?? t('app.dashboard')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php if ($isRtl): ?><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet"><?php endif; ?>
<link rel="stylesheet" href="/assets/css/app.css">
<script>
/* Apply saved theme before first paint to avoid flash */
(function () {
  var t = localStorage.getItem('exch-theme');
  if (t !== 'dark' && t !== 'light') {
    t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  document.documentElement.setAttribute('data-theme', t);
  var tc = document.querySelector('meta[name="theme-color"]');
  if (tc) tc.setAttribute('content', t === 'dark' ? '#0b0e14' : '#f6f7f9');
})();
window.EXCHANGE = { base: <?= json_encode($base ? $base['code'] : '') ?> };
</script>
</head>
<body>
<a class="skip-link" href="#mainContent"><?= t('app.skip_to_content') ?></a>
<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="<?= t('app.dashboard') ?>">
        <div class="brand">
            <div class="brand-logo"><?= e(mb_substr(SettingService::businessName(), 0, 1)) ?></div>
            <div class="brand-text">
                <strong translate="no"><?= e(SettingService::businessName()) ?></strong>
                <small><?= e(t('app.brand_sub')) ?></small>
            </div>
        </div>
        <nav class="nav">
            <?php foreach ($nav as $item): ?>
                <?php if (isset($item['href'])): ?>
                    <a href="<?= e($item['href']) ?>" class="nav-link <?= (isset($current) && $current === $item['href']) ? 'active' : '' ?>">
                        <svg class="icon"><use href="/assets/img/icons.svg#<?= e($item['icon']) ?>"/></svg>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php else: ?>
                    <div class="nav-section"><?= e($item['section']) ?></div>
                    <?php foreach ($item['items'] as $sub): ?>
                        <a href="<?= e($sub['href']) ?>" class="nav-link <?= (isset($current) && $current === $sub['href']) ? 'active' : '' ?>">
                            <svg class="icon"><use href="/assets/img/icons.svg#<?= e($sub['icon']) ?>"/></svg>
                            <span><?= e($sub['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="user-chip mb-1">
                <div class="user-avatar"><?= e(mb_substr($user['full_name'] ?? 'U', 0, 1)) ?></div>
                <div class="user-meta">
                    <strong><?= e($user['full_name'] ?? '') ?></strong>
                    <small><?= e($user['role_name'] ?? '') ?></small>
                </div>
            </div>
            <a href="/logout" class="nav-link">
                <svg class="icon"><use href="/assets/img/icons.svg#log-out"/></svg>
                <span><?= t('app.logout') ?></span>
            </a>
        </div>
    </aside>
    <div class="sidebar-scrim" id="sidebarScrim" aria-hidden="true"></div>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Menu" aria-expanded="false" aria-controls="sidebar">
                <svg class="icon"><use href="/assets/img/icons.svg#menu"/></svg>
            </button>
            <div class="topbar-title">
                <div class="topbar-title-text"><?= e($title ?? t('app.dashboard')) ?></div>
                <small><?= e(localizedDate()) ?></small>
            </div>
            <div class="topbar-credit" title="<?= e(t('app.built_by')) ?>">
                <svg class="icon app-heart" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 21s-6.7-4.35-9.33-8.11C.9 10.36 1.9 7 4.9 6.06 6.6 5.54 8.5 6.2 10 7.7 11.5 6.2 13.4 5.54 15.1 6.06c3 .94 4 4.3 2.23 6.83C18.7 16.65 12 21 12 21z"/></svg>
                <span><?= t('app.built_by') ?></span>
            </div>
            <div class="topbar-actions">
                <form class="global-search" method="get" action="/transactions" role="search">
                    <svg class="icon search-icon"><use href="/assets/img/icons.svg#search"/></svg>
                    <input type="text" name="q" id="globalSearch" placeholder="<?= t('app.search') ?>…" aria-label="<?= t('app.search') ?>">
                    <kbd translate="no">Ctrl K</kbd>
                </form>
                <a href="/currencies" class="chip chip-base" title="<?= t('currency.is_base') ?>">
                    <?= e($base ? $base['code'] : '') ?>
                </a>
                <a href="/" class="icon-btn" title="<?= t('app.notifications') ?>" aria-label="<?= t('app.notifications') ?>">
                    <svg class="icon" aria-hidden="true"><use href="/assets/img/icons.svg#bell"/></svg>
                    <?php if ($unread > 0): ?><span class="badge"><?= $unread ?></span><?php endif; ?>
                </a>
                <label class="lang-switch" title="<?= e(t('app.language')) ?>">
                    <select class="form-select lang-select" aria-label="<?= e(t('app.language')) ?>" onchange="window.location.href='/lang/'+encodeURIComponent(this.value)">
                        <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="fa" <?= $lang === 'fa' ? 'selected' : '' ?>>فارسی</option>
                    </select>
                </label>
                <button class="icon-btn" id="themeToggle" title="Toggle theme" aria-label="Toggle theme">
                    <svg class="icon" data-icon="moon"><use href="/assets/img/icons.svg#moon"/></svg>
                    <svg class="icon" data-icon="sun" style="display:none"><use href="/assets/img/icons.svg#sun"/></svg>
                </button>
                <button class="icon-btn" id="installAppBtn" hidden title="<?= e(t('app.install')) ?>" aria-label="<?= e(t('app.install')) ?>">
                    <svg class="icon"><use href="/assets/img/icons.svg#download"/></svg>
                </button>
            </div>
        </header>

        <?php if (!empty($flash)): ?>
            <div class="flash-container" aria-live="polite">
                <?php foreach ($flash as $f): ?>
                    <div class="flash flash-<?= e($f['type']) ?>">
                        <?= e($f['message']) ?>
                        <button class="flash-close" onclick="this.parentElement.remove()" aria-label="<?= t('app.close') ?>">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <main class="content" id="mainContent" tabindex="-1">
            <?= $content ?>
        </main>
    </div>
</div>

<!-- Mobile bottom navigation (phones only) -->
<nav class="mobile-tabbar" aria-label="<?= t('app.dashboard') ?>">
    <a class="mtab <?= $current === '/' ? 'active' : '' ?>" href="/">
        <svg class="icon"><use href="/assets/img/icons.svg#grid"/></svg>
        <span><?= t('app.dashboard') ?></span>
    </a>
    <a class="mtab <?= $current === '/rates' ? 'active' : '' ?>" href="/rates">
        <svg class="icon"><use href="/assets/img/icons.svg#trending-up"/></svg>
        <span><?= t('rates.title') ?></span>
    </a>
    <button class="mtab mtab-fab" id="fabNew" aria-haspopup="dialog" aria-expanded="false" aria-label="<?= t('app.new_transaction') ?>">
        <svg class="icon"><use href="/assets/img/icons.svg#plus"/></svg>
    </button>
    <a class="mtab <?= str_starts_with($current, '/transactions') ? 'active' : '' ?>" href="/transactions">
        <svg class="icon"><use href="/assets/img/icons.svg#list"/></svg>
        <span><?= t('tx.history') ?></span>
    </a>
    <button class="mtab" id="moreBtn" aria-label="<?= t('app.more') ?>">
        <svg class="icon"><use href="/assets/img/icons.svg#menu"/></svg>
        <span><?= t('app.more') ?></span>
    </button>
</nav>

<!-- Mobile "New" action sheet (phones only) -->
<div class="sheet-backdrop" id="newSheet" aria-hidden="true">
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="newSheetTitle">
        <div class="sheet-handle" aria-hidden="true"></div>
        <h3 class="sheet-title" id="newSheetTitle"><?= t('app.new_transaction') ?></h3>
        <div class="sheet-grid">
            <a class="sheet-action" href="/transactions/buy">
                <span class="sheet-action-icon buy"><svg class="icon"><use href="/assets/img/icons.svg#arrow-down-circle"/></svg></span>
                <span><?= t('tx.buy') ?></span>
            </a>
            <a class="sheet-action" href="/transactions/sell">
                <span class="sheet-action-icon sell"><svg class="icon"><use href="/assets/img/icons.svg#arrow-up-circle"/></svg></span>
                <span><?= t('tx.sell') ?></span>
            </a>
            <a class="sheet-action" href="/transactions/exchange">
                <span class="sheet-action-icon exchange"><svg class="icon"><use href="/assets/img/icons.svg#repeat"/></svg></span>
                <span><?= t('tx.exchange') ?></span>
            </a>
            <a class="sheet-action" href="/customers/create" data-ajax-form="/customers/create" data-ajax-title="<?= t('customer.new') ?>">
                <span class="sheet-action-icon default"><svg class="icon"><use href="/assets/img/icons.svg#users"/></svg></span>
                <span><?= t('customer.new') ?></span>
            </a>
            <a class="sheet-action" href="/expenses/create" data-ajax-form="/expenses/create" data-ajax-title="<?= t('expense.new') ?>">
                <span class="sheet-action-icon default"><svg class="icon"><use href="/assets/img/icons.svg#minus-circle"/></svg></span>
                <span><?= t('expense.new') ?></span>
            </a>
            <a class="sheet-action" href="/transfers/create" data-ajax-form="/transfers/create" data-ajax-title="<?= t('transfer.new') ?>">
                <span class="sheet-action-icon default"><svg class="icon"><use href="/assets/img/icons.svg#repeat"/></svg></span>
                <span><?= t('transfer.new') ?></span>
            </a>
        </div>
    </div>
</div>

<!-- Install-to-home-screen helper (PWA) -->
<div class="modal-backdrop" id="installModal" role="dialog" aria-modal="true" aria-labelledby="installModalTitle">
    <div class="modal">
        <div class="modal-head">
            <h3 id="installModalTitle"><?= t('app.install_title') ?></h3>
            <button type="button" class="icon-btn" id="installModalClose" aria-label="<?= t('app.close') ?>">
                <svg class="icon"><use href="/assets/img/icons.svg#x"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <p class="install-lead"><?= t('app.install_lead') ?></p>
            <ol class="install-steps">
                <li><?= t('app.install_step1') ?></li>
                <li><?= t('app.install_step2') ?></li>
                <li><?= t('app.install_step3') ?></li>
            </ol>
            <p class="form-hint"><?= t('app.install_note') ?></p>
        </div>
        <div class="modal-foot">
            <button class="btn btn-primary btn-block" id="installModalAction">
                <svg class="icon"><use href="/assets/img/icons.svg#download"/></svg>
                <?= t('app.install_now') ?>
            </button>
        </div>
    </div>
</div>

<!-- Generic AJAX create-form panel: opened by any [data-ajax-form] trigger -->
<div class="modal-backdrop" id="ajaxFormModal" role="dialog" aria-modal="true" aria-labelledby="ajaxFormModalTitle">
    <div class="modal ajax-form-modal">
        <div class="modal-head">
            <h3 id="ajaxFormModalTitle"></h3>
            <button type="button" class="icon-btn" id="ajaxFormModalClose" aria-label="<?= t('app.close') ?>">
                <svg class="icon"><use href="/assets/img/icons.svg#x"/></svg>
            </button>
        </div>
        <div class="modal-body" id="ajaxFormModalBody"
             data-load-failed="<?= e(t('app.load_failed')) ?>"></div>
    </div>
</div>

<script src="/assets/js/app.js"></script>
</body>
</html>

<?php /** @var array $flash */ ?>
<!DOCTYPE html>
<html lang="<?= e(I18n::lang()) ?>" dir="<?= I18n::isRtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#f6f7f9">
<meta name="color-scheme" content="light dark">
<title><?= e(SettingService::businessName()) ?> — <?= t('auth.login') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php if (I18n::isRtl()): ?><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet"><?php endif; ?>
<link rel="stylesheet" href="/assets/css/app.css">
<script>
(function () {
  var t = localStorage.getItem('exch-theme');
  if (t !== 'dark' && t !== 'light') {
    t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  document.documentElement.setAttribute('data-theme', t);
  var tc = document.querySelector('meta[name="theme-color"]');
  if (tc) tc.setAttribute('content', t === 'dark' ? '#0b0e14' : '#f6f7f9');
})();
</script>
</head>
<body>
<div class="auth-lang">
    <label class="lang-switch" title="<?= e(t('app.language')) ?>">
        <select class="form-select lang-select" aria-label="<?= e(t('app.language')) ?>" onchange="window.location.href='/lang/'+encodeURIComponent(this.value)">
            <option value="en" <?= I18n::lang() === 'en' ? 'selected' : '' ?>>English</option>
            <option value="fa" <?= I18n::lang() === 'fa' ? 'selected' : '' ?>>فارسی</option>
        </select>
    </label>
</div>
<div class="auth-shell">
    <aside class="auth-panel">
        <div class="auth-panel-inner">
            <div class="brand">
                <div class="brand-logo"><?= e(mb_substr(SettingService::businessName(), 0, 1)) ?></div>
                <div class="brand-text">
                    <strong style="color:#fff" translate="no"><?= e(SettingService::businessName()) ?></strong>
                    <small style="color:rgba(255,255,255,.65)"><?= e(t('app.brand_sub')) ?></small>
                </div>
            </div>

            <div class="auth-panel-hero">
                <h2><?= e(t('auth.hero_line1')) ?><br><?= e(t('auth.hero_line2')) ?></h2>
                <p><?= e(t('auth.hero_sub')) ?></p>
            </div>

            <ul class="auth-points">
                <li><span class="auth-point-icon"><svg class="icon"><use href="/assets/img/icons.svg#trending-up"/></svg></span> <?= e(t('auth.point_rates')) ?></li>
                <li><span class="auth-point-icon"><svg class="icon"><use href="/assets/img/icons.svg#shield"/></svg></span> <?= e(t('auth.point_audit')) ?></li>
                <li><span class="auth-point-icon"><svg class="icon"><use href="/assets/img/icons.svg#database"/></svg></span> <?= e(t('auth.point_backup')) ?></li>
            </ul>
        </div>
    </aside>

    <main class="auth-form-side">
        <?php View::partial('auth_mobile_hero', ['heroKeys' => [
            'line1' => 'auth.hero_line1', 'line2' => 'auth.hero_line2', 'sub' => 'auth.hero_sub',
        ]]); ?>
        <div class="auth-card">
            <div class="auth-brand">
                <h1><?= t('auth.welcome_back') ?></h1>
                <p><?= t('auth.login_hint') ?></p>
            </div>
            <div class="card">
                <div class="card-body">
                    <?php if (!empty($flash)): ?>
                        <?php foreach ($flash as $f): ?>
                            <div class="flash flash-<?= e($f['type']) ?> mb-2"><?= e($f['message']) ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <form method="post" action="/login">
                        <?= Csrf::field() ?>
                        <div class="form-group mb-2">
                            <label class="form-label" for="username"><?= t('auth.username') ?></label>
                            <input class="form-control" type="text" id="username" name="username" required autofocus autocomplete="username" placeholder="<?= t('auth.username') ?>…">
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label" for="password"><?= t('auth.password') ?></label>
                            <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password" placeholder="<?= t('auth.password') ?>…">
                        </div>
                        <button class="btn btn-primary btn-lg btn-block" type="submit">
                            <svg class="icon"><use href="/assets/img/icons.svg#lock"/></svg>
                            <?= t('auth.login_btn') ?>
                        </button>
                    </form>
                </div>
            </div>
            <p class="text-muted mt-2" style="text-align:center;font-size:.74rem">
                <?= e(t('auth.secure_footer')) ?>
            </p>
        </div>
    </main>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>

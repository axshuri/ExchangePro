<?php Session::start(); ?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<title>404 — <?= t('errors.404') ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="card">
            <div class="card-body" style="text-align:center;padding:36px">
                <div style="font-size:3rem;font-weight:800;color:var(--text-3)">404</div>
                <h1 style="margin:10px 0 6px"><?= t('errors.404') ?></h1>
                <p class="text-muted"><?= t('errors.404_msg') ?></p>
                <a class="btn btn-primary mt-2" href="/"><?= t('app.dashboard') ?></a>
            </div>
        </div>
    </div>
</div>
</body>
</html>

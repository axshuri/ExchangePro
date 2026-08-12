<?php
declare(strict_types=1);

$basePath = dirname(__DIR__);

// Friendly alias: /install → /install.php (web installer). Served inline so
// POST bodies (the install form) survive — a redirect would turn them into GET.
$uri = $_SERVER['REQUEST_URI'] ?? '';
if ($uri === '/install' || str_starts_with($uri, '/install?')) {
    require $basePath . '/public/install.php';
    exit;
}

// Not installed yet → run the web installer.
if (!is_file($basePath . '/config/installed.lock')) {
    header('Location: /install');
    exit;
}

require_once $basePath . '/app/Core/bootstrap.php';

try {
    App::dispatch();
} catch (LargeTransactionException $e) {
    // handled within controllers normally; safety net
    Session::start();
    Session::flash('warning', t('tx.large_confirm'));
    redirect('/transactions');
} catch (DomainException $e) {
    Session::start();
    Session::flash('danger', $e->getMessage());
    if (!headers_sent()) redirect('/');
} catch (Throwable $e) {
    error_log('[ExchangePro] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (cfg('app.debug')) {
        echo '<pre style="font:12px/1.4 monospace;padding:20px;background:#1e1e2e;color:#eee;white-space:pre-wrap">'
            . e($e->getMessage()) . "\n\n" . e($e->getTraceAsString()) . '</pre>';
    } else {
        echo 'An internal error occurred. Please try again.';
    }
}

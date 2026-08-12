<?php
declare(strict_types=1);

/**
 * Base controller: auth middleware, permission checks, view helper.
 */
abstract class Controller
{
    protected ?array $user = null;

    /** @var string|null Permission required for the whole controller (default: must be logged in). */
    protected ?string $requirePermission = null;

    /** @var array<string,string> Action => permission overrides. */
    protected array $permissions = [];

    public function call(string $action, array $args): void
    {
        Session::start();

        // Language switch handled inside auth controller; apply session lang early
        I18n::lang();

        if (!Auth::check()) {
            if ($this->isPublic($action)) {
                $this->{$action}(...$args);
                return;
            }
            Session::flash('warning', t('auth.login_required'));
            redirect('/login');
        }

        $this->user = Auth::user();

        // Permission check
        $perm = $this->permissions[$action] ?? $this->requirePermission;
        if ($perm !== null && !Auth::hasPermission($perm)) {
            if ($this->isAjax()) {
                $this->json(['ok' => false, 'message' => t('errors.403_msg')], 403);
            }
            http_response_code(403);
            View::render('errors/403');
            return;
        }

        $this->{$action}(...$args);
    }

    /** Actions reachable without login. */
    protected function isPublic(string $action): bool
    {
        return $action === 'loginForm' || $action === 'login' || $action === 'twoFactorForm'
            || $action === 'twoFactorVerify' || $action === 'setLang';
    }

    protected function render(string $view, array $data = []): void
    {
        View::render($view, array_merge(['user' => $this->user, 'flash' => Session::pullFlash()], $data));
    }

    /** Emit a JSON response and stop. */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    /** True when the request is a fetch()/AJAX call. */
    protected function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    /** Emit a CSV download from an array of rows. */
    protected function csv(array $rows, string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
        foreach ($rows as $r) {
            fputcsv($out, array_map(static fn($v) => (string)$v, $r));
        }
        fclose($out);
        exit;
    }

    /** Render a view without the app layout (used for AJAX form panels). */
    protected function renderBare(string $view, array $data = []): void
    {
        View::render($view, $data, true);
    }

    /**
     * Fail a create/store: JSON 422 for AJAX, flash+redirect otherwise.
     *
     * @param array<string, array<int, string>> $errors per-field validation messages
     */
    protected function fail(string $message, string $redirectUrl, array $errors = []): void
    {
        if ($this->isAjax()) {
            $this->json(['ok' => false, 'message' => $message, 'errors' => $errors], 422);
        }
        Session::flash('danger', $message);
        redirect($redirectUrl);
    }

    /**
     * Succeed a create/store: JSON ok for AJAX (flash queued so the reload shows it),
     * flash+redirect otherwise.
     */
    protected function succeed(string $message, string $redirectUrl, array $extra = []): void
    {
        Session::flash('success', $message);
        if ($this->isAjax()) {
            $this->json(array_merge(['ok' => true, 'message' => $message], $extra));
        }
        redirect($redirectUrl);
    }

    /** CSRF guard that degrades to JSON 419 for AJAX instead of throwing. */
    protected function csrfGuard(): void
    {
        if (cfg('security.csrf') === false) return;
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            if ($this->isAjax()) $this->json(['ok' => false, 'message' => t('app.csrf_error')], 419);
            Csrf::check();
        }
    }

    protected function requirePermission(string $perm): void
    {
        if (!Auth::hasPermission($perm)) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    /** Pagination helper: returns [rows, total, page, perPage]. */
    protected function paginate(string $baseSql, array $params, int $page, int $perPage = 20): array
    {
        $total = (int)Database::value("SELECT COUNT(*) FROM ($baseSql) t", $params);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;
        $rows = Database::query($baseSql . " LIMIT $perPage OFFSET $offset", $params);
        return [$rows, $total, $page, $pages];
    }
}

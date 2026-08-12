<?php
declare(strict_types=1);

/**
 * View renderer: renders a template inside the app layout (or standalone).
 */
final class View
{
    private static array $data = [];

    public static function render(string $view, array $data = [], bool $bare = false): void
    {
        self::$data = $data;
        $viewFile = BASE_PATH . '/app/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException("View not found: $view");
        }

        $standalone = $bare || str_starts_with($view, 'errors/') || str_starts_with($view, 'auth/')
            || str_starts_with($view, 'receipt/') || str_starts_with($view, 'export/');

        if ($standalone) {
            extract($data);
            require $viewFile;
            return;
        }

        // Default layout with sidebar
        $content = self::capture($viewFile, $data);
        extract($data);
        require BASE_PATH . '/app/views/layouts/app.php';
    }

    public static function partial(string $name, array $data = []): void
    {
        $file = BASE_PATH . '/app/views/partials/' . $name . '.php';
        if (is_file($file)) {
            extract(array_merge(self::$data, $data));
            require $file;
        }
    }

    public static function capture(string $file, array $data): string
    {
        ob_start();
        extract($data);
        require $file;
        return (string)ob_get_clean();
    }

    public static function data(): array
    {
        return self::$data;
    }
}

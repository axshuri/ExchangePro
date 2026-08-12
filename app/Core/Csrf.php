<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (!Session::has('_csrf')) {
            Session::set('_csrf', bin2hex(random_bytes(32)));
        }
        return Session::get('_csrf');
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $token): bool
    {
        $expected = Session::get('_csrf');
        if ($expected === null || $token === null) return false;
        return hash_equals($expected, $token);
    }

    public static function check(): void
    {
        if (cfg('security.csrf') === false) return;
        if (!self::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            throw new RuntimeException('Invalid CSRF token. Please refresh the page and try again.');
        }
    }
}

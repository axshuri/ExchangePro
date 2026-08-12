<?php
declare(strict_types=1);

/**
 * Lightweight i18n. Translations live in app/i18n/{lang}.php keyed by dot-notation keys.
 */
final class I18n
{
    private static array $strings = [];
    private static ?string $lang = null;

    public static function lang(): string
    {
        if (self::$lang === null) {
            self::$lang = Session::get('lang', self::defaultLang());
        }
        return self::$lang;
    }

    /**
     * Default language: DB setting (Settings → Business) when available,
     * otherwise the config default. Session choice always wins over both.
     */
    private static function defaultLang(): string
    {
        if (Installer::isInstalled()) {
            try {
                $saved = SettingService::get('language');
                if (is_string($saved) && $saved !== '' && in_array($saved, self::available(), true)) {
                    return $saved;
                }
            } catch (Throwable) {
                // Settings table not ready (e.g. during install) — fall back to config.
            }
        }
        return cfg('app.language', 'en');
    }

    public static function setLang(string $lang): void
    {
        self::$lang = $lang;
        Session::set('lang', $lang);
    }

    public static function available(): array
    {
        $files = glob(BASE_PATH . '/app/i18n/*.php');
        $langs = [];
        foreach ($files ?: [] as $f) {
            $langs[] = basename($f, '.php');
        }
        return $langs;
    }

    public static function isRtl(): bool
    {
        return self::lang() === 'fa';
    }

    private static function load(): array
    {
        $lang = self::lang();
        if (!isset(self::$strings[$lang])) {
            $file = BASE_PATH . '/app/i18n/' . $lang . '.php';
            self::$strings[$lang] = is_file($file) ? require $file : [];
        }
        return self::$strings[$lang];
    }

    public static function translate(string $key, array $params = []): string
    {
        $strings = self::load();
        $text = $strings[$key] ?? $key;
        foreach ($params as $k => $v) {
            $text = str_replace($k, (string)$v, $text);
        }
        return $text;
    }
}

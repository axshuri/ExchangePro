<?php
declare(strict_types=1);

/** HTML-escape output. */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Redirect and stop. */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** URL for an app route. */
function url(string $path = '/'): string
{
    return $path;
}

/** Translation helper: t('key', [':param' => value]). */
function t(string $key, array $params = []): string
{
    return I18n::translate($key, $params);
}

/** Shorthand money format using a currency. */
function money(?string $amount, ?array $currency = null, bool $symbol = true): string
{
    $precision = (int)($currency['amount_precision'] ?? 2);
    $formatted = Money::format((string)$amount, $precision);
    if (!$symbol || empty($currency['symbol'])) return $formatted;
    return $formatted . ' ' . $currency['symbol'];
}

/**
 * Currency display name: the localized (Persian) name in fa mode,
 * otherwise the English name. Accepts a currency row with 'localized_name'.
 */
function currencyName(?array $currency, string $fallback = ''): string
{
    if (!$currency) return $fallback;
    if (I18n::isRtl() && !empty($currency['localized_name'])) {
        return (string)$currency['localized_name'];
    }
    return (string)($currency['name'] ?? $fallback);
}

/** Convert stored UTC to business timezone for display. */
function tz(?string $datetime, string $format = 'Y-m-d H:i'): string
{
    if (!$datetime) return '';
    $tz = new DateTimeZone(cfg('app.timezone', 'UTC'));
    return (new DateTime($datetime, new DateTimeZone('UTC')))->setTimezone($tz)->format($format);
}

/** Server IP. */
function clientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/** Current user agent. */
function clientUA(): string
{
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
}

/**
 * Locale-aware date display. Converts to the business timezone and, for
 * Persian, translates English weekday/month names (Latin digits are kept).
 */
function localizedDate(?string $datetime = null, string $format = 'l, M j Y'): string
{
    $tz = new DateTimeZone(cfg('app.timezone', 'UTC'));
    if ($datetime && preg_match('/^\d{4}-\d{2}-\d{2}$/', $datetime)) {
        // Date-only input (already in business timezone) — never shift across midnight.
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
    } elseif ($datetime) {
        $dt = (new DateTime($datetime, new DateTimeZone('UTC')))->setTimezone($tz);
    } else {
        $dt = new DateTime('now', $tz);
    }
    $out = $dt->format($format);
    if (I18n::lang() !== 'fa') return $out;

    $names = [
        'Monday' => 'دوشنبه', 'Tuesday' => 'سه‌شنبه', 'Wednesday' => 'چهارشنبه',
        'Thursday' => 'پنجشنبه', 'Friday' => 'جمعه', 'Saturday' => 'شنبه', 'Sunday' => 'یکشنبه',
        'Mon' => 'دوشنبه', 'Tue' => 'سه‌شنبه', 'Wed' => 'چهارشنبه', 'Thu' => 'پنجشنبه',
        'Fri' => 'جمعه', 'Sat' => 'شنبه', 'Sun' => 'یکشنبه',
        'January' => 'ژانویه', 'February' => 'فوریه', 'March' => 'مارس', 'April' => 'آوریل',
        'May' => 'مه', 'June' => 'ژوئن', 'July' => 'ژوئیه', 'August' => 'اوت',
        'September' => 'سپتامبر', 'October' => 'اکتبر', 'November' => 'نوامبر', 'December' => 'دسامبر',
        'Jan' => 'ژانویه', 'Feb' => 'فوریه', 'Mar' => 'مارس', 'Apr' => 'آوریل',
        'Jun' => 'ژوئن', 'Jul' => 'ژوئیه', 'Aug' => 'اوت', 'Sep' => 'سپتامبر',
        'Oct' => 'اکتبر', 'Nov' => 'نوامبر', 'Dec' => 'دسامبر',
    ];
    return strtr($out, $names);
}

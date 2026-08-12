<?php
declare(strict_types=1);

/**
 * Money: exact decimal arithmetic via bcmath.
 * All financial calculations go through this class. Never use float math.
 */
final class Money
{
    private const SCALE = 10;

    public static function zero(): string
    {
        return '0';
    }

    public static function norm(string $value): string
    {
        if ($value === '' || $value === null) return '0';
        $value = bcadd((string)$value, '0', self::SCALE);
        return $value;
    }

    public static function add(string $a, string $b): string
    {
        return bcadd(self::norm($a), self::norm($b), self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub(self::norm($a), self::norm($b), self::SCALE);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul(self::norm($a), self::norm($b), self::SCALE);
    }

    public static function div(string $a, string $b, int $scale = self::SCALE): string
    {
        $b = self::norm($b);
        if (bccomp($b, '0', self::SCALE) === 0) {
            throw new DomainException('Division by zero in Money::div');
        }
        return bcdiv(self::norm($a), $b, $scale);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp(self::norm($a), self::norm($b), self::SCALE);
    }

    public static function isZero(string $a): bool
    {
        return bccomp(self::norm($a), '0', self::SCALE) === 0;
    }

    public static function isPositive(string $a): bool
    {
        return bccomp(self::norm($a), '0', self::SCALE) > 0;
    }

    public static function isNegative(string $a): bool
    {
        return bccomp(self::norm($a), '0', self::SCALE) < 0;
    }

    /** Absolute value. */
    public static function abs(string $a): string
    {
        $a = self::norm($a);
        return bccomp($a, '0', self::SCALE) < 0 ? bcsub('0', $a, self::SCALE) : $a;
    }

    /** Round half-up to given decimal places (currency precision). */
    public static function round(string $value, int $precision = 2): string
    {
        $v = self::norm($value);
        if ($precision < 0) $precision = 0;
        $neg = bccomp($v, '0', self::SCALE) < 0;
        $v = $neg ? bcsub('0', $v, self::SCALE) : $v;
        $rounded = bcadd($v, '0.' . str_repeat('0', $precision) . '5', $precision);
        return $neg ? bcsub('0', $rounded, $precision) : $rounded;
    }

    /** Format a number with thousand separators and the given decimals (display only). */
    public static function format(string $value, int $precision = 2, string $thousands = ',', string $decimal = '.'): string
    {
        $rounded = self::round($value, $precision);
        $neg = bccomp($rounded, '0', $precision) < 0;
        $rounded = $neg ? bcsub('0', $rounded, $precision) : $rounded;
        [$int, $frac] = array_pad(explode('.', $rounded, 2), 2, '');
        $frac = str_pad($frac, $precision, '0');
        if ($precision === 0) $frac = '';
        $int = ltrim($int, '0');
        if ($int === '') $int = '0';
        $intWithSep = preg_replace('/\B(?=(\d{3})+(?!\d))/', $thousands, $int);
        return ($neg ? '-' : '') . $intWithSep . ($frac !== '' ? $decimal . $frac : '');
    }

    /** Convert a value to the base currency using the given rate (base per 1 unit). */
    public static function toBase(string $amount, string $rate): string
    {
        return self::mul($amount, $rate);
    }
}

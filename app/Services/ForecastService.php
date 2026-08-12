<?php
declare(strict_types=1);

/**
 * Inventory forecasting — simple, transparent statistical model.
 *
 * Movement is measured from inventory_movements (the ledger-derived audit log),
 * never from current rates. Targets come from per-currency columns
 * (min/target/max_inventory) with setting-level defaults. The forecast is an
 * estimate based on historical activity, not a guarantee.
 */
final class ForecastService
{
    public const STATUS_NORMAL = 'normal';
    public const STATUS_LOW = 'low';
    public const STATUS_CRITICAL = 'critical';
    public const STATUS_EXCESS = 'excess';

    /** Forecast row for every active non-base currency. */
    public static function dashboard(): array
    {
        $base = SettingService::baseCurrency();
        $currencies = Database::query(
            "SELECT * FROM currencies WHERE is_active = 1" . ($base ? " AND id <> " . (int)$base['id'] : "")
        );
        $rows = [];
        foreach ($currencies as $c) {
            $rows[] = self::row($c);
        }
        return $rows;
    }

    public static function row(array $currency): array
    {
        $id = (int)$currency['id'];
        $qty = (string)InventoryService::costing($id)['qty'];

        $net7 = self::netMovement($id, 7);
        $net30 = self::netMovement($id, 30);
        $avg7 = Money::div($net7, '7', 10);
        $avg30 = Money::div($net30, '30', 10);
        // Blended daily rate: rolling 30-day with a 7-day recency tilt when both exist.
        $daily = Money::div(Money::add(Money::mul($avg30, '0.6'), Money::mul($avg7, '0.4')), '1', 10);

        $min = $currency['min_inventory'] !== null ? (string)$currency['min_inventory']
            : (string)SettingService::get('inventory_min_default', '0');
        $target = $currency['target_inventory'] !== null ? (string)$currency['target_inventory']
            : (string)SettingService::get('inventory_target_default', '0');
        $max = $currency['max_inventory'] !== null ? (string)$currency['max_inventory']
            : (string)SettingService::get('inventory_max_default', '0');

        $status = self::status($qty, $min, $max);

        // Projection over the next 7 days using the blended daily movement.
        $projection = [];
        $daysToMin = null;
        $running = $qty;
        for ($i = 1; $i <= 7; $i++) {
            $running = Money::round(Money::add($running, $daily), 10);
            $projection[$i] = $running;
            if ($daysToMin === null && Money::isPositive($min) && Money::compare($running, $min) < 0) {
                $daysToMin = $i;
            }
        }

        // Replenishment suggestion: raise to target (or minimum) if below it.
        $replenish = Money::zero();
        if (Money::isPositive($target) && Money::compare($qty, $target) < 0) {
            $replenish = Money::round(Money::sub($target, $qty), 10);
        } elseif (Money::isZero($target) && Money::isPositive($min) && Money::compare($qty, $min) < 0) {
            $replenish = Money::round(Money::sub($min, $qty), 10);
        }

        return [
            'currency' => $currency,
            'qty' => $qty,
            'net7' => $net7, 'net30' => $net30,
            'avg7' => $avg7, 'avg30' => $avg30, 'daily' => $daily,
            'min' => $min, 'target' => $target, 'max' => $max,
            'status' => $status,
            'projection' => $projection,
            'days_to_min' => $daysToMin,
            'replenish' => $replenish,
        ];
    }

    public static function status(string $qty, string $min, string $max): string
    {
        if (Money::isPositive($max) && Money::compare($qty, $max) > 0) return self::STATUS_EXCESS;
        if (Money::isPositive($min)) {
            if (Money::compare($qty, Money::div($min, '2', 10)) <= 0) return self::STATUS_CRITICAL;
            if (Money::compare($qty, $min) < 0) return self::STATUS_LOW;
        }
        return self::STATUS_NORMAL;
    }

    /** Save per-currency targets: [currency_id => ['min'=>..,'target'=>..,'max'=>..]]. */
    public static function saveTargets(array $targets): void
    {
        foreach ($targets as $currencyId => $t) {
            $currencyId = (int)$currencyId;
            $exists = Database::fetch("SELECT id FROM currencies WHERE id = ?", [$currencyId]);
            if (!$exists) continue;
            $data = [];
            foreach (['min_inventory', 'target_inventory', 'max_inventory'] as $col) {
                $key = str_replace('_inventory', '', $col);
                $v = trim((string)($t[$key] ?? ''));
                if ($v === '' || !is_numeric($v)) {
                    $data[$col] = null;
                } else {
                    $data[$col] = Money::round($v, 10);
                }
            }
            Database::update('currencies', $data, 'id = ?', [$currencyId]);
        }
        AuditService::log('update_inventory_targets', 'currency', null, null, ['count' => count($targets)]);
    }

    /** Net movement (in − out) over the last N days from inventory_movements. */
    private static function netMovement(int $currencyId, int $days): string
    {
        $v = Database::value(
            "SELECT COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END),0)
             FROM inventory_movements
             WHERE currency_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$currencyId, $days]
        );
        return (string)$v;
    }
}

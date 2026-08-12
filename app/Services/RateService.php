<?php
declare(strict_types=1);

/**
 * Exchange rate management. Rates are never overwritten historically —
 * each change is appended to rate_history; transactions keep their own rate.
 */
final class RateService
{
    /** Current rates for all active currencies (with base excluded). */
    public static function all(): array
    {
        return Database::query(
            "SELECT c.*, r.buy_rate, r.sell_rate, r.mid_rate, r.previous_buy, r.previous_sell,
                    r.reference_rate, r.previous_reference,
                    r.buy_spread_type, r.buy_spread_value, r.sell_spread_type, r.sell_spread_value,
                    r.buy_override, r.sell_override, r.override_persistent,
                    r.source, r.is_manual, r.provider, r.provider_timestamp, r.retrieved_at, r.rate_status,
                    r.updated_at, r.updated_by
             FROM currencies c
             LEFT JOIN exchange_rates r ON r.currency_id = c.id
             WHERE c.is_active = 1 AND c.is_base = 0
             ORDER BY c.code");
    }

    public static function forCurrency(int $currencyId): ?array
    {
        return Database::fetch(
            "SELECT c.*, r.buy_rate, r.sell_rate, r.mid_rate, r.previous_buy, r.previous_sell, r.source, r.is_manual
             FROM currencies c LEFT JOIN exchange_rates r ON r.currency_id = c.id
             WHERE c.id = ?", [$currencyId]);
    }

    public static function get(string $currencyCode): ?array
    {
        return Database::fetch(
            "SELECT c.*, r.buy_rate, r.sell_rate, r.mid_rate
             FROM currencies c LEFT JOIN exchange_rates r ON r.currency_id = c.id
             WHERE c.code = ? AND c.is_active = 1", [$currencyCode]);
    }

    /** Update rates; records previous values + history + audit. */
    public static function update(int $currencyId, string $buy, string $sell, ?string $source = 'manual'): void
    {
        $cur = Database::fetch("SELECT * FROM currencies WHERE id = ?", [$currencyId]);
        if (!$cur) throw new DomainException('Currency not found.');
        if (!is_numeric($buy) || !is_numeric($sell) || Money::compare($buy, '0') <= 0 || Money::compare($sell, '0') <= 0) {
            throw new DomainException('Rates must be positive numbers.');
        }
        $buy = Money::round($buy, 10);
        $sell = Money::round($sell, 10);
        $mid = Money::round(Money::div(Money::add($buy, $sell), '2'), 10);

        $prev = Database::fetch("SELECT * FROM exchange_rates WHERE currency_id = ?", [$currencyId]);

        Database::transaction(function () use ($currencyId, $buy, $sell, $mid, $prev, $source) {
            if ($prev) {
                Database::update('exchange_rates', [
                    'buy_rate' => $buy, 'sell_rate' => $sell, 'mid_rate' => $mid,
                    'previous_buy' => $prev['buy_rate'], 'previous_sell' => $prev['sell_rate'],
                    'source' => $source, 'is_manual' => 1, 'updated_by' => Auth::id(),
                ], 'currency_id = ?', [$currencyId]);
            } else {
                Database::insert('exchange_rates', [
                    'currency_id' => $currencyId, 'buy_rate' => $buy, 'sell_rate' => $sell, 'mid_rate' => $mid,
                    'source' => $source, 'is_manual' => 1, 'updated_by' => Auth::id(),
                ]);
            }
            Database::insert('rate_history', [
                'currency_id' => $currencyId, 'buy_rate' => $buy, 'sell_rate' => $sell, 'mid_rate' => $mid,
                'source' => $source, 'is_manual' => 1, 'changed_by' => Auth::id(),
            ]);
        });

        $code = $cur['code'];
        AuditService::log('update_rates', 'currency', $currencyId,
            $prev ? ['buy_rate' => $prev['buy_rate'], 'sell_rate' => $prev['sell_rate']] : null,
            ['buy_rate' => $buy, 'sell_rate' => $sell],
            "Changed $code rates" . ($prev && $prev['buy_rate'] !== $buy ? " (buy " . Money::format((string)$prev['buy_rate'], 4) . " → " . Money::format($buy, 4) . ")" : ''));
    }

    public static function history(int $currencyId, int $limit = 100): array
    {
        return Database::query(
            "SELECT rh.*, u.username FROM rate_history rh
             LEFT JOIN users u ON u.id = rh.changed_by
             WHERE rh.currency_id = ? ORDER BY rh.changed_at DESC LIMIT " . (int)$limit,
            [$currencyId]);
    }

    /**
     * Price-board data. Internal mode exposes reference/source/update info;
     * public mode exposes only currency + Buy/Sell (never spreads or margins).
     */
    public static function board(bool $public = false): array
    {
        $rows = self::all();
        $out = [];
        $lastUpdate = null;
        foreach ($rows as $r) {
            if ($public) {
                $out[] = [
                    'code' => $r['code'],
                    'name' => $r['name'],
                    'localized_name' => $r['localized_name'],
                    'symbol' => $r['symbol'],
                    'buy_rate' => (string)$r['buy_rate'],
                    'sell_rate' => (string)$r['sell_rate'],
                    'rate_precision' => (int)$r['rate_precision'],
                ];
            } else {
                $out[] = [
                    'code' => $r['code'],
                    'name' => $r['name'],
                    'localized_name' => $r['localized_name'],
                    'symbol' => $r['symbol'],
                    'buy_rate' => (string)$r['buy_rate'],
                    'sell_rate' => (string)$r['sell_rate'],
                    'reference_rate' => $r['reference_rate'] !== null ? (string)$r['reference_rate'] : null,
                    'rate_precision' => (int)$r['rate_precision'],
                    'source' => $r['source'],
                    'rate_status' => $r['rate_status'],
                    'updated_at' => $r['updated_at'],
                ];
            }
            if ($r['updated_at'] && (!$lastUpdate || strtotime($r['updated_at']) > strtotime($lastUpdate))) {
                $lastUpdate = $r['updated_at'];
            }
        }
        return ['rates' => $out, 'updated_at' => $lastUpdate, 'public' => $public];
    }

    /** Rate history for all currencies, filtered. */
    public static function historyAll(?int $currencyId = null, string $from = '', string $to = '', int $limit = 200): array
    {
        $sql = "SELECT rh.*, c.code, c.name, c.localized_name, c.symbol, u.username
                FROM rate_history rh JOIN currencies c ON c.id = rh.currency_id
                LEFT JOIN users u ON u.id = rh.changed_by";
        $params = [];
        $where = [];
        if ($currencyId) { $where[] = 'rh.currency_id = ?'; $params[] = $currencyId; }
        if ($from) { $where[] = 'rh.changed_at >= ?'; $params[] = $from . ' 00:00:00'; }
        if ($to) { $where[] = 'rh.changed_at <= ?'; $params[] = $to . ' 23:59:59'; }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY rh.changed_at DESC LIMIT ' . (int)$limit;
        return Database::query($sql, $params);
    }
}

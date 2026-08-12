<?php
declare(strict_types=1);

/**
 * Automatic online rate synchronization.
 *
 * Architecture (per feature spec):
 *
 *     EXTERNAL PROVIDER  →  Reference Market Rate  →  Validation + Cache
 *        →  Rate Calculation Engine (spreads / manual overrides)
 *        →  Buy / Sell Rates  →  Customer Transaction (immutable snapshot)
 *
 * The external rate is a REFERENCE input only — actual Buy/Sell rates are
 * business-controlled values (configured spreads or manual overrides).
 *
 * Sync triggers: authenticated login (fallback), manual "Sync Now" button,
 * and a scheduled cron job (database/sync_rates.php). All triggers share this
 * service; a MySQL advisory lock prevents duplicate concurrent syncs.
 */
final class RateSyncService
{
    private const LOCK_NAME = 'exchange_rate_sync';

    public static function availableProviders(): array
    {
        return [
            ['id' => 'frankfurter', 'name' => 'Frankfurter / ECB', 'desc' => 'ECB reference rates, no API key required'],
        ];
    }

    // ------------------------------------------------------------ config

    /** Setting value wins over config default (config/env = default, DB = override). */
    public static function cfg(string $key, $default = null)
    {
        $v = SettingService::get('rate_sync_' . $key);
        return ($v !== null && $v !== '') ? $v : cfg('rate_sync.' . $key, $default);
    }

    public static function enabled(): bool
    {
        return filter_var(self::cfg('enabled', true), FILTER_VALIDATE_BOOL);
    }

    public static function ttl(): int
    {
        return max(60, (int)self::cfg('cache_ttl', 3600));
    }

    public static function staleThreshold(): int
    {
        return max(self::ttl() * 2, (int)self::cfg('stale_threshold', 86400));
    }

    public static function providerId(): string
    {
        return (string)self::cfg('provider', 'frankfurter');
    }

    public static function providerBase(): string
    {
        return strtoupper((string)self::cfg('base_currency', 'EUR'));
    }

    public static function apiTimeout(): int
    {
        return max(1, (int)self::cfg('api_timeout', 8));
    }

    public static function maxChangePercent(): string
    {
        $v = (string)self::cfg('max_change_percent', '10');
        return Money::isPositive($v) ? $v : '10';
    }

    public static function provider(?string $id = null): ExchangeRateProvider
    {
        $id ??= self::providerId();
        return match ($id) {
            'frankfurter' => new FrankfurterProvider(self::apiTimeout()),
            default => throw new DomainException('Unknown rate provider: ' . $id),
        };
    }

    // ------------------------------------------------------------ state

    public static function lastSyncLog(?string $status = null): ?array
    {
        $sql = 'SELECT * FROM rate_sync_logs';
        $params = [];
        if ($status) {
            $sql .= ' WHERE status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        return Database::fetch($sql, $params);
    }

    public static function recentLogs(int $limit = 10): array
    {
        return Database::query('SELECT * FROM rate_sync_logs ORDER BY id DESC LIMIT ' . (int)$limit);
    }

    /** True when the last successful sync is still within the cache TTL. */
    public static function isFresh(): bool
    {
        $last = self::lastSyncLog('success');
        if (!$last) return false;
        $at = $last['completed_at'] ?? $last['started_at'];
        return $at !== null && (time() - strtotime((string)$at)) < self::ttl();
    }

    /**
     * Sync status for the UI. state ∈ manual | online | cached | stale | offline.
     */
    public static function status(): array
    {
        RateSyncSchema::ensure();

        $lastAny = self::lastSyncLog();
        $lastSuccess = self::lastSyncLog('success');
        $now = time();
        $hasProviderData = (int)Database::value('SELECT COUNT(*) FROM exchange_rate_history') > 0;

        $out = [
            'state' => 'manual',
            'enabled' => self::enabled(),
            'provider' => self::providerId(),
            'provider_name' => '',
            'last_sync_at' => null,
            'last_success_at' => null,
            'last_error' => null,
            'last_sync_age' => null,
            'last_success_age' => null,
            'provider_timestamp' => null,
        ];
        try {
            $out['provider_name'] = self::provider()->name();
        } catch (Throwable) {
            $out['provider_name'] = $out['provider'];
        }

        if ($lastAny) {
            $out['last_sync_at'] = $lastAny['completed_at'] ?? $lastAny['started_at'];
            $out['last_error'] = $lastAny['status'] === 'failed' ? ($lastAny['error_message'] ?? '') : null;
            if ($out['last_sync_at']) $out['last_sync_age'] = $now - strtotime((string)$out['last_sync_at']);
        }
        if ($lastSuccess) {
            $out['last_success_at'] = $lastSuccess['completed_at'] ?? $lastSuccess['started_at'];
            if ($out['last_success_at']) $out['last_success_age'] = $now - strtotime((string)$out['last_success_at']);
        }
        $pt = Database::fetch('SELECT MAX(provider_timestamp) AS pt, MAX(retrieved_at) AS rt FROM exchange_rate_history');
        if ($pt) {
            $out['provider_timestamp'] = $pt['pt'] ?? null;
        }

        if (!$out['enabled'] || !$hasProviderData) {
            $out['state'] = 'manual';
        } elseif ($out['last_success_at'] === null) {
            $out['state'] = 'offline';
        } else {
            $age = (int)$out['last_success_age'];
            $lastAttemptFailed = $lastAny && $lastAny['status'] === 'failed'
                && ($lastAny['completed_at'] ?? $lastAny['started_at']) >= ($lastSuccess['completed_at'] ?? $lastSuccess['started_at']);
            if ($age < self::ttl()) {
                $out['state'] = 'online';
            } elseif ($age < self::staleThreshold()) {
                $out['state'] = $lastAttemptFailed ? 'offline' : 'cached';
            } else {
                $out['state'] = 'stale';
            }
        }
        return $out;
    }

    // ------------------------------------------------------------ triggers

    /**
     * Background trigger (login): sync only if enabled AND cache expired.
     * Never throws — a provider outage must not block login.
     */
    public static function maybeSync(bool $force = false): array
    {
        try {
            RateSyncSchema::ensure();
            if (!self::enabled() && !$force) return ['status' => 'disabled'];
            if (!$force && self::isFresh()) return ['status' => 'cached'];
            return self::sync($force, 'login');
        } catch (Throwable $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Run a full synchronization. Never throws for provider failures —
     * returns a result array so callers can flash/display it.
     *
     * @param string $triggeredBy login | manual | cron
     * @param ExchangeRateProvider|null $provider injected for tests; otherwise the configured provider
     */
    public static function sync(bool $force = false, string $triggeredBy = 'manual', ?ExchangeRateProvider $provider = null): array
    {
        $triggeredBy = in_array($triggeredBy, ['login', 'manual', 'cron'], true) ? $triggeredBy : 'manual';

        try {
            RateSyncSchema::ensure();
        } catch (Throwable $e) {
            return ['status' => 'failed', 'message' => 'Schema error: ' . $e->getMessage(), 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        // One synchronization at a time across all users/processes.
        if (!Database::namedLock(self::LOCK_NAME, 0)) {
            return ['status' => 'in_progress', 'message' => 'Another synchronization is already running.', 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $logId = null;
        try {
            if (!$force && self::isFresh()) {
                return ['status' => 'cached', 'message' => 'Rates are already up to date.', 'updated' => 0, 'skipped' => 0, 'failed' => 0];
            }

            $provider = $provider ?? self::provider();
            $started = date('Y-m-d H:i:s');
            $logId = Database::insert('rate_sync_logs', [
                'provider' => $provider->identifier(),
                'status' => 'skipped',
                'triggered_by' => $triggeredBy,
                'started_at' => $started,
            ]);

            $response = self::fetchWithRetry($provider, $triggeredBy);
            $result = self::apply($provider, $response, (int)$logId);

            Database::update('rate_sync_logs', [
                'status' => $result['failed'] > 0 ? 'partial' : 'success',
                'completed_at' => date('Y-m-d H:i:s'),
                'currencies_updated' => $result['updated'],
                'currencies_skipped' => $result['skipped'],
                'currencies_failed' => $result['failed'],
                'error_message' => $result['error'] ? implode('; ', array_slice($result['error'], 0, 3)) : null,
            ], 'id = ?', [$logId]);

            AuditService::log('rate_sync', 'rates', null, null, [
                'provider' => $provider->identifier(),
                'status' => $result['failed'] > 0 ? 'partial' : 'success',
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
                'triggered_by' => $triggeredBy,
            ], 'Automatic rate synchronization (' . $triggeredBy . ')', null);

            return $result;
        } catch (Throwable $e) {
            if ($logId !== null) {
                Database::update('rate_sync_logs', [
                    'status' => 'failed',
                    'completed_at' => date('Y-m-d H:i:s'),
                    'error_message' => mb_substr($e->getMessage(), 0, 2000),
                ], 'id = ?', [$logId]);
            }
            AuditService::log('rate_sync', 'rates', null, null, null,
                'Rate synchronization failed: ' . mb_substr($e->getMessage(), 0, 500), null);
            return ['status' => 'failed', 'message' => $e->getMessage(), 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        } finally {
            Database::namedUnlock(self::LOCK_NAME);
        }
    }

    /**
     * Fetch with retry + backoff. Login-triggered syncs use a single attempt
     * so login is never delayed by retries; manual/cron use the configured
     * retry policy (default 2s / 5s / 15s).
     */
    private static function fetchWithRetry(ExchangeRateProvider $provider, string $triggeredBy): ProviderRateResponse
    {
        $attempts = $triggeredBy === 'login' ? 1 : max(1, (int)self::cfg('retry_attempts', 3));
        $delays = self::cfg('retry_delays', [2, 5, 15]);
        $delays = is_array($delays) ? array_values($delays) : [2];

        $last = null;
        for ($i = 1; $i <= $attempts; $i++) {
            try {
                return $provider->latestRates(self::providerBase());
            } catch (Throwable $e) {
                $last = $e;
                if ($i < $attempts) {
                    sleep((int)($delays[$i - 1] ?? 2));
                }
            }
        }
        throw $last ?? new RuntimeException('Rate provider request failed.');
    }

    // ------------------------------------------------------------ apply

    private static function validate(ProviderRateResponse $res): void
    {
        if (!preg_match('/^[A-Z]{3}$/', $res->base)) {
            throw new DomainException('Provider returned an invalid base currency.');
        }
        if ($res->date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $res->date)) {
            throw new DomainException('Provider returned an invalid rate date.');
        }
        if (count($res->rates) === 0) {
            throw new DomainException('Provider returned no rates.');
        }
        foreach ($res->rates as $code => $rate) {
            if (!preg_match('/^[A-Z]{3}$/', (string)$code)) {
                throw new DomainException('Provider returned an unknown currency code.');
            }
            if (!is_numeric($rate) || !Money::isPositive((string)$rate)) {
                throw new DomainException('Provider returned an invalid (zero/negative/non-numeric) rate for ' . $code . '.');
            }
        }
    }

    /**
     * Apply a validated provider response to all active currencies.
     * Never deletes or zeroes existing rates — unsupported currencies keep
     * their manual rates untouched.
     */
    private static function apply(ExchangeRateProvider $provider, ProviderRateResponse $res, int $logId): array
    {
        self::validate($res);

        $base = SettingService::baseCurrency();
        $baseCode = (string)$base['code'];
        $rates = $res->rates;

        // Cross-rate: we need units of app-base per 1 unit of currency.
        // Provider rates r[X] = X per 1 provider-base, so basePer(cur) = r[base] / r[cur].
        $baseRate = $res->base === $baseCode ? '1' : ($rates[$baseCode] ?? null);
        if ($baseRate === null) {
            throw new DomainException('The provider does not supply rates for the base currency (' . $baseCode . ').');
        }
        $baseRate = Money::round((string)$baseRate, 10);

        $providerTs = $res->date !== null ? $res->date . ' 00:00:00' : null;
        $retrieved = date('Y-m-d H:i:s');
        $maxChange = Money::round(self::maxChangePercent(), 10);

        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        $currencies = Database::query('SELECT * FROM currencies WHERE is_active = 1 AND is_base = 0 ORDER BY code');
        $providerBase = $res->base;

        Database::transaction(function () use ($currencies, $rates, $baseRate, $providerBase, $provider, $providerTs, $retrieved, $maxChange, $logId, $base, &$updated, &$skipped, &$failed, &$errors) {
            foreach ($currencies as $c) {
                $code = (string)$c['code'];
                // The provider's own base currency is implicit (rate 1.0): Frankfurter
                // omits it from the rates map, so treat it as present.
                $rateValue = $code === $providerBase ? '1' : ($rates[$code] ?? null);
                if ($rateValue === null) {
                    $skipped++; // unsupported by provider → stays manual
                    continue;
                }
                $reference = Money::round(Money::div($baseRate, $rateValue, 10), 10);

                $row = Database::fetch('SELECT * FROM exchange_rates WHERE currency_id = ?', [(int)$c['id']]);
                $prevRef = $row ? $row['reference_rate'] : null;

                // Rate-change protection: reject jumps beyond the configured max.
                if ($prevRef !== null && Money::isPositive((string)$prevRef) && Money::isPositive($reference)) {
                    $pct = Money::round(Money::mul(
                        Money::div(Money::abs(Money::sub($reference, (string)$prevRef)), (string)$prevRef, 10),
                        '100', 10
                    ), 2);
                    if (Money::compare($pct, $maxChange) > 0) {
                        $failed++;
                        $errors[] = $code . ': change +' . Money::format($pct, 1) . '% exceeds the allowed ' . Money::format($maxChange, 1) . '%';
                        continue; // keep previous rates; requires manual action
                    }
                }

                // Effective Buy/Sell: manual override wins, otherwise reference ± spread.
                $pinned = $row && (int)$row['override_persistent'] === 1;
                $data = [
                    'reference_rate' => $reference,
                    'previous_reference' => $prevRef,
                    'provider' => $provider->identifier(),
                    'provider_timestamp' => $providerTs,
                    'retrieved_at' => $retrieved,
                    'rate_status' => 'online',
                    'source' => 'api',
                    'is_manual' => 0,
                    'updated_by' => Auth::id() ?: null,
                ];

                if ($row) {
                    if ($pinned && $row['buy_override'] !== null && $row['sell_override'] !== null) {
                        // Persistent override — reference only; Buy/Sell stay owner-controlled.
                        $data['rate_status'] = $row['rate_status'] === 'manual' ? 'manual' : 'cached';
                        Database::update('exchange_rates', $data, 'currency_id = ?', [(int)$c['id']]);
                        $skipped++;
                        continue;
                    }

                    [$buy, $sell] = self::effectiveRates($c, $row, $reference);
                    $mid = Money::round(Money::div(Money::add($buy, $sell), '2', 10), 10);
                    $prevBuy = $row['buy_rate'];
                    $prevSell = $row['sell_rate'];
                    $data['buy_rate'] = $buy;
                    $data['sell_rate'] = $sell;
                    $data['mid_rate'] = $mid;
                    $data['previous_buy'] = $prevBuy;
                    $data['previous_sell'] = $prevSell;
                    $data['buy_override'] = null;
                    $data['sell_override'] = null;
                    Database::update('exchange_rates', $data, 'currency_id = ?', [(int)$c['id']]);

                    if ($prevBuy !== $buy || $prevSell !== $sell) {
                        Database::insert('rate_history', [
                            'currency_id' => (int)$c['id'],
                            'buy_rate' => $buy, 'sell_rate' => $sell, 'mid_rate' => $mid,
                            'source' => 'api', 'is_manual' => 0, 'changed_by' => null,
                        ]);
                        AuditService::log('rate_sync_update', 'currency', (int)$c['id'],
                            ['buy_rate' => $prevBuy, 'sell_rate' => $prevSell, 'reference_rate' => $prevRef],
                            ['buy_rate' => $buy, 'sell_rate' => $sell, 'reference_rate' => $reference],
                            'Automatic rate synchronization', null);
                    }
                } else {
                    [$buy, $sell] = self::effectiveRates($c, null, $reference);
                    $data['currency_id'] = (int)$c['id'];
                    $data['buy_rate'] = $buy;
                    $data['sell_rate'] = $sell;
                    $data['mid_rate'] = Money::round(Money::div(Money::add($buy, $sell), '2', 10), 10);
                    $data['previous_buy'] = null;
                    $data['previous_sell'] = null;
                    $data['buy_override'] = null;
                    $data['sell_override'] = null;
                    Database::insert('exchange_rates', $data);
                }

                Database::insert('exchange_rate_history', [
                    'currency_id' => (int)$c['id'],
                    'base_currency_id' => (int)$base['id'],
                    'reference_rate' => $reference,
                    'provider' => $provider->identifier(),
                    'provider_timestamp' => $providerTs,
                    'retrieved_at' => $retrieved,
                    'sync_id' => $logId,
                ]);
                $updated++;
            }
        });

        return [
            'status' => $failed > 0 ? 'partial' : 'success',
            'message' => 'Rates synchronized.',
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'error' => $errors,
        ];
    }

    /** Buy/Sell from reference + spread config (per-currency, falling back to global). */
    private static function effectiveRates(array $currency, ?array $row, string $reference): array
    {
        $buyType = $row['buy_spread_type'] ?? null;
        $buyValue = $row['buy_spread_value'] ?? null;
        $sellType = $row['sell_spread_type'] ?? null;
        $sellValue = $row['sell_spread_value'] ?? null;

        if ($buyType === null || $buyValue === null) {
            $buyType = (string)self::cfg('buy_spread_type', 'percent');
            $buyValue = (string)self::cfg('buy_spread_value', '-0.5');
        }
        if ($sellType === null || $sellValue === null) {
            $sellType = (string)self::cfg('sell_spread_type', 'percent');
            $sellValue = (string)self::cfg('sell_spread_value', '0.5');
        }

        $buy = self::applySpread($reference, (string)$buyType, (string)$buyValue);
        $sell = self::applySpread($reference, (string)$sellType, (string)$sellValue);
        return [$buy, $sell];
    }

    /** value is signed: buy usually negative, sell positive. percent = percent points. */
    private static function applySpread(string $reference, string $type, string $value): string
    {
        $v = Money::norm($value);
        if ($type === 'percent') {
            $adj = Money::round(Money::div(Money::mul($reference, $v), '100', 10), 10);
        } else {
            $adj = Money::round($v, 10);
        }
        $rate = Money::round(Money::add($reference, $adj), 10);
        return Money::isPositive($rate) ? $rate : $reference; // never produce a non-positive rate
    }

    // ------------------------------------------------------------ settings

    public static function settings(): array
    {
        return [
            'enabled' => self::enabled(),
            'provider' => self::providerId(),
            'base_currency' => self::providerBase(),
            'cache_ttl' => self::ttl(),
            'max_change_percent' => self::maxChangePercent(),
            'buy_spread_type' => (string)self::cfg('buy_spread_type', 'percent'),
            'buy_spread_value' => (string)self::cfg('buy_spread_value', '-0.5'),
            'sell_spread_type' => (string)self::cfg('sell_spread_type', 'percent'),
            'sell_spread_value' => (string)self::cfg('sell_spread_value', '0.5'),
        ];
    }

    /** Persist global sync settings (Settings card on the Rates page). */
    public static function saveSettings(array $d): void
    {
        $before = SettingService::all();
        $updates = [];

        $updates['rate_sync_enabled'] = !empty($d['enabled']) ? '1' : '0';

        $provider = (string)($d['provider'] ?? 'frankfurter');
        if (!in_array($provider, array_column(self::availableProviders(), 'id'), true)) {
            throw new DomainException('Unknown rate provider.');
        }
        $updates['rate_sync_provider'] = $provider;

        $baseCur = strtoupper(trim((string)($d['base_currency'] ?? 'EUR')));
        if (!preg_match('/^[A-Z]{3}$/', $baseCur)) {
            throw new DomainException('Base currency must be a 3-letter ISO code.');
        }
        $updates['rate_sync_base_currency'] = $baseCur;

        $ttl = (int)($d['cache_ttl'] ?? 3600);
        if (!in_array($ttl, [900, 1800, 3600, 21600, 43200, 86400], true)) {
            throw new DomainException('Invalid sync interval.');
        }
        $updates['rate_sync_cache_ttl'] = (string)$ttl;

        $maxChange = (string)($d['max_change_percent'] ?? '10');
        if (!is_numeric($maxChange) || Money::compare($maxChange, '0') <= 0) {
            throw new DomainException('Maximum automatic change must be a positive number.');
        }
        $updates['rate_sync_max_change_percent'] = $maxChange;

        foreach (['buy', 'sell'] as $side) {
            $type = (string)($d[$side . '_spread_type'] ?? 'percent');
            if (!in_array($type, ['fixed', 'percent'], true)) {
                throw new DomainException('Spread type must be fixed or percent.');
            }
            $value = (string)($d[$side . '_spread_value'] ?? '0');
            if (!is_numeric($value)) {
                throw new DomainException('Spread value must be a number.');
            }
            $updates['rate_sync_' . $side . '_spread_type'] = $type;
            $updates['rate_sync_' . $side . '_spread_value'] = $value;
        }

        foreach ($updates as $k => $v) {
            SettingService::set($k, $v);
        }
        AuditService::log('rate_sync_settings', 'settings', null, $before, $updates,
            'Rate synchronization settings changed');
    }

    /**
     * Save per-currency spread/override config from the Rates page form row.
     * Effective Buy/Sell values are handled separately by RateService::update().
     */
    public static function saveCurrencyConfig(int $currencyId, array $d): void
    {
        $data = [];

        foreach (['buy', 'sell'] as $side) {
            if (array_key_exists($side . '_spread_type', $d)) {
                $t = (string)$d[$side . '_spread_type'];
                $data[$side . '_spread_type'] = in_array($t, ['fixed', 'percent'], true) ? $t : 'percent';
            }
            if (array_key_exists($side . '_spread_value', $d)) {
                $v = trim((string)$d[$side . '_spread_value']);
                $data[$side . '_spread_value'] = ($v === '' || !is_numeric($v)) ? '0' : $v;
            }
        }

        if (array_key_exists('override_persistent', $d)) {
            $pinned = !empty($d['override_persistent']);
            $data['override_persistent'] = $pinned ? 1 : 0;
            if ($pinned) {
                // Persistent override values come from the effective Buy/Sell inputs
                // (RateController::update() copies them in before calling this).
                foreach (['buy', 'sell'] as $side) {
                    if (array_key_exists($side . '_override', $d)) {
                        $v = trim((string)$d[$side . '_override']);
                        $data[$side . '_override'] = ($v === '' || !is_numeric($v)) ? null : $v;
                    }
                }
            } else {
                // Unpinned → clear stored overrides so the next sync recalculates.
                $data['buy_override'] = null;
                $data['sell_override'] = null;
            }
        }

        if (!$data) return;
        Database::update('exchange_rates', $data, 'currency_id = ?', [$currencyId]);
        AuditService::log('rate_config', 'currency', $currencyId, null, $data,
            'Rate spread / override configuration changed');
    }

    // ------------------------------------------------------------ dashboard

    /** Latest reference rates for the dashboard widget (clearly labeled as reference). */
    public static function dashboardRates(int $limit = 6): array
    {
        return Database::query(
            "SELECT c.code, c.symbol, c.rate_precision, c.name, c.localized_name,
                    r.reference_rate, r.previous_reference, r.rate_status, r.retrieved_at
             FROM currencies c
             JOIN exchange_rates r ON r.currency_id = c.id
             WHERE c.is_active = 1 AND c.is_base = 0
               AND r.reference_rate IS NOT NULL AND r.reference_rate > 0
             ORDER BY c.code ASC LIMIT " . (int)$limit
        );
    }
}

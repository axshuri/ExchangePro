<?php
/** @var array $rates @var ?array $base @var array $status @var array $settings @var array $logs @var array $providers */
$st = $status;
$statePill = match ($st['state']) {
    'online' => ['pill-green', '● ' . t('rates.status.online')],
    'cached' => ['pill-blue', '● ' . t('rates.status.cached')],
    'stale' => ['pill-amber', '⚠ ' . t('rates.status.stale')],
    'offline' => ['pill-red', '⚠ ' . t('rates.status.offline')],
    default => ['pill-gray', t('rates.status.manual')],
};
$intervals = [900 => t('rates.interval.15m'), 1800 => t('rates.interval.30m'), 3600 => t('rates.interval.1h'),
    21600 => t('rates.interval.6h'), 43200 => t('rates.interval.12h'), 86400 => t('rates.interval.24h')];
$bases = ['EUR', 'USD', 'GBP', 'CHF', 'JPY', 'CNY', 'CAD', 'AUD'];
$providerDesc = '';
foreach ($providers as $p) {
    if ($p['id'] === $settings['provider']) { $providerDesc = $p['desc'] ?? ''; break; }
}
?>
<div class="page-head">
    <h1><?= t('rates.title') ?></h1>
    <div class="page-actions">
        <form method="post" action="/rates/sync" id="syncRatesForm">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-primary" id="syncRatesBtn" data-sync-rates
                    data-syncing="<?= e(t('rates.syncing')) ?>" data-failed="<?= e(t('rates.sync_failed')) ?>">
                <svg class="icon"><use href="/assets/img/icons.svg#refresh"/></svg>
                <span><?= t('rates.sync_now') ?></span>
            </button>
        </form>
        <a href="/rates/history" class="btn btn-ghost btn-sm"><?= t('rates.history') ?></a>
    </div>
</div>

<!-- Sync status + provider info -->
<div class="card mb-2" style="max-width:860px">
    <div class="card-header">
        <h2><?= t('rates.sync_status') ?></h2>
        <div class="card-actions">
            <span class="pill <?= $statePill[0] ?>"><?= $statePill[1] ?></span>
        </div>
    </div>
    <div class="card-body">
        <div class="detail-list">
            <div class="detail-item">
                <dt><?= t('rates.source') ?></dt>
                <dd><?= e($st['provider_name'] ?: $st['provider']) ?></dd>
            </div>
            <div class="detail-item">
                <dt><?= t('rates.last_synced') ?></dt>
                <dd><?= $st['last_sync_at'] ? e(tz($st['last_sync_at'])) : '—' ?></dd>
            </div>
            <div class="detail-item">
                <dt><?= t('rates.last_successful') ?></dt>
                <dd><?= $st['last_success_at'] ? e(tz($st['last_success_at'])) : '—' ?></dd>
            </div>
            <div class="detail-item">
                <dt><?= t('rates.last_provider_update') ?></dt>
                <dd><?= $st['provider_timestamp'] ? e(tz($st['provider_timestamp'], 'Y-m-d')) : '—' ?></dd>
            </div>
        </div>

        <?php if (in_array($st['state'], ['stale', 'offline'], true)): ?>
            <div class="alert-item alert-warning mt-2">
                <svg class="icon"><use href="/assets/img/icons.svg#alert-triangle"/></svg>
                <div>
                    <strong><?= t('rates.stale_warning') ?></strong>
                    <div>
                        <?= $st['state'] === 'offline' ? t('rates.provider_unavailable') : '' ?>
                        <?= t('rates.last_successful') ?>: <?= $st['last_success_at'] ? e(tz($st['last_success_at'])) : '—' ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($st['last_error'])): ?>
            <div class="alert-item alert-warning mt-2">
                <svg class="icon"><use href="/assets/img/icons.svg#info"/></svg>
                <div><strong><?= t('rates.sync_failed') ?></strong> — <?= e($st['last_error']) ?></div>
            </div>
        <?php endif; ?>

        <p class="form-hint mt-2" style="max-width:70ch">
            <?= t('rates.business_warning') ?>
        </p>
    </div>
</div>

<!-- Automatic synchronization settings -->
<div class="card mb-2" style="max-width:860px">
    <div class="card-header"><h2><?= t('rates.settings_title') ?></h2></div>
    <div class="card-body">
        <form method="post" action="/rates/settings">
            <?= Csrf::field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="enabled" value="1" <?= $settings['enabled'] ? 'checked' : '' ?>>
                        <?= t('rates.auto_sync') ?>
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label" for="rs_provider"><?= t('rates.provider_label') ?></label>
                    <select class="form-select" id="rs_provider" name="provider">
                        <?php foreach ($providers as $p): ?>
                            <option value="<?= e($p['id']) ?>" <?= $settings['provider'] === $p['id'] ? 'selected' : '' ?>
                                    data-desc="<?= e($p['desc'] ?? '') ?>">
                                <?= e($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint" id="rs_provider_desc" style="margin:0"><?= e($providerDesc) ?></p>
                </div>
                <!-- XE.com credentials — only shown while the XE provider is selected -->
                <div class="form-group xe-fields" <?= $settings['provider'] === 'xe' ? '' : 'hidden' ?>>
                    <label class="form-label" for="rs_xe_account"><?= t('rates.xe_account_id') ?></label>
                    <input class="form-control" type="text" id="rs_xe_account" name="xe_account_id"
                           autocomplete="off" value="<?= e((string)$settings['xe_account_id']) ?>">
                </div>
                <div class="form-group xe-fields" <?= $settings['provider'] === 'xe' ? '' : 'hidden' ?>>
                    <label class="form-label" for="rs_xe_key"><?= t('rates.xe_api_key') ?></label>
                    <input class="form-control" type="password" id="rs_xe_key" name="xe_api_key"
                           autocomplete="new-password"
                           placeholder="<?= $settings['xe_api_key_set'] ? t('rates.xe_key_keep') : '' ?>">
                </div>
                <p class="form-hint xe-fields" style="grid-column:1/-1" <?= $settings['provider'] === 'xe' ? '' : 'hidden' ?>>
                    <?= t('rates.xe_credentials_hint') ?>
                </p>
                <div class="form-group">
                    <label class="form-label" for="rs_base"><?= t('rates.base_currency') ?></label>
                    <select class="form-select" id="rs_base" name="base_currency">
                        <?php foreach ($bases as $b): ?>
                            <option value="<?= e($b) ?>" <?= $settings['base_currency'] === $b ? 'selected' : '' ?>><?= e($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="rs_ttl"><?= t('rates.sync_interval') ?></label>
                    <select class="form-select" id="rs_ttl" name="cache_ttl">
                        <?php foreach ($intervals as $v => $label): ?>
                            <option value="<?= (int)$v ?>" <?= (int)$settings['cache_ttl'] === (int)$v ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="rs_max"><?= t('rates.max_change') ?></label>
                    <input class="form-control" type="number" step="any" min="0" id="rs_max" name="max_change_percent"
                           value="<?= e((string)$settings['max_change_percent']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="rs_buy_type"><?= t('rates.buy_spread') ?></label>
                    <div class="spread-ctl">
                        <select class="form-select" id="rs_buy_type" name="buy_spread_type">
                            <option value="fixed" <?= $settings['buy_spread_type'] === 'fixed' ? 'selected' : '' ?>><?= t('rates.fixed') ?></option>
                            <option value="percent" <?= $settings['buy_spread_type'] === 'percent' ? 'selected' : '' ?>><?= t('rates.percent') ?></option>
                        </select>
                        <input class="form-control" type="number" step="any" name="buy_spread_value" value="<?= e((string)$settings['buy_spread_value']) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="rs_sell_type"><?= t('rates.sell_spread') ?></label>
                    <div class="spread-ctl">
                        <select class="form-select" id="rs_sell_type" name="sell_spread_type">
                            <option value="fixed" <?= $settings['sell_spread_type'] === 'fixed' ? 'selected' : '' ?>><?= t('rates.fixed') ?></option>
                            <option value="percent" <?= $settings['sell_spread_type'] === 'percent' ? 'selected' : '' ?>><?= t('rates.percent') ?></option>
                        </select>
                        <input class="form-control" type="number" step="any" name="sell_spread_value" value="<?= e((string)$settings['sell_spread_value']) ?>">
                    </div>
                </div>
            </div>
            <div class="form-hint mt-2"><?= t('rates.default_spreads') ?></div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= t('app.save') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Rate table: effective Buy/Sell + per-currency spread & override config -->
<div class="card">
    <div class="card-header">
        <h2><?= t('rates.title') ?></h2>
        <div class="card-actions">
            <span class="text-muted" style="font-size:.74rem"><?= t('rates.reference_note') ?></span>
        </div>
    </div>
    <form method="post" action="/rates/update">
        <?= Csrf::field() ?>
        <div class="table-wrap">
            <table class="table rates-table">
                <thead>
                    <tr>
                        <th><?= t('app.currency') ?></th>
                        <th class="num"><?= t('rates.reference') ?> (<?= e($base['code']) ?>)</th>
                        <th class="num"><?= t('rates.buy') ?> (<?= e($base['code']) ?>)</th>
                        <th class="num"><?= t('rates.sell') ?> (<?= e($base['code']) ?>)</th>
                        <th><?= t('rates.buy_spread') ?></th>
                        <th><?= t('rates.sell_spread') ?></th>
                        <th><?= t('rates.pin') ?></th>
                        <th><?= t('rates.last_updated') ?></th>
                        <th><?= t('rates.source') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rates as $r):
                        $rp = (int)$r['rate_precision'];
                        $buyType = $r['buy_spread_type'] ?: $settings['buy_spread_type'];
                        $buyVal = $r['buy_spread_value'] ?? $settings['buy_spread_value'];
                        $sellType = $r['sell_spread_type'] ?: $settings['sell_spread_type'];
                        $sellVal = $r['sell_spread_value'] ?? $settings['sell_spread_value'];
                        $pinned = (int)($r['override_persistent'] ?? 0) === 1;
                        $srcPill = $pinned ? ['pill-gray', t('rates.source.manual')]
                            : match ($r['rate_status'] ?? 'manual') {
                                'online' => ['pill-green', t('rates.source.online')],
                                'cached' => ['pill-blue', t('rates.source.cached')],
                                'stale' => ['pill-amber', t('rates.source.stale')],
                                default => ((int)($r['is_manual'] ?? 1) === 1 ? ['pill-gray', t('rates.source.manual')] : ['pill-blue', t('rates.source.calculated')]),
                            };
                        $chg = null;
                        if ($r['reference_rate'] !== null && $r['previous_reference'] !== null
                            && Money::isPositive((string)$r['previous_reference'])) {
                            $chg = Money::round(Money::mul(Money::div(
                                Money::sub((string)$r['reference_rate'], (string)$r['previous_reference']),
                                (string)$r['previous_reference'], 10), '100', 10), 2);
                        }
                    ?>
                        <tr>
                            <td>
                                <strong><?= e($r['code']) ?></strong>
                                <span class="text-muted"> <?= e(currencyName($r)) ?></span>
                            </td>
                            <td class="num">
                                <?php if ($r['reference_rate'] !== null): ?>
                                    <span class="mono"><?= Money::format((string)$r['reference_rate'], $rp) ?></span>
                                    <?php if ($chg !== null): ?>
                                        <span class="rate-chg <?= Money::compare($chg, '0') >= 0 ? 'up' : 'down' ?>">
                                            <?= Money::compare($chg, '0') >= 0 ? '↑' : '↓' ?><?= Money::format(Money::abs($chg), 1) ?>%
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="num">
                                <input class="form-control rate-input" type="number" step="any" min="0"
                                       name="rates[<?= (int)$r['id'] ?>][buy]" value="<?= e((string)$r['buy_rate']) ?>">
                            </td>
                            <td class="num">
                                <input class="form-control rate-input" type="number" step="any" min="0"
                                       name="rates[<?= (int)$r['id'] ?>][sell]" value="<?= e((string)$r['sell_rate']) ?>">
                            </td>
                            <td>
                                <div class="spread-ctl">
                                    <select class="form-select" name="rates[<?= (int)$r['id'] ?>][buy_spread_type]">
                                        <option value="fixed" <?= $buyType === 'fixed' ? 'selected' : '' ?>><?= t('rates.fixed') ?></option>
                                        <option value="percent" <?= $buyType === 'percent' ? 'selected' : '' ?>><?= t('rates.percent') ?></option>
                                    </select>
                                    <input class="form-control" type="number" step="any" name="rates[<?= (int)$r['id'] ?>][buy_spread_value]"
                                           value="<?= e((string)$buyVal) ?>">
                                </div>
                            </td>
                            <td>
                                <div class="spread-ctl">
                                    <select class="form-select" name="rates[<?= (int)$r['id'] ?>][sell_spread_type]">
                                        <option value="fixed" <?= $sellType === 'fixed' ? 'selected' : '' ?>><?= t('rates.fixed') ?></option>
                                        <option value="percent" <?= $sellType === 'percent' ? 'selected' : '' ?>><?= t('rates.percent') ?></option>
                                    </select>
                                    <input class="form-control" type="number" step="any" name="rates[<?= (int)$r['id'] ?>][sell_spread_value]"
                                           value="<?= e((string)$sellVal) ?>">
                                </div>
                            </td>
                            <td>
                                <input type="hidden" name="rates[<?= (int)$r['id'] ?>][override_persistent]" value="0">
                                <label class="form-check" title="<?= e(t('rates.pin_hint')) ?>">
                                    <input type="checkbox" name="rates[<?= (int)$r['id'] ?>][override_persistent]" value="1" <?= $pinned ? 'checked' : '' ?>>
                                    <?= t('rates.pin') ?>
                                </label>
                            </td>
                            <td class="text-muted"><?= e(tz($r['updated_at'], 'm-d H:i')) ?></td>
                            <td><span class="pill <?= $srcPill[0] ?>"><?= $srcPill[1] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rates): ?><tr><td colspan="9" class="text-muted" style="text-align:center;padding:24px">—</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body">
            <button class="btn btn-primary"><?= t('app.save') ?></button>
            <span class="form-hint"><?= t('rates.pin_hint') ?></span>
        </div>
    </form>
</div>

<?php if ($logs): ?>
<div class="card mt-2" style="max-width:860px">
    <div class="card-header"><h2><?= t('rates.recent_syncs') ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= t('app.date') ?></th>
                    <th><?= t('rates.source') ?></th>
                    <th><?= t('app.status') ?></th>
                    <th class="num"><?= t('rates.updated_count') ?></th>
                    <th class="num"><?= t('rates.skipped_count') ?></th>
                    <th class="num"><?= t('rates.failed_count') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="text-muted"><?= e(tz($l['started_at'])) ?></td>
                        <td><?= e($l['provider']) ?><?= $l['triggered_by'] !== 'manual' ? ' <span class="text-muted">(' . e($l['triggered_by']) . ')</span>' : '' ?></td>
                        <td>
                            <?php $lc = match ($l['status']) { 'success' => 'pill-green', 'partial' => 'pill-amber', 'failed' => 'pill-red', default => 'pill-gray' }; ?>
                            <span class="pill <?= $lc ?>"><?= e(strtoupper($l['status'])) ?></span>
                            <?php if (!empty($l['error_message'])): ?>
                                <div class="form-hint"><?= e(mb_substr($l['error_message'], 0, 120)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= (int)$l['currencies_updated'] ?></td>
                        <td class="num"><?= (int)$l['currencies_skipped'] ?></td>
                        <td class="num"><?= (int)$l['currencies_failed'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

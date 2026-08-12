<?php
/** @var string $preset @var string $from @var string $to @var array $metrics @var array $by_currency @var array $by_type @var array $trend @var array $performance @var array $top @var array $base */
$m = $metrics;
$presets = [
    'today' => t('analytics.today'), 'yesterday' => t('analytics.yesterday'),
    '7d' => t('analytics.7d'), '30d' => t('analytics.30d'),
    'month' => t('analytics.month'), 'prev_month' => t('analytics.prev_month'),
    'custom' => t('analytics.custom'),
];
$maxTrend = 1;
foreach ($trend as $t) { $maxTrend = max($maxTrend, (float)$t['volume'], (float)Money::abs($t['profit'])); }
$maxVol = 1;
foreach ($by_currency as $r) { $maxVol = max($maxVol, (float)Money::add((string)$r['buy_base'], (string)$r['sell_base'])); }
?>
<div class="page-head">
    <h1><?= t('analytics.profit_title') ?></h1>
    <div class="page-actions">
        <a href="/export/transactions?format=csv" class="btn btn-ghost btn-sm">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#download"/></svg> CSV
        </a>
    </div>
</div>

<form method="get" action="/analytics/profit" class="card mb-2" style="max-width:860px">
    <div class="toolbar">
        <div class="period-tabs">
            <?php foreach ($presets as $key => $label): ?>
                <button type="submit" name="period" value="<?= $key ?>" class="btn btn-sm <?= $preset === $key ? 'btn-primary' : 'btn-ghost' ?>">
                    <?= e($label) ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php if ($preset === 'custom'): ?>
            <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
            <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
        <?php endif; ?>
        <span class="text-muted" style="font-size:.8rem"><?= e($from) ?> → <?= e($to) ?></span>
    </div>
</form>

<div class="stat-grid">
    <div class="card stat-card" style="--stat-tint:var(--green);--stat-tint-bg:var(--green-bg)">
        <div class="stat-label"><?= t('analytics.net_profit') ?></div>
        <div class="stat-value <?= Money::isNegative($m['net_profit']) ? 'stat-negative' : 'stat-positive' ?>"><?= money($m['net_profit'], $base) ?></div>
        <div class="stat-sub"><?= t('analytics.realized') ?>: <?= money($m['realized_pl'], $base) ?></div>
    </div>
    <div class="card stat-card" style="--stat-tint:var(--primary);--stat-tint-bg:var(--primary-soft)">
        <div class="stat-label"><?= t('analytics.volume') ?></div>
        <div class="stat-value"><?= money($m['volume'], $base) ?></div>
        <div class="stat-sub"><?= $m['tx_count'] ?> <?= t('reports.transactions') ?></div>
    </div>
    <div class="card stat-card" style="--stat-tint:var(--green);--stat-tint-bg:var(--green-bg)">
        <div class="stat-label"><?= t('analytics.revenue') ?></div>
        <div class="stat-value"><?= money($m['revenue'], $base) ?></div>
        <div class="stat-sub"><?= t('tx.sell') ?>: <?= money($m['sell_volume'], $base) ?></div>
    </div>
    <div class="card stat-card" style="--stat-tint:var(--blue);--stat-tint-bg:var(--blue-bg)">
        <div class="stat-label"><?= t('analytics.fees') ?></div>
        <div class="stat-value"><?= money($m['fees'], $base) ?></div>
        <div class="stat-sub"><?= t('analytics.trading_profit') ?>: <?= money($m['trading_profit'], $base) ?></div>
    </div>
    <div class="card stat-card" style="--stat-tint:var(--red);--stat-tint-bg:var(--red-bg)">
        <div class="stat-label"><?= t('analytics.expenses') ?></div>
        <div class="stat-value stat-negative"><?= money($m['expenses'], $base) ?></div>
        <div class="stat-sub"><?= t('analytics.income') ?>: <?= money($m['income'], $base) ?></div>
    </div>
</div>

<div class="grid-2">
    <div class="stack">
        <div class="card">
            <div class="card-header"><h2><?= t('analytics.profit_by_currency') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th><?= t('app.currency') ?></th>
                        <th class="num"><?= t('analytics.buy_vol') ?></th>
                        <th class="num"><?= t('analytics.sell_vol') ?></th>
                        <th class="num"><?= t('analytics.gross_profit') ?></th>
                        <th class="num"><?= t('reports.transactions') ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($by_currency as $r): ?>
                            <tr>
                                <td><strong><?= e($r['code']) ?></strong></td>
                                <td class="num"><?= money((string)$r['buy_base'], $base) ?></td>
                                <td class="num"><?= money((string)$r['sell_base'], $base) ?></td>
                                <td class="num <?= Money::isNegative((string)$r['realized']) ? 'text-red' : 'text-green' ?>"><?= money((string)$r['realized'], $base) ?></td>
                                <td class="num"><?= (int)$r['tx_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$by_currency): ?><tr><td colspan="5"><div class="empty">—</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2><?= t('analytics.profit_by_type') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= t('app.type') ?></th><th class="num"><?= t('app.amount') ?></th><th class="num"><?= t('reports.transactions') ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($by_type['rows'] as $r): ?>
                            <tr>
                                <td><span class="pill <?= match ($r['type']) { 'buy' => 'pill-green', 'sell' => 'pill-red', default => 'pill-blue' } ?>"><?= t('tx.type.' . $r['type']) ?></span></td>
                                <td class="num"><?= money((string)$r['base_amount'], $base) ?></td>
                                <td class="num"><?= (int)$r['tx_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><span class="pill pill-blue"><?= t('analytics.fees') ?></span></td>
                            <td class="num"><?= money($by_type['fees'], $base) ?></td>
                            <td class="num">—</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-header"><h2><?= t('analytics.daily_trend') ?></h2></div>
            <div class="chart-box chart-box--trend">
                <div class="chart-bars">
                    <?php foreach ($trend as $t): ?>
                        <div class="bar-col" title="<?= e($t['date']) ?>">
                            <div class="bar bar-buy" style="height:<?= round(((float)$t['volume'] / $maxTrend) * 100) ?>%"></div>
                            <div class="bar bar-sell <?= Money::isNegative((string)$t['profit']) ? '' : '' ?>" style="height:<?= round(max(0, (float)Money::abs($t['profit']) / $maxTrend) * 100) ?>%;background:<?= Money::isNegative((string)$t['profit']) ? 'var(--red)' : 'var(--primary)' ?>"></div>
                            <span class="bar-label"><?= e(substr($t['label'], 4)) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-1" style="display:flex;gap:16px;justify-content:center;font-size:.72rem;color:var(--text-3)">
                    <span><span class="dot" style="background:var(--green)"></span> <?= t('analytics.volume') ?></span>
                    <span><span class="dot" style="background:var(--primary)"></span> <?= t('analytics.net_profit') ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2><?= t('analytics.currency_performance') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th><?= t('app.currency') ?></th>
                        <th class="num"><?= t('analytics.volume') ?></th>
                        <th class="num"><?= t('analytics.avg_size') ?></th>
                        <th class="num"><?= t('analytics.gross_profit') ?></th>
                        <th class="num"><?= t('reports.transactions') ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($performance as $r): ?>
                            <tr>
                                <td><strong><?= e($r['code']) ?></strong></td>
                                <td class="num"><?= money((string)$r['volume'], $base) ?></td>
                                <td class="num"><?= money((string)$r['avg_size'], $base) ?></td>
                                <td class="num <?= Money::isNegative((string)$r['realized']) ? 'text-red' : 'text-green' ?>"><?= money((string)$r['realized'], $base) ?></td>
                                <td class="num"><?= (int)$r['tx_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$performance): ?><tr><td colspan="5"><div class="empty">—</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2><?= t('analytics.top_currencies') ?></h2></div>
            <div class="card-body">
                <div class="detail-list">
                    <?php foreach (['volume' => t('analytics.by_volume'), 'profit' => t('analytics.by_profit'), 'count' => t('analytics.by_count')] as $key => $label): ?>
                        <div class="detail-item" style="grid-column:span 1">
                            <dt><?= e($label) ?></dt>
                            <dd style="font-size:.78rem;line-height:1.7">
                                <?php foreach ($top[$key] as $i => $r): ?>
                                    <?= $i + 1 ?>. <?= e($r['code']) ?> — <span class="mono"><?= $key === 'count' ? (int)$r['tx_count'] . ' tx' : money($key === 'volume' ? (string)$r['volume'] : (string)$r['realized'], $base) ?></span><br>
                                <?php endforeach; ?>
                                <?php if (!$top[$key]): ?>—<?php endif; ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

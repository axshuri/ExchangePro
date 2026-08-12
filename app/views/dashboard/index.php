<?php
/** @var array $base @var array $daily @var array $monthly @var array $last_monthly
 *  @var array $yearly @var array $inventory @var array $balance_sheet @var array $recent_tx
 *  @var array $weekly @var array $alerts @var array $notifications @var int $unread
 *  @var string $today @var string $today_volume @var string $cash_position @var int $active_customers
 */
$bp = (int)($base['amount_precision'] ?? 2);
$bs = $balance_sheet;
$maxBar = 0;
foreach ($weekly as $w) { $maxBar = max($maxBar, (float)$w['buy'], (float)$w['sell']); }
if ($maxBar <= 0) $maxBar = 1;
$unrealizedTotal = '0';
foreach ($inventory['rows'] as $r) { $unrealizedTotal = Money::add($unrealizedTotal, (string)$r['unrealized_pl']); }

// ---- vs-yesterday deltas for the KPI band ----
$cmp = $comparisons['today'] ?? [];
$profitPct = (string)($cmp['net_pct'] ?? '0');
$volumePct = (string)($cmp['volume_pct'] ?? '0');
$txDelta = (int)($daily['tx_count'] - (int)($cmp['prev_tx_count'] ?? 0));
$deltaChip = static function (string $pct) {
    $up = Money::compare($pct, '0') > 0;
    $down = Money::compare($pct, '0') < 0;
    $cls = $up ? 'up' : ($down ? 'down' : 'flat');
    $arrow = $up ? '↑' : ($down ? '↓' : '•');
    return '<span class="stat-delta ' . $cls . '">' . $arrow . ' ' . Money::format(Money::abs($pct), 1) . '%</span>';
};
$maxQty = 0;
foreach ($inventory['rows'] as $r) { $maxQty = max($maxQty, (float)$r['qty']); }
if ($maxQty <= 0) $maxQty = 1;
?>

<!-- Hero -->
<div class="hero-band">
    <div class="hero-eyebrow"><?= t('app.dashboard') ?> · <?= e(localizedDate($today)) ?></div>
    <h1 class="hero-title"><?= t('app.welcome_back') ?>, <?= e($user['full_name'] ?? '') ?></h1>
    <div class="hero-value"><?= money((string)$bs['asset_total'], $base) ?></div>
    <div class="hero-meta">
        <?= t('dashboard.total_assets') ?> (<?= e($base['code']) ?>) ·
        <?= t('app.today') ?>: <?= money($daily['net_profit'], $base) ?> <?= t('reports.net_profit') ?> <?= $deltaChip($profitPct) ?> ·
        <?= $daily['tx_count'] ?> <?= t('reports.transactions') ?>
    </div>
    <div class="hero-actions">
        <a href="/reports/daily" class="btn btn-sm" style="background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.25);color:#fff"><?= t('reports.daily') ?></a>
        <a href="/export/transactions?format=csv" class="btn btn-sm" style="background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.25);color:#fff">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#download"/></svg> CSV
        </a>
    </div>
</div>

<?php if (!empty($alerts)): ?>
<div class="alert-list mb-2">
    <?php foreach ($alerts as $a): ?>
        <div class="alert-item alert-warning">
            <svg class="icon"><use href="/assets/img/icons.svg#alert-triangle"/></svg>
            <span><?= e($a['message']) ?></span>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Quick actions -->
<div class="quick-actions">
    <a href="/transactions/buy" class="qa-btn qa-buy">
        <svg class="icon"><use href="/assets/img/icons.svg#arrow-down-circle"/></svg>
        <?= t('tx.buy') ?>
    </a>
    <a href="/transactions/sell" class="qa-btn qa-sell">
        <svg class="icon"><use href="/assets/img/icons.svg#arrow-up-circle"/></svg>
        <?= t('tx.sell') ?>
    </a>
    <a href="/transactions/exchange" class="qa-btn qa-primary">
        <svg class="icon"><use href="/assets/img/icons.svg#repeat"/></svg>
        <?= t('tx.exchange') ?>
    </a>
    <a href="/customers/create" class="qa-btn" data-ajax-form="/customers/create" data-ajax-title="<?= t('customer.new') ?>">
        <svg class="icon"><use href="/assets/img/icons.svg#plus"/></svg>
        <?= t('customer.new') ?>
    </a>
    <a href="/expenses/create" class="qa-btn" data-ajax-form="/expenses/create" data-ajax-title="<?= t('expense.new') ?>">
        <svg class="icon"><use href="/assets/img/icons.svg#minus-circle"/></svg>
        <?= t('expense.new') ?>
    </a>
    <a href="/transfers/create" class="qa-btn" data-ajax-form="/transfers/create" data-ajax-title="<?= t('transfer.new') ?>">
        <svg class="icon"><use href="/assets/img/icons.svg#repeat"/></svg>
        <?= t('transfer.new') ?>
    </a>
    <a href="/cash-count/create" class="qa-btn" data-ajax-form="/cash-count/create" data-ajax-title="<?= t('cashcount.new') ?>" data-ajax-wide>
        <svg class="icon"><use href="/assets/img/icons.svg#hash"/></svg>
        <?= t('cashcount.new') ?>
    </a>
    <a href="/closing" class="qa-btn">
        <svg class="icon"><use href="/assets/img/icons.svg#lock"/></svg>
        <?= t('closing.title') ?>
    </a>
</div>

<!-- Today's performance -->
<div class="snap-sec">
    <div class="snap-sec-head">
        <h2><?= t('dashboard.today_perf') ?></h2>
        <span class="snap-sec-note"><?= t('dashboard.vs_yesterday') ?></span>
    </div>
    <div class="kpi-grid">
        <div class="kpi-card" style="--kpi-tint:var(--green);--kpi-tint-bg:var(--green-bg)">
            <div class="kpi-label"><?= t('dashboard.today_profit') ?></div>
            <div class="kpi-value <?= Money::isNegative($daily['net_profit']) ? 'neg' : 'pos' ?>"><?= money($daily['net_profit'], $base) ?></div>
            <div class="kpi-foot">
                <?= $deltaChip($profitPct) ?>
                <span class="kpi-sub"><?= t('reports.trading_profit') ?>: <?= money($daily['trading_profit'], $base) ?></span>
            </div>
        </div>
        <div class="kpi-card" style="--kpi-tint:var(--primary);--kpi-tint-bg:var(--primary-soft)">
            <div class="kpi-label"><?= t('dashboard.today_volume') ?></div>
            <div class="kpi-value"><?= money($today_volume, $base) ?></div>
            <div class="kpi-foot">
                <?= $deltaChip($volumePct) ?>
                <span class="kpi-sub"><?= t('dashboard.buy') ?> <?= money($daily['buy_total_base'], $base) ?> · <?= t('dashboard.sell') ?> <?= money($daily['sell_total_base'], $base) ?></span>
            </div>
        </div>
        <div class="kpi-card" style="--kpi-tint:var(--green-2);--kpi-tint-bg:var(--green-bg)">
            <div class="kpi-label"><?= t('dashboard.today_revenue') ?></div>
            <div class="kpi-value"><?= money(Money::add($daily['sell_total_base'], $daily['income_total']), $base) ?></div>
            <div class="kpi-foot">
                <span class="kpi-sub"><?= t('dashboard.today_tx_count') ?>: <?= (int)$daily['tx_count'] ?></span>
            </div>
        </div>
        <div class="kpi-card" style="--kpi-tint:var(--blue);--kpi-tint-bg:var(--blue-bg)">
            <div class="kpi-label"><?= t('dashboard.today_tx_count') ?></div>
            <div class="kpi-value"><?= (int)$daily['tx_count'] ?></div>
            <div class="kpi-foot">
                <span class="stat-delta <?= $txDelta > 0 ? 'up' : ($txDelta < 0 ? 'down' : 'flat') ?>">
                    <?= $txDelta > 0 ? '↑' : ($txDelta < 0 ? '↓' : '•') ?> <?= $txDelta > 0 ? '+' . $txDelta : (string)$txDelta ?>
                </span>
                <span class="kpi-sub"><?= t('app.today') ?></span>
            </div>
        </div>
        <div class="kpi-card" style="--kpi-tint:var(--red);--kpi-tint-bg:var(--red-bg)">
            <div class="kpi-label"><?= t('dashboard.today_expenses') ?></div>
            <div class="kpi-value neg"><?= money($daily['expense_total'], $base) ?></div>
            <div class="kpi-foot">
                <span class="kpi-sub"><?= t('dashboard.cmp_month') ?>: <?= money($monthly['expense_total'] ?? '0', $base) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Positions -->
<div class="snap-sec">
    <div class="snap-sec-head"><h2><?= t('dashboard.positions') ?></h2></div>
    <div class="stat-grid">
        <div class="card stat-card" style="--stat-tint:var(--green);--stat-tint-bg:var(--green-bg)">
            <div class="stat-label"><?= t('dashboard.cash_position') ?>
                <span class="stat-icon"><svg class="icon"><use href="/assets/img/icons.svg#wallet"/></svg></span>
            </div>
            <div class="stat-value <?= Money::isNegative($cash_position) ? 'stat-negative' : 'stat-positive' ?>"><?= money($cash_position, $base) ?></div>
            <div class="stat-sub"><?= t('dashboard.cash_desk') ?></div>
        </div>
        <div class="card stat-card" style="--stat-tint:var(--green);--stat-tint-bg:var(--green-bg)">
            <div class="stat-label"><?= t('dashboard.receivables') ?>
                <span class="stat-icon"><svg class="icon"><use href="/assets/img/icons.svg#arrow-down-circle"/></svg></span>
            </div>
            <div class="stat-value stat-positive"><?= money((string)$bs['receivables'], $base) ?></div>
            <div class="stat-sub"><?= t('customer.receivable') ?></div>
        </div>
        <div class="card stat-card" style="--stat-tint:var(--red);--stat-tint-bg:var(--red-bg)">
            <div class="stat-label"><?= t('dashboard.payables') ?>
                <span class="stat-icon"><svg class="icon"><use href="/assets/img/icons.svg#arrow-up-circle"/></svg></span>
            </div>
            <div class="stat-value stat-negative"><?= money((string)$bs['payables'], $base) ?></div>
            <div class="stat-sub"><?= t('customer.payable') ?></div>
        </div>
        <div class="card stat-card" style="--stat-tint:var(--primary);--stat-tint-bg:var(--primary-soft)">
            <div class="stat-label"><?= t('dashboard.unrealized_pl') ?>
                <span class="stat-icon"><svg class="icon"><use href="/assets/img/icons.svg#coins"/></svg></span>
            </div>
            <div class="stat-value <?= Money::isNegative($unrealizedTotal) ? 'stat-negative' : 'stat-positive' ?>"><?= money($unrealizedTotal, $base) ?></div>
            <div class="stat-sub"><?= t('dashboard.reference_value') ?></div>
        </div>
        <div class="card stat-card" style="--stat-tint:var(--amber);--stat-tint-bg:var(--amber-bg)">
            <div class="stat-label"><?= t('dashboard.month_profit') ?>
                <span class="stat-icon"><svg class="icon"><use href="/assets/img/icons.svg#activity"/></svg></span>
            </div>
            <div class="stat-value <?= Money::isNegative($monthly['net_profit']) ? 'stat-negative' : 'stat-positive' ?>"><?= money($monthly['net_profit'], $base) ?></div>
            <div class="stat-sub"><?= t('app.this_month') ?></div>
        </div>
        <div class="card stat-card" style="--stat-tint:var(--blue);--stat-tint-bg:var(--blue-bg)">
            <div class="stat-label"><?= t('dashboard.active_customers') ?>
                <span class="stat-icon"><svg class="icon"><use href="/assets/img/icons.svg#users"/></svg></span>
            </div>
            <div class="stat-value"><?= (int)$active_customers ?></div>
            <div class="stat-sub"><?= t('app.today') ?></div>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="stack">
        <!-- Currency inventory -->
        <div class="card">
            <div class="card-header">
                <h2><?= t('dashboard.currency_inventory') ?></h2>
                <div class="card-actions">
                    <a href="/inventory" class="btn btn-ghost btn-sm"><?= t('app.view') ?></a>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= t('app.currency') ?></th>
                            <th class="num"><?= t('dashboard.total_balance') ?></th>
                            <th class="num"><?= t('dashboard.avg_cost') ?></th>
                            <th class="num"><?= t('dashboard.reference_value') ?></th>
                            <th class="num"><?= t('dashboard.unrealized_pl') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory['rows'] as $r): ?>
                            <?php $pl = (string)$r['unrealized_pl']; ?>
                            <?php $barW = max(4, round(((float)$r['qty'] / $maxQty) * 100)); ?>
                            <tr>
                                <td>
                                    <strong><?= e($r['currency']['code']) ?></strong>
                                    <span class="text-muted"> <?= e(currencyName($r['currency'])) ?></span>
                                </td>
                                <td class="num">
                                    <div class="mono"><?= money((string)$r['qty'], $r['currency']) ?></div>
                                    <div class="inv-bar" aria-hidden="true"><span style="width:<?= (int)$barW ?>%"></span></div>
                                </td>
                                <td class="num"><?= money((string)$r['avg_cost'], $base) ?></td>
                                <td class="num"><?= money((string)$r['market_value'], $base) ?></td>
                                <td class="num <?= Money::isNegative($pl) ? 'text-red' : (Money::isPositive($pl) ? 'text-green' : '') ?>">
                                    <?= money($pl, $base) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$inventory['rows']): ?>
                            <tr><td colspan="5" class="text-muted" style="text-align:center;padding:24px">—</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent transactions -->
        <div class="card">
            <div class="card-header">
                <h2><?= t('dashboard.recent_transactions') ?></h2>
                <div class="card-actions"><a href="/transactions" class="btn btn-ghost btn-sm"><?= t('app.view') ?></a></div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= t('app.date') ?></th>
                            <th><?= t('app.type') ?></th>
                            <th><?= t('app.customer') ?></th>
                            <th class="num"><?= t('app.amount') ?></th>
                            <th class="num"><?= t('app.rate') ?></th>
                            <th class="num"><?= t('app.total') ?></th>
                            <th><?= t('app.status') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_tx as $tx): ?>
                            <tr>
                                <td class="mono"><a href="/transactions/<?= (int)$tx['id'] ?>"><?= e($tx['tx_number']) ?></a></td>
                                <td><?= e(tz($tx['tx_date'], 'm-d H:i')) ?></td>
                                <td>
                                    <?php $cls = match($tx['type']) { 'buy' => 'pill-green', 'sell' => 'pill-red', 'exchange' => 'pill-blue', 'reversal' => 'pill-amber', default => 'pill-gray' }; ?>
                                    <span class="pill <?= $cls ?>"><?= t('tx.type.' . $tx['type']) ?></span>
                                </td>
                                <td><?= e($tx['customer_name'] ?? '—') ?></td>
                                <td class="num mono"><?= money((string)$tx['foreign_amount'], ['symbol' => $tx['currency_code']]) ?></td>
                                <td class="num"><?= Money::format((string)$tx['rate'], 4) ?></td>
                                <td class="num"><?= money((string)$tx['base_amount'], $base) ?></td>
                                <td>
                                    <?php $sc = $tx['status'] === 'completed' ? 'pill-green' : ($tx['status'] === 'reversed' ? 'pill-amber' : 'pill-gray'); ?>
                                    <span class="pill <?= $sc ?>"><?= t('tx.status.' . $tx['status']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recent_tx): ?>
                            <tr><td colspan="8"><div class="empty"><?= t('dashboard.no_transactions') ?></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="stack">
        <!-- Weekly chart -->
        <div class="card">
            <div class="card-header"><h2><?= t('dashboard.weekly_volume') ?></h2></div>
            <div class="chart-box">
                <div class="chart-bars chart-bars--snap">
                    <?php foreach ($weekly as $i => $w): ?>
                        <?php $bw = round(((float)$w['buy'] / $maxBar) * 100); $sw = round(((float)$w['sell'] / $maxBar) * 100); ?>
                        <div class="bar-col">
                            <div class="bar-pair">
                                <div class="bar bar-buy" style="height:<?= $bw ?>%;animation-delay:<?= $i * 60 ?>ms"
                                     title="<?= t('dashboard.buy') ?>: <?= money($w['buy'], $base) ?>"></div>
                                <div class="bar bar-sell" style="height:<?= $sw ?>%;animation-delay:<?= $i * 60 + 40 ?>ms"
                                     title="<?= t('dashboard.sell') ?>: <?= money($w['sell'], $base) ?>"></div>
                            </div>
                            <span class="bar-label"><?= e($w['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-1" style="display:flex;gap:16px;justify-content:center;font-size:.72rem;color:var(--text-3)">
                    <span><span class="dot" style="background:var(--green)"></span> <?= t('dashboard.buy') ?></span>
                    <span><span class="dot" style="background:var(--red)"></span> <?= t('dashboard.sell') ?></span>
                </div>
            </div>
        </div>

        <!-- Performance -->
        <div class="card">
            <div class="card-header"><h2><?= t('dashboard.performance') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th></th><th class="num"><?= t('reports.net_profit') ?></th><th class="num"><?= t('reports.trading_profit') ?></th></tr></thead>
                    <tbody>
                        <tr>
                            <td><?= t('app.today') ?></td>
                            <td class="num <?= Money::isNegative($daily['net_profit']) ? 'text-red' : 'text-green' ?>"><?= money($daily['net_profit'], $base) ?></td>
                            <td class="num"><?= money($daily['trading_profit'], $base) ?></td>
                        </tr>
                        <tr>
                            <td><?= t('app.this_month') ?></td>
                            <td class="num <?= Money::isNegative($monthly['net_profit']) ? 'text-red' : 'text-green' ?>"><?= money($monthly['net_profit'], $base) ?></td>
                            <td class="num"><?= money($monthly['trading_profit'], $base) ?></td>
                        </tr>
                        <tr>
                            <td><?= t('app.last_month') ?></td>
                            <td class="num <?= Money::isNegative($last_monthly['net_profit']) ? 'text-red' : 'text-green' ?>"><?= money($last_monthly['net_profit'], $base) ?></td>
                            <td class="num"><?= money($last_monthly['trading_profit'], $base) ?></td>
                        </tr>
                        <tr>
                            <td><?= t('app.this_year') ?></td>
                            <td class="num <?= Money::isNegative($yearly['net_profit']) ? 'text-red' : 'text-green' ?>"><?= money($yearly['net_profit'], $base) ?></td>
                            <td class="num"><?= money($yearly['trading_profit'], $base) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Outstanding -->
        <div class="card">
            <div class="card-header"><h2><?= t('dashboard.outstanding') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th></th><th class="num"><?= t('app.amount') ?></th></tr></thead>
                    <tbody>
                        <tr>
                            <td><?= t('dashboard.receivables') ?> <span class="pill pill-green"><?= t('customer.receivable') ?></span></td>
                            <td class="num text-green"><?= money((string)$bs['receivables'], $base) ?></td>
                        </tr>
                        <tr>
                            <td><?= t('dashboard.payables') ?> <span class="pill pill-red"><?= t('customer.payable') ?></span></td>
                            <td class="num text-red"><?= money((string)$bs['payables'], $base) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Market reference rates -->
        <div class="card">
            <div class="card-header">
                <h2><?= t('dashboard.rates_title') ?></h2>
                <div class="card-actions"><a href="/rates" class="btn btn-ghost btn-sm"><?= t('rates.title') ?></a></div>
            </div>
            <?php if (!$ref_rates): ?>
                <div class="card-body"><p class="text-muted" style="padding:8px 0"><?= t('dashboard.no_ref_rates') ?></p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th><?= t('app.currency') ?></th><th class="num"><?= t('rates.reference') ?></th><th class="num"><?= t('app.status') ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($ref_rates as $rr):
                                $chg = null;
                                if ($rr['previous_reference'] !== null && Money::isPositive((string)$rr['previous_reference'])) {
                                    $chg = Money::round(Money::mul(Money::div(
                                        Money::sub((string)$rr['reference_rate'], (string)$rr['previous_reference']),
                                        (string)$rr['previous_reference'], 10), '100', 10), 2);
                                }
                                $rrs = $rr['rate_status'] ?: 'manual';
                                $rrPill = match ($rrs) { 'online' => 'pill-green', 'cached' => 'pill-blue', 'stale' => 'pill-amber', default => 'pill-gray' };
                            ?>
                                <tr>
                                    <td><strong><?= e($rr['code']) ?></strong> <span class="text-muted"><?= e(currencyName($rr)) ?></span></td>
                                    <td class="num mono"><?= Money::format((string)$rr['reference_rate'], (int)$rr['rate_precision']) ?>
                                        <?php if ($chg !== null): ?>
                                            <span class="rate-chg <?= Money::compare($chg, '0') >= 0 ? 'up' : 'down' ?>">
                                                <?= Money::compare($chg, '0') >= 0 ? '↑' : '↓' ?><?= Money::format(Money::abs($chg), 1) ?>%
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="pill <?= $rrPill ?>"><?= e(t('rates.status.' . $rrs)) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-body" style="padding:10px 18px">
                    <p class="form-hint"><?= t('dashboard.rates_note') ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Business snapshot comparisons -->
        <div class="card">
            <div class="card-header"><h2><?= t('dashboard.comparisons') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th><?= t('dashboard.period') ?></th>
                        <th class="num"><?= t('dashboard.net_profit') ?></th>
                        <th class="num"><?= t('dashboard.change') ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($comparisons as $cmp2): ?>
                            <tr>
                                <td><?= e($cmp2['label']) ?></td>
                                <td class="num <?= Money::isNegative((string)$cmp2['net_profit']) ? 'text-red' : 'text-green' ?>">
                                    <?= money((string)$cmp2['net_profit'], $base) ?>
                                    <div class="form-hint"><?= t('dashboard.prev') ?>: <?= money((string)$cmp2['prev_net_profit'], $base) ?></div>
                                </td>
                                <td class="num">
                                    <?php $pct = (string)($cmp2['net_pct'] ?? '0'); ?>
                                    <span class="rate-chg <?= Money::compare($pct, '0') >= 0 ? 'up' : 'down' ?>">
                                        <?= Money::compare($pct, '0') >= 0 ? '↑' : '↓' ?><?= Money::format(Money::abs($pct), 1) ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Backup status -->
        <div class="card">
            <div class="card-header">
                <h2><?= t('dashboard.backup_status') ?></h2>
                <div class="card-actions"><a href="/settings/backup" class="btn btn-ghost btn-sm"><?= t('app.view') ?></a></div>
            </div>
            <div class="card-body" style="padding:10px 18px">
                <?php if ($backup_status['last']): ?>
                    <div class="detail-item">
                        <dt><?= t('dashboard.last_backup') ?></dt>
                        <dd><?= e(tz($backup_status['last']['created_at'], 'Y-m-d H:i')) ?>
                            <span class="pill <?= $backup_status['last']['status'] === 'ok' ? 'pill-green' : 'pill-red' ?>"><?= e($backup_status['last']['status']) ?></span>
                            <?php if (!empty($backup_status['last']['verified'])): ?>
                                <span class="pill pill-green" style="font-size:.65rem"><?= t('settings.backup_verified') ?></span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="detail-item">
                        <dt><?= t('dashboard.next_backup') ?></dt>
                        <dd><?= $backup_status['enabled'] && $backup_status['next'] ? e($backup_status['next']) : t('dashboard.backup_disabled') ?></dd>
                    </div>
                <?php else: ?>
                    <p class="text-muted" style="padding:8px 0"><?= t('dashboard.no_backup') ?></p>
                <?php endif; ?>
                <?php if ($backup_status['failed_count'] > 0): ?>
                    <div class="alert-item alert-warning" style="margin-top:8px">
                        <svg class="icon"><use href="/assets/img/icons.svg#alert-triangle"/></svg>
                        <span><?= (int)$backup_status['failed_count'] ?> <?= t('dashboard.backup_failed_count') ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notifications -->
        <div class="card">
            <div class="card-header">
                <h2><?= t('app.notifications') ?></h2>
                <div class="card-actions">
                    <form method="post" action="/notifications/read"><?= Csrf::field() ?>
                        <button class="btn btn-ghost btn-sm" type="submit"><?= t('app.mark_all_read') ?></button>
                    </form>
                </div>
            </div>
            <div class="card-body" style="padding:8px 18px">
                <?php if (!$notifications): ?>
                    <p class="text-muted" style="padding:10px 0">—</p>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <div class="alert-item alert-info <?= !$n['is_read'] ? '' : 'no-print' ?>" style="margin:6px 0">
                            <svg class="icon"><use href="/assets/img/icons.svg#info"/></svg>
                            <div>
                                <strong><?= e($n['title']) ?></strong>
                                <div><?= e($n['message']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

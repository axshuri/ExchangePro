<?php
/** @var array $valuation @var ?array $base @var array $positions @var array $by_account @var array $accounts @var array $currencies */
?>
<div class="page-head">
    <h1><?= t('dashboard.currency_inventory') ?></h1>
    <div class="page-actions">
        <a href="/reports/inventory" class="btn btn-ghost btn-sm"><?= t('reports.inventory') ?></a>
        <a href="/export/inventory?format=csv" class="btn btn-ghost btn-sm">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#download"/></svg> CSV
        </a>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat-card">
        <div class="stat-label"><?= t('dashboard.total_assets') ?></div>
        <div class="stat-value"><?= money((string)$valuation['total'], $base) ?></div>
        <div class="stat-sub"><?= e($base['code']) ?> <?= t('dashboard.reference_value') ?></div>
    </div>
    <?php foreach ($valuation['rows'] as $r): ?>
        <?php if ((int)$r['currency']['id'] === (int)$base['id']) continue; ?>
        <div class="card stat-card">
            <div class="stat-label"><?= e($r['currency']['code']) ?> <?= t('dashboard.total_balance') ?></div>
            <div class="stat-value"><?= money((string)$r['qty'], $r['currency']) ?></div>
            <div class="stat-sub"><?= t('dashboard.reference_value') ?>: <?= money((string)$r['market_value'], $base) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><h2><?= t('dashboard.currency_inventory') ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= t('app.currency') ?></th>
                    <th class="num"><?= t('dashboard.total_balance') ?></th>
                    <th class="num"><?= t('dashboard.avg_cost') ?></th>
                    <th class="num"><?= t('dashboard.total_cost') ?></th>
                    <th class="num"><?= t('dashboard.reference_rate') ?></th>
                    <th class="num"><?= t('dashboard.reference_value') ?></th>
                    <th class="num"><?= t('dashboard.unrealized_pl') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($valuation['rows'] as $r): ?>
                    <?php $pl = (string)$r['unrealized_pl']; ?>
                    <tr>
                        <td><strong><?= e($r['currency']['code']) ?></strong> <?= e($r['currency']['name']) ?></td>
                        <td class="num mono"><?= money((string)$r['qty'], $r['currency']) ?></td>
                        <td class="num"><?= money((string)$r['avg_cost'], $base) ?></td>
                        <td class="num"><?= money((string)$r['total_cost'], $base) ?></td>
                        <td class="num"><?= money((string)$r['reference_rate'], $base, false) ?></td>
                        <td class="num"><?= money((string)$r['market_value'], $base) ?></td>
                        <td class="num <?= Money::isNegative($pl) ? 'text-red' : (Money::isPositive($pl) ? 'text-green' : '') ?>"><?= money($pl, $base) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><h2><?= t('account.accounts') ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= t('account.name') ?></th>
                    <th><?= t('account.type') ?></th>
                    <?php foreach ($currencies as $c): ?>
                        <th class="num"><?= e($c['code']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $a): ?>
                    <tr>
                        <td><a href="/accounts/<?= (int)$a['id'] ?>"><strong><?= e($a['name']) ?></strong></a> <span class="text-muted mono"><?= e($a['code']) ?></span></td>
                        <td><span class="pill pill-gray"><?= t('account.' . $a['type']) ?></span></td>
                        <?php foreach ($currencies as $c): ?>
                            <td class="num mono">
                                <?php $bal = $by_account[$a['id']][$c['id']]['amount'] ?? '0'; ?>
                                <?= Money::format($bal, (int)$c['amount_precision']) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

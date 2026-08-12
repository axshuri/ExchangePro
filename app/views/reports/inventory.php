<?php /** @var array $report @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('reports.inventory') ?></h1>
    <div class="page-actions">
        <a href="/export/inventory?format=csv" class="btn btn-ghost btn-sm">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#download"/></svg> CSV
        </a>
        <a href="/reports" class="btn btn-ghost btn-sm"><?= t('reports.title') ?></a>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat-card">
        <div class="stat-label"><?= t('dashboard.total_assets') ?></div>
        <div class="stat-value"><?= money((string)$report['total'], $base) ?></div>
        <div class="stat-sub"><?= e($base['code']) ?> <?= t('dashboard.reference_value') ?></div>
    </div>
</div>

<div class="card">
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
                <?php foreach ($report['rows'] as $r): ?>
                    <?php $pl = (string)$r['unrealized_pl']; ?>
                    <tr>
                        <td><strong><?= e($r['currency']['code']) ?></strong> <?= e($r['currency']['name']) ?></td>
                        <td class="num mono"><?= money((string)$r['qty'], $r['currency']) ?></td>
                        <td class="num"><?= money((string)$r['avg_cost'], $base) ?></td>
                        <td class="num"><?= money((string)$r['total_cost'], $base) ?></td>
                        <td class="num"><?= Money::format((string)$r['reference_rate'], 6) ?></td>
                        <td class="num"><?= money((string)$r['market_value'], $base) ?></td>
                        <td class="num <?= Money::isNegative($pl) ? 'text-red' : (Money::isPositive($pl) ? 'text-green' : '') ?>"><?= money($pl, $base) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--surface-2);font-weight:800">
                    <td colspan="5"><?= t('reports.title') ?></td>
                    <td class="num"><?= money((string)$report['total'], $base) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php /** @var array $report @var string $month @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('reports.monthly') ?> — <?= e($month) ?></h1>
    <div class="page-actions">
        <form method="get" action="/reports/monthly" style="display:flex;gap:8px">
            <input class="form-control" type="month" name="month" value="<?= e($month) ?>" style="width:160px">
            <button class="btn"><?= t('reports.generate') ?></button>
        </form>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat-card">
        <div class="stat-label"><?= t('reports.trading_profit') ?></div>
        <div class="stat-value <?= Money::isNegative($report['trading_profit']) ? 'stat-negative' : 'stat-positive' ?>"><?= money($report['trading_profit'], $base) ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('dashboard.other_income') ?></div>
        <div class="stat-value stat-positive"><?= money($report['income_total'], $base) ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('dashboard.expenses') ?></div>
        <div class="stat-value stat-negative"><?= money($report['expense_total'], $base) ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('dashboard.net_profit') ?></div>
        <div class="stat-value <?= Money::isNegative($report['net_profit']) ? 'stat-negative' : 'stat-positive' ?>"><?= money($report['net_profit'], $base) ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('reports.transactions') ?></div>
        <div class="stat-value"><?= (int)$report['tx_count'] ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= t('reports.currency') ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= t('app.currency') ?></th>
                    <th class="num"><?= t('reports.buy_volume') ?></th>
                    <th class="num"><?= t('reports.sell_volume') ?></th>
                    <th class="num"><?= t('tx.base_amount') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['by_currency'] as $c): ?>
                    <tr>
                        <td><strong><?= e($c['code']) ?></strong> <?= e($c['symbol'] ?? '') ?></td>
                        <td class="num mono"><?= money((string)$c['buy_amount'], $c) ?></td>
                        <td class="num mono"><?= money((string)$c['sell_amount'], $c) ?></td>
                        <td class="num"><?= money((string)$c['base_amount'], $base) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$report['by_currency']): ?><tr><td colspan="4"><div class="empty">—</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

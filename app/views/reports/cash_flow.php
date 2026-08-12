<?php /** @var array $rows @var string $from @var string $to @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('reports.cash_flow') ?></h1>
    <div class="page-actions">
        <form method="get" action="/accounting/cash-flow" style="display:flex;gap:8px">
            <input class="form-control" type="date" name="from" value="<?= e($from) ?>" style="width:150px">
            <input class="form-control" type="date" name="to" value="<?= e($to) ?>" style="width:150px">
            <button class="btn"><?= t('reports.generate') ?></button>
        </form>
    </div>
</div>

<?php
$inTotal = '0'; $outTotal = '0';
foreach ($rows as $r) { $inTotal = Money::add($inTotal, (string)$r['inflow']); $outTotal = Money::add($outTotal, (string)$r['outflow']); }
?>
<div class="stat-grid">
    <div class="card stat-card"><div class="stat-label"><?= t('reports.inflow') ?></div>
        <div class="stat-value stat-positive"><?= money($inTotal, $base) ?></div></div>
    <div class="card stat-card"><div class="stat-label"><?= t('reports.outflow') ?></div>
        <div class="stat-value stat-negative"><?= money($outTotal, $base) ?></div></div>
    <div class="card stat-card"><div class="stat-label"><?= t('dashboard.net_profit') ?></div>
        <div class="stat-value <?= Money::isNegative(Money::sub($inTotal, $outTotal)) ? 'stat-negative' : 'stat-positive' ?>">
            <?= money(Money::sub($inTotal, $outTotal), $base) ?></div></div>
</div>

<div class="card">
    <div class="card-header"><h2><?= t('reports.cash_flow') ?> — <?= e($from) ?> → <?= e($to) ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= t('account.name') ?></th><th><?= t('account.type') ?></th><th><?= t('app.currency') ?></th><th class="num"><?= t('reports.inflow') ?></th><th class="num"><?= t('reports.outflow') ?></th><th class="num"><?= t('reports.title') ?></th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['account_name']) ?></td>
                        <td><span class="pill pill-gray"><?= t('account.' . $r['account_type']) ?></span></td>
                        <td><?= e($r['currency_code']) ?></td>
                        <td class="num text-green"><?= money((string)$r['inflow'], $base) ?></td>
                        <td class="num text-red"><?= money((string)$r['outflow'], $base) ?></td>
                        <td class="num <?= Money::isNegative(Money::sub((string)$r['inflow'], (string)$r['outflow'])) ? 'text-red' : 'text-green' ?>">
                            <?= money(Money::sub((string)$r['inflow'], (string)$r['outflow']), $base) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="6"><div class="empty">—</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

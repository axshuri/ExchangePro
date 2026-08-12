<?php /** @var array $report @var string $from @var string $to @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('reports.pnl') ?></h1>
    <div class="page-actions">
        <form method="get" action="/accounting/pnl" style="display:flex;gap:8px">
            <input class="form-control" type="date" name="from" value="<?= e($from) ?>" style="width:150px">
            <input class="form-control" type="date" name="to" value="<?= e($to) ?>" style="width:150px">
            <button class="btn"><?= t('reports.generate') ?></button>
        </form>
    </div>
</div>

<div class="card" style="max-width:720px">
    <div class="card-header"><h2><?= t('reports.pnl') ?> — <?= e($from) ?> → <?= e($to) ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th></th><th class="num"><?= t('app.amount') ?> (<?= e($base['code']) ?>)</th></tr></thead>
            <tbody>
                <tr style="background:var(--surface-2)"><td colspan="2" style="font-weight:700"><?= t('reports.income') ?></td></tr>
                <?php foreach ($report['income'] as $i): ?>
                    <tr>
                        <td style="padding-inline-start:28px"><?= e($i['name']) ?> <?php if ((int)$i['balance'] === 0): ?><span class="text-muted" style="font-size:.75rem">(<?= t('reports.trading_profit') ?>)</span><?php endif; ?></td>
                        <td class="num"><?= money((string)$i['balance'], $base) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td style="padding-inline-start:28px"><?= t('dashboard.other_income') ?></td>
                    <td class="num"><?= money($report['other_income'], $base) ?></td>
                </tr>
                <tr style="font-weight:700">
                    <td><?= t('reports.income') ?> — <?= t('reports.title') ?></td>
                    <td class="num text-green"><?= money($report['income_total'], $base) ?></td>
                </tr>

                <tr style="background:var(--surface-2)"><td colspan="2" style="font-weight:700"><?= t('dashboard.expenses') ?></td></tr>
                <?php foreach ($report['expenses'] as $x): ?>
                    <tr>
                        <td style="padding-inline-start:28px"><?= e($x['name']) ?></td>
                        <td class="num"><?= money((string)$x['balance'], $base) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td style="padding-inline-start:28px"><?= t('dashboard.expenses') ?> (<?= t('expense.expenses') ?>)</td>
                    <td class="num"><?= money($report['other_expenses'], $base) ?></td>
                </tr>
                <tr style="font-weight:700">
                    <td><?= t('dashboard.expenses') ?> — <?= t('reports.title') ?></td>
                    <td class="num text-red"><?= money($report['expense_total'], $base) ?></td>
                </tr>

                <tr style="background:var(--surface-2);border-top:2px solid var(--border)">
                    <td style="font-weight:800;font-size:.95rem"><?= t('dashboard.net_profit') ?></td>
                    <td class="num <?= Money::isNegative($report['net_profit']) ? 'text-red' : 'text-green' ?>" style="font-weight:800;font-size:.95rem">
                        <?= money($report['net_profit'], $base) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

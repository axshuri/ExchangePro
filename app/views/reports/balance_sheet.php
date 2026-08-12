<?php /** @var array $report @var string $as_of @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('reports.balance_sheet') ?></h1>
    <div class="page-actions">
        <form method="get" action="/accounting/balance-sheet" style="display:flex;gap:8px">
            <input class="form-control" type="date" name="as_of" value="<?= e($as_of) ?>" style="width:150px">
            <button class="btn"><?= t('reports.generate') ?></button>
        </form>
    </div>
</div>

<div class="card" style="max-width:760px">
    <div class="card-header"><h2><?= t('reports.balance_sheet') ?> — <?= e($as_of) ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th></th><th class="num"><?= t('app.amount') ?> (<?= e($base['code']) ?>)</th></tr></thead>
            <tbody>
                <tr style="background:var(--surface-2)"><td colspan="2" style="font-weight:700"><?= t('reports.assets') ?></td></tr>
                <?php foreach ($report['assets'] as $a): ?>
                    <tr>
                        <td style="padding-inline-start:28px"><?= e($a['currency']['code']) ?> <?= e($a['currency']['name']) ?> <span class="text-muted">(<?= money((string)$a['amount'], $a['currency']) ?>)</span></td>
                        <td class="num"><?= money((string)$a['base_value'], $base) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td style="padding-inline-start:28px"><?= t('dashboard.receivables') ?></td>
                    <td class="num"><?= money((string)$report['receivables'], $base) ?></td>
                </tr>
                <tr style="font-weight:700">
                    <td><?= t('reports.assets') ?> — <?= t('reports.title') ?></td>
                    <td class="num text-green"><?= money((string)$report['asset_total'], $base) ?></td>
                </tr>

                <tr style="background:var(--surface-2)"><td colspan="2" style="font-weight:700"><?= t('reports.liabilities') ?></td></tr>
                <tr>
                    <td style="padding-inline-start:28px"><?= t('dashboard.payables') ?></td>
                    <td class="num text-red"><?= money((string)$report['payables'], $base) ?></td>
                </tr>

                <tr style="background:var(--surface-2)"><td colspan="2" style="font-weight:700"><?= t('reports.equity') ?></td></tr>
                <tr>
                    <td style="padding-inline-start:28px"><?= t('reports.equity') ?> (retained earnings)</td>
                    <td class="num"><?= money((string)$report['equity'], $base) ?></td>
                </tr>
                <tr style="font-weight:700;border-top:2px solid var(--border)">
                    <td style="font-weight:800"><?= t('reports.liabilities') ?> + <?= t('reports.equity') ?></td>
                    <td class="num" style="font-weight:800"><?= money(Money::add((string)$report['payables'], (string)$report['equity']), $base) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

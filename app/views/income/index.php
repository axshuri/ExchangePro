<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $totals @var string $from @var string $to */
$base = SettingService::baseCurrency();
$queryStr = http_build_query(array_filter(['from' => $from, 'to' => $to]));
?>
<div class="page-head">
    <h1><?= t('income.income') ?> <span class="text-muted">(<?= money((string)$totals['total'], $base) ?>)</span></h1>
    <div class="page-actions">
        <a href="/income/create" class="btn btn-primary btn-sm" data-ajax-form="/income/create" data-ajax-title="<?= t('income.new') ?>">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#plus"/></svg> <?= t('income.new') ?>
        </a>
    </div>
</div>

<div class="card">
    <div class="toolbar">
        <form method="get" action="/income">
            <input class="form-control" type="date" name="from" value="<?= e($from) ?>" style="width:150px">
            <input class="form-control" type="date" name="to" value="<?= e($to) ?>" style="width:150px">
            <button class="btn" type="submit"><?= t('app.search') ?></button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= t('income.date') ?></th>
                    <th><?= t('income.category') ?></th>
                    <th class="num"><?= t('income.amount') ?></th>
                    <th class="num"><?= t('tx.base_amount') ?> (<?= e($base['code']) ?>)</th>
                    <th><?= t('income.account') ?></th>
                    <th><?= t('income.description') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i): ?>
                    <tr>
                        <td class="mono"><?= e($i['ref_number']) ?></td>
                        <td><?= e($i['income_date']) ?></td>
                        <td><span class="pill pill-green"><?= e(ucfirst(str_replace('_', ' ', $i['category']))) ?></span></td>
                        <td class="num mono"><?= money((string)$i['amount'], ['symbol' => $i['currency_code']]) ?></td>
                        <td class="num"><?= money((string)$i['base_amount'], $base) ?></td>
                        <td><?= e($i['account_name'] ?? '—') ?></td>
                        <td><?= e($i['description'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="7"><div class="empty">—</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php View::partial('pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'query' => $queryStr]); ?>
</div>

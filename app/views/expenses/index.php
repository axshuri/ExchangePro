<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $totals @var array $categories @var string $from @var string $to @var string $category */
$base = SettingService::baseCurrency();
$queryStr = http_build_query(array_filter(['from' => $from, 'to' => $to, 'category' => $category]));
?>
<div class="page-head">
    <h1><?= t('expense.expenses') ?> <span class="text-muted">(<?= money((string)$totals['total'], $base) ?>)</span></h1>
    <div class="page-actions">
        <a href="/expenses/create" class="btn btn-primary btn-sm" data-ajax-form="/expenses/create" data-ajax-title="<?= t('expense.new') ?>">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#plus"/></svg> <?= t('expense.new') ?>
        </a>
        <a href="/export/expenses?format=csv" class="btn btn-ghost btn-sm">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#download"/></svg> CSV
        </a>
    </div>
</div>

<div class="card">
    <div class="toolbar">
        <form method="get" action="/expenses">
            <select class="form-select" name="category" style="width:170px">
                <option value=""><?= t('expense.category') ?></option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e(t('expense.cat_' . $cat, [], $cat)) ?></option>
                <?php endforeach; ?>
            </select>
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
                    <th><?= t('expense.date') ?></th>
                    <th><?= t('expense.category') ?></th>
                    <th class="num"><?= t('expense.amount') ?></th>
                    <th class="num"><?= t('tx.base_amount') ?> (<?= e($base['code']) ?>)</th>
                    <th><?= t('expense.account') ?></th>
                    <th><?= t('expense.description') ?></th>
                    <th><?= t('app.employee') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $e): ?>
                    <tr>
                        <td class="mono"><?= e($e['ref_number']) ?></td>
                        <td><?= e($e['expense_date']) ?></td>
                        <td><span class="pill pill-red"><?= e(t('expense.cat_' . $e['category'], [], ucfirst(str_replace('_', ' ', $e['category'])))) ?></span></td>
                        <td class="num mono"><?= money((string)$e['amount'], ['symbol' => $e['currency_code']]) ?></td>
                        <td class="num"><?= money((string)$e['base_amount'], $base) ?></td>
                        <td><?= e($e['account_name'] ?? '—') ?></td>
                        <td><?= e($e['description'] ?? '—') ?></td>
                        <td><?= e($e['employee_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="8"><div class="empty">—</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php View::partial('pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'query' => $queryStr]); ?>
</div>

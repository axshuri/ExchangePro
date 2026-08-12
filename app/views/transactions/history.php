<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $currencies @var array $customers
 *  @var string $q @var string $type @var int $currency_id @var int $customer_id @var string $status @var string $from @var string $to */
$base = SettingService::baseCurrency();
$queryStr = http_build_query(array_filter(['q' => $q, 'type' => $type, 'currency_id' => $currency_id, 'customer_id' => $customer_id, 'status' => $status, 'from' => $from, 'to' => $to]));
?>
<div class="page-head">
    <h1><?= t('tx.history') ?></h1>
    <div class="page-actions">
        <a href="/transactions/buy" class="btn btn-success btn-sm"><?= t('tx.buy') ?></a>
        <a href="/transactions/sell" class="btn btn-danger btn-sm"><?= t('tx.sell') ?></a>
        <a href="/transactions/exchange" class="btn btn-primary btn-sm"><?= t('tx.exchange') ?></a>
        <a href="/export/transactions?format=csv" class="btn btn-ghost btn-sm">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#download"/></svg> CSV
        </a>
    </div>
</div>

<div class="card">
    <div class="toolbar">
        <form method="get" action="/transactions">
            <div class="search-box">
                <svg class="icon"><use href="/assets/img/icons.svg#search"/></svg>
                <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="<?= t('app.search') ?> (#, <?= t('app.customer') ?>, phone)">
            </div>
            <select class="form-select" name="type" style="width:130px">
                <option value=""><?= t('app.type') ?>: <?= t('app.all') ?></option>
                <?php foreach (['buy', 'sell', 'exchange', 'reversal', 'adjustment'] as $tp): ?>
                    <option value="<?= $tp ?>" <?= $type === $tp ? 'selected' : '' ?>><?= t('tx.type.' . $tp) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="currency_id" style="width:110px">
                <option value=""><?= t('app.currency') ?></option>
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $currency_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="customer_id" style="width:160px">
                <option value=""><?= t('app.customer') ?></option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $customer_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="status" style="width:130px">
                <option value=""><?= t('app.status') ?></option>
                <?php foreach (['completed', 'cancelled', 'reversed', 'draft', 'pending'] as $st): ?>
                    <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= t('tx.status.' . $st) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control" type="date" name="from" value="<?= e($from) ?>" style="width:150px">
            <input class="form-control" type="date" name="to" value="<?= e($to) ?>" style="width:150px">
            <button class="btn" type="submit"><?= t('app.search') ?></button>
            <a class="btn btn-ghost" href="/transactions"><?= t('app.cancel') ?></a>
        </form>
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
                    <th class="num"><?= t('app.total') ?> (<?= e($base['code']) ?>)</th>
                    <th class="num"><?= t('tx.fee') ?></th>
                    <th><?= t('app.status') ?></th>
                    <th><?= t('app.employee') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $tx): ?>
                    <tr>
                        <td class="mono"><a href="/transactions/<?= (int)$tx['id'] ?>"><?= e($tx['tx_number']) ?></a></td>
                        <td><?= e(tz($tx['tx_date'], 'Y-m-d H:i')) ?></td>
                        <td>
                            <?php $cls = match($tx['type']) { 'buy' => 'pill-green', 'sell' => 'pill-red', 'exchange' => 'pill-blue', 'reversal' => 'pill-amber', default => 'pill-gray' }; ?>
                            <span class="pill <?= $cls ?>"><?= t('tx.type.' . $tx['type']) ?></span>
                            <?php if ($tx['is_large']): ?><span class="pill pill-amber"><?= t('tx.large') ?></span><?php endif; ?>
                        </td>
                        <td><?= e($tx['customer_name'] ?? '—') ?></td>
                        <td class="num mono"><?= money((string)$tx['foreign_amount'], ['symbol' => $tx['currency_code']]) ?></td>
                        <td class="num"><?= Money::format((string)$tx['rate'], 4) ?></td>
                        <td class="num"><?= money((string)$tx['base_amount'], $base) ?></td>
                        <td class="num"><?= money((string)$tx['fee_amount'], $base) ?></td>
                        <td>
                            <?php $sc = match($tx['status']) { 'completed' => 'pill-green', 'reversed' => 'pill-amber', 'cancelled' => 'pill-red', 'pending' => 'pill-blue', default => 'pill-gray' }; ?>
                            <span class="pill <?= $sc ?>"><?= t('tx.status.' . $tx['status']) ?></span>
                        </td>
                        <td><?= e($tx['employee_name'] ?? '—') ?></td>
                        <td class="right"><a href="/transactions/<?= (int)$tx['id'] ?>" class="btn btn-ghost btn-sm"><?= t('app.view') ?></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="11"><div class="empty"><?= t('dashboard.no_transactions') ?></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php View::partial('pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'query' => $queryStr]); ?>
</div>

<?php /** @var array $rows @var int $total @var int $page @var int $pages @var string $q */ ?>
<div class="page-head">
    <h1><?= t('customer.customers') ?> <span class="text-muted">(<?= $total ?>)</span></h1>
    <div class="page-actions">
        <a href="/customers/create" class="btn btn-primary btn-sm" data-ajax-form="/customers/create" data-ajax-title="<?= t('customer.new') ?>">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#plus"/></svg> <?= t('customer.new') ?>
        </a>
        <a href="/export/customers?format=csv" class="btn btn-ghost btn-sm">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#download"/></svg> CSV
        </a>
    </div>
</div>

<div class="card">
    <div class="toolbar">
        <form method="get" action="/customers">
            <div class="search-box">
                <svg class="icon"><use href="/assets/img/icons.svg#search"/></svg>
                <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="<?= t('app.search') ?> (<?= t('customer.full_name') ?>, phone, <?= t('customer.code') ?>)">
            </div>
            <button class="btn" type="submit"><?= t('app.search') ?></button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= t('customer.code') ?></th>
                    <th><?= t('customer.full_name') ?></th>
                    <th><?= t('customer.phone') ?></th>
                    <th><?= t('customer.email') ?></th>
                    <th class="num"><?= t('reports.transactions') ?></th>
                    <th class="num"><?= t('customer.net_balance') ?></th>
                    <th><?= t('app.status') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $c): ?>
                    <tr>
                        <td class="mono"><?= e($c['code']) ?></td>
                        <td><a href="/customers/<?= (int)$c['id'] ?>"><strong><?= e($c['full_name']) ?></strong></a></td>
                        <td><?= e($c['phone'] ?? '—') ?></td>
                        <td><?= e($c['email'] ?? '—') ?></td>
                        <td class="num"><?= (int)$c['tx_count'] ?></td>
                        <td class="num <?= Money::isPositive((string)$c['net_balance']) ? 'text-green' : (Money::isNegative((string)$c['net_balance']) ? 'text-red' : '') ?>">
                            <?= money((string)$c['net_balance'], \SettingService::baseCurrency()) ?>
                        </td>
                        <td>
                            <span class="pill <?= $c['status'] === 'active' ? 'pill-green' : ($c['status'] === 'blocked' ? 'pill-red' : 'pill-gray') ?>"><?= e($c['status']) ?></span>
                        </td>
                        <td class="right"><a href="/customers/<?= (int)$c['id'] ?>" class="btn btn-ghost btn-sm"><?= t('app.view') ?></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="8"><div class="empty">—</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php View::partial('pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'query' => $q ? 'q=' . urlencode($q) : '']); ?>
</div>

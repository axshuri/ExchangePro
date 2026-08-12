<?php /** @var array $rows @var int $total @var int $page @var int $pages */ ?>
<div class="page-head">
    <h1><?= t('transfer.transfers') ?></h1>
    <div class="page-actions">
        <a href="/transfers/create" class="btn btn-primary btn-sm" data-ajax-form="/transfers/create" data-ajax-title="<?= t('transfer.new') ?>">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#plus"/></svg> <?= t('transfer.new') ?>
        </a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= t('app.date') ?></th>
                    <th><?= t('transfer.source') ?></th>
                    <th><?= t('transfer.destination') ?></th>
                    <th class="num"><?= t('transfer.amount') ?></th>
                    <th class="num"><?= t('tx.base_amount') ?></th>
                    <th><?= t('app.employee') ?></th>
                    <th><?= t('app.status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $tr): ?>
                    <tr>
                        <td class="mono"><?= e($tr['ref_number']) ?></td>
                        <td><?= e($tr['transfer_date']) ?></td>
                        <td><?= e($tr['source_name']) ?></td>
                        <td><?= e($tr['destination_name']) ?></td>
                        <td class="num mono"><?= money((string)$tr['amount'], ['symbol' => $tr['currency_code']]) ?></td>
                        <td class="num"><?= money((string)$tr['base_amount'], \SettingService::baseCurrency()) ?></td>
                        <td><?= e($tr['employee_name'] ?? '—') ?></td>
                        <td><span class="pill pill-green"><?= e($tr['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="8"><div class="empty">—</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php View::partial('pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'query' => '']); ?>
</div>

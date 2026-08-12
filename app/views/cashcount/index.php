<?php /** @var array $rows */ ?>
<div class="page-head">
    <h1><?= t('cashcount.title') ?></h1>
    <div class="page-actions">
        <a href="/cash-count/create" class="btn btn-primary btn-sm" data-ajax-form="/cash-count/create" data-ajax-title="<?= t('cashcount.new') ?>" data-ajax-wide>
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#plus"/></svg> <?= t('cashcount.new') ?>
        </a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= t('cashcount.date') ?></th>
                    <th><?= t('cashcount.account') ?></th>
                    <th class="num"><?= t('cashcount.total') ?></th>
                    <th><?= t('app.status') ?></th>
                    <th><?= t('app.employee') ?></th>
                    <th><?= t('app.notes') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $c): ?>
                    <tr>
                        <td class="mono"><?= e($c['count_number']) ?></td>
                        <td><?= e($c['count_date']) ?></td>
                        <td><?= e($c['account_name']) ?></td>
                        <td class="num"><?= money((string)$c['total'], \SettingService::baseCurrency()) ?></td>
                        <td><span class="pill <?= $c['status'] === 'confirmed' ? 'pill-green' : 'pill-amber' ?>"><?= e($c['status']) ?></span></td>
                        <td><?= e($c['employee_name']) ?></td>
                        <td><?= e($c['notes'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="7"><div class="empty">—</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

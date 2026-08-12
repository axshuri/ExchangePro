<?php /** @var array $rows @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('currency.currencies') ?></h1>
    <div class="page-actions">
        <a href="/currencies/create" class="btn btn-primary btn-sm" data-ajax-form="/currencies/create" data-ajax-title="<?= t('currency.new') ?>">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#plus"/></svg> <?= t('currency.new') ?>
        </a>
    </div>
</div>

<?php if ($base): ?>
<div class="progress-note">
    <svg class="icon"><use href="/assets/img/icons.svg#info"/></svg>
    <?= t('currency.base_hint') ?> — <?= e($base['code']) ?> (<?= e($base['name']) ?>)
</div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= t('currency.code') ?></th>
                    <th><?= t('currency.name') ?></th>
                    <th><?= t('currency.localized_name') ?></th>
                    <th><?= t('currency.symbol') ?></th>
                    <th class="num"><?= t('rates.buy') ?></th>
                    <th class="num"><?= t('rates.sell') ?></th>
                    <th class="num"><?= t('currency.amount_precision') ?></th>
                    <th><?= t('currency.is_base') ?></th>
                    <th><?= t('currency.is_active') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $c): ?>
                    <tr>
                        <td class="mono"><strong><?= e($c['code']) ?></strong></td>
                        <td><?= e($c['name']) ?></td>
                        <td><?= e($c['localized_name'] ?? '—') ?></td>
                        <td><?= e($c['symbol'] ?? '—') ?></td>
                        <td class="num mono"><?= Money::format((string)$c['buy_rate'], (int)$c['rate_precision']) ?></td>
                        <td class="num mono"><?= Money::format((string)$c['sell_rate'], (int)$c['rate_precision']) ?></td>
                        <td class="num"><?= (int)$c['amount_precision'] ?></td>
                        <td><?= $c['is_base'] ? '<span class="pill pill-blue">base</span>' : '—' ?></td>
                        <td><?= $c['is_active'] ? '<span class="pill pill-green">' . t('currency.is_active') . '</span>' : '<span class="pill pill-gray">' . t('app.status') . '</span>' ?></td>
                        <td class="right">
                            <a href="/currencies/<?= (int)$c['id'] ?>/edit" class="btn btn-ghost btn-sm"><?= t('app.edit') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

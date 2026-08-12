<?php /** @var array $rows @var string $type @var ?array $base @var array $currencies */ ?>
<div class="page-head">
    <h1><?= t('account.accounts') ?></h1>
    <div class="page-actions">
        <a href="/transfers/create" class="btn btn-sm" data-ajax-form="/transfers/create" data-ajax-title="<?= t('transfer.new') ?>"><?= t('transfer.new') ?></a>
        <a href="/accounts/create" class="btn btn-primary btn-sm" data-ajax-form="/accounts/create" data-ajax-title="<?= t('account.new') ?>">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#plus"/></svg> <?= t('account.new') ?>
        </a>
    </div>
</div>

<div class="card">
    <div class="toolbar">
        <form method="get" action="/accounts">
            <select class="form-select" name="type" style="width:180px">
                <option value=""><?= t('account.type') ?>: <?= t('app.all') ?></option>
                <?php foreach (['cash_desk', 'vault', 'bank', 'wallet', 'other'] as $tp): ?>
                    <option value="<?= $tp ?>" <?= $type === $tp ? 'selected' : '' ?>><?= t('account.' . $tp) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit"><?= t('app.search') ?></button>
        </form>
        <span class="spacer"></span>
        <a href="/reconciliation" class="btn btn-ghost btn-sm"><?= t('recon.title') ?></a>
    </div>
    <?php foreach ($rows as $a): ?>
    <div class="card-body" style="border-bottom:1px solid var(--border)">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
            <strong style="font-size:.95rem"><a href="/accounts/<?= (int)$a['id'] ?>"><?= e($a['name']) ?></a></strong>
            <span class="pill pill-gray"><?= t('account.' . $a['type']) ?></span>
            <span class="mono text-muted"><?= e($a['code']) ?></span>
            <?php if (!$a['is_active']): ?><span class="pill pill-red">inactive</span><?php endif; ?>
            <a href="/accounts/<?= (int)$a['id'] ?>/edit" class="btn btn-ghost btn-sm" style="margin-inline-start:auto"><?= t('app.edit') ?></a>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($a['balances'] as $b): ?>
                <span class="pill pill-blue" style="font-size:.82rem;padding:4px 12px">
                    <?= e($b['code']) ?>: <strong class="mono"><?= money((string)$b['balance'], $b) ?></strong>
                </span>
            <?php endforeach; ?>
            <?php if (!$a['balances']): ?><span class="text-muted" style="font-size:.8rem">—</span><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="empty">—</div><?php endif; ?>
</div>

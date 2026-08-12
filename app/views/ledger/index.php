<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $accounts @var string $from @var string $to @var int $account_id */
$base = SettingService::baseCurrency();
$queryStr = http_build_query(array_filter(['from' => $from, 'to' => $to, 'account_id' => $account_id]));
?>
<div class="page-head">
    <h1><?= t('ledger.title') ?></h1>
    <div class="page-actions">
        <a href="/export/ledger?format=csv" class="btn btn-ghost btn-sm">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#download"/></svg> CSV
        </a>
    </div>
</div>

<div class="progress-note">
    <svg class="icon"><use href="/assets/img/icons.svg#info"/></svg>
    <?= t('ledger.balanced') ?>
</div>

<div class="card">
    <div class="toolbar">
        <form method="get" action="/ledger">
            <input class="form-control" type="date" name="from" value="<?= e($from) ?>" style="width:150px">
            <input class="form-control" type="date" name="to" value="<?= e($to) ?>" style="width:150px">
            <select class="form-select" name="account_id" style="width:190px">
                <option value=""><?= t('account.accounts') ?></option>
                <?php foreach ($accounts as $a): ?>
                    <option value="<?= (int)$a['id'] ?>" <?= $account_id === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit"><?= t('app.search') ?></button>
        </form>
    </div>

    <?php foreach ($rows as $en): ?>
    <div class="card-body" style="border-bottom:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;flex-wrap:wrap;gap:6px">
            <div>
                <span class="mono text-strong"><?= e($en['entry_no']) ?></span>
                <span class="text-muted" style="font-size:.8rem"> · <?= e($en['description'] ?? '') ?></span>
            </div>
            <div style="font-size:.78rem;color:var(--text-3)">
                <?= e(tz($en['created_at'], 'Y-m-d H:i')) ?> · <?= e($en['user_name'] ?? '') ?>
                <?php if ($en['tx_number']): ?> · <a href="/transactions/<?= (int)($en['transaction_id']) ?>" class="mono"><?= e($en['tx_number']) ?></a><?php endif; ?>
            </div>
        </div>
        <div class="journal">
            <div class="jl" style="color:var(--text-3)">
                <span><?= t('ledger.account') ?></span><span class="d"><?= t('ledger.debit') ?></span><span class="c"><?= t('ledger.credit') ?></span><span><?= t('app.currency') ?></span>
            </div>
            <?php foreach ($en['lines'] as $l): ?>
                <div class="jl">
                    <span><?= e($l['account_name'] ?? $l['gl_name'] ?? '') ?></span>
                    <span class="d"><?= Money::isPositive((string)$l['base_debit']) ? money((string)$l['base_debit'], $base) : '' ?></span>
                    <span class="c"><?= Money::isPositive((string)$l['base_credit']) ? money((string)$l['base_credit'], $base) : '' ?></span>
                    <span class="text-muted"><?= e($l['currency_code']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="empty">—</div><?php endif; ?>
    <?php View::partial('pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'query' => $queryStr]); ?>
</div>

<?php /** @var array $account @var array $balances @var array $movements @var ?array $base */ ?>
<div class="page-head">
    <h1><?= e($account['name']) ?> <span class="text-muted mono"><?= e($account['code']) ?></span></h1>
    <div class="page-actions">
        <a href="/accounts/<?= (int)$account['id'] ?>/edit" class="btn btn-sm"><?= t('app.edit') ?></a>
        <a href="/transfers/create" class="btn btn-sm" data-ajax-form="/transfers/create" data-ajax-title="<?= t('transfer.new') ?>"><?= t('transfer.new') ?></a>
        <a href="/reconciliation" class="btn btn-ghost btn-sm"><?= t('recon.title') ?></a>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= t('account.balances') ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= t('app.currency') ?></th><th class="num"><?= t('app.amount') ?></th><th class="num"><?= t('dashboard.reference_value') ?></th></tr></thead>
            <tbody>
                <?php foreach ($balances as $b): ?>
                    <?php $rate = (int)$b['currency_id'] === (int)$base['id'] ? '1' : (Database::value("SELECT mid_rate FROM exchange_rates WHERE currency_id = ?", [$b['currency_id']]) ?: '1'); ?>
                    <tr>
                        <td><strong><?= e($b['code']) ?></strong> <?= e($b['symbol'] ?? '') ?></td>
                        <td class="num mono"><?= money((string)$b['balance'], $b) ?></td>
                        <td class="num"><?= money(Money::mul((string)$b['balance'], (string)$rate), $base) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$balances): ?><tr><td colspan="3" class="text-muted" style="text-align:center;padding:20px">—</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><h2><?= t('account.movements') ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= t('app.date') ?></th><th><?= t('app.currency') ?></th><th><?= t('app.type') ?></th><th class="num"><?= t('tx.amount') ?></th><th class="num"><?= t('tx.base_amount') ?></th><th class="num"><?= t('tx.available') ?></th><th><?= t('app.notes') ?></th></tr></thead>
            <tbody>
                <?php foreach ($movements as $m): ?>
                    <tr>
                        <td class="text-muted"><?= e(tz($m['created_at'], 'Y-m-d H:i')) ?></td>
                        <td><?= e($m['currency_code']) ?></td>
                        <td><span class="pill <?= $m['direction'] === 'in' ? 'pill-green' : 'pill-red' ?>"><?= $m['direction'] === 'in' ? 'IN' : 'OUT' ?></span></td>
                        <td class="num mono"><?= money((string)$m['amount'], ['symbol' => $m['currency_code']]) ?></td>
                        <td class="num"><?= money((string)$m['base_amount'], $base) ?></td>
                        <td class="num mono"><?= money((string)$m['balance_after'], ['symbol' => $m['currency_code']]) ?></td>
                        <td><?= e($m['note'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$movements): ?><tr><td colspan="7"><div class="empty">—</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

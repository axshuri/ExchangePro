<?php /** @var array $customer @var array $balances */ ?>
<div class="page-head">
    <h1><?= t('customer.receivable') ?> / <?= t('customer.payable') ?> — <?= e($customer['full_name']) ?></h1>
    <div class="page-actions"><a href="/customers/<?= (int)$customer['id'] ?>" class="btn btn-ghost btn-sm"><?= t('customer.profile') ?></a></div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert-item alert-info mb-2">
            <svg class="icon"><use href="/assets/img/icons.svg#info"/></svg>
            <span><?= t('customer.balance_hint') ?></span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= t('app.currency') ?></th><th class="num"><?= t('app.amount') ?></th><th><?= t('reports.title') ?></th></tr></thead>
                <tbody>
                    <?php foreach ($balances as $b): ?>
                        <tr>
                            <td><strong><?= e($b['code']) ?></strong></td>
                            <td class="num mono"><?= money((string)$b['balance'], $b) ?></td>
                            <td>
                                <?php if (Money::isPositive((string)$b['balance'])): ?>
                                    <span class="pill pill-green"><?= t('customer.receivable') ?></span>
                                <?php else: ?>
                                    <span class="pill pill-red"><?= t('customer.payable') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$balances): ?><tr><td colspan="3" class="text-muted" style="text-align:center;padding:24px">—</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

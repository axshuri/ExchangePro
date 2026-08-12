<?php
/** @var array $customer @var array $balances @var array $stats @var array $txs @var int $total @var int $page @var int $pages @var array $audit */
$base = SettingService::baseCurrency();
?>
<div class="page-head">
    <h1><?= e($customer['full_name']) ?> <span class="text-muted mono"><?= e($customer['code']) ?></span></h1>
    <div class="page-actions">
        <a href="/customers/<?= (int)$customer['id'] ?>/edit" class="btn btn-sm"><?= t('app.edit') ?></a>
        <a href="/transactions/buy" class="btn btn-success btn-sm"><?= t('tx.buy') ?></a>
        <a href="/transactions/sell" class="btn btn-danger btn-sm"><?= t('tx.sell') ?></a>
        <a href="/customers" class="btn btn-ghost btn-sm"><?= t('customer.customers') ?></a>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat-card">
        <div class="stat-label"><?= t('customer.total_purchases') ?></div>
        <div class="stat-value"><?= money($stats['total_buy'], $base) ?></div>
        <div class="stat-sub"><?= e($base['code']) ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('customer.total_sales') ?></div>
        <div class="stat-value"><?= money($stats['total_sell'], $base) ?></div>
        <div class="stat-sub"><?= e($base['code']) ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('customer.fees_paid') ?></div>
        <div class="stat-value"><?= money($stats['total_fees'], $base) ?></div>
        <div class="stat-sub"><?= $stats['tx_count'] ?> <?= t('reports.transactions') ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('customer.net_balance') ?></div>
        <?php $net = '0'; foreach ($balances as $b) { $net = Money::add($net, (string)$b['balance']); } ?>
        <div class="stat-value <?= Money::isPositive($net) ? 'stat-positive' : (Money::isNegative($net) ? 'stat-negative' : '') ?>">
            <?= money($net, $base) ?>
        </div>
        <div class="stat-sub"><?= t('customer.balance_hint') ?></div>
    </div>
</div>

<div class="grid-2">
    <div class="stack">
        <div class="card">
            <div class="card-header"><h2><?= t('customer.balances') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= t('app.currency') ?></th><th class="num"><?= t('app.amount') ?></th><th><?= t('reports.title') ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($balances as $b): ?>
                            <tr>
                                <td><strong><?= e($b['code']) ?></strong> <?= e($b['symbol'] ?? '') ?></td>
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
                        <?php if (!$balances): ?><tr><td colspan="3" class="text-muted" style="text-align:center;padding:20px">—</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2><?= t('tx.history') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>#</th><th><?= t('app.date') ?></th><th><?= t('app.type') ?></th><th class="num"><?= t('app.amount') ?></th><th class="num"><?= t('app.rate') ?></th><th class="num"><?= t('app.total') ?></th><th><?= t('app.status') ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($txs as $tx): ?>
                            <tr>
                                <td class="mono"><a href="/transactions/<?= (int)$tx['id'] ?>"><?= e($tx['tx_number']) ?></a></td>
                                <td><?= e(tz($tx['tx_date'], 'Y-m-d H:i')) ?></td>
                                <td><?php $cls = match($tx['type']) { 'buy' => 'pill-green', 'sell' => 'pill-red', 'exchange' => 'pill-blue', 'reversal' => 'pill-amber', default => 'pill-gray' }; ?>
                                    <span class="pill <?= $cls ?>"><?= t('tx.type.' . $tx['type']) ?></span></td>
                                <td class="num mono"><?= money((string)$tx['foreign_amount'], ['symbol' => $tx['currency_code']]) ?></td>
                                <td class="num"><?= Money::format((string)$tx['rate'], 4) ?></td>
                                <td class="num"><?= money((string)$tx['base_amount'], $base) ?></td>
                                <td><span class="pill <?= $tx['status'] === 'completed' ? 'pill-green' : 'pill-amber' ?>"><?= t('tx.status.' . $tx['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$txs): ?><tr><td colspan="7"><div class="empty">—</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php View::partial('pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'query' => '']); ?>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-header"><h2><?= t('app.notes') ?></h2></div>
            <div class="card-body">
                <p><?= e($customer['notes'] ?? '—') ?></p>
                <dl class="detail-list mt-2">
                    <div class="detail-item"><dt><?= t('customer.phone') ?></dt><dd><?= e($customer['phone'] ?? '—') ?></dd></div>
                    <div class="detail-item"><dt><?= t('customer.email') ?></dt><dd><?= e($customer['email'] ?? '—') ?></dd></div>
                    <div class="detail-item"><dt><?= t('customer.id_type') ?></dt><dd><?= e($customer['id_type'] ?? '—') ?></dd></div>
                    <div class="detail-item"><dt><?= t('customer.id_number') ?></dt><dd><?= e($customer['id_number'] ?? '—') ?></dd></div>
                    <div class="detail-item"><dt><?= t('app.date') ?></dt><dd><?= e(tz($customer['created_at'], 'Y-m-d')) ?></dd></div>
                    <div class="detail-item"><dt><?= t('customer.address') ?></dt><dd><?= e($customer['address'] ?? '—') ?></dd></div>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2><?= t('customer.audit') ?></h2></div>
            <div class="card-body flush">
                <table class="table">
                    <thead><tr><th><?= t('audit.action') ?></th><th><?= t('audit.user') ?></th><th><?= t('app.date') ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($audit as $a): ?>
                            <tr>
                                <td><span class="pill pill-gray"><?= e($a['action']) ?></span></td>
                                <td><?= e($a['username'] ?? '—') ?></td>
                                <td class="text-muted"><?= e(tz($a['created_at'], 'm-d H:i')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

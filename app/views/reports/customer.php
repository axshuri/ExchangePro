<?php /** @var array $customers @var int $customer_id @var string $from @var string $to @var ?array $report @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('reports.customer') ?></h1>
    <div class="page-actions"><a href="/reports" class="btn btn-ghost btn-sm"><?= t('reports.title') ?></a></div>
</div>

<div class="card mb-2">
    <div class="toolbar">
        <form method="get" action="/reports/customer">
            <select class="form-select" name="customer_id" style="width:220px">
                <option value=""><?= t('app.customer') ?></option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $customer_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['full_name']) ?> (<?= e($c['code']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <input class="form-control" type="date" name="from" value="<?= e($from) ?>" style="width:150px">
            <input class="form-control" type="date" name="to" value="<?= e($to) ?>" style="width:150px">
            <button class="btn"><?= t('reports.generate') ?></button>
        </form>
    </div>
</div>

<?php if ($report): ?>
    <?php $s = $report['stats']; ?>
    <div class="stat-grid">
        <div class="card stat-card"><div class="stat-label"><?= t('customer.total_purchases') ?></div>
            <div class="stat-value"><?= money($s['total_buy'], $base) ?></div></div>
        <div class="card stat-card"><div class="stat-label"><?= t('customer.total_sales') ?></div>
            <div class="stat-value"><?= money($s['total_sell'], $base) ?></div></div>
        <div class="card stat-card"><div class="stat-label"><?= t('customer.fees_paid') ?></div>
            <div class="stat-value"><?= money($s['total_fees'], $base) ?></div></div>
        <div class="card stat-card"><div class="stat-label"><?= t('reports.transactions') ?></div>
            <div class="stat-value"><?= (int)$s['tx_count'] ?></div></div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= e($report['customer']['full_name']) ?> — <?= t('tx.history') ?></h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th><?= t('app.date') ?></th><th><?= t('app.type') ?></th><th class="num"><?= t('tx.amount') ?></th><th class="num"><?= t('tx.rate') ?></th><th class="num"><?= t('tx.base_amount') ?></th><th><?= t('app.status') ?></th></tr></thead>
                <tbody>
                    <?php foreach ($report['txs'] as $tx): ?>
                        <tr>
                            <td class="mono"><a href="/transactions/<?= (int)$tx['id'] ?>"><?= e($tx['tx_number']) ?></a></td>
                            <td><?= e(tz($tx['tx_date'], 'Y-m-d H:i')) ?></td>
                            <td><?= t('tx.type.' . $tx['type']) ?></td>
                            <td class="num mono"><?= money((string)$tx['foreign_amount'], ['symbol' => $tx['currency_code']]) ?></td>
                            <td class="num"><?= Money::format((string)$tx['rate'], 6) ?></td>
                            <td class="num"><?= money((string)$tx['base_amount'], $base) ?></td>
                            <td><span class="pill pill-green"><?= t('tx.status.' . $tx['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$report['txs']): ?><tr><td colspan="7"><div class="empty">—</div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

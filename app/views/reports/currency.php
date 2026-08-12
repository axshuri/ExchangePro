<?php /** @var array $currencies @var int $currency_id @var string $from @var string $to @var ?array $report @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('reports.currency') ?></h1>
    <div class="page-actions"><a href="/reports" class="btn btn-ghost btn-sm"><?= t('reports.title') ?></a></div>
</div>

<div class="card mb-2">
    <div class="toolbar">
        <form method="get" action="/reports/currency">
            <select class="form-select" name="currency_id" style="width:160px">
                <option value=""><?= t('app.currency') ?></option>
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $currency_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control" type="date" name="from" value="<?= e($from) ?>" style="width:150px">
            <input class="form-control" type="date" name="to" value="<?= e($to) ?>" style="width:150px">
            <button class="btn"><?= t('reports.generate') ?></button>
        </form>
    </div>
</div>

<?php if ($report): ?>
    <?php $c = $report['currency']; $cost = $report['costing']; ?>
    <div class="stat-grid">
        <div class="card stat-card">
            <div class="stat-label"><?= e($c['code']) ?> <?= t('reports.opening') ?></div>
            <div class="stat-value"><?= money((string)($report['movements'][0]['opening'] ?? '0'), $c) ?></div>
        </div>
        <div class="card stat-card">
            <div class="stat-label"><?= t('reports.closing') ?></div>
            <div class="stat-value"><?= money((string)$cost['qty'], $c) ?></div>
            <div class="stat-sub"><?= t('reports.closing') ?></div>
        </div>
        <div class="card stat-card">
            <div class="stat-label"><?= t('dashboard.avg_cost') ?></div>
            <div class="stat-value"><?= money((string)$cost['avg_cost'], $base) ?></div>
            <div class="stat-sub"><?= e($base['code']) ?></div>
        </div>
        <div class="card stat-card">
            <div class="stat-label"><?= t('dashboard.reference_value') ?></div>
            <div class="stat-value"><?= money(Money::mul((string)$cost['qty'], (string)($c['id'] == $base['id'] ? '1' : (Database::value("SELECT mid_rate FROM exchange_rates WHERE currency_id = ?", [$c['id']]) ?: '1'))), $base) ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= e($c['code']) ?> — <?= t('reports.movements') ?></h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= t('app.date') ?></th><th><?= t('app.type') ?></th><th class="num"><?= t('tx.amount') ?></th><th class="num"><?= t('tx.base_amount') ?></th></tr></thead>
                <tbody>
                    <?php foreach ($report['movements'] as $m): ?>
                        <tr>
                            <td><?= e(substr((string)$m['tx_date'], 0, 10)) ?></td>
                            <td><?= e($m['type'] ?? 'opening') ?></td>
                            <td class="num mono"><?= money((string)$m['amount'], $c) ?></td>
                            <td class="num"><?= money((string)$m['base_amount'], $base) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$report['movements']): ?><tr><td colspan="4"><div class="empty">—</div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-header"><h2><?= t('tx.history') ?></h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th><?= t('app.date') ?></th><th><?= t('app.type') ?></th><th><?= t('app.customer') ?></th><th class="num"><?= t('tx.amount') ?></th><th class="num"><?= t('tx.rate') ?></th><th class="num"><?= t('tx.base_amount') ?></th></tr></thead>
                <tbody>
                    <?php foreach ($report['transactions'] as $tx): ?>
                        <tr>
                            <td class="mono"><a href="/transactions/<?= (int)$tx['id'] ?>"><?= e($tx['tx_number']) ?></a></td>
                            <td><?= e(tz($tx['tx_date'], 'Y-m-d H:i')) ?></td>
                            <td><?= t('tx.type.' . $tx['type']) ?></td>
                            <td><?= e($tx['customer_name'] ?? '—') ?></td>
                            <td class="num mono"><?= money((string)$tx['foreign_amount'], $c) ?></td>
                            <td class="num"><?= Money::format((string)$tx['rate'], 6) ?></td>
                            <td class="num"><?= money((string)$tx['base_amount'], $base) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

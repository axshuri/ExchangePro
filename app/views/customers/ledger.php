<?php
/** @var array $customer @var array $data @var array $balances @var array $currencies @var array $f @var array $base */
$rows = $data['rows'];
$totals = $data['totals'];
$typeLabels = ['buy' => 'pill-green', 'sell' => 'pill-red', 'exchange' => 'pill-blue', 'reversal' => 'pill-amber', 'adjustment' => 'pill-gray', 'fee' => 'pill-blue'];
?>
<div class="page-head">
    <h1><?= t('customer.ledger') ?> — <?= e($customer['full_name']) ?> <span class="text-muted mono"><?= e($customer['code']) ?></span></h1>
    <div class="page-actions">
        <a href="/customers/<?= (int)$customer['id'] ?>" class="btn btn-ghost btn-sm"><?= t('customer.profile') ?></a>
        <a href="/customers/<?= (int)$customer['id'] ?>/receivables" class="btn btn-ghost btn-sm"><?= t('customer.receivable') ?> / <?= t('customer.payable') ?></a>
        <a href="/customers/<?= (int)$customer['id'] ?>/ledger/export?<?= http_build_query(array_filter($f)) ?>" class="btn btn-ghost btn-sm">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#download"/></svg> CSV
        </a>
    </div>
</div>

<?php if ($balances): ?>
<div class="card mb-2" style="max-width:640px">
    <div class="card-header"><h2><?= t('customer.balances') ?></h2></div>
    <div class="card-body" style="padding:10px 18px">
        <div class="detail-list">
            <?php foreach ($balances as $b): ?>
                <div class="detail-item">
                    <dt><?= e($b['code']) ?> <?= e(currencyName($b)) ?></dt>
                    <dd class="mono <?= Money::isNegative((string)$b['balance']) ? 'text-red' : 'text-green' ?>">
                        <?= money((string)$b['balance'], $b) ?>
                        <span class="pill <?= Money::isNegative((string)$b['balance']) ? 'pill-red' : 'pill-green' ?>" style="font-size:.65rem">
                            <?= Money::isNegative((string)$b['balance']) ? t('customer.payable') : t('customer.receivable') ?>
                        </span>
                    </dd>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<form method="get" action="/customers/<?= (int)$customer['id'] ?>/ledger" class="card mb-2">
    <div class="toolbar">
        <input class="form-control" type="date" name="from" value="<?= e($f['from'] ?? '') ?>" aria-label="<?= t('app.from') ?>">
        <input class="form-control" type="date" name="to" value="<?= e($f['to'] ?? '') ?>" aria-label="<?= t('app.to') ?>">
        <select class="form-select" name="currency_id" aria-label="<?= t('app.currency') ?>">
            <option value=""><?= t('app.all') ?> <?= t('app.currency') ?></option>
            <?php foreach ($currencies as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)($f['currency_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['code']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select" name="type" aria-label="<?= t('app.type') ?>">
            <option value=""><?= t('app.all') ?> <?= t('app.type') ?></option>
            <?php foreach (['buy', 'sell', 'exchange', 'reversal', 'adjustment'] as $t): ?>
                <option value="<?= $t ?>" <?= ($f['type'] ?? '') === $t ? 'selected' : '' ?>><?= t('tx.type.' . $t) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit"><?= t('app.search') ?></button>
        <a href="/customers/<?= (int)$customer['id'] ?>/ledger" class="btn btn-ghost"><?= t('app.all') ?></a>
    </div>
</form>

<div class="card">
    <div class="card-header"><h2><?= t('customer.ledger') ?> <span class="text-muted">(<?= count($rows) ?>)</span></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= t('app.date') ?></th>
                    <th><?= t('app.type') ?></th>
                    <th><?= t('app.currency') ?></th>
                    <th class="num"><?= t('tx.amount') ?></th>
                    <th class="num"><?= t('app.rate') ?></th>
                    <th class="num"><?= t('tx.base_amount') ?> (<?= e($base['code']) ?>)</th>
                    <th class="num"><?= t('tx.fee') ?></th>
                    <th class="num"><?= t('tx.discount') ?></th>
                    <th class="num"><?= t('app.total') ?></th>
                    <th><?= t('app.status') ?></th>
                    <th><?= t('app.employee') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $t): ?>
                    <tr>
                        <td class="mono"><a href="/transactions/<?= (int)$t['id'] ?>"><?= e($t['tx_number']) ?></a></td>
                        <td class="text-muted"><?= e(tz($t['tx_date'], 'Y-m-d H:i')) ?></td>
                        <td><span class="pill <?= $typeLabels[$t['type']] ?? 'pill-gray' ?>"><?= t('tx.type.' . $t['type']) ?></span></td>
                        <td><?= e($t['currency_code'] ?? '—') ?></td>
                        <td class="num mono"><?= money((string)$t['foreign_amount'], ['symbol' => $t['currency_code'] ?? $base['code']]) ?></td>
                        <td class="num"><?= Money::format((string)$t['rate'], 4) ?></td>
                        <td class="num"><?= money((string)$t['base_amount'], $base) ?></td>
                        <td class="num"><?= Money::isZero((string)$t['fee_base']) ? '—' : money((string)$t['fee_base'], $base) ?></td>
                        <td class="num"><?= Money::isZero((string)$t['discount_amount']) ? '—' : money((string)$t['discount_amount'], $base) ?></td>
                        <td class="num"><?= money((string)$t['total_amount'], $base) ?></td>
                        <td>
                            <?php $sc = $t['status'] === 'completed' ? 'pill-green' : ($t['status'] === 'reversed' ? 'pill-amber' : 'pill-gray'); ?>
                            <span class="pill <?= $sc ?>"><?= t('tx.status.' . $t['status']) ?></span>
                        </td>
                        <td><?= e($t['employee_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="12"><div class="empty">—</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totals): ?>
    <div class="card-body" style="padding:10px 18px">
        <h3 style="font-size:.8rem;margin-bottom:6px"><?= t('customer.period_totals') ?></h3>
        <div class="detail-list">
            <?php foreach ($totals as $tt): ?>
                <div class="detail-item">
                    <dt><?= e($tt['code']) ?></dt>
                    <dd class="mono">
                        <?= t('tx.buy') ?>: <span class="text-green"><?= money((string)$tt['buy_base'], $base) ?></span> ·
                        <?= t('tx.sell') ?>: <span class="text-red"><?= money((string)$tt['sell_base'], $base) ?></span>
                    </dd>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

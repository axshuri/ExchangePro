<?php
/** @var string $today @var array $status @var array $daily @var array $accounts @var array $currencies @var array $positions @var array $history @var array $checks @var array $summary @var bool $canApprove */
$base = SettingService::baseCurrency();
$posByKey = [];
foreach ($positions as $p) { $posByKey[$p['account_id'] . ':' . $p['currency_id']] = $p; }
?>
<div class="page-head">
    <h1><?= t('closing.title') ?> — <?= e($today) ?></h1>
    <div class="page-actions"><a href="/reports/daily?date=<?= e($today) ?>" class="btn btn-ghost btn-sm"><?= t('closing.report') ?></a></div>
</div>

<?php if (!$status['opened']): ?>
<div class="card mb-2" style="max-width:520px">
    <div class="card-body">
        <div class="alert-item alert-info mb-2">
            <svg class="icon"><use href="/assets/img/icons.svg#info"/></svg>
            <span><?= t('closing.open') ?></span>
        </div>
        <form method="post" action="/closing/start">
            <?= Csrf::field() ?>
            <input type="hidden" name="closing_date" value="<?= e($today) ?>">
            <button class="btn btn-primary" type="submit"><?= t('closing.start') ?></button>
        </form>
    </div>
</div>
<?php else: ?>
    <div class="progress-note">
        <svg class="icon"><use href="/assets/img/icons.svg#info"/></svg>
        <?= $status['row']['status'] === 'closed' ? t('closing.closed') : t('closing.in_progress') ?>
        (<?= e(tz($status['row']['opened_at'], 'Y-m-d H:i')) ?><?= $status['row']['closed_at'] ? ' → ' . e(tz($status['row']['closed_at'], 'Y-m-d H:i')) : '' ?>)
    </div>
<?php endif; ?>

<?php if ($status['opened'] && $status['row']['status'] !== 'closed' && $status['row']['status'] !== 'approved'): ?>
<div class="card mb-2">
    <div class="card-header"><h2><?= t('closing.complete') ?></h2></div>
    <div class="card-body">
        <form method="post" action="/closing/complete">
            <?= Csrf::field() ?>
            <input type="hidden" name="closing_date" value="<?= e($today) ?>">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= t('recon.account') ?></th>
                            <th><?= t('recon.currency') ?></th>
                            <th class="num"><?= t('closing.system') ?></th>
                            <th class="num"><?= t('closing.physical') ?></th>
                            <th class="num"><?= t('closing.diff') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $a): ?>
                            <?php foreach ($currencies as $c): ?>
                                <?php $key = $a['id'] . ':' . $c['id']; $sys = $posByKey[$key]['amount'] ?? '0'; ?>
                                <tr>
                                    <td><?= e($a['name']) ?></td>
                                    <td><?= e($c['code']) ?></td>
                                    <td class="num mono"><?= money($sys, $c) ?></td>
                                    <td class="num">
                                        <input class="form-control" style="width:120px;text-align:end" type="number" step="any"
                                               name="physical[<?= e($key) ?>]" value="<?= e($sys) ?>">
                                    </td>
                                    <td class="num mono" id="diff-<?= e($key) ?>">0</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-group mt-2">
                <label class="form-label" for="notes"><?= t('closing.notes') ?></label>
                <textarea class="form-textarea" id="notes" name="notes"></textarea>
            </div>
            <div class="form-actions">
                <button class="btn btn-success" type="submit"><?= t('closing.complete') ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($status['opened'] && $status['row']['status'] === 'closed'): ?>
<div class="card mb-2" style="max-width:640px">
    <div class="card-header"><h2><?= t('closing.approve_title') ?></h2></div>
    <div class="card-body">
        <div class="alert-item alert-info mb-2">
            <svg class="icon"><use href="/assets/img/icons.svg#info"/></svg>
            <span><?= t('closing.approve_hint') ?></span>
        </div>
        <div class="form-actions" style="justify-content:flex-start;gap:8px">
            <?php if ($canApprove): ?>
            <form method="post" action="/closing/approve" style="display:inline">
                <?= Csrf::field() ?>
                <input type="hidden" name="closing_date" value="<?= e($today) ?>">
                <button class="btn btn-success" type="submit"><?= t('closing.approve') ?></button>
            </form>
            <form method="post" action="/closing/reopen" style="display:inline">
                <?= Csrf::field() ?>
                <input type="hidden" name="closing_date" value="<?= e($today) ?>">
                <button class="btn btn-ghost" type="submit"><?= t('closing.reopen') ?></button>
            </form>
            <?php else: ?>
            <span class="text-muted" style="font-size:.8rem"><?= t('closing.approve_requires_manager') ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($status['opened'] && in_array($status['row']['status'], ['closed', 'approved'], true)): ?>
<div class="card mb-2" style="max-width:640px">
    <div class="card-header"><h2><?= t('closing.reopen_title') ?></h2></div>
    <div class="card-body">
        <?php if ($canApprove): ?>
        <form method="post" action="/closing/reopen">
            <?= Csrf::field() ?>
            <input type="hidden" name="closing_date" value="<?= e($today) ?>">
            <button class="btn btn-ghost" type="submit"><?= t('closing.reopen') ?></button>
        </form>
        <?php else: ?>
        <span class="text-muted" style="font-size:.8rem"><?= t('closing.reopen_requires_manager') ?></span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($status['opened']): ?>
<div class="card mb-2">
    <div class="card-header"><h2><?= t('closing.checks_title') ?></h2></div>
    <div class="card-body" style="padding:10px 18px">
        <div class="detail-list">
            <?php foreach ($checks as $c): ?>
                <div class="detail-item">
                    <dt><?= t('closing.check.' . $c['key']) ?></dt>
                    <dd>
                        <span class="pill <?= $c['ok'] ? 'pill-green' : 'pill-amber' ?>">
                            <?= $c['ok'] ? t('closing.check.ok') : t('closing.check.warn') ?>
                        </span>
                        <span class="text-muted" style="font-size:.78rem"><?= e($c['message']) ?></span>
                    </dd>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header"><h2><?= t('closing.currency_summary') ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr>
                <th><?= t('app.currency') ?></th>
                <th class="num"><?= t('closing.opening') ?></th>
                <th class="num"><?= t('closing.purchased') ?></th>
                <th class="num"><?= t('closing.sold') ?></th>
                <th class="num"><?= t('closing.expected') ?></th>
                <th class="num"><?= t('closing.current') ?></th>
                <th class="num"><?= t('closing.diff') ?></th>
            </tr></thead>
            <tbody>
                <?php foreach ($summary['rows'] as $s): ?>
                    <?php $diff = (string)$s['difference']; ?>
                    <tr>
                        <td><strong><?= e($s['currency']['code']) ?></strong></td>
                        <td class="num mono"><?= money((string)$s['opening'], $s['currency']) ?></td>
                        <td class="num mono text-green"><?= money((string)$s['bought'], $s['currency']) ?></td>
                        <td class="num mono text-red"><?= money((string)$s['sold'], $s['currency']) ?></td>
                        <td class="num mono"><?= money((string)$s['expected'], $s['currency']) ?></td>
                        <td class="num mono"><?= money((string)$s['current'], $s['currency']) ?></td>
                        <td class="num mono <?= Money::isZero($diff) ? '' : (Money::isNegative($diff) ? 'text-red' : 'text-green') ?>">
                            <?= money($diff, $s['currency']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2><?= t('closing.history') ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= t('app.date') ?></th><th><?= t('app.status') ?></th><th><?= t('closing.open') ?> by</th><th><?= t('closing.closed') ?> by</th><th><?= t('closing.notes') ?></th></tr></thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= e($h['closing_date']) ?></td>
                        <td><span class="pill <?= $h['status'] === 'closed' ? 'pill-green' : 'pill-amber' ?>"><?= t('closing.' . $h['status']) ?></span></td>
                        <td><?= e($h['opened_by_name'] ?? '—') ?></td>
                        <td><?= e($h['closed_by_name'] ?? '—') ?></td>
                        <td><?= e($h['notes'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$history): ?><tr><td colspan="5"><div class="empty">—</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><h2><?= t('closing.report') ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= t('reports.trading_profit') ?></th><th><?= t('dashboard.expenses') ?></th><th><?= t('dashboard.other_income') ?></th><th><?= t('dashboard.net_profit') ?></th><th><?= t('reports.transactions') ?></th></tr></thead>
            <tbody>
                <tr>
                    <td class="num"><?= money($daily['trading_profit'], $base) ?></td>
                    <td class="num"><?= money($daily['expense_total'], $base) ?></td>
                    <td class="num"><?= money($daily['income_total'], $base) ?></td>
                    <td class="num <?= Money::isNegative($daily['net_profit']) ? 'text-red' : 'text-green' ?>"><?= money($daily['net_profit'], $base) ?></td>
                    <td class="num"><?= $daily['tx_count'] ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php
/** @var array $rows @var array $accounts @var array $currencies @var array $positions */
$canAdjust = Auth::hasPermission('adjust_balance');
?>
<div class="page-head">
    <h1><?= t('recon.title') ?></h1>
    <div class="page-actions"><a href="/cash-count/create" class="btn btn-ghost btn-sm" data-ajax-form="/cash-count/create" data-ajax-title="<?= t('cashcount.new') ?>" data-ajax-wide><?= t('cashcount.new') ?></a></div>
</div>

<?php if (Auth::hasPermission('perform_reconciliation')): ?>
<div class="card mb-2">
    <div class="card-header"><h2><?= t('recon.title') ?></h2></div>
    <div class="card-body">
        <div class="alert-item alert-info mb-2">
            <svg class="icon"><use href="/assets/img/icons.svg#info"/></svg>
            <span><?= t('recon.hint') ?></span>
        </div>
        <form method="post" action="/reconciliation">
            <?= Csrf::field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="account_id"><?= t('recon.account') ?> <span class="req">*</span></label>
                    <select class="form-select" id="account_id" name="account_id" required>
                        <option value="">—</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="currency_id"><?= t('recon.currency') ?> <span class="req">*</span></label>
                    <select class="form-select" id="currency_id" name="currency_id" required>
                        <option value="">—</option>
                        <?php foreach ($currencies as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="physical_balance"><?= t('recon.physical') ?> <span class="req">*</span></label>
                    <input class="form-control" type="number" step="any" id="physical_balance" name="physical_balance" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="reason"><?= t('recon.reason') ?> <span class="req">*</span></label>
                    <input class="form-control" type="text" id="reason" name="reason" required>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= t('recon.title') ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2><?= t('recon.title') ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= t('recon.account') ?></th>
                    <th><?= t('recon.currency') ?></th>
                    <th class="num"><?= t('recon.system') ?></th>
                    <th class="num"><?= t('recon.physical') ?></th>
                    <th class="num"><?= t('recon.difference') ?></th>
                    <th><?= t('recon.reason') ?></th>
                    <th><?= t('app.status') ?></th>
                    <th><?= t('audit.user') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="mono"><?= e($r['rec_number']) ?></td>
                        <td><?= e($r['account_name']) ?></td>
                        <td><?= e($r['currency_code']) ?></td>
                        <td class="num mono"><?= money((string)$r['system_balance'], ['symbol' => $r['currency_code']]) ?></td>
                        <td class="num mono"><?= money((string)$r['physical_balance'], ['symbol' => $r['currency_code']]) ?></td>
                        <td class="num mono <?= Money::isNegative((string)$r['difference']) ? 'text-red' : (Money::isPositive((string)$r['difference']) ? 'text-green' : '') ?>">
                            <?= money((string)$r['difference'], ['symbol' => $r['currency_code']]) ?>
                        </td>
                        <td><?= e($r['reason'] ?? '—') ?></td>
                        <td>
                            <?php $sc = match($r['status']) { 'approved' => 'pill-green', 'pending' => 'pill-amber', 'rejected' => 'pill-red' }; ?>
                            <span class="pill <?= $sc ?>"><?= t('recon.status.' . $r['status']) ?></span>
                        </td>
                        <td><?= e($r['created_by_name']) ?></td>
                        <td class="right">
                            <?php if ($r['status'] === 'pending' && $canAdjust): ?>
                                <form method="post" action="/reconciliation/<?= (int)$r['id'] ?>/approve" style="display:inline">
                                    <?= Csrf::field() ?>
                                    <button class="btn btn-success btn-sm"><?= t('recon.approve') ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="10"><div class="empty">—</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

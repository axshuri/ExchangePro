<?php
/** @var array $tx @var array $entries @var array $items @var array $fees @var array $movements @var array $audit */
$base = SettingService::baseCurrency();
$canCancel = in_array($tx['status'], ['completed'], true) && !$tx['original_transaction_id'];
?>
<div class="page-head">
    <h1><?= t('tx.detail') ?> <span class="mono"><?= e($tx['tx_number']) ?></span></h1>
    <div class="page-actions">
        <a href="/transactions/<?= (int)$tx['id'] ?>/receipt" class="btn btn-primary btn-sm">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#print"/></svg> <?= t('receipt.title') ?>
        </a>
        <?php if ($canCancel && Auth::hasPermission('cancel_transaction')): ?>
            <button class="btn btn-danger btn-sm" onclick="openModal('cancelModal')"><?= t('tx.cancel_tx') ?></button>
        <?php endif; ?>
        <a href="/transactions" class="btn btn-ghost btn-sm"><?= t('tx.history') ?></a>
    </div>
</div>

<?php if ($tx['original_transaction_id']): ?>
    <div class="progress-note">
        <svg class="icon"><use href="/assets/img/icons.svg#info"/></svg>
        <?= t('tx.reversal_of') ?>: <a href="/transactions/<?= (int)$tx['original_transaction_id'] ?>">#<?= (int)$tx['original_transaction_id'] ?></a>
    </div>
<?php endif; ?>
<?php if ($tx['reversal_transaction_id']): ?>
    <div class="progress-note">
        <svg class="icon"><use href="/assets/img/icons.svg#info"/></svg>
        <?= t('tx.type.reversal') ?>: <a href="/transactions/<?= (int)$tx['reversal_transaction_id'] ?>">#<?= (int)$tx['reversal_transaction_id'] ?></a>
    </div>
<?php endif; ?>

<div class="grid-2">
    <div class="stack">
        <div class="card">
            <div class="card-header"><h2><?= t('tx.detail') ?></h2></div>
            <div class="card-body">
                <dl class="detail-list">
                    <div class="detail-item"><dt><?= t('app.status') ?></dt>
                        <dd><?php $sc = match($tx['status']) { 'completed' => 'pill-green', 'reversed' => 'pill-amber', 'cancelled' => 'pill-red', default => 'pill-gray' }; ?>
                            <span class="pill <?= $sc ?>"><?= t('tx.status.' . $tx['status']) ?></span></dd></div>
                    <div class="detail-item"><dt><?= t('app.type') ?></dt>
                        <dd><?= t('tx.type.' . $tx['type']) ?> <?= $tx['is_large'] ? '<span class="pill pill-amber">' . t('tx.large') . '</span>' : '' ?></dd></div>
                    <div class="detail-item"><dt><?= t('app.date') ?></dt><dd><?= e(tz($tx['tx_date'], 'Y-m-d H:i:s')) ?></dd></div>
                    <div class="detail-item"><dt><?= t('app.customer') ?></dt><dd><?= e($tx['customer_name'] ?? '—') ?></dd></div>
                    <div class="detail-item"><dt><?= t('app.employee') ?></dt><dd><?= e($tx['employee_id']) ?></dd></div>
                    <div class="detail-item"><dt><?= t('app.currency') ?></dt><dd><?= e($tx['currency_code'] ?? '—') ?></dd></div>
                    <div class="detail-item"><dt><?= t('tx.amount') ?></dt><dd><?= money((string)$tx['foreign_amount'], ['symbol' => $tx['currency_code']]) ?></dd></div>
                    <div class="detail-item"><dt><?= t('tx.rate') ?></dt><dd class="mono"><?= Money::format((string)$tx['rate'], 6) ?></dd></div>
                    <div class="detail-item"><dt><?= t('tx.base_amount') ?> (<?= e($base['code']) ?>)</dt><dd><?= money((string)$tx['base_amount'], $base) ?></dd></div>
                    <div class="detail-item"><dt><?= t('tx.fee') ?></dt><dd><?= money((string)$tx['fee_amount'], $base) ?></dd></div>
                    <div class="detail-item"><dt><?= t('tx.discount') ?></dt><dd><?= money((string)$tx['discount_amount'], $base) ?></dd></div>
                    <div class="detail-item"><dt><?= t('tx.total') ?> (<?= e($base['code']) ?>)</dt><dd class="text-strong"><?= money((string)$tx['total_amount'], $base) ?></dd></div>
                    <div class="detail-item"><dt><?= t('tx.payment_method') ?></dt><dd><?= t('tx.' . ($tx['payment_method'] ?: 'cash')) ?></dd></div>
                    <div class="detail-item"><dt><?= t('tx.source_account') ?></dt><dd><?= e($tx['source_account_name'] ?? '—') ?></dd></div>
                    <div class="detail-item"><dt><?= t('tx.destination_account') ?></dt><dd><?= e($tx['destination_account_name'] ?? '—') ?></dd></div>
                    <?php if ($tx['notes']): ?><div class="detail-item full"><dt><?= t('app.notes') ?></dt><dd><?= e($tx['notes']) ?></dd></div><?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($items): ?>
        <div class="card">
            <div class="card-header"><h2><?= t('tx.items') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= t('tx.source_currency') ?></th><th class="num"><?= t('tx.amount') ?></th><th><?= t('tx.target_currency') ?></th><th class="num"><?= t('tx.amount') ?></th><th class="num"><?= t('tx.cross_rate') ?></th><th class="num"><?= t('tx.base_amount') ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td><?= e($it['source_code']) ?></td><td class="num mono"><?= money((string)$it['source_amount'], ['symbol' => $it['source_code']]) ?></td>
                                <td><?= e($it['target_code']) ?></td><td class="num mono"><?= money((string)$it['target_amount'], ['symbol' => $it['target_code']]) ?></td>
                                <td class="num mono"><?= Money::format((string)$it['rate'], 6) ?></td>
                                <td class="num"><?= money((string)$it['base_amount'], $base) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($fees): ?>
        <div class="card">
            <div class="card-header"><h2><?= t('tx.fees') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= t('app.type') ?></th><th class="num"><?= t('app.amount') ?></th><th class="num"><?= t('tx.base_amount') ?></th><th><?= t('app.notes') ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($fees as $f): ?>
                            <tr>
                                <td><?= e($f['type']) ?></td>
                                <td class="num mono"><?= money((string)$f['amount'], ['symbol' => $f['currency_code']]) ?></td>
                                <td class="num"><?= money((string)$f['base_amount'], $base) ?></td>
                                <td><?= e($f['description'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Journal entries -->
        <div class="card">
            <div class="card-header"><h2><?= t('tx.journal_entries') ?></h2></div>
            <div class="card-body">
                <?php foreach ($entries as $en): ?>
                    <div style="margin-bottom:14px">
                        <div style="display:flex;justify-content:space-between;font-size:.78rem;color:var(--text-3)">
                            <span class="mono"><?= e($en['entry_no']) ?></span>
                            <span><?= e($en['description']) ?> · <?= e(tz($en['created_at'], 'Y-m-d H:i')) ?> · <?= e($en['user_name'] ?? '') ?></span>
                        </div>
                        <div class="journal mt-1">
                            <div class="jl" style="color:var(--text-3)">
                                <span><?= t('ledger.account') ?></span><span class="d"><?= t('ledger.debit') ?></span><span class="c"><?= t('ledger.credit') ?></span><span><?= t('app.currency') ?></span>
                            </div>
                            <?php foreach ($en['lines'] as $l): ?>
                                <div class="jl">
                                    <span><?= e($l['account_name'] ?? $l['gl_name'] ?? '') ?></span>
                                    <span class="d"><?= Money::isPositive((string)$l['debit']) ? money((string)$l['debit'], ['symbol' => $l['currency_code']]) : '' ?></span>
                                    <span class="c"><?= Money::isPositive((string)$l['credit']) ? money((string)$l['credit'], ['symbol' => $l['currency_code']]) : '' ?></span>
                                    <span class="text-muted"><?= e($l['currency_code']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Inventory movements -->
        <div class="card">
            <div class="card-header"><h2><?= t('tx.inventory_movements') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= t('account.name') ?></th><th><?= t('app.currency') ?></th><th><?= t('app.type') ?></th><th class="num"><?= t('tx.amount') ?></th><th class="num"><?= t('tx.rate') ?></th><th class="num"><?= t('tx.base_amount') ?></th><th class="num"><?= t('tx.available') ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($movements as $m): ?>
                            <tr>
                                <td><?= e($m['account_name']) ?></td>
                                <td><?= e($m['currency_code']) ?></td>
                                <td><span class="pill <?= $m['direction'] === 'in' ? 'pill-green' : 'pill-red' ?>"><?= $m['direction'] === 'in' ? 'IN' : 'OUT' ?></span></td>
                                <td class="num mono"><?= money((string)$m['amount'], ['symbol' => $m['currency_code']]) ?></td>
                                <td class="num mono"><?= Money::format((string)$m['rate'], 6) ?></td>
                                <td class="num"><?= money((string)$m['base_amount'], $base) ?></td>
                                <td class="num mono"><?= money((string)$m['balance_after'], ['symbol' => $m['currency_code']]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$movements): ?><tr><td colspan="7" class="text-muted" style="text-align:center;padding:18px">—</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-header"><h2><?= t('audit.title') ?></h2></div>
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

<!-- Cancel modal -->
<?php if ($canCancel && Auth::hasPermission('cancel_transaction')): ?>
<div class="modal-backdrop" id="cancelModal" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
    <div class="modal">
        <form method="post" action="/transactions/<?= (int)$tx['id'] ?>/cancel">
            <?= Csrf::field() ?>
            <div class="modal-head">
                <h3 id="cancelModalTitle"><?= t('tx.cancel_tx') ?></h3>
                <button type="button" class="icon-btn" aria-label="<?= t('app.close') ?>" onclick="closeModal('cancelModal')"><svg class="icon"><use href="/assets/img/icons.svg#x"/></svg></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><?= t('tx.cancelled') ?> — <?= e($tx['tx_number']) ?></p>
                <p class="form-hint mb-2"><?= t('tx.reason_required') ?></p>
                <div class="form-group">
                    <label class="form-label" for="reason"><?= t('tx.cancel_reason') ?> <span class="req">*</span></label>
                    <textarea class="form-textarea" id="reason" name="reason" required></textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" onclick="closeModal('cancelModal')"><?= t('app.cancel') ?></button>
                <button type="submit" class="btn btn-danger"><?= t('app.confirm') ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

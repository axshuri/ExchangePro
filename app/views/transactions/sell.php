<?php
/** @var array $rates @var array $base @var array $accounts @var array $customers @var array $inventory @var string $large_threshold */
$rateMap = [];
foreach ($rates as $r) { $rateMap[$r['id']] = ['buy' => (string)$r['buy_rate'], 'sell' => (string)$r['sell_rate']]; }
?>
<div class="page-head">
    <h1><?= t('tx.sell') ?></h1>
    <div class="page-actions"><a href="/transactions" class="btn btn-ghost btn-sm"><?= t('tx.history') ?></a></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2><?= t('tx.new_sell') ?></h2></div>
        <div class="card-body">
            <form method="post" action="/transactions/sell" id="txCalculator" data-mode="sell" data-submit-guard>
                <?= Csrf::field() ?>
                <?php if (isset($_GET['large'])): ?>
                    <div class="alert-item alert-warning mb-2">
                        <svg class="icon"><use href="/assets/img/icons.svg#alert-triangle"/></svg>
                        <span><?= t('tx.large_confirm') ?></span>
                    </div>
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="customer_id"><?= t('tx.customer_optional') ?></label>
                        <?php View::partial('customer_picker', ['pickerId' => 'sellCustomerPicker']); ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="currency_id"><?= t('tx.currency') ?> <span class="req">*</span></label>
                        <select class="form-select" id="currency_id" name="currency_id" required>
                            <option value="">—</option>
                            <?php foreach ($inventory as $inv): ?>
                                <option value="<?= (int)$inv['currency_id'] ?>"><?= e($inv['code']) ?> — <?= t('tx.available') ?>: <?= Money::format((string)$inv['qty'], 2) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint" id="rateHint"></small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="foreign_amount"><?= t('tx.amount') ?> <span class="req">*</span></label>
                        <input class="form-control" type="number" step="any" min="0" id="foreign_amount" name="foreign_amount" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="rate"><?= t('tx.rate') ?> (<?= e($base['code']) ?>) <span class="req">*</span></label>
                        <input class="form-control" type="number" step="any" min="0" id="rate" name="rate" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fee_type"><?= t('tx.fee') ?></label>
                        <div class="quick-ctl">
                            <select class="form-select" id="fee_type" name="fee_type" style="max-width:110px">
                                <option value="fixed"><?= t('rates.fixed') ?></option>
                                <option value="percent"><?= t('rates.percent') ?> (%)</option>
                            </select>
                            <input class="form-control" type="number" step="any" min="0" id="fee_amount" name="fee_amount" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="discount_type"><?= t('tx.discount') ?></label>
                        <div class="quick-ctl">
                            <select class="form-select" id="discount_type" name="discount_type" style="max-width:110px">
                                <option value="fixed"><?= t('rates.fixed') ?></option>
                                <option value="percent"><?= t('rates.percent') ?> (%)</option>
                            </select>
                            <input class="form-control" type="number" step="any" min="0" id="discount_amount" name="discount_amount" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="account_id"><?= t('tx.source_account') ?> <span class="req">*</span></label>
                        <select class="form-select" id="account_id" name="account_id" required>
                            <option value="">—</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= t('account.' . $a['type']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="destination_account_id"><?= t('tx.destination_account') ?> (<?= e($base['code']) ?>) <span class="req">*</span></label>
                        <select class="form-select" id="destination_account_id" name="destination_account_id" required>
                            <option value="">—</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= t('account.' . $a['type']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="payment_method"><?= t('tx.payment_method') ?></label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <option value="cash"><?= t('tx.cash') ?></option>
                            <option value="bank_transfer"><?= t('tx.bank_transfer') ?></option>
                            <option value="card"><?= t('tx.card') ?></option>
                            <option value="internal_balance"><?= t('tx.internal_balance') ?></option>
                            <option value="other"><?= t('tx.other') ?></option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="notes"><?= t('tx.notes') ?></label>
                        <textarea class="form-textarea" id="notes" name="notes"></textarea>
                    </div>
                </div>

                <div class="receipt-total mt-2">
                    <span><?= t('tx.total') ?> (<?= t('tx.destination_account') ?>)</span>
                    <span class="amt" id="calcTotal">0</span>
                </div>
                <p class="form-hint mt-1"><?= t('tx.base_amount') ?>: <span id="calcBaseAmount">0</span> &middot; <?= t('tx.fee') ?>: <span id="calcFeeBase">0</span></p>

                <input type="hidden" name="large_confirmed" value="<?= isset($_GET['large']) ? '1' : '0' ?>">

                <div class="form-actions">
                    <button class="btn btn-success btn-lg" type="submit">
                        <svg class="icon"><use href="/assets/img/icons.svg#check-square"/></svg>
                        <?= t('tx.confirm') ?>
                    </button>
                    <a href="/" class="btn btn-ghost"><?= t('app.cancel') ?></a>
                </div>
            </form>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-header"><h2><?= t('dashboard.currency_inventory') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= t('app.currency') ?></th><th class="num"><?= t('tx.available') ?></th><th class="num"><?= t('dashboard.avg_cost') ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($inventory as $inv): ?>
                            <tr>
                                <td><strong><?= e($inv['code']) ?></strong> <?= e(currencyName($inv)) ?></td>
                                <td class="num mono"><?= money((string)$inv['qty'], $inv) ?></td>
                                <td class="num"><?= money((string)$inv['avg_cost'], $base) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$inventory): ?><tr><td colspan="3" class="text-muted" style="text-align:center;padding:20px">—</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php View::partial('customer_picker_modal', ['pickerId' => 'sellCustomerPicker']); ?>
<script>window.EXCHANGE_RATES = <?= json_encode($rateMap) ?>;</script>

<?php
/** @var array $rates @var array $base @var array $accounts @var array $customers @var string $large_threshold */
$rateMap = [];
foreach ($rates as $r) { $rateMap[$r['id']] = ['buy' => (string)$r['buy_rate'], 'sell' => (string)$r['sell_rate']]; }
$pending = Session::get('large_pending');
if ($pending) Session::remove('large_pending');
?>
<div class="page-head">
    <h1><?= t('tx.buy') ?></h1>
    <div class="page-actions"><a href="/transactions" class="btn btn-ghost btn-sm"><?= t('tx.history') ?></a></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2><?= t('tx.new_buy') ?></h2></div>
        <div class="card-body">
            <form method="post" action="/transactions/buy" id="txCalculator" data-mode="buy" data-submit-guard>
                <?= Csrf::field() ?>
                <?php if (isset($_GET['large'])): ?>
                    <div class="alert-item alert-warning mb-2">
                        <svg class="icon"><use href="/assets/img/icons.svg#alert-triangle"/></svg>
                        <span><?= t('tx.large_confirm') ?> (<?= t('settings.large_tx_threshold') ?>: <?= money($large_threshold, $base) ?>)</span>
                    </div>
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="customer_id"><?= t('tx.customer_optional') ?></label>
                        <?php View::partial('customer_picker', ['pickerId' => 'buyCustomerPicker']); ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="currency_id"><?= t('tx.currency') ?> <span class="req">*</span></label>
                        <select class="form-select" id="currency_id" name="currency_id" required>
                            <option value="">—</option>
                            <?php foreach ($rates as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= e($r['code']) ?> — <?= e(currencyName($r)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="foreign_amount"><?= t('tx.amount') ?> <span class="req">*</span></label>
                        <input class="form-control" type="number" step="any" min="0" id="foreign_amount" name="foreign_amount" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="rate"><?= t('tx.rate') ?> (<?= e($base['code']) ?>) <span class="req">*</span></label>
                        <input class="form-control" type="number" step="any" min="0" id="rate" name="rate" required>
                        <small class="form-hint" id="rateHint"></small>
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
                        <small class="form-hint"><?= t('tx.fee_currency') ?>: <?= e($base['code']) ?></small>
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
                        <small class="form-hint"><?= t('tx.currency') ?> <?= t('tx.amount') ?> <?= t('tx.source_account') ?></small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="source_account_id"><?= t('tx.destination_account') ?> (<?= e($base['code']) ?>) <span class="req">*</span></label>
                        <select class="form-select" id="source_account_id" name="source_account_id" required>
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
            <div class="card-header"><h2><?= t('rates.title') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= t('app.currency') ?></th><th class="num"><?= t('rates.buy') ?></th><th class="num"><?= t('rates.sell') ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($rates as $r): ?>
                            <tr>
                                <td><strong><?= e($r['code']) ?></strong> <?= e(currencyName($r)) ?></td>
                                <td class="num mono"><?= Money::format((string)$r['buy_rate'], (int)$r['rate_precision']) ?></td>
                                <td class="num mono"><?= Money::format((string)$r['sell_rate'], (int)$r['rate_precision']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body"><a href="/rates" class="btn btn-sm"><?= t('rates.title') ?></a></div>
        </div>
    </div>
</div>
<?php View::partial('customer_picker_modal', ['pickerId' => 'buyCustomerPicker']); ?>
<script>window.EXCHANGE_RATES = <?= json_encode($rateMap) ?>;</script>

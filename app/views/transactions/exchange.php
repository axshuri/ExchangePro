<?php
/** @var array $rates @var array $base @var array $accounts @var array $customers @var array $inventory @var string $large_threshold */
$rateMap = [];
foreach ($rates as $r) { $rateMap[$r['id']] = ['buy' => (string)$r['buy_rate'], 'sell' => (string)$r['sell_rate']]; }
?>
<div class="page-head">
    <h1><?= t('tx.exchange') ?></h1>
    <div class="page-actions"><a href="/transactions" class="btn btn-ghost btn-sm"><?= t('tx.history') ?></a></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2><?= t('tx.new_exchange') ?> — <?= t('tx.source_currency') ?> → <?= t('tx.target_currency') ?></h2></div>
        <div class="card-body">
            <form method="post" action="/transactions/exchange" id="exchangeCalc" data-submit-guard>
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
                        <?php View::partial('customer_picker', ['pickerId' => 'exchangeCustomerPicker']); ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="source_currency_id"><?= t('tx.source_currency') ?> <span class="req">*</span></label>
                        <select class="form-select" id="source_currency_id" name="source_currency_id" required>
                            <option value="">—</option>
                            <?php foreach ($rates as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= e($r['code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="target_currency_id"><?= t('tx.target_currency') ?> <span class="req">*</span></label>
                        <select class="form-select" id="target_currency_id" name="target_currency_id" required>
                            <option value="">—</option>
                            <?php foreach ($inventory as $inv): ?>
                                <option value="<?= (int)$inv['currency_id'] ?>"><?= e($inv['code']) ?> (<?= t('tx.available') ?>: <?= Money::format((string)$inv['qty'], 2) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="source_amount"><?= t('tx.amount') ?> (<?= t('tx.source_currency') ?>) <span class="req">*</span></label>
                        <input class="form-control" type="number" step="any" min="0" id="source_amount" name="source_amount" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="target_amount"><?= t('tx.amount') ?> (<?= t('tx.target_currency') ?>)</label>
                        <input class="form-control" type="number" step="any" id="target_amount" name="target_amount" readonly>
                        <small class="form-hint"><?= t('tx.cross_rate') ?>: <span id="cross_rate"></span></small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fee_amount"><?= t('tx.fee') ?></label>
                        <input class="form-control" type="number" step="any" min="0" id="fee_amount" name="fee_amount" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="discount_amount"><?= t('tx.discount') ?></label>
                        <input class="form-control" type="number" step="any" min="0" id="discount_amount" name="discount_amount" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="source_account_id"><?= t('tx.source_account') ?> (<?= t('tx.source_currency') ?>) <span class="req">*</span></label>
                        <select class="form-select" id="source_account_id" name="source_account_id" required>
                            <option value="">—</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="destination_account_id"><?= t('tx.destination_account') ?> (<?= t('tx.target_currency') ?>) <span class="req">*</span></label>
                        <select class="form-select" id="destination_account_id" name="destination_account_id" required>
                            <option value="">—</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option>
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
                                <td><strong><?= e($r['code']) ?></strong></td>
                                <td class="num mono"><?= Money::format((string)$r['buy_rate'], (int)$r['rate_precision']) ?></td>
                                <td class="num mono"><?= Money::format((string)$r['sell_rate'], (int)$r['rate_precision']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2><?= t('tx.inventory') ?></h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= t('app.currency') ?></th><th class="num"><?= t('tx.available') ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($inventory as $inv): ?>
                            <tr><td><strong><?= e($inv['code']) ?></strong></td><td class="num mono"><?= money((string)$inv['qty'], $inv) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php View::partial('customer_picker_modal', ['pickerId' => 'exchangeCustomerPicker']); ?>
<script>window.EXCHANGE_RATES = <?= json_encode($rateMap) ?>;</script>

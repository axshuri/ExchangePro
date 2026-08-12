<?php
/** @var array $rates @var array $base @var array $accounts @var int $default_currency_id @var string $default_direction @var ?int $default_cash_account */
$threshold = SettingService::largeTxThreshold();
?>
<div class="page-head">
    <h1><?= t('quick.title') ?></h1>
    <div class="page-actions">
        <span class="text-muted" style="font-size:.74rem">
            <kbd>F1</kbd> <?= t('tx.buy') ?> · <kbd>F2</kbd> <?= t('tx.sell') ?> · <kbd>F3</kbd> <?= t('quick.customer') ?> ·
            <kbd>F4</kbd> <?= t('quick.currency') ?> · <kbd>F5</kbd> <?= t('quick.calc') ?> · <kbd>ESC</kbd> <?= t('app.cancel') ?>
        </span>
    </div>
</div>

<div class="card quick-card" style="max-width:920px">
    <div class="card-body">
        <form method="post" action="/quick" id="quickForm" data-submit-guard
              data-large-threshold="<?= e($threshold) ?>"
              data-base-code="<?= e($base['code']) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="direction" id="quickDirection" value="<?= e($default_direction) ?>">
            <input type="hidden" name="large_confirmed" id="quickLargeConfirmed" value="0">

            <div class="quick-direction" role="group" aria-label="<?= t('quick.direction') ?>">
                <button type="button" class="quick-dir quick-buy <?= $default_direction === 'buy' ? 'active' : '' ?>"
                        data-dir="buy" aria-pressed="<?= $default_direction === 'buy' ? 'true' : 'false' ?>">
                    <svg class="icon" aria-hidden="true"><use href="/assets/img/icons.svg#arrow-down-circle"/></svg>
                    <?= t('tx.buy') ?>
                </button>
                <button type="button" class="quick-dir quick-sell <?= $default_direction === 'sell' ? 'active' : '' ?>"
                        data-dir="sell" aria-pressed="<?= $default_direction === 'sell' ? 'true' : 'false' ?>">
                    <svg class="icon" aria-hidden="true"><use href="/assets/img/icons.svg#arrow-up-circle"/></svg>
                    <?= t('tx.sell') ?>
                </button>
            </div>

            <div class="quick-currencies" id="quickCurrencies">
                <?php foreach ($rates as $r): ?>
                    <button type="button" class="quick-cur <?= (int)$r['id'] === (int)$default_currency_id ? 'active' : '' ?>"
                            data-id="<?= (int)$r['id'] ?>" data-buy="<?= e((string)$r['buy_rate']) ?>" data-sell="<?= e((string)$r['sell_rate']) ?>"
                            data-prec="<?= (int)$r['rate_precision'] ?>" data-code="<?= e($r['code']) ?>">
                        <strong><?= e($r['code']) ?></strong>
                        <small><?= t('rates.buy') ?> <?= Money::format((string)$r['buy_rate'], (int)$r['rate_precision']) ?></small>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="quick-main">
                <div class="form-group">
                    <label class="form-label" for="quickAmount"><?= t('tx.amount') ?> <span class="req">*</span></label>
                    <input class="form-control quick-amount" type="number" step="any" min="0" id="quickAmount" name="foreign_amount" autofocus required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="quickRate"><?= t('tx.rate') ?> (<?= e($base['code']) ?>) <span class="req">*</span></label>
                    <input class="form-control quick-rate" type="number" step="any" min="0" id="quickRate" name="rate" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= t('tx.total') ?> (<?= e($base['code']) ?>)</label>
                    <div class="quick-total <?= $default_direction === 'sell' ? 'is-sell' : '' ?>" id="quickTotal">0</div>
                </div>
            </div>

            <div class="quick-row">
                <div class="form-group">
                    <label class="form-label" for="quickCustomer"><?= t('tx.customer_optional') ?></label>
                    <?php View::partial('customer_picker', ['pickerId' => 'quickCustomerPicker']); ?>
                </div>
                <div class="form-group">
                    <label class="form-label" for="quickAccount"><?= t('quick.currency_account') ?></label>
                    <select class="form-select" id="quickAccount" name="account_id">
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="quickCash"><?= t('quick.cash_account') ?></label>
                    <select class="form-select" id="quickCash" name="cash_account_id">
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['id'] ?>" <?= (int)$a['id'] === (int)($default_cash_account ?? 0) ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <details class="quick-details">
                <summary><?= t('quick.optional') ?></summary>
                <div class="quick-row">
                    <div class="form-group">
                        <label class="form-label" for="quickFee"><?= t('tx.fee') ?></label>
                        <div class="quick-ctl">
                            <select class="form-select" name="fee_type">
                                <option value="fixed"><?= t('rates.fixed') ?></option>
                                <option value="percent"><?= t('rates.percent') ?> (%)</option>
                            </select>
                            <input class="form-control" type="number" step="any" min="0" id="quickFee" name="fee_amount" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="quickDiscount"><?= t('tx.discount') ?></label>
                        <div class="quick-ctl">
                            <select class="form-select" name="discount_type">
                                <option value="fixed"><?= t('rates.fixed') ?></option>
                                <option value="percent"><?= t('rates.percent') ?> (%)</option>
                            </select>
                            <input class="form-control" type="number" step="any" min="0" id="quickDiscount" name="discount_amount" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="quickMethod"><?= t('tx.payment_method') ?></label>
                        <select class="form-select" id="quickMethod" name="payment_method">
                            <option value="cash"><?= t('tx.cash') ?></option>
                            <option value="bank_transfer"><?= t('tx.bank_transfer') ?></option>
                            <option value="card"><?= t('tx.card') ?></option>
                            <option value="internal_balance"><?= t('tx.internal_balance') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="quickNotes"><?= t('tx.notes') ?></label>
                        <input class="form-control" id="quickNotes" name="notes">
                    </div>
                </div>
            </details>

            <div class="form-actions">
                <button class="btn btn-success btn-lg btn-block" type="submit" id="quickConfirm">
                    <svg class="icon"><use href="/assets/img/icons.svg#check-square"/></svg>
                    <?= t('quick.confirm') ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php View::partial('customer_picker_modal', ['pickerId' => 'quickCustomerPicker']); ?>

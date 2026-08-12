<?php
/** @var array $rates @var array $base @var array $accounts @var int $default_currency_id
 *  @var int|null $default_cash_account @var int|null $default_currency_account @var string $large_threshold */
$rateMap = [];
foreach ($rates as $r) {
    $rateMap[$r['id']] = [
        'buy' => (string)$r['buy_rate'], 'sell' => (string)$r['sell_rate'],
        'ap' => (int)$r['amount_precision'], 'rp' => (int)$r['rate_precision'],
    ];
}
$pending = Session::get('large_pending');
if ($pending) Session::remove('large_pending');
?>
<div class="page-head">
    <h1><?= t('calc.title') ?></h1>
    <div class="page-actions">
        <a href="/quick" class="btn btn-ghost btn-sm"><?= t('quick.title') ?></a>
        <a href="/transactions" class="btn btn-ghost btn-sm"><?= t('tx.history') ?></a>
    </div>
</div>

<?php if (isset($_GET['large'])): ?>
    <div class="alert-item alert-warning mb-2">
        <svg class="icon"><use href="/assets/img/icons.svg#alert-triangle"/></svg>
        <span><?= t('tx.large_confirm') ?> (<?= t('settings.large_tx_threshold') ?>: <?= money($large_threshold, $base) ?>)</span>
    </div>
<?php endif; ?>

<div class="grid-2 calc-layout">
    <!-- ============ Inputs ============ -->
    <div class="card">
        <div class="card-header"><h2><?= t('calc.card_title') ?></h2></div>
        <div class="card-body">
            <form method="post" action="/calculator" id="calcForm"
                  data-base-code="<?= e($base['code']) ?>"
                  data-base-prec="<?= (int)($base['amount_precision'] ?? 2) ?>"
                  data-large-threshold="<?= e($large_threshold) ?>"
                  data-receives="<?= e(t('calc.customer_receives')) ?>"
                  data-pays="<?= e(t('calc.customer_pays')) ?>"
                  data-result="<?= e(t('calc.result')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="direction" id="calcDirection" value="buy">
                <input type="hidden" name="currency_id" id="calcCurrencyId" value="<?= (int)$default_currency_id ?>">
                <input type="hidden" name="large_confirmed" value="<?= isset($_GET['large']) ? '1' : '0' ?>">

                <div class="quick-direction" role="tablist" aria-label="<?= t('calc.direction') ?>">
                    <button type="button" class="quick-dir quick-buy active" data-dir="buy" role="tab" aria-selected="true">
                        <svg class="icon" aria-hidden="true"><use href="/assets/img/icons.svg#arrow-down-circle"/></svg>
                        <?= t('tx.buy') ?>
                    </button>
                    <button type="button" class="quick-dir quick-sell" data-dir="sell" role="tab" aria-selected="false">
                        <svg class="icon" aria-hidden="true"><use href="/assets/img/icons.svg#arrow-up-circle"/></svg>
                        <?= t('tx.sell') ?>
                    </button>
                </div>

                <div class="calc-mode" role="tablist" aria-label="<?= t('calc.mode') ?>">
                    <button type="button" class="calc-mode-btn active" data-mode="to_base" role="tab" aria-selected="true">
                        <?= t('calc.mode_to_base') ?>
                    </button>
                    <button type="button" class="calc-mode-btn" data-mode="from_base" role="tab" aria-selected="false">
                        <?= t('calc.mode_from_base') ?>
                    </button>
                </div>

                <label class="form-label" for="calcCurrencies" style="display:block;margin:14px 0 6px"><?= t('tx.currency') ?></label>
                <div class="quick-currencies" id="calcCurrencies">
                    <?php foreach ($rates as $r): ?>
                        <?php $qty = (string)($inventory[(int)$r['id']] ?? '0'); ?>
                        <button type="button" class="quick-cur <?= (int)$r['id'] === (int)$default_currency_id ? 'active' : '' ?>"
                                data-id="<?= (int)$r['id'] ?>" data-buy="<?= e((string)$r['buy_rate']) ?>" data-sell="<?= e((string)$r['sell_rate']) ?>"
                                data-ap="<?= (int)$r['amount_precision'] ?>" data-rp="<?= (int)$r['rate_precision'] ?>"
                                data-code="<?= e($r['code']) ?>" data-qty="<?= e($qty) ?>">
                            <strong><?= e($r['code']) ?></strong>
                            <small><?= t('rates.buy') ?> <?= Money::format((string)$r['buy_rate'], (int)$r['rate_precision']) ?></small>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="calc-strip" id="calcRateStrip"
                     data-buy-label="<?= e(t('rates.buy')) ?>"
                     data-sell-label="<?= e(t('rates.sell')) ?>"
                     aria-live="polite"></div>

                <div class="quick-main">
                    <div class="form-group">
                        <label class="form-label" for="calcAmount"><?= t('calc.amount') ?> <span class="req">*</span></label>
                        <input class="form-control quick-amount" type="number" step="any" min="0" id="calcAmount" name="calc_amount" autofocus required>
                        <small class="form-hint" id="calcAmountUnit"></small>
                        <div class="calc-presets" id="calcPresets" aria-label="<?= t('calc.quick_amounts') ?>"></div>
                        <div class="calc-avail" id="calcAvail" aria-live="polite" hidden
                             data-available="<?= e(t('calc.available')) ?>"
                             data-not-enough="<?= e(t('calc.not_enough')) ?>"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="calcRate"><?= t('tx.rate') ?> (<?= e($base['code']) ?>) <span class="req">*</span></label>
                        <input class="form-control quick-rate" type="number" step="any" min="0" id="calcRate" name="rate" required>
                        <small class="form-hint" id="calcRateHint"><?= t('calc.rate_override') ?></small>
                        <div class="calc-rate-badge" id="calcRateBadge" hidden>
                            <span class="pill pill-amber" id="calcRateBadgeLabel"><?= t('calc.custom_rate') ?></span>
                            <button type="button" class="calc-rate-reset" id="calcRateReset">
                                <?= t('calc.board_rate') ?>: <b id="calcRateBoard"></b>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="calcMiniValue"><?= t('calc.live_total') ?></label>
                        <div class="calc-mini" id="calcMiniValue">0</div>
                        <small class="form-hint" id="calcMiniLabel"></small>
                    </div>
                </div>

                <div class="quick-row">
                    <div class="form-group">
                        <label class="form-label" for="calcFeeType"><?= t('tx.fee') ?></label>
                        <div class="quick-ctl">
                            <select class="form-select" id="calcFeeType" name="fee_type">
                                <option value="fixed"><?= t('rates.fixed') ?></option>
                                <option value="percent"><?= t('rates.percent') ?> (%)</option>
                            </select>
                            <input class="form-control" type="number" step="any" min="0" id="calcFee" name="fee_amount" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="calcDiscType"><?= t('tx.discount') ?></label>
                        <div class="quick-ctl">
                            <select class="form-select" id="calcDiscType" name="discount_type">
                                <option value="fixed"><?= t('rates.fixed') ?></option>
                                <option value="percent"><?= t('rates.percent') ?> (%)</option>
                            </select>
                            <input class="form-control" type="number" step="any" min="0" id="calcDisc" name="discount_amount" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="calcMethod"><?= t('tx.payment_method') ?></label>
                        <select class="form-select" id="calcMethod" name="payment_method">
                            <option value="cash"><?= t('tx.cash') ?></option>
                            <option value="bank_transfer"><?= t('tx.bank_transfer') ?></option>
                            <option value="card"><?= t('tx.card') ?></option>
                            <option value="internal_balance"><?= t('tx.internal_balance') ?></option>
                        </select>
                    </div>
                </div>

                <div class="quick-row">
                    <div class="form-group">
                        <label class="form-label" for="calcCustomer"><?= t('tx.customer_optional') ?></label>
                        <?php View::partial('customer_picker', ['pickerId' => 'calcCustomerPicker']); ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="calcAccount"><?= t('calc.currency_account') ?></label>
                        <select class="form-select" id="calcAccount" name="account_id">
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= (int)$a['id'] ?>" <?= (int)$a['id'] === (int)($default_currency_account ?? 0) ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="calcCash"><?= t('calc.cash_account') ?></label>
                        <select class="form-select" id="calcCash" name="cash_account_id">
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= (int)$a['id'] ?>" <?= (int)$a['id'] === (int)($default_cash_account ?? 0) ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <input type="hidden" name="foreign_amount" id="calcForeign">
            </form>
        </div>
    </div>

    <!-- ============ Live result + actions ============ -->
    <div class="stack calc-side">
        <div class="card calc-receipt">
            <div class="card-header"><h2><?= t('calc.live_result') ?></h2></div>
            <div class="calc-result" aria-live="polite">
                <div class="calc-result-label" id="calcOtherLabel"></div>
                <div class="calc-result-value" id="calcOtherValue">0</div>
                <div class="calc-result-unit" id="calcOtherUnit"></div>

                <div class="calc-breakdown" id="calcBreakdown" aria-live="polite">
                    <div class="calc-line"><span><?= t('calc.subtotal') ?></span><b id="calcSubtotal">0</b></div>
                    <div class="calc-line"><span><?= t('tx.fee') ?></span><b id="calcFeeB">0</b></div>
                    <div class="calc-line"><span><?= t('tx.discount') ?></span><b id="calcDiscB">0</b></div>
                    <div class="calc-line calc-line-total"><span id="calcFinalLabel"><?= t('calc.final_total') ?></span><b id="calcFinal">0</b></div>
                </div>
            </div>

            <div class="form-actions" style="margin-top:14px">
                <button type="button" class="btn" id="calcCalculate">
                    <svg class="icon"><use href="/assets/img/icons.svg#percent"/></svg>
                    <?= t('calc.calculate') ?>
                </button>
                <button type="button" class="btn btn-success btn-lg" id="calcCreate" style="flex:1">
                    <svg class="icon"><use href="/assets/img/icons.svg#check-square"/></svg>
                    <?= t('calc.create') ?>
                </button>
            </div>
            <p class="form-hint" style="margin:10px 2px 0;text-align:center"><?= t('calc.not_created_hint') ?></p>
        </div>

        <details class="card calc-board">
            <summary class="calc-board-summary">
                <svg class="icon"><use href="/assets/img/icons.svg#activity"/></svg>
                <?= t('rates.title') ?>
                <span class="text-muted" style="margin-inline-start:auto;font-size:.72rem"><?= t('calc.rates_toggle') ?></span>
            </summary>
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
            <div class="card-body" style="padding:10px 18px"><a href="/rates" class="btn btn-sm btn-ghost"><?= t('rates.title') ?></a></div>
        </details>

        <div class="card">
            <div class="card-header"><h2><?= t('calc.how_title') ?></h2></div>
            <div class="card-body">
                <ol class="calc-steps">
                    <li><?= t('calc.how_1') ?></li>
                    <li><?= t('calc.how_2') ?></li>
                    <li><?= t('calc.how_3') ?></li>
                    <li><?= t('calc.how_4') ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation modal -->
<div class="modal-backdrop" id="calcConfirmModal" role="dialog" aria-modal="true" aria-labelledby="calcConfirmTitle">
    <div class="modal">
        <div class="modal-head">
            <h3 id="calcConfirmTitle"><?= t('calc.confirm_title') ?></h3>
            <button type="button" class="icon-btn" id="calcConfirmClose" aria-label="<?= t('app.close') ?>">
                <svg class="icon"><use href="/assets/img/icons.svg#x"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="receipt-total" style="margin-top:0">
                <span id="calcConfirmDir"></span>
                <span class="amt" id="calcConfirmTotal">0</span>
            </div>
            <table class="table mt-2">
                <tr><td class="text-muted"><?= t('app.amount') ?></td><td class="num" id="calcConfirmAmount">—</td></tr>
                <tr><td class="text-muted"><?= t('app.rate') ?></td><td class="num" id="calcConfirmRate">—</td></tr>
                <tr><td class="text-muted"><?= t('tx.fee') ?></td><td class="num" id="calcConfirmFee">—</td></tr>
                <tr><td class="text-muted"><?= t('tx.discount') ?></td><td class="num" id="calcConfirmDisc">—</td></tr>
                <tr><td class="text-muted"><?= t('app.customer') ?></td><td class="num" id="calcConfirmCustomer">—</td></tr>
            </table>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" id="calcConfirmCancel"><?= t('app.cancel') ?></button>
            <button type="button" class="btn btn-success" id="calcConfirmSubmit"><?= t('app.confirm') ?></button>
        </div>
    </div>
</div>
<?php View::partial('customer_picker_modal', ['pickerId' => 'calcCustomerPicker']); ?>
<script>window.EXCHANGE_RATES = <?= json_encode($rateMap) ?>;</script>

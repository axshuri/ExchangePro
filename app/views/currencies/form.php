<?php /** @var ?array $currency */ ?>
<div class="page-head">
    <h1><?= $currency ? t('currency.currencies') . ' — ' . e($currency['code']) : t('currency.new') ?></h1>
    <div class="page-actions"><a href="/currencies" class="btn btn-ghost btn-sm"><?= t('currency.currencies') ?></a></div>
</div>

<div class="card" style="max-width:720px">
    <div class="card-body">
        <form method="post" action="<?= $currency ? '/currencies/' . (int)$currency['id'] : '/currencies' ?>">
            <?= Csrf::field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="code"><?= t('currency.code') ?> (ISO 4217) <span class="req">*</span></label>
                    <input class="form-control" type="text" id="code" name="code" required maxlength="8" style="text-transform:uppercase"
                           value="<?= e($currency['code'] ?? '') ?>" <?= $currency ? 'disabled' : '' ?>>
                </div>
                <div class="form-group">
                    <label class="form-label" for="name"><?= t('currency.name') ?> <span class="req">*</span></label>
                    <input class="form-control" type="text" id="name" name="name" required value="<?= e($currency['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="localized_name"><?= t('currency.localized_name') ?></label>
                    <input class="form-control" type="text" id="localized_name" name="localized_name" value="<?= e($currency['localized_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="symbol"><?= t('currency.symbol') ?></label>
                    <input class="form-control" type="text" id="symbol" name="symbol" value="<?= e($currency['symbol'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="amount_precision"><?= t('currency.amount_precision') ?></label>
                    <input class="form-control" type="number" id="amount_precision" name="amount_precision" min="0" max="10" value="<?= (int)($currency['amount_precision'] ?? 2) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="rate_precision"><?= t('currency.rate_precision') ?></label>
                    <input class="form-control" type="number" id="rate_precision" name="rate_precision" min="0" max="10" value="<?= (int)($currency['rate_precision'] ?? 4) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="min_amount"><?= t('currency.min_amount') ?></label>
                    <input class="form-control" type="number" step="any" id="min_amount" name="min_amount" value="<?= e((string)($currency['min_amount'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="max_amount"><?= t('currency.max_amount') ?></label>
                    <input class="form-control" type="number" step="any" id="max_amount" name="max_amount" value="<?= e((string)($currency['max_amount'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-check" style="margin-top:22px">
                        <input type="checkbox" name="is_active" <?= !isset($currency) || $currency['is_active'] ? 'checked' : '' ?>>
                        <?= t('currency.is_active') ?>
                    </label>
                </div>
                <div class="form-group full">
                    <label class="form-label" for="notes"><?= t('currency.notes') ?></label>
                    <textarea class="form-textarea" id="notes" name="notes"><?= e($currency['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= t('app.save') ?></button>
                <a href="/currencies" class="btn btn-ghost"><?= t('app.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

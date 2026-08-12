<?php /** @var array $currencies @var array $accounts @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('income.new') ?></h1>
    <div class="page-actions"><a href="/income" class="btn btn-ghost btn-sm"><?= t('income.income') ?></a></div>
</div>

<div class="card" style="max-width:640px">
    <div class="card-body">
        <form method="post" action="/income" data-submit-guard>
            <?= Csrf::field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="category"><?= t('income.category') ?></label>
                    <select class="form-select" id="category" name="category">
                        <option value="service_fee">Service fee</option>
                        <option value="transfer_commission">Transfer commission</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="amount"><?= t('income.amount') ?> <span class="req">*</span></label>
                    <input class="form-control" type="number" step="any" min="0" id="amount" name="amount" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="currency_id"><?= t('income.currency') ?></label>
                    <select class="form-select" id="currency_id" name="currency_id">
                        <?php foreach ($currencies as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$base['id'] ? 'selected' : '' ?>><?= e($c['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="account_id"><?= t('income.account') ?> <span class="req">*</span></label>
                    <select class="form-select" id="account_id" name="account_id" required>
                        <option value="">—</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="income_date"><?= t('income.date') ?></label>
                    <input class="form-control" type="date" id="income_date" name="income_date" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group full">
                    <label class="form-label" for="description"><?= t('income.description') ?></label>
                    <textarea class="form-textarea" id="description" name="description"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= t('app.save') ?></button>
                <a href="/income" class="btn btn-ghost"><?= t('app.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

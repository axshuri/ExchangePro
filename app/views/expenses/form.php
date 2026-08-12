<?php /** @var array $categories @var array $currencies @var array $accounts @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('expense.new') ?></h1>
    <div class="page-actions"><a href="/expenses" class="btn btn-ghost btn-sm"><?= t('expense.expenses') ?></a></div>
</div>

<div class="card" style="max-width:680px">
    <div class="card-body">
        <form method="post" action="/expenses" data-submit-guard>
            <?= Csrf::field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="category"><?= t('expense.category') ?> <span class="req">*</span></label>
                    <select class="form-select" id="category" name="category" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>"><?= e(t('expense.cat_' . $cat, [], ucfirst(str_replace('_', ' ', $cat)))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="amount"><?= t('expense.amount') ?> <span class="req">*</span></label>
                    <input class="form-control" type="number" step="any" min="0" id="amount" name="amount" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="currency_id"><?= t('expense.currency') ?></label>
                    <select class="form-select" id="currency_id" name="currency_id">
                        <?php foreach ($currencies as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$base['id'] ? 'selected' : '' ?>><?= e($c['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="account_id"><?= t('expense.account') ?> <span class="req">*</span></label>
                    <select class="form-select" id="account_id" name="account_id" required>
                        <option value="">—</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="expense_date"><?= t('expense.date') ?></label>
                    <input class="form-control" type="date" id="expense_date" name="expense_date" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="reference_no"><?= t('expense.reference_no') ?></label>
                    <input class="form-control" type="text" id="reference_no" name="reference_no">
                </div>
                <div class="form-group full">
                    <label class="form-label" for="description"><?= t('expense.description') ?></label>
                    <textarea class="form-textarea" id="description" name="description"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= t('app.save') ?></button>
                <a href="/expenses" class="btn btn-ghost"><?= t('app.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

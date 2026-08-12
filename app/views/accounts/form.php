<?php /** @var ?array $account */ ?>
<div class="page-head">
    <h1><?= $account ? t('account.accounts') . ' — ' . e($account['name']) : t('account.new') ?></h1>
    <div class="page-actions"><a href="/accounts" class="btn btn-ghost btn-sm"><?= t('account.accounts') ?></a></div>
</div>

<div class="card" style="max-width:720px">
    <div class="card-body">
        <form method="post" action="<?= $account ? '/accounts/' . (int)$account['id'] : '/accounts' ?>">
            <?= Csrf::field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="code"><?= t('account.code') ?> <span class="req">*</span></label>
                    <input class="form-control" type="text" id="code" name="code" required value="<?= e($account['code'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="name"><?= t('account.name') ?> <span class="req">*</span></label>
                    <input class="form-control" type="text" id="name" name="name" required value="<?= e($account['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="type"><?= t('account.type') ?></label>
                    <select class="form-select" id="type" name="type">
                        <?php foreach (['cash_desk', 'vault', 'bank', 'wallet', 'other'] as $tp): ?>
                            <option value="<?= $tp ?>" <?= ($account['type'] ?? 'cash_desk') === $tp ? 'selected' : '' ?>><?= t('account.' . $tp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-check" style="margin-top:22px">
                        <input type="checkbox" name="is_active" <?= !isset($account) || $account['is_active'] ? 'checked' : '' ?>>
                        <?= t('account.is_active') ?>
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label" for="bank_name"><?= t('account.bank_name') ?></label>
                    <input class="form-control" type="text" id="bank_name" name="bank_name" value="<?= e($account['bank_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="account_number"><?= t('account.account_number') ?></label>
                    <input class="form-control" type="text" id="account_number" name="account_number" value="<?= e($account['account_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="account_holder"><?= t('account.account_holder') ?></label>
                    <input class="form-control" type="text" id="account_holder" name="account_holder" value="<?= e($account['account_holder'] ?? '') ?>">
                </div>
                <div class="form-group full">
                    <label class="form-label" for="notes"><?= t('account.notes') ?></label>
                    <textarea class="form-textarea" id="notes" name="notes"><?= e($account['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= t('app.save') ?></button>
                <a href="/accounts" class="btn btn-ghost"><?= t('app.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

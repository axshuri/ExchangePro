<?php /** @var array $accounts @var array $currencies @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('transfer.new') ?></h1>
    <div class="page-actions"><a href="/transfers" class="btn btn-ghost btn-sm"><?= t('transfer.transfers') ?></a></div>
</div>

<div class="card" style="max-width:640px">
    <div class="card-body">
        <div class="alert-item alert-info mb-2">
            <svg class="icon"><use href="/assets/img/icons.svg#info"/></svg>
            <span><?= t('transfer.hint') ?></span>
        </div>
        <form method="post" action="/transfers">
            <?= Csrf::field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="source_account_id"><?= t('transfer.source') ?> <span class="req">*</span></label>
                    <select class="form-select" id="source_account_id" name="source_account_id" required>
                        <option value="">—</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= t('account.' . $a['type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="destination_account_id"><?= t('transfer.destination') ?> <span class="req">*</span></label>
                    <select class="form-select" id="destination_account_id" name="destination_account_id" required>
                        <option value="">—</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= t('account.' . $a['type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="currency_id"><?= t('transfer.currency') ?> <span class="req">*</span></label>
                    <select class="form-select" id="currency_id" name="currency_id" required>
                        <option value="">—</option>
                        <?php foreach ($currencies as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['code']) ?> — <?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="amount"><?= t('transfer.amount') ?> <span class="req">*</span></label>
                    <input class="form-control" type="number" step="any" min="0" id="amount" name="amount" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="transfer_date"><?= t('transfer.date') ?></label>
                    <input class="form-control" type="date" id="transfer_date" name="transfer_date" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group full">
                    <label class="form-label" for="note"><?= t('transfer.note') ?></label>
                    <textarea class="form-textarea" id="note" name="note"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= t('app.confirm') ?></button>
                <a href="/transfers" class="btn btn-ghost"><?= t('app.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?php /** @var ?array $customer */ ?>
<div class="page-head">
    <h1><?= $customer ? t('customer.profile') . ' — ' . e($customer['full_name']) : t('customer.new') ?></h1>
    <div class="page-actions"><a href="/customers" class="btn btn-ghost btn-sm"><?= t('customer.customers') ?></a></div>
</div>

<div class="card" style="max-width:820px">
    <div class="card-body">
        <form method="post" action="<?= $customer ? '/customers/' . (int)$customer['id'] : '/customers' ?>">
            <?= Csrf::field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="full_name"><?= t('customer.full_name') ?> <span class="req">*</span></label>
                    <input class="form-control" type="text" id="full_name" name="full_name" required value="<?= e($customer['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="phone"><?= t('customer.phone') ?></label>
                    <input class="form-control" type="text" id="phone" name="phone" value="<?= e($customer['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="email"><?= t('customer.email') ?></label>
                    <input class="form-control" type="email" id="email" name="email" value="<?= e($customer['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="code"><?= t('customer.code') ?></label>
                    <input class="form-control" type="text" value="<?= e($customer['code'] ?? '') ?>" disabled>
                    <small class="form-hint"><?= t('customer.code') ?>: <?= e($customer['code'] ?? t('app.create')) ?></small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="id_type"><?= t('customer.id_type') ?></label>
                    <input class="form-control" type="text" id="id_type" name="id_type" value="<?= e($customer['id_type'] ?? '') ?>" placeholder="passport / driver_license / national_id">
                </div>
                <div class="form-group">
                    <label class="form-label" for="id_number"><?= t('customer.id_number') ?></label>
                    <input class="form-control" type="text" id="id_number" name="id_number" value="<?= e($customer['id_number'] ?? '') ?>">
                </div>
                <?php if ($customer): ?>
                <div class="form-group">
                    <label class="form-label" for="status"><?= t('app.status') ?></label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?= $customer['status'] === 'active' ? 'selected' : '' ?>>active</option>
                        <option value="inactive" <?= $customer['status'] === 'inactive' ? 'selected' : '' ?>>inactive</option>
                        <option value="blocked" <?= $customer['status'] === 'blocked' ? 'selected' : '' ?>>blocked</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group full">
                    <label class="form-label" for="address"><?= t('customer.address') ?></label>
                    <input class="form-control" type="text" id="address" name="address" value="<?= e($customer['address'] ?? '') ?>">
                </div>
                <div class="form-group full">
                    <label class="form-label" for="notes"><?= t('customer.notes') ?></label>
                    <textarea class="form-textarea" id="notes" name="notes"><?= e($customer['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= t('app.save') ?></button>
                <a href="<?= $customer ? '/customers/' . (int)$customer['id'] : '/customers' ?>" class="btn btn-ghost"><?= t('app.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

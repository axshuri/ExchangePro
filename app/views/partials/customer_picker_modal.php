<?php
/**
 * Customer picker modal — search + inline add, all AJAX.
 * Include once per page OUTSIDE the main <form> (it contains its own form).
 * Paired with customer_picker.php (the trigger).
 *
 * @var string $pickerId unique id for this picker instance
 */
$pickerId = $pickerId ?? 'customerPicker';
$modalId = $pickerId . 'Modal';
$resultsId = $pickerId . 'Results';
$addFormId = $pickerId . 'AddForm';
$canManage = Auth::hasPermission('manage_customers');
?>
<div class="modal-backdrop" id="<?= e($modalId) ?>" role="dialog" aria-modal="true" aria-labelledby="<?= e($modalId) ?>Title">
    <div class="modal customer-picker-modal">
        <div class="modal-head">
            <h3 id="<?= e($modalId) ?>Title"><?= t('customer.picker_title') ?></h3>
            <button type="button" class="icon-btn" aria-label="<?= t('app.close') ?>" data-picker-close>
                <svg class="icon"><use href="/assets/img/icons.svg#x"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <!-- Search -->
            <div class="picker-search">
                <svg class="icon"><use href="/assets/img/icons.svg#search"/></svg>
                <input type="text" class="form-control" id="<?= e($pickerId) ?>Search" data-picker-search
                       placeholder="<?= t('customer.picker_search_placeholder') ?>" autocomplete="off"
                       role="combobox" aria-expanded="true" aria-controls="<?= e($resultsId) ?>"
                       aria-autocomplete="list" aria-label="<?= t('customer.picker_search_placeholder') ?>">
            </div>
            <div class="picker-results" id="<?= e($resultsId) ?>" data-picker-results role="listbox"
                 aria-label="<?= t('customer.customers') ?>"
                 data-empty="<?= e(t('customer.picker_no_results')) ?>"
                 data-hint="<?= e(t('customer.picker_hint')) ?>"></div>

            <?php if ($canManage): ?>
            <!-- Divider -->
            <div class="picker-divider"><span><?= t('customer.picker_or') ?></span></div>

            <!-- Inline add -->
            <form class="picker-add-form" id="<?= e($addFormId) ?>" data-picker-add-form novalidate
                  data-msg-required="<?= e(t('customer.full_name') . ' ' . t('validate.required')) ?>">
                <?= Csrf::field() ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="<?= e($addFormId) ?>Name"><?= t('customer.full_name') ?> <span class="req">*</span></label>
                        <input class="form-control" type="text" id="<?= e($addFormId) ?>Name" name="full_name" required maxlength="160">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="<?= e($addFormId) ?>Phone"><?= t('customer.phone') ?></label>
                        <input class="form-control" type="text" id="<?= e($addFormId) ?>Phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="<?= e($addFormId) ?>Email"><?= t('customer.email') ?></label>
                        <input class="form-control" type="email" id="<?= e($addFormId) ?>Email" name="email">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="<?= e($addFormId) ?>IdType"><?= t('customer.id_type') ?></label>
                        <input class="form-control" type="text" id="<?= e($addFormId) ?>IdType" name="id_type" placeholder="passport / driver_license / national_id">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="<?= e($addFormId) ?>IdNumber"><?= t('customer.id_number') ?></label>
                        <input class="form-control" type="text" id="<?= e($addFormId) ?>IdNumber" name="id_number">
                    </div>
                </div>
                <p class="picker-add-feedback" data-picker-feedback role="alert"></p>
                <div class="form-actions">
                    <button class="btn btn-primary btn-sm" type="submit" data-picker-add-submit>
                        <svg class="icon icon-sm"><use href="/assets/img/icons.svg#plus"/></svg>
                        <?= t('customer.picker_add') ?>
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm" data-picker-add-cancel><?= t('app.cancel') ?></button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

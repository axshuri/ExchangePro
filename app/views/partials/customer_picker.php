<?php
/**
 * Customer picker trigger — replaces the plain <select>.
 * Renders a button + hidden input; the picker modal itself lives in
 * customer_picker_modal.php (included once per page, outside the form).
 *
 * @var string $pickerId unique id for this picker instance
 * @var ?array $selected preselected customer ['id','full_name','code'] (optional)
 */
$pickerId = $pickerId ?? 'customerPicker';
$selected = $selected ?? null;
$modalId = $pickerId . 'Modal';
?>
<div class="customer-picker" data-picker="<?= e($pickerId) ?>" data-picker-modal="<?= e($modalId) ?>">
    <input type="hidden" name="customer_id" id="customer_id" value="<?= (int)($selected['id'] ?? 0) ?>">
    <button type="button" class="customer-picker-trigger" data-picker-open aria-haspopup="dialog"
            aria-controls="<?= e($modalId) ?>" aria-expanded="false">
        <svg class="icon icon-sm"><use href="/assets/img/icons.svg#user"/></svg>
        <span class="customer-picker-value" data-picker-value>
            <?= $selected ? e($selected['full_name']) . ' (' . e($selected['code']) . ')' : '—' ?>
        </span>
        <svg class="icon icon-sm"><use href="/assets/img/icons.svg#chevron-down"/></svg>
    </button>
</div>

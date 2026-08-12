<?php
/** @var array $tx @var array $items @var array $fees @var array $currency @var array $business */
$base = SettingService::baseCurrency();
?>
<!DOCTYPE html>
<html lang="<?= e(I18n::lang()) ?>" dir="<?= I18n::isRtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8">
<title><?= t('receipt.title') ?> — <?= e($tx['tx_number']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php if (I18n::isRtl()): ?><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet"><?php endif; ?>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="receipt-actions no-print">
    <button class="btn btn-primary" id="printReceipt">
        <svg class="icon"><use href="/assets/img/icons.svg#print"/></svg> <?= t('receipt.print') ?>
    </button>
    <a class="btn btn-ghost" href="/transactions/<?= (int)$tx['id'] ?>"><?= t('receipt.back') ?></a>
</div>

<div class="receipt-page">
    <div class="receipt-head">
        <div>
            <div class="bn"><?= e($business['name']) ?></div>
            <small><?= e(t('app.brand_sub')) ?></small>
        </div>
        <div class="receipt-meta">
            <div><strong><?= t('receipt.no') ?>:</strong> <?= e($tx['tx_number']) ?></div>
            <div><strong><?= t('receipt.date') ?>:</strong> <?= e(tz($tx['tx_date'], 'Y-m-d H:i')) ?></div>
        </div>
    </div>
    <div class="receipt-body">
        <table>
            <tr><td class="label"><?= t('receipt.type') ?></td><td class="value"><?= e(strtoupper(t('tx.type.' . $tx['type']))) ?></td></tr>
            <tr><td class="label"><?= t('receipt.customer') ?></td><td class="value"><?= e($tx['customer_name'] ?? '—') ?></td></tr>
            <?php if ($tx['type'] === 'exchange' && $items): ?>
                <?php foreach ($items as $it): ?>
                    <tr><td class="label"><?= t('tx.source_currency') ?></td><td class="value"><?= money((string)$it['source_amount'], ['symbol' => $it['source_code']]) ?></td></tr>
                    <tr><td class="label"><?= t('tx.target_currency') ?></td><td class="value"><?= money((string)$it['target_amount'], ['symbol' => $it['target_code']]) ?></td></tr>
                    <tr><td class="label"><?= t('tx.cross_rate') ?></td><td class="value mono"><?= Money::format((string)$it['rate'], 6) ?></td></tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td class="label"><?= t('receipt.currency') ?></td><td class="value"><?= e($tx['currency_code'] ?? '—') ?></td></tr>
                <tr><td class="label"><?= t('receipt.amount') ?></td><td class="value"><?= money((string)$tx['foreign_amount'], ['symbol' => $tx['currency_code']]) ?></td></tr>
                <tr><td class="label"><?= t('receipt.rate') ?></td><td class="value mono"><?= Money::format((string)$tx['rate'], 6) ?></td></tr>
            <?php endif; ?>
            <tr><td class="label"><?= t('receipt.fee') ?></td><td class="value"><?= money((string)$tx['fee_amount'], $base) ?></td></tr>
            <tr><td class="label"><?= t('receipt.discount') ?></td><td class="value"><?= money((string)$tx['discount_amount'], $base) ?></td></tr>
            <tr><td class="label"><?= t('receipt.payment') ?></td><td class="value"><?= t('tx.' . ($tx['payment_method'] ?: 'cash')) ?></td></tr>
            <tr><td class="label"><?= t('receipt.employee') ?></td><td class="value"><?= e($tx['employee_id']) ?></td></tr>
            <?php if ($tx['notes']): ?><tr><td class="label"><?= t('receipt.notes') ?></td><td class="value"><?= e($tx['notes']) ?></td></tr><?php endif; ?>
        </table>
        <div class="receipt-total">
            <span><?= t('receipt.total') ?> (<?= e($base['code']) ?>)</span>
            <span class="amt"><?= money((string)$tx['total_amount'], $base) ?></span>
        </div>
    </div>
    <div class="receipt-footer">
        <?= e($business['footer']) ?>
        <div class="mt-1"><?= t('receipt.legal') ?></div>
    </div>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>

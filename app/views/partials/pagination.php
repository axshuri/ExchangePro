<?php
/** @var int $page @var int $pages @var int $total @var string $query (querystring preserved) */
$query = $query ?? '';
if ($pages <= 1) return;

if (!function_exists('exchangePageUrl')) {
    function exchangePageUrl($i, $q) { return '?page=' . $i . ($q ? '&' . $q : ''); }
}

// Window of page numbers around the current page
$window = 2;
$start = max(1, $page - $window);
$end = min($pages, $page + $window);
?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?>
        <a href="<?= e(exchangePageUrl($page - 1, $query)) ?>" aria-label="Previous page">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#arrow-left"/></svg>
        </a>
    <?php else: ?>
        <span class="page-btn" aria-hidden="true" style="opacity:.4;pointer-events:none">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#arrow-left"/></svg>
        </span>
    <?php endif; ?>

    <?php if ($start > 1): ?>
        <a href="<?= e(exchangePageUrl(1, $query)) ?>">1</a>
        <?php if ($start > 2): ?><span class="page-btn" aria-hidden="true">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
        <?php if ($i === $page): ?>
            <span class="current" aria-current="page"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= e(exchangePageUrl($i, $query)) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($end < $pages): ?>
        <?php if ($end < $pages - 1): ?><span class="page-btn" aria-hidden="true">…</span><?php endif; ?>
        <a href="<?= e(exchangePageUrl($pages, $query)) ?>"><?= $pages ?></a>
    <?php endif; ?>

    <?php if ($page < $pages): ?>
        <a href="<?= e(exchangePageUrl($page + 1, $query)) ?>" aria-label="Next page">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#arrow-right"/></svg>
        </a>
    <?php else: ?>
        <span class="page-btn" aria-hidden="true" style="opacity:.4;pointer-events:none">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#arrow-right"/></svg>
        </span>
    <?php endif; ?>

    <span class="info"><?= $total ?> <?= t('reports.transactions') ?></span>
</nav>

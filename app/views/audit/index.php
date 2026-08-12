<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var string $q @var string $action @var string $entity_type @var string $from @var string $to @var array $actions @var array $entities */
$queryStr = http_build_query(array_filter(['q' => $q, 'action' => $action, 'entity_type' => $entity_type, 'from' => $from, 'to' => $to]));
?>
<div class="page-head">
    <h1><?= t('audit.title') ?></h1>
    <div class="page-actions">
        <a href="/export/audit?format=csv" class="btn btn-ghost btn-sm">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#download"/></svg> CSV
        </a>
    </div>
</div>

<div class="card">
    <div class="toolbar">
        <form method="get" action="/audit">
            <div class="search-box">
                <svg class="icon"><use href="/assets/img/icons.svg#search"/></svg>
                <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="<?= t('app.search') ?>">
            </div>
            <select class="form-select" name="action" style="width:160px">
                <option value=""><?= t('audit.action') ?></option>
                <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="entity_type" style="width:150px">
                <option value=""><?= t('audit.entity') ?></option>
                <?php foreach ($entities as $e2): ?><option value="<?= e($e2) ?>" <?= $entity_type === $e2 ? 'selected' : '' ?>><?= e($e2) ?></option><?php endforeach; ?>
            </select>
            <input class="form-control" type="date" name="from" value="<?= e($from) ?>" style="width:150px">
            <input class="form-control" type="date" name="to" value="<?= e($to) ?>" style="width:150px">
            <button class="btn" type="submit"><?= t('app.search') ?></button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= t('app.date') ?></th>
                    <th><?= t('audit.user') ?></th>
                    <th><?= t('audit.action') ?></th>
                    <th><?= t('audit.entity') ?></th>
                    <th><?= t('audit.reason') ?></th>
                    <th><?= t('audit.ip') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $a): ?>
                    <tr>
                        <td class="text-muted"><?= e(tz($a['created_at'], 'Y-m-d H:i')) ?></td>
                        <td><?= e($a['username'] ?? '—') ?></td>
                        <td><span class="pill pill-gray"><?= e($a['action']) ?></span></td>
                        <td><?= e($a['entity_type']) ?> <span class="mono text-muted">#<?= e($a['entity_id'] ?? '') ?></span></td>
                        <td><?= e($a['reason'] ?? '—') ?></td>
                        <td class="mono text-muted"><?= e($a['ip'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="6"><div class="empty">—</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php View::partial('pagination', ['page' => $page, 'pages' => $pages, 'total' => $total, 'query' => $queryStr]); ?>
</div>

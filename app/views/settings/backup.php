<?php
/** @var array $backups @var array $status */
$st = $status;
$last = $st['last'];
$modals = '';
foreach ($backups as $b) {
    $id = (int)$b['id'];
    $modals .= '<div class="modal-backdrop" id="restore-' . $id . '" role="dialog" aria-modal="true" aria-labelledby="restore-title-' . $id . '">'
        . '<div class="modal">'
        . '<form method="post" action="/settings/restore">' . Csrf::field()
        . '<input type="hidden" name="backup_id" value="' . $id . '">'
        . '<div class="modal-head"><h3 id="restore-title-' . $id . '">' . e(t('settings.backup_restore')) . ' — ' . e($b['file_name']) . '</h3>'
        . '<button type="button" class="icon-btn" aria-label="' . e(t('app.close')) . '" onclick="closeModal(\'restore-' . $id . '\')"><svg class="icon"><use href="/assets/img/icons.svg#x"/></svg></button></div>'
        . '<div class="modal-body">'
        . '<p class="mb-2 text-red text-strong">⚠ ' . e(t('settings.restore_confirm')) . '</p>'
        . '<p class="form-hint mb-2">' . e(t('settings.restore_safety')) . '</p>'
        . '<input class="form-control" type="text" name="confirm" placeholder="RESTORE" required autocomplete="off">'
        . '</div>'
        . '<div class="modal-foot">'
        . '<button type="button" class="btn btn-ghost" onclick="closeModal(\'restore-' . $id . '\')">' . e(t('app.cancel')) . '</button>'
        . '<button type="submit" class="btn btn-danger">' . e(t('settings.backup_restore')) . '</button>'
        . '</div>'
        . '</form></div></div>';
}
?>
<div class="page-head">
    <h1><?= t('settings.backup') ?></h1>
    <div class="page-actions">
        <a href="/settings/business" class="btn btn-ghost btn-sm"><?= t('settings.business') ?></a>
        <a href="/settings/system" class="btn btn-ghost btn-sm"><?= t('settings.system') ?></a>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat-card">
        <div class="stat-label"><?= t('settings.backup_last') ?></div>
        <div class="stat-value" style="font-size:1.05rem">
            <?php if ($last): ?>
                <?= e(tz($last['created_at'], 'Y-m-d H:i')) ?>
                <span class="pill <?= $last['status'] === 'ok' ? 'pill-green' : 'pill-red' ?>" style="font-size:.65rem"><?= e($last['status']) ?></span>
                <?php if (!empty($last['verified'])): ?><span class="pill pill-green" style="font-size:.65rem"><?= t('settings.backup_verified') ?></span><?php endif; ?>
            <?php else: ?>—<?php endif; ?>
        </div>
        <div class="stat-sub"><?= $last ? e($last['file_name']) : t('settings.backup_none') ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('settings.backup_schedule') ?></div>
        <div class="stat-value" style="font-size:1.05rem">
            <?= $st['enabled'] ? e($st['time']) : t('app.off') ?>
        </div>
        <div class="stat-sub">
            <?= $st['enabled'] && $st['next'] ? t('settings.backup_next') . ': ' . e($st['next']) : t('settings.backup_disabled') ?>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('settings.backup_count') ?></div>
        <div class="stat-value" style="font-size:1.05rem"><?= count($backups) ?></div>
        <div class="stat-sub">
            <?= $st['failed_count'] ? '<span class="text-red">' . (int)$st['failed_count'] . ' ' . t('settings.backup_failed') . '</span>' : t('settings.backup_no_failures') ?>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2><?= t('settings.backup_create') ?></h2></div>
        <div class="card-body">
            <form method="post" action="/settings/backup">
                <?= Csrf::field() ?>
                <label class="form-check mb-2">
                    <input type="checkbox" name="encrypt" checked>
                    <?= t('settings.backup_encrypt') ?> (AES-256-CBC)
                </label>
                <button class="btn btn-primary" type="submit"><?= t('settings.backup_create') ?></button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= t('settings.backup_schedule') ?></h2></div>
        <div class="card-body">
            <form method="post" action="/settings/backup/settings">
                <?= Csrf::field() ?>
                <label class="form-check mb-2">
                    <input type="checkbox" name="backup_enabled" value="1" <?= $st['enabled'] ? 'checked' : '' ?>>
                    <?= t('settings.backup_auto_enable') ?>
                </label>
                <div class="form-group">
                    <label class="form-label" for="backup_time"><?= t('settings.backup_time') ?></label>
                    <input class="form-control" type="time" id="backup_time" name="backup_time" value="<?= e($st['time']) ?>">
                </div>
                <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr">
                    <div class="form-group">
                        <label class="form-label" for="b_rd"><?= t('settings.backup_retain_daily') ?></label>
                        <input class="form-control" type="number" min="1" max="365" id="b_rd" name="backup_retention_daily" value="<?= e(SettingService::get('backup_retention_daily', '30')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="b_rw"><?= t('settings.backup_retain_weekly') ?></label>
                        <input class="form-control" type="number" min="1" max="52" id="b_rw" name="backup_retention_weekly" value="<?= e(SettingService::get('backup_retention_weekly', '12')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="b_rm"><?= t('settings.backup_retain_monthly') ?></label>
                        <input class="form-control" type="number" min="1" max="120" id="b_rm" name="backup_retention_monthly" value="<?= e(SettingService::get('backup_retention_monthly', '12')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="b_refresh"><?= t('settings.backup_board_refresh') ?></label>
                    <input class="form-control" type="number" min="10" max="300" id="b_refresh" name="price_board_refresh" value="<?= e(SettingService::get('price_board_refresh', '30')) ?>">
                    <small class="form-hint"><?= t('settings.backup_board_refresh_hint') ?></small>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit"><?= t('app.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><h2><?= t('settings.backup_history') ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= t('app.date') ?></th>
                    <th>File</th>
                    <th class="num"><?= t('settings.backup_size') ?></th>
                    <th>Kind</th>
                    <th>Enc</th>
                    <th><?= t('settings.backup_checksum') ?></th>
                    <th><?= t('audit.user') ?></th>
                    <th><?= t('app.status') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $b): ?>
                    <tr>
                        <td class="text-muted"><?= e(tz($b['created_at'], 'Y-m-d H:i')) ?></td>
                        <td class="mono"><?= e($b['file_name']) ?></td>
                        <td class="num"><?= BackupService::humanSize((int)$b['size']) ?></td>
                        <td><span class="pill pill-blue"><?= e($b['kind']) ?></span></td>
                        <td><?= !empty($b['encrypted']) ? '🔒' : '—' ?></td>
                        <td class="mono" style="font-size:.65rem" title="<?= e($b['checksum'] ?? '') ?>"><?= $b['checksum'] ? e(substr($b['checksum'], 0, 12)) . '…' : '—' ?></td>
                        <td><?= e($b['username'] ?? '—') ?></td>
                        <td>
                            <span class="pill <?= $b['status'] === 'ok' ? 'pill-green' : 'pill-red' ?>"><?= e($b['status']) ?></span>
                            <?php if (!empty($b['verified'])): ?><span class="pill pill-green" style="font-size:.65rem"><?= t('settings.backup_verified') ?></span><?php endif; ?>
                        </td>
                        <td class="right">
                            <button class="btn btn-ghost btn-sm" onclick="openModal('restore-<?= (int)$b['id'] ?>')"><?= t('settings.backup_restore') ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$backups): ?><tr><td colspan="9"><div class="empty"><?= t('settings.backup_none') ?></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $modals ?>

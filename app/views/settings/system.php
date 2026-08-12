<?php /** @var array $config @var array $app @var array $defaults */ ?>
<div class="page-head">
    <h1><?= t('settings.system') ?></h1>
    <div class="page-actions">
        <a href="/settings/business" class="btn btn-ghost btn-sm"><?= t('settings.business') ?></a>
        <a href="/settings/backup" class="btn btn-ghost btn-sm"><?= t('settings.backup') ?></a>
    </div>
</div>

<div class="card" style="max-width:640px">
    <div class="card-body">
        <form method="post" action="/settings/system">
            <?= Csrf::field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="session_lifetime"><?= t('settings.session_lifetime') ?></label>
                    <input class="form-control" type="number" id="session_lifetime" name="session_lifetime" value="<?= e(SettingService::get('session_lifetime', $config['session_lifetime'])) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="login_max_attempts"><?= t('settings.login_max_attempts') ?></label>
                    <input class="form-control" type="number" id="login_max_attempts" name="login_max_attempts" value="<?= e(SettingService::get('login_max_attempts', $config['login_max_attempts'])) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="login_lock_minutes"><?= t('settings.login_lock_minutes') ?></label>
                    <input class="form-control" type="number" id="login_lock_minutes" name="login_lock_minutes" value="<?= e(SettingService::get('login_lock_minutes', $config['login_lock_minutes'])) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="backup_encrypt_key"><?= t('settings.backup_encrypt_key') ?></label>
                    <input class="form-control" type="password" id="backup_encrypt_key" name="backup_encrypt_key" value="<?= e(SettingService::get('backup_encrypt_key', $config['backup_encrypt_key'])) ?>">
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= t('app.save') ?></button>
            </div>
        </form>
    </div>
</div>

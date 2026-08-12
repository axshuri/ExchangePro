<?php /** @var array $currencies @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('settings.business') ?></h1>
    <div class="page-actions">
        <a href="/settings/system" class="btn btn-ghost btn-sm"><?= t('settings.system') ?></a>
        <a href="/settings/backup" class="btn btn-ghost btn-sm"><?= t('settings.backup') ?></a>
    </div>
</div>

<div class="card" style="max-width:760px">
    <div class="card-body">
        <form method="post" action="/settings/business">
            <?= Csrf::field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="business_name"><?= t('settings.business_name') ?></label>
                    <input class="form-control" type="text" id="business_name" name="business_name" value="<?= e(SettingService::get('business_name', cfg('app.name'))) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="base_currency"><?= t('settings.base_currency') ?></label>
                    <select class="form-select" id="base_currency" name="base_currency">
                        <?php foreach ($currencies as $c): ?>
                            <option value="<?= e($c['code']) ?>" <?= ($base['code'] ?? '') === $c['code'] ? 'selected' : '' ?>><?= e($c['code']) ?> — <?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint"><?= t('currency.base_hint') ?></small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="timezone"><?= t('settings.timezone') ?></label>
                    <select class="form-select" id="timezone" name="timezone">
                        <?php
                        $tzs = ['America/Toronto', 'America/New_York', 'America/Vancouver', 'Europe/London', 'Europe/Berlin', 'Asia/Tehran', 'Asia/Dubai', 'Asia/Tokyo', 'UTC'];
                        $curTz = SettingService::get('timezone', cfg('app.timezone'));
                        foreach ($tzs as $tz): ?>
                            <option value="<?= e($tz) ?>" <?= $curTz === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="language"><?= t('settings.language') ?></label>
                    <select class="form-select" id="language" name="language">
                        <option value="en" <?= SettingService::get('language', 'en') === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="fa" <?= SettingService::get('language', 'en') === 'fa' ? 'selected' : '' ?>>فارسی</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="tx_prefix"><?= t('settings.tx_prefix') ?></label>
                    <input class="form-control" type="text" id="tx_prefix" name="tx_prefix" value="<?= e(SettingService::get('tx_prefix', cfg('defaults.tx_prefix'))) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="large_tx_threshold"><?= t('settings.large_tx_threshold') ?></label>
                    <input class="form-control" type="number" step="any" id="large_tx_threshold" name="large_tx_threshold" value="<?= e(SettingService::get('large_tx_threshold', cfg('defaults.large_tx_threshold'))) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="profit_method"><?= t('settings.profit_method') ?></label>
                    <select class="form-select" id="profit_method" name="profit_method">
                        <option value="weighted_average" <?= SettingService::get('profit_method', 'weighted_average') === 'weighted_average' ? 'selected' : '' ?>>Weighted average cost</option>
                        <option value="fifo" <?= SettingService::get('profit_method', 'weighted_average') === 'fifo' ? 'selected' : '' ?>>FIFO (future)</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label class="form-label" for="receipt_footer"><?= t('settings.receipt_footer') ?></label>
                    <textarea class="form-textarea" id="receipt_footer" name="receipt_footer"><?= e(SettingService::get('receipt_footer', '')) ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= t('app.save') ?></button>
            </div>
        </form>
    </div>
</div>

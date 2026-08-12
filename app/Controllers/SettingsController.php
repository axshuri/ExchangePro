<?php
declare(strict_types=1);

final class SettingsController extends Controller
{
    protected ?string $requirePermission = 'manage_settings';

    public function index(): void
    {
        redirect('/settings/business');
    }

    public function business(): void
    {
        $currencies = Database::query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY code");
        $this->render('settings/business', [
            'currencies' => $currencies,
            'base' => SettingService::baseCurrency(),
        ]);
    }

    public function saveBusiness(): void
    {
        Csrf::check();
        $before = SettingService::all();
        $fields = [
            'business_name', 'base_currency', 'timezone', 'language',
            'tx_prefix', 'large_tx_threshold', 'profit_method', 'receipt_footer',
        ];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                SettingService::set($f, trim((string)$_POST[$f]));
            }
        }
        // Apply the saved language to the current session so the change is visible immediately.
        $savedLang = trim((string)($_POST['language'] ?? ''));
        if ($savedLang !== '' && in_array($savedLang, I18n::available(), true)) {
            I18n::setLang($savedLang);
        }
        AuditService::log('update_settings', 'settings', null, $before,
            array_intersect_key($_POST, array_flip($fields)), 'Business settings changed');
        Session::flash('success', t('settings.saved'));
        redirect('/settings/business');
    }

    public function system(): void
    {
        $this->render('settings/system', [
            'config' => cfg('security'),
            'app' => cfg('app'),
            'defaults' => cfg('defaults'),
        ]);
    }

    public function saveSystem(): void
    {
        Csrf::check();
        $before = SettingService::all();
        foreach (['session_lifetime', 'login_max_attempts', 'login_lock_minutes', 'backup_encrypt_key'] as $f) {
            if (isset($_POST[$f])) {
                SettingService::set($f, trim((string)$_POST[$f]));
            }
        }
        AuditService::log('update_system_settings', 'settings', null, $before, $_POST);
        Session::flash('success', t('settings.saved'));
        redirect('/settings/system');
    }

    public function backup(): void
    {
        $this->render('settings/backup', [
            'backups' => BackupService::list(),
            'status' => BackupService::status(),
        ]);
    }

    public function createBackup(): void
    {
        Csrf::check();
        try {
            $result = BackupService::create(isset($_POST['encrypt']), $_POST['kind'] ?? 'manual');
            Session::flash('success', t('backup.created') . ': ' . $result['file']);
        } catch (Throwable $e) {
            Session::flash('danger', 'Backup failed: ' . $e->getMessage());
        }
        redirect('/settings/backup');
    }

    public function restore(): void
    {
        Csrf::check();
        $id = (int)($_POST['backup_id'] ?? 0);
        $confirm = trim($_POST['confirm'] ?? '');
        if ($confirm !== 'RESTORE') {
            Session::flash('danger', t('backup.confirm_restore'));
            redirect('/settings/backup');
        }
        try {
            BackupService::restore($id);
            Session::flash('success', t('backup.restored'));
        } catch (Throwable $e) {
            Session::flash('danger', 'Restore failed: ' . $e->getMessage());
        }
        redirect('/settings/backup');
    }

    public function saveBackupSettings(): void
    {
        Csrf::check();
        $fields = [
            'backup_enabled' => (int)!empty($_POST['backup_enabled']) ? '1' : '0',
            'backup_time' => preg_match('/^\d{2}:\d{2}$/', (string)($_POST['backup_time'] ?? ''))
                ? $_POST['backup_time'] : '02:00',
            'backup_retention_daily' => max(1, min(365, (int)($_POST['backup_retention_daily'] ?? 30))),
            'backup_retention_weekly' => max(1, min(52, (int)($_POST['backup_retention_weekly'] ?? 12))),
            'backup_retention_monthly' => max(1, min(120, (int)($_POST['backup_retention_monthly'] ?? 12))),
            'price_board_refresh' => max(10, min(300, (int)($_POST['price_board_refresh'] ?? 30))),
        ];
        foreach ($fields as $k => $v) {
            SettingService::set($k, $v);
        }
        AuditService::log('update_backup_settings', 'settings', null, null, $fields, 'Backup settings changed');
        Session::flash('success', t('settings.saved'));
        redirect('/settings/backup');
    }
}

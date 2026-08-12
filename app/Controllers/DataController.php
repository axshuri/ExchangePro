<?php
declare(strict_types=1);

/**
 * Settings → Data transfer: export transactions to the AEX ticket XLSX format
 * and import XLSX registers (optionally after erasing all financial data).
 */
final class DataController extends Controller
{
    protected ?string $requirePermission = 'manage_settings';

    public function index(): void
    {
        $report = Session::get('import_report');
        if ($report) Session::remove('import_report');

        $this->render('settings/data', [
            'title' => t('data.title'),
            'accounts' => Database::query('SELECT * FROM accounts WHERE is_active = 1 ORDER BY name'),
            'report' => $report,
            'stats' => [
                'transactions' => (int)(Database::value('SELECT COUNT(*) FROM transactions') ?: 0),
                'customers' => (int)(Database::value('SELECT COUNT(*) FROM customers') ?: 0),
                'currencies' => (int)(Database::value('SELECT COUNT(*) FROM currencies') ?: 0),
            ],
        ]);
    }

    public function exportXlsx(): void
    {
        $bytes = DataTransferService::exportFile();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="transactions_export_' . date('Ymd_His') . '.xlsx"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    public function import(): void
    {
        Csrf::check();
        @ini_set('memory_limit', '256M');
        @ini_set('max_execution_time', '300');

        $accountId = (int)($_POST['account_id'] ?? 0);
        $erase = !empty($_POST['erase']);
        $allowShort = !empty($_POST['allow_short']);

        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            Session::flash('danger', t('data.no_file'));
            redirect('/settings/data');
        }
        $file = $_FILES['file'];
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('danger', t('data.upload_error'));
            redirect('/settings/data');
        }
        if ((int)$file['size'] > 25 * 1024 * 1024) {
            Session::flash('danger', t('data.file_too_large'));
            redirect('/settings/data');
        }
        if (strtolower((string)pathinfo((string)$file['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            Session::flash('danger', t('data.not_xlsx'));
            redirect('/settings/data');
        }

        $dir = (string)cfg('paths.uploads');
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $dest = rtrim($dir, '/\\') . '/import_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.xlsx';

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Session::flash('danger', t('data.upload_error'));
            redirect('/settings/data');
        }

        try {
            $result = DataTransferService::import($dest, [
                'account_id' => $accountId,
                'erase' => $erase,
                'allow_short' => $allowShort,
            ]);
            $result['file'] = (string)($file['name'] ?? '');
            Session::set('import_report', $result);
            Session::flash($result['imported'] > 0 ? 'success' : 'warning',
                $result['imported'] > 0 ? t('data.import_ok') : t('data.import_nothing'));
        } catch (Throwable $e) {
            Session::flash('danger', t('data.import_failed') . ' ' . $e->getMessage());
        } finally {
            @unlink($dest);
        }
        redirect('/settings/data');
    }
}

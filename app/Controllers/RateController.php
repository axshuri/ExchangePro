<?php
declare(strict_types=1);

final class RateController extends Controller
{
    protected ?string $requirePermission = 'manage_rates';

    public function index(): void
    {
        RateSyncSchema::ensure();
        $this->render('rates/index', [
            'rates' => RateService::all(),
            'base' => SettingService::baseCurrency(),
            'status' => RateSyncService::status(),
            'settings' => RateSyncService::settings(),
            'logs' => RateSyncService::recentLogs(6),
            'providers' => RateSyncService::availableProviders(),
        ]);
    }

    /**
     * Manual update form: effective Buy/Sell inputs + per-currency
     * spread/override configuration for the next automatic sync.
     */
    public function update(): void
    {
        Csrf::check();
        foreach (($_POST['rates'] ?? []) as $currencyId => $r) {
            $currencyId = (int)$currencyId;
            $buy = (string)($r['buy'] ?? '');
            $sell = (string)($r['sell'] ?? '');

            if ($buy !== '' || $sell !== '') {
                $existing = Database::fetch("SELECT * FROM exchange_rates WHERE currency_id = ?", [$currencyId]);
                $buy = $buy !== '' ? $buy : (string)($existing['buy_rate'] ?? '0');
                $sell = $sell !== '' ? $sell : (string)($existing['sell_rate'] ?? '0');
                try {
                    RateService::update($currencyId, $buy, $sell);
                } catch (DomainException $e) {
                    Session::flash('danger', $e->getMessage());
                    redirect('/rates');
                }
                // Pinning stores the entered effective values as persistent overrides.
                if (!empty($r['override_persistent'])) {
                    $r['buy_override'] = $buy;
                    $r['sell_override'] = $sell;
                }
            }
            RateSyncService::saveCurrencyConfig($currencyId, $r);
        }
        Session::flash('success', t('rates.updated'));
        redirect('/rates');
    }

    /** Force a synchronization now (manual trigger). Supports AJAX (Sync Now button). */
    public function sync(): void
    {
        $this->csrfGuard();
        $result = RateSyncService::sync(true, 'manual');

        if ($this->isAjax()) {
            $ok = in_array($result['status'], ['success', 'partial'], true);
            $this->json([
                'ok' => $ok,
                'status' => $result['status'],
                'message' => $this->syncMessage($result),
                'result' => $result,
            ], $ok ? 200 : 422);
        }

        if (in_array($result['status'], ['success', 'partial'], true)) {
            Session::flash('success', $this->syncMessage($result));
        } else {
            Session::flash('danger', $this->syncMessage($result));
        }
        redirect('/rates');
    }

    /** Global automatic-sync settings (Settings card on the Rates page). */
    public function saveSettings(): void
    {
        $this->csrfGuard();
        try {
            RateSyncService::saveSettings($_POST);
            Session::flash('success', t('rates.settings_saved'));
        } catch (DomainException $e) {
            Session::flash('danger', $e->getMessage());
        }
        redirect('/rates');
    }

    public function history(): void
    {
        $currencyId = (int)($_GET['currency_id'] ?? 0);
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $this->render('rates/history', [
            'history' => RateService::historyAll($currencyId ?: null, $from, $to),
            'currencies' => Database::query("SELECT id, code, name, localized_name FROM currencies WHERE is_active = 1 ORDER BY code"),
            'selected' => $currencyId,
            'from' => $from, 'to' => $to,
        ]);
    }

    private function syncMessage(array $result): string
    {
        return match ($result['status'] ?? 'failed') {
            'success' => t('rates.sync_success') . ' (' . (int)($result['updated'] ?? 0) . ')',
            'partial' => t('rates.sync_partial') . ' (' . (int)($result['updated'] ?? 0) . ' / '
                . (int)($result['skipped'] ?? 0) . ' / ' . (int)($result['failed'] ?? 0) . ')'
                . (!empty($result['error']) ? ' — ' . implode('; ', array_slice($result['error'], 0, 2)) : ''),
            'cached' => t('rates.sync_cached'),
            'in_progress' => t('rates.sync_in_progress'),
            'disabled' => t('rates.sync_disabled'),
            default => t('rates.sync_failed') . ' ' . (string)($result['message'] ?? ''),
        };
    }
}

<?php
declare(strict_types=1);

final class ClosingController extends Controller
{
    protected ?string $requirePermission = 'perform_reconciliation';

    public function index(): void
    {
        $today = (new DateTime('now', new DateTimeZone(cfg('app.timezone', 'UTC'))))->format('Y-m-d');
        $status = ClosingService::status($today);
        $daily = ReportService::daily($today);
        $accounts = Database::query("SELECT * FROM accounts WHERE is_active = 1 ORDER BY name");
        $currencies = Database::query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY code");
        $positions = LedgerService::positions();

        // recent closings
        $history = Database::query(
            "SELECT dc.*, ob.full_name AS opened_by_name, cb.full_name AS closed_by_name
             FROM daily_closings dc
             LEFT JOIN users ob ON ob.id = dc.opened_by
             LEFT JOIN users cb ON cb.id = dc.closed_by
             ORDER BY dc.closing_date DESC LIMIT 30");

        $this->render('closing/index', [
            'today' => $today, 'status' => $status, 'daily' => $daily,
            'accounts' => $accounts, 'currencies' => $currencies, 'positions' => $positions,
            'history' => $history,
            'checks' => ClosingService::checks($today),
            'summary' => ClosingService::currencySummary($today),
            'canApprove' => Auth::hasPermission('closing_approve'),
        ]);
    }

    public function start(): void
    {
        Csrf::check();
        $date = $_POST['closing_date'] ?? '';
        try {
            ClosingService::open($date);
            Session::flash('success', t('closing.opened'));
        } catch (DomainException $e) {
            Session::flash('danger', $e->getMessage());
        }
        redirect('/closing');
    }

    public function complete(): void
    {
        Csrf::check();
        $date = $_POST['closing_date'] ?? '';
        $physical = $_POST['physical'] ?? []; // key "acct:cur" => qty
        $notes = $_POST['notes'] ?? '';
        try {
            $diffs = ClosingService::complete($date, $physical, $notes);
            if ($diffs) {
                Session::flash('warning', t('closing.differences_found') . ' ' . count($diffs));
            } else {
                Session::flash('success', t('closing.closed'));
            }
        } catch (DomainException $e) {
            Session::flash('danger', $e->getMessage());
        }
        redirect('/closing');
    }

    public function approve(): void
    {
        Csrf::check();
        $date = $_POST['closing_date'] ?? '';
        try {
            ClosingService::approve($date);
            Session::flash('success', t('closing.approved'));
        } catch (DomainException $e) {
            Session::flash('danger', $e->getMessage());
        }
        redirect('/closing');
    }

    public function reopen(): void
    {
        Csrf::check();
        $date = $_POST['closing_date'] ?? '';
        try {
            ClosingService::reopen($date);
            Session::flash('success', t('closing.reopened'));
        } catch (DomainException $e) {
            Session::flash('danger', $e->getMessage());
        }
        redirect('/closing');
    }
}

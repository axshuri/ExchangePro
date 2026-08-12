<?php
declare(strict_types=1);

final class DashboardController extends Controller
{
    protected ?string $requirePermission = 'view_dashboard';

    public function index(): void
    {
        $base = SettingService::baseCurrency();
        $today = (new DateTime('now', new DateTimeZone(cfg('app.timezone', 'UTC'))))->format('Y-m-d');
        $month = substr($today, 0, 7);
        $lastMonth = date('Y-m', strtotime('-1 month', strtotime($today . ' 00:00:00')));
        $year = substr($today, 0, 4);

        $daily = ReportService::daily($today);
        $monthly = ReportService::monthly($month);
        $lastMonthly = ReportService::monthly($lastMonth);
        $yearly = ReportService::dailyPeriod($year . '-01-01', $today);
        $inventory = ReportService::inventoryValuation();
        $bs = ReportService::balanceSheet($today);

        // ---- Business snapshot: headline performance ----
        $todayVolume = Money::add($daily['buy_total_base'], $daily['sell_total_base']);
        $activeCustomers = (int)(Database::value(
            "SELECT COUNT(DISTINCT customer_id) FROM transactions
             WHERE customer_id IS NOT NULL AND status = 'completed' AND tx_date BETWEEN ? AND ?",
            [$today . ' 00:00:00', $today . ' 23:59:59']) ?: 0);
        // Cash position = net position of cash-desk accounts, in base currency.
        // base_amount is already ledger-converted to base, so no rate is applied here.
        $cashPosition = (string)(Database::value(
            "SELECT COALESCE(SUM(l.base_debit) - SUM(l.base_credit), 0)
             FROM journal_lines l
             JOIN journal_entries je ON je.id = l.entry_id
             JOIN accounts a ON a.id = l.account_id
             WHERE a.type = 'cash_desk'") ?: '0');

        // ---- Business-snapshot comparisons ----
        $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
        $dailyPrev = ReportService::daily($yesterday);
        $weekStart = date('N', strtotime($today)) === '1' ? $today : date('Y-m-d', strtotime('last monday', strtotime($today)));
        $week = ReportService::dailyPeriod($weekStart, $today);
        $prevWeekEnd = date('Y-m-d', strtotime('-1 day', strtotime($weekStart)));
        $prevWeekStart = date('Y-m-d', strtotime('-6 day', strtotime($prevWeekEnd)));
        $prevWeek = ReportService::dailyPeriod($prevWeekStart, $prevWeekEnd);
        $pct = static fn(string $cur, string $prev): string => Money::isZero($prev)
            ? (Money::isPositive($cur) ? '100' : '0')
            : Money::round(Money::mul(Money::div(Money::sub($cur, $prev), $prev, 6), '100'), 1);
        $comparisons = [
            'today' => [
                'label' => t('dashboard.cmp_today'),
                'net_profit' => $daily['net_profit'], 'prev_net_profit' => $dailyPrev['net_profit'],
                'net_pct' => $pct($daily['net_profit'], $dailyPrev['net_profit']),
                'volume' => Money::add($daily['buy_total_base'], $daily['sell_total_base']),
                'prev_volume' => Money::add($dailyPrev['buy_total_base'], $dailyPrev['sell_total_base']),
                'volume_pct' => $pct(Money::add($daily['buy_total_base'], $daily['sell_total_base']),
                    Money::add($dailyPrev['buy_total_base'], $dailyPrev['sell_total_base'])),
                'tx_count' => $daily['tx_count'], 'prev_tx_count' => $dailyPrev['tx_count'],
            ],
            'week' => [
                'label' => t('dashboard.cmp_week'),
                'net_profit' => $week['net_profit'], 'prev_net_profit' => $prevWeek['net_profit'],
                'net_pct' => $pct($week['net_profit'], $prevWeek['net_profit']),
                'tx_count' => $week['tx_count'], 'prev_tx_count' => $prevWeek['tx_count'],
            ],
            'month' => [
                'label' => t('dashboard.cmp_month'),
                'net_profit' => $monthly['net_profit'], 'prev_net_profit' => $lastMonthly['net_profit'],
                'net_pct' => $pct($monthly['net_profit'], $lastMonthly['net_profit']),
                'tx_count' => $monthly['tx_count'], 'prev_tx_count' => $lastMonthly['tx_count'],
            ],
        ];

        $recentTx = Database::query(
            "SELECT t.*, c.code AS currency_code, cu.full_name AS customer_name
             FROM transactions t
             LEFT JOIN currencies c ON c.id = t.currency_id
             LEFT JOIN customers cu ON cu.id = t.customer_id
             WHERE t.status IN ('completed')
             ORDER BY t.id DESC LIMIT 10");

        // Weekly volume for chart
        $weekly = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day", strtotime($today)));
            $buy = (string)(Database::value(
                "SELECT COALESCE(SUM(base_amount),0) FROM transactions
                 WHERE type='buy' AND status='completed' AND tx_date BETWEEN ? AND ?",
                [$d . ' 00:00:00', $d . ' 23:59:59']) ?: '0');
            $sell = (string)(Database::value(
                "SELECT COALESCE(SUM(base_amount),0) FROM transactions
                 WHERE type='sell' AND status='completed' AND tx_date BETWEEN ? AND ?",
                [$d . ' 00:00:00', $d . ' 23:59:59']) ?: '0');
            $weekly[] = ['date' => $d, 'label' => date('M j', strtotime($d)), 'buy' => $buy, 'sell' => $sell];
        }

        $this->render('dashboard/index', [
            'base' => $base,
            'today' => $today,
            'daily' => $daily,
            'monthly' => $monthly,
            'last_monthly' => $lastMonthly,
            'yearly' => $yearly,
            'inventory' => $inventory,
            'balance_sheet' => $bs,
            'recent_tx' => $recentTx,
            'weekly' => $weekly,
            'alerts' => NotificationService::evaluateAlerts(),
            'notifications' => NotificationService::recent(8, Auth::id()),
            'unread' => NotificationService::unread(Auth::id()),
            'ref_rates' => RateSyncService::dashboardRates(6),
            'rate_status' => RateSyncService::status(),
            'comparisons' => $comparisons,
            'backup_status' => BackupService::status(),
            'today_volume' => $todayVolume,
            'active_customers' => $activeCustomers,
            'cash_position' => $cashPosition,
        ]);
    }

    public function markNotificationsRead(): void
    {
        Csrf::check();
        NotificationService::markAllRead(Auth::id());
        redirect('/');
    }
}

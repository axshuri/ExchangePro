<?php
declare(strict_types=1);

final class AnalyticsController extends Controller
{
    protected ?string $requirePermission = 'view_analytics';

    public function profit(): void
    {
        $preset = $_GET['period'] ?? 'today';
        $from = trim($_GET['from'] ?? '');
        $to = trim($_GET['to'] ?? '');
        [$from, $to] = AnalyticsService::resolvePeriod($preset, $from, $to);

        $this->render('analytics/profit', [
            'preset' => $preset,
            'from' => $from, 'to' => $to,
            'metrics' => AnalyticsService::metrics($from, $to),
            'by_currency' => AnalyticsService::byCurrency($from, $to),
            'by_type' => AnalyticsService::byType($from, $to),
            'trend' => AnalyticsService::trend($from, $to),
            'performance' => AnalyticsService::currencyPerformance($from, $to),
            'top' => AnalyticsService::topCurrencies($from, $to),
            'base' => SettingService::baseCurrency(),
        ]);
    }
}

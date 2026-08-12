<?php
declare(strict_types=1);

final class ForecastController extends Controller
{
    protected ?string $requirePermission = 'view_inventory';

    public function index(): void
    {
        $this->render('inventory/forecast', [
            'rows' => ForecastService::dashboard(),
            'base' => SettingService::baseCurrency(),
        ]);
    }

    public function saveTargets(): void
    {
        $this->csrfGuard();
        ForecastService::saveTargets($_POST['targets'] ?? []);
        Session::flash('success', t('forecast.targets_saved'));
        redirect('/inventory/forecast');
    }
}

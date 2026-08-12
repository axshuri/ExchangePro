<?php
declare(strict_types=1);

final class BoardController extends Controller
{
    protected ?string $requirePermission = 'view_price_board';

    /** Fullscreen price board (bare page, no app layout). */
    public function index(): void
    {
        $mode = ($_GET['mode'] ?? '') === 'public' ? 'public' : 'internal';
        $refresh = max(10, (int)SettingService::get('price_board_refresh', '30'));
        $this->renderBare('rates/board', [
            'data' => RateService::board($mode === 'public'),
            'mode' => $mode,
            'refresh' => $refresh,
            'base' => SettingService::baseCurrency(),
            'lang' => I18n::lang(),
            'isRtl' => I18n::isRtl(),
        ]);
    }

    /** JSON endpoint used by the board's auto-refresh. */
    public function data(): void
    {
        $mode = ($_GET['mode'] ?? '') === 'public' ? 'public' : 'internal';
        $this->json(RateService::board($mode === 'public'));
    }
}

<?php
/**
 * Mobile-only themed hero for auth pages (shown ≤860px where the desktop
 * illustration panel is hidden). Renders the brand + a decorative mini
 * rate board + the page's hero headline.
 *
 * @var array $heroKeys Array of i18n keys: ['line1' => 'auth.hero_line1', 'line2' => ..., 'sub' => ...]
 */
$heroKeys = $heroKeys ?? ['line1' => 'auth.hero_line1', 'line2' => 'auth.hero_line2', 'sub' => 'auth.hero_sub'];
?>
<div class="auth-mobile-hero" aria-hidden="true">
    <div class="auth-mobile-brand">
        <div class="brand-logo"><?= e(mb_substr(SettingService::businessName(), 0, 1)) ?></div>
        <div>
            <strong translate="no"><?= e(SettingService::businessName()) ?></strong>
            <small><?= e(t('app.brand_sub')) ?></small>
        </div>
    </div>
    <div class="auth-illus">
        <div class="auth-illus-card">
            <div class="auth-illus-top"><span class="auth-illus-sym">$</span><strong>USD</strong></div>
            <div class="auth-illus-row"><small>Buy</small><b class="up">93,700</b></div>
            <div class="auth-illus-row"><small>Sell</small><b class="down">94,700</b></div>
            <div class="auth-illus-spark">
                <i style="--h:40%"></i><i style="--h:64%"></i><i style="--h:52%"></i><i style="--h:82%"></i><i style="--h:70%"></i>
            </div>
        </div>
        <div class="auth-illus-card">
            <div class="auth-illus-top"><span class="auth-illus-sym">€</span><strong>EUR</strong></div>
            <div class="auth-illus-row"><small>Buy</small><b class="up">109,000</b></div>
            <div class="auth-illus-row"><small>Sell</small><b class="down">110,000</b></div>
            <div class="auth-illus-spark">
                <i style="--h:58%"></i><i style="--h:46%"></i><i style="--h:72%"></i><i style="--h:62%"></i><i style="--h:80%"></i>
            </div>
        </div>
        <div class="auth-illus-card">
            <div class="auth-illus-top"><span class="auth-illus-sym">AED</span><strong>AED</strong></div>
            <div class="auth-illus-row"><small>Buy</small><b class="up">25,400</b></div>
            <div class="auth-illus-row"><small>Sell</small><b class="down">25,900</b></div>
            <div class="auth-illus-spark">
                <i style="--h:34%"></i><i style="--h:50%"></i><i style="--h:44%"></i><i style="--h:66%"></i><i style="--h:58%"></i>
            </div>
        </div>
    </div>
    <h2 class="auth-mobile-title"><?= e(t($heroKeys['line1'])) ?><br><?= e(t($heroKeys['line2'])) ?></h2>
    <p class="auth-mobile-sub"><?= e(t($heroKeys['sub'])) ?></p>
</div>

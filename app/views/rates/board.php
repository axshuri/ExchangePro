<?php
/** @var array $data @var string $mode @var int $refresh @var array $base @var string $lang @var bool $isRtl */
$rates = $data['rates'];
$updatedAt = $data['updated_at'];
$isPublic = $mode === 'public';
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0b0e14">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<title><?= e(SettingService::businessName()) ?> — <?= t('board.title') ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="board-body">
<div class="board-shell">
    <header class="board-head">
        <div class="board-brand">
            <strong translate="no"><?= e(SettingService::businessName()) ?></strong>
            <span><?= t('board.title') ?> · <?= $isPublic ? t('board.public_mode') : t('board.internal_mode') ?></span>
        </div>
        <div class="board-tools">
            <span class="board-clock" id="boardClock"></span>
            <span class="board-updated" id="boardUpdated">
                <?= $updatedAt ? t('board.updated') . ' ' . e(tz($updatedAt, 'H:i:s')) : t('board.never') ?>
            </span>
            <a class="btn btn-sm" href="/rates/board?mode=<?= $isPublic ? 'internal' : 'public' ?>">
                <?= $isPublic ? t('board.internal_mode') : t('board.public_mode') ?>
            </a>
            <a class="btn btn-sm" href="/rates"><?= t('board.back') ?></a>
            <button class="btn btn-sm" id="boardFullscreen"><?= t('board.fullscreen') ?></button>
        </div>
    </header>

    <main class="board-grid" id="boardGrid" aria-live="polite">
        <?php foreach ($rates as $r): ?>
            <div class="board-card" data-code="<?= e($r['code']) ?>">
                <div class="board-currency">
                    <strong><?= e($r['code']) ?></strong>
                    <span><?= e(currencyName($r)) ?></span>
                </div>
                <div class="board-rate buy">
                    <small><?= t('rates.buy') ?></small>
                    <strong data-field="buy"><?= Money::format((string)$r['buy_rate'], (int)$r['rate_precision']) ?></strong>
                </div>
                <div class="board-rate sell">
                    <small><?= t('rates.sell') ?></small>
                    <strong data-field="sell"><?= Money::format((string)$r['sell_rate'], (int)$r['rate_precision']) ?></strong>
                </div>
                <?php if (!$isPublic): ?>
                    <div class="board-meta">
                        <?php if (!empty($r['reference_rate'])): ?>
                            <span><?= t('rates.reference') ?>: <b data-field="ref"><?= Money::format((string)$r['reference_rate'], (int)$r['rate_precision']) ?></b></span>
                        <?php endif; ?>
                        <span><?= t('rates.source') ?>: <?= e($r['source'] ?? '—') ?></span>
                        <?php if (!empty($r['rate_status'])): ?>
                            <span class="pill pill-<?= match ($r['rate_status']) { 'online' => 'green', 'cached' => 'blue', 'stale' => 'amber', default => 'gray' } ?>">
                                <?= e(t('rates.status.' . $r['rate_status'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$rates): ?>
            <div class="empty" style="grid-column:1/-1"><?= t('board.no_rates') ?></div>
        <?php endif; ?>
    </main>

    <footer class="board-foot">
        <span id="boardMsg"><?= t('board.estimate_note') ?></span>
        <span class="board-count"><?= count($rates) ?> <?= t('board.currencies') ?></span>
    </footer>
</div>

<script src="/assets/js/app.js"></script>
<script>
(function () {
  'use strict';
  var mode = <?= $isPublic ? "'public'" : "'internal'" ?>;
  var interval = Math.max(10, <?= (int)$refresh ?>);
  var grid = document.getElementById('boardGrid');
  var updated = document.getElementById('boardUpdated');
  var msg = document.getElementById('boardMsg');
  var timer = null;

  function fmt(v, p) {
    return Number(v).toLocaleString('en-US', { minimumFractionDigits: p, maximumFractionDigits: p });
  }

  function paint(data) {
    if (data.updated_at) updated.textContent = '<?= t('board.updated') ?> ' + new Date(data.updated_at.replace(' ', 'T') + 'Z').toLocaleTimeString();
    if (grid) {
      grid.querySelectorAll('.board-card').forEach(function (card) {
        var code = card.getAttribute('data-code');
        var r = null;
        (data.rates || []).forEach(function (x) { if (x.code === code) r = x; });
        if (!r) return;
        var buy = card.querySelector('[data-field="buy"]');
        var sell = card.querySelector('[data-field="sell"]');
        var ref = card.querySelector('[data-field="ref"]');
        if (buy && r.buy_rate != null) buy.textContent = fmt(r.buy_rate, r.rate_precision);
        if (sell && r.sell_rate != null) sell.textContent = fmt(r.sell_rate, r.rate_precision);
        if (ref && r.reference_rate != null) ref.textContent = fmt(r.reference_rate, r.rate_precision);
      });
    }
  }

  function refresh() {
    fetch('/rates/board/data?mode=' + mode, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (msg) msg.textContent = ''; paint(d); })
      .catch(function () { if (msg) msg.textContent = '<?= t('board.refresh_failed') ?>'; });
  }
  timer = setInterval(refresh, interval * 1000);

  /* Live clock */
  var clock = document.getElementById('boardClock');
  function tick() { if (clock) clock.textContent = new Date().toLocaleTimeString(); }
  tick(); setInterval(tick, 1000);

  /* Fullscreen */
  var fs = document.getElementById('boardFullscreen');
  if (fs) fs.addEventListener('click', function () {
    if (!document.fullscreenElement) {
      (document.documentElement.requestFullscreen || function () {}).call(document.documentElement);
    } else {
      (document.exitFullscreen || function () {}).call(document);
    }
  });
})();
</script>
</body>
</html>

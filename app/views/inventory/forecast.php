<?php
/** @var array $rows @var array $base */
$statusMeta = [
    'normal' => ['pill-green', 'forecast.status.normal'],
    'low' => ['pill-amber', 'forecast.status.low'],
    'critical' => ['pill-red', 'forecast.status.critical'],
    'excess' => ['pill-blue', 'forecast.status.excess'],
];
$projectionDays = [1, 2, 3, 5, 7];
?>
<div class="page-head">
    <h1><?= t('forecast.title') ?></h1>
    <div class="page-actions">
        <a href="/inventory" class="btn btn-ghost btn-sm"><?= t('dashboard.currency_inventory') ?></a>
    </div>
</div>

<div class="alert-item alert-info mb-2" style="max-width:760px">
    <svg class="icon"><use href="/assets/img/icons.svg#info"/></svg>
    <span><?= t('forecast.estimate_note') ?></span>
</div>

<div class="card">
    <div class="card-header">
        <h2><?= t('forecast.dashboard') ?></h2>
        <div class="card-actions">
            <a href="/reports/inventory" class="btn btn-ghost btn-sm"><?= t('reports.inventory') ?></a>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= t('app.currency') ?></th>
                    <th class="num"><?= t('forecast.current') ?></th>
                    <th class="num"><?= t('forecast.net_7d') ?></th>
                    <th class="num"><?= t('forecast.net_30d') ?></th>
                    <th class="num"><?= t('forecast.daily') ?></th>
                    <th class="num"><?= t('forecast.min_target') ?></th>
                    <th class="num"><?= t('forecast.target') ?></th>
                    <th><?= t('app.status') ?></th>
                    <th class="num"><?= t('forecast.days_to_min') ?></th>
                    <th class="num"><?= t('forecast.replenish') ?></th>
                    <th><?= t('forecast.projection') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $c = $r['currency']; $st = $statusMeta[$r['status']] ?? $statusMeta['normal']; ?>
                    <tr>
                        <td>
                            <strong><?= e($c['code']) ?></strong>
                            <span class="text-muted"> <?= e(currencyName($c)) ?></span>
                        </td>
                        <td class="num mono"><strong><?= money((string)$r['qty'], $c) ?></strong></td>
                        <td class="num mono <?= Money::isNegative((string)$r['net7']) ? 'text-red' : 'text-green' ?>"><?= Money::format((string)$r['net7'], (int)$c['amount_precision']) ?></td>
                        <td class="num mono <?= Money::isNegative((string)$r['net30']) ? 'text-red' : 'text-green' ?>"><?= Money::format((string)$r['net30'], (int)$c['amount_precision']) ?></td>
                        <td class="num mono"><?= Money::format((string)$r['daily'], 4) ?></td>
                        <td class="num"><?= Money::isPositive((string)$r['min']) ? Money::format((string)$r['min'], (int)$c['amount_precision']) : '—' ?></td>
                        <td class="num"><?= Money::isPositive((string)$r['target']) ? Money::format((string)$r['target'], (int)$c['amount_precision']) : '—' ?></td>
                        <td>
                            <div class="status-cell">
                                <span class="pill <?= $st[0] ?>"><?= t($st[1]) ?></span>
                                <?php if ($r['days_to_min'] !== null && Money::isPositive((string)$r['min'])): ?>
                                    <span class="text-amber" style="font-size:.68rem;font-weight:600"><?= t('forecast.below_min_warn') ?> (~<?= $r['days_to_min'] ?>d)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="num"><?= $r['days_to_min'] !== null ? $r['days_to_min'] : '—' ?></td>
                        <td class="num">
                            <?php if (Money::isPositive((string)$r['replenish'])): ?>
                                <span class="text-amber"><?= money((string)$r['replenish'], $c) ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <div class="mini-spark">
                                <?php foreach ($projectionDays as $d): ?>
                                    <?php $p = (string)($r['projection'][$d] ?? ''); ?>
                                    <span class="mini-spark-col <?= $p !== '' && Money::compare($p, '0') < 0 ? 'is-neg' : '' ?>"
                                          title="<?= t('forecast.day_n', ['n' => $d]) ?>: <?= $p !== '' ? Money::format($p, (int)$c['amount_precision']) : '—' ?>"
                                          style="height:<?= $p !== '' ? max(4, min(100, abs((float)$p) / max(1, abs((float)$r['qty'])) * 100)) : 4 ?>%"></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="11"><div class="empty">—</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><h2><?= t('forecast.targets') ?></h2></div>
    <div class="card-body">
        <p class="form-hint mb-2"><?= t('forecast.targets_hint') ?></p>
        <form method="post" action="/inventory/forecast/targets">
            <?= Csrf::field() ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= t('app.currency') ?></th>
                            <th class="num"><?= t('forecast.minimum') ?></th>
                            <th class="num"><?= t('forecast.target') ?></th>
                            <th class="num"><?= t('forecast.maximum') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php $c = $r['currency']; ?>
                            <tr>
                                <td><strong><?= e($c['code']) ?></strong> <span class="text-muted"><?= e(currencyName($c)) ?></span></td>
                                <td class="num">
                                    <input class="form-control" style="width:110px;text-align:end" type="number" step="any" min="0"
                                           name="targets[<?= (int)$c['id'] ?>][min]"
                                           value="<?= Money::isPositive((string)$r['min']) ? e((string)$r['min']) : '' ?>"
                                           placeholder="0">
                                </td>
                                <td class="num">
                                    <input class="form-control" style="width:110px;text-align:end" type="number" step="any" min="0"
                                           name="targets[<?= (int)$c['id'] ?>][target]"
                                           value="<?= Money::isPositive((string)$r['target']) ? e((string)$r['target']) : '' ?>"
                                           placeholder="0">
                                </td>
                                <td class="num">
                                    <input class="form-control" style="width:110px;text-align:end" type="number" step="any" min="0"
                                           name="targets[<?= (int)$c['id'] ?>][max]"
                                           value="<?= Money::isPositive((string)$r['max']) ? e((string)$r['max']) : '' ?>"
                                           placeholder="0">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-actions mt-2">
                <button class="btn btn-primary" type="submit"><?= t('forecast.save_targets') ?></button>
            </div>
        </form>
    </div>
</div>

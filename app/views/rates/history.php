<?php /** @var array $history @var array $currencies @var int $selected @var string $from @var string $to */ ?>
<div class="page-head">
    <h1><?= t('rates.history') ?></h1>
    <div class="page-actions"><a href="/rates" class="btn btn-ghost btn-sm"><?= t('rates.title') ?></a></div>
</div>

<div class="card">
    <div class="toolbar">
        <form method="get" action="/rates/history">
            <select class="form-select" name="currency_id" style="width:150px">
                <option value=""><?= t('app.currency') ?></option>
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $selected === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['code']) ?> — <?= e(currencyName($c)) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control" type="date" name="from" value="<?= e($from) ?>" style="width:150px">
            <input class="form-control" type="date" name="to" value="<?= e($to) ?>" style="width:150px">
            <button class="btn" type="submit"><?= t('reports.generate') ?></button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= t('app.date') ?></th>
                    <th><?= t('app.currency') ?></th>
                    <th class="num"><?= t('rates.buy') ?></th>
                    <th class="num"><?= t('rates.sell') ?></th>
                    <th class="num"><?= t('rates.mid') ?></th>
                    <th><?= t('rates.source') ?></th>
                    <th><?= t('audit.user') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td class="text-muted"><?= e(tz($h['changed_at'], 'Y-m-d H:i')) ?></td>
                        <td><strong><?= e($h['code']) ?></strong> <?= e(currencyName($h)) ?></td>
                        <td class="num mono"><?= Money::format((string)$h['buy_rate'], 6) ?></td>
                        <td class="num mono"><?= Money::format((string)$h['sell_rate'], 6) ?></td>
                        <td class="num mono"><?= Money::format((string)$h['mid_rate'], 6) ?></td>
                        <td><?= e($h['source'] ?? '—') ?></td>
                        <td><?= e($h['username'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$history): ?><tr><td colspan="7"><div class="empty">—</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php /** @var array $report @var string $date @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('reports.daily') ?> — <?= e($date) ?></h1>
    <div class="page-actions">
        <form method="get" action="/reports/daily" style="display:flex;gap:8px">
            <input class="form-control" type="date" name="date" value="<?= e($date) ?>" style="width:160px">
            <button class="btn"><?= t('reports.generate') ?></button>
        </form>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat-card">
        <div class="stat-label"><?= t('reports.trading_profit') ?></div>
        <div class="stat-value <?= Money::isNegative($report['trading_profit']) ? 'stat-negative' : 'stat-positive' ?>"><?= money($report['trading_profit'], $base) ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('reports.fees') ?></div>
        <div class="stat-value"><?= money(Money::sub($report['trading_profit'], '0'), $base) ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('dashboard.expenses') ?></div>
        <div class="stat-value stat-negative"><?= money($report['expense_total'], $base) ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('dashboard.other_income') ?></div>
        <div class="stat-value stat-positive"><?= money($report['income_total'], $base) ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('dashboard.net_profit') ?></div>
        <div class="stat-value <?= Money::isNegative($report['net_profit']) ? 'stat-negative' : 'stat-positive' ?>"><?= money($report['net_profit'], $base) ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('reports.transactions') ?></div>
        <div class="stat-value"><?= (int)$report['tx_count'] ?></div>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header"><h2><?= t('tx.buy') ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= t('app.currency') ?></th><th class="num"><?= t('reports.transactions') ?></th><th class="num"><?= t('tx.amount') ?></th><th class="num"><?= t('tx.base_amount') ?></th></tr></thead>
            <tbody>
                <?php foreach ($report['buys'] as $b): ?>
                    <tr>
                        <td><strong><?= e($b['code']) ?></strong></td>
                        <td class="num"><?= (int)$b['tx_count'] ?></td>
                        <td class="num mono"><?= money((string)$b['amount'], $b) ?></td>
                        <td class="num"><?= money((string)$b['base_amount'], $base) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$report['buys']): ?><tr><td colspan="4" class="text-muted" style="text-align:center;padding:16px">—</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header"><h2><?= t('tx.sell') ?></h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= t('app.currency') ?></th><th class="num"><?= t('reports.transactions') ?></th><th class="num"><?= t('tx.amount') ?></th><th class="num"><?= t('tx.base_amount') ?></th></tr></thead>
            <tbody>
                <?php foreach ($report['sells'] as $s): ?>
                    <tr>
                        <td><strong><?= e($s['code']) ?></strong></td>
                        <td class="num"><?= (int)$s['tx_count'] ?></td>
                        <td class="num mono"><?= money((string)$s['amount'], $s) ?></td>
                        <td class="num"><?= money((string)$s['base_amount'], $base) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$report['sells']): ?><tr><td colspan="4" class="text-muted" style="text-align:center;padding:16px">—</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2><?= t('dashboard.expenses') ?></h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= t('expense.category') ?></th><th class="num"><?= t('app.amount') ?></th></tr></thead>
                <tbody>
                    <?php foreach ($report['expenses'] as $e): ?>
                        <tr>
                            <td><?= e(t('expense.cat_' . $e['category'], [], ucfirst(str_replace('_', ' ', $e['category'])))) ?></td>
                            <td class="num"><?= money((string)$e['base_amount'], $base) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$report['expenses']): ?><tr><td colspan="2" class="text-muted" style="text-align:center;padding:16px">—</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2><?= t('dashboard.other_income') ?></h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= t('income.category') ?></th><th class="num"><?= t('app.amount') ?></th></tr></thead>
                <tbody>
                    <?php foreach ($report['income'] as $i): ?>
                        <tr>
                            <td><?= e(ucfirst(str_replace('_', ' ', $i['category']))) ?></td>
                            <td class="num"><?= money((string)$i['base_amount'], $base) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$report['income']): ?><tr><td colspan="2" class="text-muted" style="text-align:center;padding:16px">—</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

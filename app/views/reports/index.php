<?php /** @var ?array $base */ ?>
<div class="page-head">
    <h1><?= t('reports.title') ?></h1>
</div>

<div class="quick-actions">
    <a href="/reports/daily" class="qa-btn">
        <svg class="icon"><use href="/assets/img/icons.svg#calendar"/></svg> <?= t('reports.daily') ?>
    </a>
    <a href="/reports/monthly" class="qa-btn">
        <svg class="icon"><use href="/assets/img/icons.svg#calendar"/></svg> <?= t('reports.monthly') ?>
    </a>
    <a href="/reports/currency" class="qa-btn">
        <svg class="icon"><use href="/assets/img/icons.svg#coins"/></svg> <?= t('reports.currency') ?>
    </a>
    <a href="/reports/customer" class="qa-btn">
        <svg class="icon"><use href="/assets/img/icons.svg#users"/></svg> <?= t('reports.customer') ?>
    </a>
    <a href="/reports/inventory" class="qa-btn">
        <svg class="icon"><use href="/assets/img/icons.svg#briefcase"/></svg> <?= t('reports.inventory') ?>
    </a>
    <a href="/accounting/pnl" class="qa-btn qa-primary">
        <svg class="icon"><use href="/assets/img/icons.svg#percent"/></svg> <?= t('reports.pnl') ?>
    </a>
    <a href="/accounting/balance-sheet" class="qa-btn">
        <svg class="icon"><use href="/assets/img/icons.svg#scale"/></svg> <?= t('reports.balance_sheet') ?>
    </a>
    <a href="/accounting/cash-flow" class="qa-btn">
        <svg class="icon"><use href="/assets/img/icons.svg#activity"/></svg> <?= t('reports.cash_flow') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <h2 style="font-size:.95rem;margin-bottom:8px"><?= t('app.export') ?></h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="/export/transactions?format=csv" class="btn btn-sm"><?= t('tx.history') ?> (CSV)</a>
            <a href="/export/customers?format=csv" class="btn btn-sm"><?= t('customer.customers') ?> (CSV)</a>
            <a href="/export/ledger?format=csv" class="btn btn-sm"><?= t('ledger.title') ?> (CSV)</a>
            <a href="/export/expenses?format=csv" class="btn btn-sm"><?= t('expense.expenses') ?> (CSV)</a>
            <a href="/export/inventory?format=csv" class="btn btn-sm"><?= t('dashboard.currency_inventory') ?> (CSV)</a>
            <a href="/export/audit?format=csv" class="btn btn-sm"><?= t('audit.title') ?> (CSV)</a>
            <a href="/export/rates?format=csv" class="btn btn-sm"><?= t('rates.title') ?> (CSV)</a>
            <a href="/export/transactions?format=json" class="btn btn-sm"><?= t('tx.history') ?> (JSON)</a>
        </div>
    </div>
</div>

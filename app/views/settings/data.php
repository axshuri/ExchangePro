<?php
/** @var array $accounts @var array|null $report @var array $stats */
$report = $report ?? null;
?>
<div class="page-head">
    <h1><?= t('data.title') ?></h1>
    <div class="page-actions">
        <a href="/settings/business" class="btn btn-ghost btn-sm"><?= t('settings.business') ?></a>
        <a href="/settings/system" class="btn btn-ghost btn-sm"><?= t('settings.system') ?></a>
        <a href="/settings/backup" class="btn btn-ghost btn-sm"><?= t('settings.backup') ?></a>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat-card">
        <div class="stat-label"><?= t('app.transactions') ?></div>
        <div class="stat-value" style="font-size:1.05rem"><?= (int)$stats['transactions'] ?></div>
        <div class="stat-sub"><?= t('data.stats_transactions_hint') ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('app.customers') ?></div>
        <div class="stat-value" style="font-size:1.05rem"><?= (int)$stats['customers'] ?></div>
        <div class="stat-sub"><?= t('data.stats_customers_hint') ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label"><?= t('app.currency') ?></div>
        <div class="stat-value" style="font-size:1.05rem"><?= (int)$stats['currencies'] ?></div>
        <div class="stat-sub"><?= t('data.stats_currencies_hint') ?></div>
    </div>
</div>

<?php if ($report): ?>
<div class="card mt-2">
    <div class="card-header">
        <h2>
            <svg class="icon" aria-hidden="true" style="color:<?= $report['imported'] > 0 ? 'var(--green)' : 'var(--amber)' ?>">
                <use href="/assets/img/icons.svg#<?= $report['imported'] > 0 ? 'check-square' : 'alert-triangle' ?>"/>
            </svg>
            <span><?= $report['imported'] > 0 ? t('data.import_ok') : t('data.import_nothing') ?></span>
            <?php if (!empty($report['file'])): ?>
                <span class="text-muted" style="font-weight:400;font-size:.8rem">— <?= e($report['file']) ?></span>
            <?php endif; ?>
        </h2>
    </div>
    <div class="card-body">
        <div class="stat-grid">
            <div class="card stat-card" style="box-shadow:none">
                <div class="stat-label"><?= t('data.report.imported') ?></div>
                <div class="stat-value" style="font-size:1.05rem;color:var(--green)"><?= (int)$report['imported'] ?></div>
            </div>
            <div class="card stat-card" style="box-shadow:none">
                <div class="stat-label"><?= t('data.report.failed') ?></div>
                <div class="stat-value" style="font-size:1.05rem;color:<?= $report['failed'] ? 'var(--red)' : 'var(--text)' ?>">
                    <?= count($report['failed']) ?>
                </div>
            </div>
            <div class="card stat-card" style="box-shadow:none">
                <div class="stat-label"><?= t('data.report.skipped') ?></div>
                <div class="stat-value" style="font-size:1.05rem"><?= count($report['skipped']) ?></div>
            </div>
            <div class="card stat-card" style="box-shadow:none">
                <div class="stat-label"><?= t('data.report.created_customers') ?></div>
                <div class="stat-value" style="font-size:1.05rem"><?= (int)$report['created_customers'] ?></div>
            </div>
            <div class="card stat-card" style="box-shadow:none">
                <div class="stat-label"><?= t('data.report.created_currencies') ?></div>
                <div class="stat-value" style="font-size:1.05rem">
                    <?= $report['created_currencies'] ? implode(', ', array_map('e', $report['created_currencies'])) : '0' ?>
                </div>
            </div>
        </div>

        <?php if (!empty($report['erased'])): ?>
            <p class="text-red text-strong mt-2" style="display:flex;align-items:center;gap:7px">
                <svg class="icon" aria-hidden="true"><use href="/assets/img/icons.svg#alert-triangle"/></svg>
                <span><?= t('data.report.erased') ?></span>
            </p>
        <?php endif; ?>

        <?php $issues = array_merge(
            array_map(static fn($s) => ['row' => $s['row'], 'reason' => $s['reason'], 'kind' => 'skip'], $report['skipped'] ?? []),
            array_map(static fn($f) => ['row' => $f['row'], 'reason' => $f['reason'], 'kind' => 'fail'], $report['failed'] ?? [])
        ); ?>
        <?php if ($issues): ?>
            <div class="table-wrap mt-2">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= t('data.report.row') ?></th>
                            <th><?= t('data.report.reason') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($issues as $issue): ?>
                            <tr>
                                <td class="num">
                                    <span class="pill <?= $issue['kind'] === 'fail' ? 'pill-red' : 'pill-blue' ?>">
                                        <?= (int)$issue['row'] ?>
                                    </span>
                                </td>
                                <td class="text-muted"><?= e((string)$issue['reason']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="form-hint mt-2"><?= t('data.report.hint') ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="grid-2 mt-2">
    <div class="card">
        <div class="card-header"><h2><?= t('data.export_title') ?></h2></div>
        <div class="card-body">
            <p class="form-hint mb-2"><?= t('data.export_hint') ?></p>
            <a class="btn btn-primary" href="/settings/data/export">
                <svg class="icon"><use href="/assets/img/icons.svg#download"/></svg>
                <?= t('data.export_btn') ?>
            </a>
            <div class="form-hint mt-2">
                <strong><?= t('data.columns_title') ?>:</strong>
                <span class="mono">Date · Amount · Currency · Rate · Method · Amount (CAD) · Name · Address · DOB · ID Type · ID Number · Place of Issue</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= t('data.import_title') ?></h2></div>
        <div class="card-body">
            <p class="form-hint mb-2"><?= t('data.import_hint') ?></p>

            <?php if (!$accounts): ?>
                <div class="empty">
                    <p class="text-red text-strong mb-1"><?= t('data.no_accounts') ?></p>
                    <p class="form-hint"><?= t('data.no_accounts_hint') ?></p>
                    <a class="btn btn-ghost btn-sm mt-2" href="/accounts"><?= t('account.accounts') ?></a>
                </div>
            <?php else: ?>
            <form method="post" action="/settings/data/import" enctype="multipart/form-data" id="importForm">
                <?= Csrf::field() ?>

                <div class="form-group">
                    <label class="form-label" for="importFile"><?= t('data.file') ?></label>
                    <input class="form-control" type="file" id="importFile" name="file"
                           accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                    <small class="form-hint"><?= t('data.file_hint') ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="importAccount"><?= t('data.account') ?></label>
                    <select class="form-select" id="importAccount" name="account_id" required>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= e($a['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint"><?= t('data.account_hint') ?></small>
                </div>

                <label class="form-check mb-2">
                    <input type="checkbox" name="allow_short" value="1">
                    <?= t('data.allow_short') ?>
                    <small class="form-hint"><?= t('data.allow_short_hint') ?></small>
                </label>

                <label class="form-check mb-2" style="align-items:flex-start">
                    <input type="checkbox" name="erase" value="1" id="eraseChk" style="margin-top:3px">
                    <span>
                        <strong class="text-red"><?= t('data.erase') ?></strong>
                        <small class="form-hint"><?= t('data.erase_hint') ?></small>
                    </span>
                </label>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit" id="importSubmit" data-importing="<?= t('data.importing') ?>">
                        <?= t('data.import_btn') ?>
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><h2><?= t('data.help_title') ?></h2></div>
    <div class="card-body">
        <ul class="form-hint" style="list-style:disc;padding-inline-start:1.2rem;line-height:1.9">
            <li><?= t('data.help_1') ?></li>
            <li><?= t('data.help_2') ?></li>
            <li><?= t('data.help_3') ?></li>
            <li><?= t('data.help_4') ?></li>
        </ul>
    </div>
</div>

<?php if ($accounts): ?>
<!-- Erase confirmation modal -->
<div class="modal-backdrop" id="eraseConfirm" role="dialog" aria-modal="true" aria-labelledby="eraseConfirmTitle">
    <div class="modal">
        <div class="modal-head">
            <h3 id="eraseConfirmTitle"><?= t('data.erase_confirm_title') ?></h3>
            <button type="button" class="icon-btn" aria-label="<?= t('app.close') ?>" onclick="closeModal('eraseConfirm')">
                <svg class="icon"><use href="/assets/img/icons.svg#x"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <p class="text-red text-strong mb-2" style="display:flex;align-items:flex-start;gap:7px">
                <svg class="icon" aria-hidden="true" style="flex:none;margin-top:2px"><use href="/assets/img/icons.svg#alert-triangle"/></svg>
                <span><?= t('data.erase_confirm') ?></span>
            </p>
            <input class="form-control" type="text" id="eraseInput" placeholder="ERASE"
                   autocomplete="off" aria-label="ERASE" aria-invalid="false" aria-describedby="eraseError">
            <small class="form-field-error" id="eraseError" hidden><?= t('data.erase_error') ?></small>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" onclick="closeModal('eraseConfirm')"><?= t('app.cancel') ?></button>
            <button type="button" class="btn btn-danger" onclick="confirmErase()"><?= t('data.erase_confirm_btn') ?></button>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('importForm');
    var chk = document.getElementById('eraseChk');
    var confirmed = false;
    var submitting = false;
    if (!form || !chk) return;
    var input = document.getElementById('eraseInput');
    var error = document.getElementById('eraseError');
    function showEraseError(show) {
        if (!input || !error) return;
        input.classList.toggle('is-invalid', show);
        error.hidden = !show;
        input.setAttribute('aria-invalid', show ? 'true' : 'false');
    }
    if (input) input.addEventListener('input', function () { showEraseError(false); });
    form.addEventListener('submit', function (e) {
        if (submitting) { e.preventDefault(); return; }
        if (chk.checked && !confirmed) {
            e.preventDefault();
            if (input) input.value = '';
            showEraseError(false);
            openModal('eraseConfirm');
            return;
        }
        submitting = true;
        var btn = document.getElementById('importSubmit');
        if (btn) {
            btn.disabled = true;
            var s = btn.getAttribute('data-importing');
            if (s) btn.textContent = s;
        }
    });
    window.confirmErase = function () {
        var v = (input.value || '').trim().toUpperCase();
        if (v === 'ERASE') {
            confirmed = true;
            showEraseError(false);
            closeModal('eraseConfirm');
            // requestSubmit re-runs validation and fires the submit listener,
            // which applies the double-submit guard on the Import button.
            if (form.requestSubmit) form.requestSubmit(); else form.submit();
        } else {
            showEraseError(true);
            if (input) input.focus();
        }
    };
})();
</script>
<?php endif; ?>

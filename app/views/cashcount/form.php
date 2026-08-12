<?php
/** @var array $accounts @var array $currencies @var array $denominations */
$denomsByCur = [];
foreach ($denominations as $d) { $denomsByCur[$d['currency_id']][] = $d; }
?>
<div class="page-head">
    <h1><?= t('cashcount.new') ?></h1>
    <div class="page-actions"><a href="/cash-count" class="btn btn-ghost btn-sm"><?= t('cashcount.title') ?></a></div>
</div>

<div class="card" style="max-width:860px">
    <div class="card-body">
        <form method="post" action="/cash-count">
            <?= Csrf::field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="account_id"><?= t('cashcount.account') ?> <span class="req">*</span></label>
                    <select class="form-select" id="account_id" name="account_id" required>
                        <option value="">—</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="count_date"><?= t('cashcount.date') ?></label>
                    <input class="form-control" type="date" id="count_date" name="count_date" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <?php foreach ($currencies as $c): ?>
                <?php if (empty($denomsByCur[$c['id']])) continue; ?>
                <div class="card mt-2" style="background:var(--surface-2)">
                    <div class="card-header"><h2><?= e($c['code']) ?> — <?= e($c['name']) ?></h2></div>
                    <div class="card-body">
                        <div class="form-grid" style="grid-template-columns:repeat(auto-fill,minmax(130px,1fr))">
                            <?php foreach ($denomsByCur[$c['id']] as $d): ?>
                                <div class="form-group">
                                    <label class="form-label" for="d<?= (int)$d['id'] ?>"><?= e($d['label']) ?> <?= e($d['kind'] === 'coin' ? '🪙' : '💵') ?></label>
                                    <input class="form-control" type="number" step="any" min="0" id="d<?= (int)$d['id'] ?>"
                                           name="counts[<?= (int)$c['id'] ?>][<?= (int)$d['id'] ?>]" placeholder="0">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="form-group mt-2">
                <label class="form-label" for="notes"><?= t('cashcount.notes') ?></label>
                <textarea class="form-textarea" id="notes" name="notes"></textarea>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= t('app.confirm') ?></button>
                <a href="/cash-count" class="btn btn-ghost"><?= t('app.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

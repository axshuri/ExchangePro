<?php /** @var array $roles @var array $permissions @var array $map */ ?>
<div class="page-head">
    <h1><?= t('role.roles') ?></h1>
    <div class="page-actions"><a href="/users" class="btn btn-ghost btn-sm"><?= t('user.users') ?></a></div>
</div>

<?php foreach ($roles as $role): ?>
<div class="card mb-2">
    <div class="card-header">
        <h2><?= e($role['name']) ?></h2>
        <span class="pill pill-gray" style="margin-inline-start:8px"><?= e($role['description']) ?></span>
        <?php if ($role['is_system']): ?><span class="pill pill-blue">system</span><?php endif; ?>
    </div>
    <form method="post" action="/roles/<?= (int)$role['id'] ?>">
        <?= Csrf::field() ?>
        <div class="card-body">
            <input type="hidden" name="name" value="<?= e($role['name']) ?>">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:6px 14px">
                <?php foreach ($permissions as $p): ?>
                    <label class="form-check">
                        <input type="checkbox" name="permissions[]" value="<?= e($p['code']) ?>"
                            <?= in_array($p['code'], $map[$role['id']] ?? [], true) ? 'checked' : '' ?>
                            <?= $role['is_system'] && $role['name'] === 'owner' ? 'disabled' : '' ?>>
                        <span><?= e($p['description']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (!$role['is_system'] || $role['name'] !== 'owner'): ?>
        <div class="card-body" style="padding-top:0">
            <button class="btn btn-primary btn-sm" type="submit"><?= t('app.save') ?></button>
        </div>
        <?php endif; ?>
    </form>
</div>
<?php endforeach; ?>

<div class="card">
    <div class="card-header"><h2><?= t('role.name') ?> +</h2></div>
    <div class="card-body">
        <form method="post" action="/roles" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <?= Csrf::field() ?>
            <div class="form-group" style="flex:1;min-width:180px">
                <label class="form-label" for="name"><?= t('role.name') ?></label>
                <input class="form-control" type="text" id="name" name="name" required>
            </div>
            <div class="form-group" style="flex:2;min-width:220px">
                <label class="form-label" for="description"><?= t('role.description') ?></label>
                <input class="form-control" type="text" id="description" name="description">
            </div>
            <button class="btn btn-primary" type="submit"><?= t('app.create') ?></button>
        </form>
    </div>
</div>

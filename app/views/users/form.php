<?php /** @var ?array $user @var array $roles */ ?>
<div class="page-head">
    <h1><?= $user ? t('user.users') . ' — ' . e($user['username']) : t('user.new') ?></h1>
    <div class="page-actions"><a href="/users" class="btn btn-ghost btn-sm"><?= t('user.users') ?></a></div>
</div>

<div class="card" style="max-width:640px">
    <div class="card-body">
        <form method="post" action="<?= $user ? '/users/' . (int)$user['id'] : '/users' ?>">
            <?= Csrf::field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="username"><?= t('user.username') ?> <span class="req">*</span></label>
                    <input class="form-control" type="text" id="username" name="username" required value="<?= e($user['username'] ?? '') ?>" <?= $user ? 'disabled' : '' ?>>
                </div>
                <div class="form-group">
                    <label class="form-label" for="full_name"><?= t('user.full_name') ?> <span class="req">*</span></label>
                    <input class="form-control" type="text" id="full_name" name="full_name" required value="<?= e($user['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="email"><?= t('user.email') ?> <span class="req">*</span></label>
                    <input class="form-control" type="email" id="email" name="email" required value="<?= e($user['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="role_id"><?= t('user.role') ?> <span class="req">*</span></label>
                    <select class="form-select" id="role_id" name="role_id" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= (int)($user['role_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password"><?= $user ? t('user.password') . ' (leave blank to keep)' : t('user.password') ?> <span class="req"><?= $user ? '' : '*' ?></span></label>
                    <input class="form-control" type="password" id="password" name="password" <?= $user ? '' : 'required' ?> minlength="8" autocomplete="new-password">
                    <small class="form-hint"><?= t('auth.password_min') ?></small>
                </div>
                <?php if ($user): ?>
                <div class="form-group">
                    <label class="form-label" for="status"><?= t('user.status') ?></label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>active</option>
                        <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>inactive</option>
                        <option value="locked" <?= $user['status'] === 'locked' ? 'selected' : '' ?>>locked</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-check" style="margin-top:22px">
                        <input type="checkbox" name="totp_enabled" <?= $user['totp_enabled'] ? 'checked' : '' ?>>
                        <?= t('user.totp') ?>
                    </label>
                    <small class="form-hint"><?= t('user.totp_hint') ?></small>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= t('app.save') ?></button>
                <a href="/users" class="btn btn-ghost"><?= t('app.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?php /** @var array $rows */ ?>
<div class="page-head">
    <h1><?= t('user.users') ?></h1>
    <div class="page-actions">
        <a href="/roles" class="btn btn-ghost btn-sm"><?= t('role.roles') ?></a>
        <a href="/users/create" class="btn btn-primary btn-sm" data-ajax-form="/users/create" data-ajax-title="<?= t('user.new') ?>">
            <svg class="icon icon-sm"><use href="/assets/img/icons.svg#plus"/></svg> <?= t('user.new') ?>
        </a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= t('user.username') ?></th>
                    <th><?= t('user.full_name') ?></th>
                    <th><?= t('user.email') ?></th>
                    <th><?= t('user.role') ?></th>
                    <th><?= t('user.status') ?></th>
                    <th><?= t('user.totp') ?></th>
                    <th><?= t('user.last_login') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $u): ?>
                    <tr>
                        <td class="mono"><strong><?= e($u['username']) ?></strong></td>
                        <td><?= e($u['full_name']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><span class="pill pill-blue"><?= e($u['role_name']) ?></span></td>
                        <td>
                            <span class="pill <?= $u['status'] === 'active' ? 'pill-green' : ($u['status'] === 'locked' ? 'pill-red' : 'pill-gray') ?>"><?= e($u['status']) ?></span>
                        </td>
                        <td><?= $u['totp_enabled'] ? '<span class="pill pill-green">ON</span>' : '<span class="pill pill-gray">OFF</span>' ?></td>
                        <td class="text-muted"><?= e(tz($u['last_login_at'], 'Y-m-d H:i')) ?></td>
                        <td class="right" style="white-space:nowrap">
                            <a href="/users/<?= (int)$u['id'] ?>/edit" class="btn btn-ghost btn-sm"><?= t('app.edit') ?></a>
                            <?php if ((int)$u['id'] !== Auth::id()): ?>
                                <form method="post" action="/users/<?= (int)$u['id'] ?>/disable" style="display:inline">
                                    <?= Csrf::field() ?>
                                    <button class="btn btn-ghost btn-sm" type="submit"><?= $u['status'] === 'active' ? t('app.cancel') : t('app.confirm') ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

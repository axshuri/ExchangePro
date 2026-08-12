<?php
declare(strict_types=1);

final class UserController extends Controller
{
    protected ?string $requirePermission = 'manage_users';

    public function index(): void
    {
        $rows = Database::query(
            "SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.id");
        $this->render('users/index', ['rows' => $rows]);
    }

    public function createForm(): void
    {
        $data = [
            'user' => null,
            'roles' => Database::query("SELECT * FROM roles ORDER BY id"),
        ];
        if ($this->isAjax()) {
            $this->renderBare('users/form', $data);
            return;
        }
        $this->render('users/form', $data);
    }

    public function store(): void
    {
        $this->csrfGuard();
        $v = new Validator($_POST, ['username' => 'Username', 'email' => 'Email', 'full_name' => 'Name', 'password' => 'Password', 'role_id' => 'Role']);
        $v->required('username')->required('email')->required('full_name')->required('password')->required('role_id')->email('email');
        if (strlen((string)($_POST['password'] ?? '')) < 8) {
            $v->errors()['password'][] = t('auth.password_min');
        }
        if (Database::fetch("SELECT id FROM users WHERE username = ?", [$_POST['username']])) {
            $this->fail(t('user.username_exists'), '/users/create');
        }
        if (Database::fetch("SELECT id FROM users WHERE email = ?", [$_POST['email']])) {
            $this->fail(t('user.email_exists'), '/users/create');
        }
        if ($v->passes() && strlen((string)($_POST['password'] ?? '')) >= 8) {
            $id = Database::insert('users', [
                'username' => trim($_POST['username']),
                'email' => trim($_POST['email']),
                'password_hash' => password_hash((string)$_POST['password'], cfg('security.password_algo')),
                'full_name' => trim($_POST['full_name']),
                'role_id' => (int)$_POST['role_id'],
            ]);
            AuditService::log('create_user', 'user', $id, null, ['username' => $_POST['username'], 'role' => (int)$_POST['role_id']]);
            $this->succeed(t('user.created'), '/users');
        }
        $this->fail($v->firstError(), '/users/create', $v->errors());
    }

    public function editForm(string $id): void
    {
        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) redirect('/users');
        $this->render('users/form', [
            'user' => $user,
            'roles' => Database::query("SELECT * FROM roles ORDER BY id"),
        ]);
    }

    public function update(string $id): void
    {
        Csrf::check();
        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) redirect('/users');
        $before = ['full_name' => $user['full_name'], 'role_id' => $user['role_id'], 'status' => $user['status']];

        $data = [
            'full_name' => trim($_POST['full_name']),
            'email' => trim($_POST['email']),
            'role_id' => (int)$_POST['role_id'],
            'status' => $_POST['status'] ?? 'active',
        ];
        if (!empty($_POST['password'])) {
            if (strlen((string)$_POST['password']) < 8) {
                Session::flash('danger', t('auth.password_min'));
                redirect('/users/' . $id . '/edit');
            }
            $data['password_hash'] = password_hash((string)$_POST['password'], cfg('security.password_algo'));
        }
        if (!empty($_POST['totp_enabled']) && empty($user['totp_secret'])) {
            $data['totp_secret'] = Auth::generateTOTPSecret();
            $data['totp_enabled'] = 1;
        } elseif (empty($_POST['totp_enabled'])) {
            $data['totp_enabled'] = 0;
        }

        Database::update('users', $data, 'id = ?', [$id]);
        AuditService::log('update_user', 'user', (int)$id, $before, $data);
        Session::flash('success', t('user.updated'));
        redirect('/users');
    }

    public function toggleStatus(string $id): void
    {
        Csrf::check();
        if ((int)$id === Auth::id()) {
            Session::flash('danger', t('user.cannot_self_disable'));
            redirect('/users');
        }
        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) redirect('/users');
        $new = $user['status'] === 'active' ? 'inactive' : 'active';
        Database::update('users', ['status' => $new], 'id = ?', [$id]);
        AuditService::log('toggle_user_status', 'user', (int)$id, ['status' => $user['status']], ['status' => $new]);
        Session::flash('success', t('user.updated'));
        redirect('/users');
    }

    // ---- Roles & permissions ----

    public function roles(): void
    {
        $roles = Database::query("SELECT * FROM roles ORDER BY id");
        $permissions = Database::query("SELECT * FROM permissions ORDER BY id");
        $map = [];
        foreach (Database::query("SELECT rp.role_id, p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id") as $rp) {
            $map[$rp['role_id']][] = $rp['code'];
        }
        $this->render('users/roles', ['roles' => $roles, 'permissions' => $permissions, 'map' => $map]);
    }

    public function storeRole(): void
    {
        Csrf::check();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            Session::flash('danger', t('validate.required'));
            redirect('/roles');
        }
        $id = Database::insert('roles', ['name' => $name, 'description' => $_POST['description'] ?? null]);
        $this->syncRolePermissions($id, $_POST['permissions'] ?? []);
        AuditService::log('create_role', 'role', $id, null, ['name' => $name]);
        Session::flash('success', t('role.saved'));
        redirect('/roles');
    }

    public function updateRole(string $id): void
    {
        Csrf::check();
        Database::update('roles', ['name' => trim($_POST['name']), 'description' => $_POST['description'] ?? null], 'id = ?', [$id]);
        $this->syncRolePermissions((int)$id, $_POST['permissions'] ?? []);
        AuditService::log('update_role', 'role', (int)$id, null, ['permissions' => array_values($_POST['permissions'] ?? [])]);
        Session::flash('success', t('role.saved'));
        redirect('/roles');
    }

    private function syncRolePermissions(int $roleId, array $codes): void
    {
        Database::execute("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
        foreach ($codes as $code) {
            $p = Database::fetch("SELECT id FROM permissions WHERE code = ?", [$code]);
            if ($p) {
                Database::insert('role_permissions', ['role_id' => $roleId, 'permission_id' => (int)$p['id']]);
            }
        }
    }
}

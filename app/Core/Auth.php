<?php
declare(strict_types=1);

/**
 * Authentication + RBAC. Passwords hashed with bcrypt; sessions; login history; rate limiting.
 */
final class Auth
{
    public static function attempt(string $username, string $password, string $ip): array
    {
        // Rate limiting: max attempts per IP+username within lock window
        $sec = cfg('security');
        $lockKey = 'login_lock_' . md5($username . '|' . $ip);
        $attempts = (int)(Database::value(
            "SELECT COUNT(*) FROM login_history WHERE username = ? AND ip = ? AND success = 0
             AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$username, $ip, (int)$sec['login_lock_minutes']]
        ) ?: 0);

        if ($attempts >= (int)$sec['login_max_attempts']) {
            return ['success' => false, 'error' => t('auth.too_many_attempts')];
        }

        $user = Database::fetch("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $username]);
        $ok = $user && password_verify($password, $user['password_hash']);

        Database::insert('login_history', [
            'user_id' => $user ? $user['id'] : null,
            'username' => $username,
            'ip' => $ip,
            'user_agent' => clientUA(),
            'success' => $ok ? 1 : 0,
        ]);

        if (!$ok) {
            return ['success' => false, 'error' => t('auth.invalid_credentials')];
        }
        if ($user['status'] !== 'active') {
            return ['success' => false, 'error' => t('auth.account_inactive')];
        }

        // Start 2FA challenge if enabled
        if ((int)$user['totp_enabled'] === 1 && !empty($user['totp_secret'])) {
            Session::set('pending_2fa_user_id', (int)$user['id']);
            Session::set('pending_2fa_username', $user['username']);
            return ['success' => true, 'two_factor' => true, 'user' => $user];
        }

        self::login($user);
        return ['success' => true, 'two_factor' => false, 'user' => $user];
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        Session::set('user_id', (int)$user['id']);
        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [(int)$user['id']]);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::get('user_id') !== null;
    }

    public static function user(): ?array
    {
        $id = Session::get('user_id');
        if (!$id) return null;
        static $cache = null;
        if ($cache && (int)$cache['id'] === (int)$id) return $cache;
        $cache = Database::fetch("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?", [$id]);
        return $cache ?: null;
    }

    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    /** Fallback user id for CLI/seeding when no session exists. */
    public static function effectiveUserId(): ?int
    {
        $id = self::id();
        if ($id) return $id;
        $u = Database::fetch("SELECT id FROM users WHERE status = 'active' ORDER BY id LIMIT 1");
        return $u ? (int)$u['id'] : null;
    }

    public static function hasPermission(string $perm): bool
    {
        $user = self::user();
        if (!$user) return false;
        $role = (int)$user['role_id'];
        static $cache = [];
        if (isset($cache[$role])) return in_array($perm, $cache[$role], true);
        $perms = array_column(Database::query(
            "SELECT p.code FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?", [$role]), 'code');
        $cache[$role] = $perms;
        return in_array($perm, $perms, true);
    }

    public static function verifyTOTP(string $code, string $secret): bool
    {
        if (!preg_match('/^\d{6}$/', $code)) return false;
        // 30-second windows, allow ±1 window
        for ($offset = -1; $offset <= 1; $offset++) {
            $timeSlice = intdiv(time() + $offset * 30, 30);
            $hash = hash_hmac('sha1', pack('N', $timeSlice), $secret, true);
            $offsetHash = ord($hash[strlen($hash) - 1]) & 0x0f;
            $truncated = (unpack('N', substr($hash, $offsetHash, 4))[1] & 0x7fffffff) % 1000000;
            if (hash_equals(str_pad((string)$truncated, 6, '0', STR_PAD_LEFT), $code)) {
                return true;
            }
        }
        return false;
    }

    /** Generate a base32 TOTP secret for 2FA setup. */
    public static function generateTOTPSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 20; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }
}

<?php
declare(strict_types=1);

final class AuthController extends Controller
{
    protected ?string $requirePermission = null;

    public function loginForm(): void
    {
        if (Auth::check()) redirect('/');
        $this->render('auth/login');
    }

    public function login(): void
    {
        Csrf::check();
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            Session::flash('danger', t('auth.invalid_credentials'));
            redirect('/login');
        }

        $result = Auth::attempt($username, $password, clientIp());
        if (!$result['success']) {
            Session::flash('danger', $result['error']);
            redirect('/login');
        }
        if (!empty($result['two_factor'])) {
            redirect('/2fa');
        }
        self::afterLoginSync();
        redirect('/');
    }

    public function twoFactorForm(): void
    {
        if (!Session::get('pending_2fa_user_id')) redirect('/login');
        $this->render('auth/2fa');
    }

    public function twoFactorVerify(): void
    {
        Csrf::check();
        $userId = Session::get('pending_2fa_user_id');
        if (!$userId) redirect('/login');
        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        $code = trim($_POST['code'] ?? '');
        if (!$user || !Auth::verifyTOTP($code, $user['totp_secret'])) {
            Session::flash('danger', t('auth.invalid_2fa'));
            redirect('/2fa');
        }
        Session::remove('pending_2fa_user_id');
        Session::remove('pending_2fa_username');
        Auth::login($user);
        self::afterLoginSync();
        redirect('/');
    }

    public function logout(): void
    {
        if (Auth::check()) {
            AuditService::log('logout', 'auth', Auth::id());
        }
        Auth::logout();
        redirect('/login');
    }

    public function setLang(string $lang): void
    {
        if (in_array($lang, I18n::available(), true)) {
            I18n::setLang($lang);
        }
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    /**
     * Login-triggered rate sync (fallback behind the scheduled cron job).
     * Best-effort: an outage must never block or fail the login.
     */
    private static function afterLoginSync(): void
    {
        try {
            RateSyncService::maybeSync();
        } catch (Throwable) {
            // Ignore — the app continues with the last known rates.
        }
    }
}

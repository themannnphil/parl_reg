<?php
// ParlReg — Authentication Helper

class Auth {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure',   APP_ENV === 'production' ? 1 : 0);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime',  SESSION_LIFETIME);
            session_start();
        }
    }

    public static function login(array $user): void {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['fullname'];
        $_SESSION['logged_in'] = true;
        // Update last_login
        DB::run('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
        Audit::log('login', 'user', $user['id']);
    }

    public static function logout(): void {
        self::start();
        Audit::log('logout', 'user', $_SESSION['user_id'] ?? null);
        session_unset();
        session_destroy();
    }

    public static function check(): bool {
        self::start();
        return !empty($_SESSION['logged_in']);
    }

    public static function user(): ?array {
        if (!self::check()) return null;
        return DB::row('SELECT id, fullname, email, role FROM users WHERE id = ? AND is_active = 1',
                       [$_SESSION['user_id']]);
    }

    public static function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string {
        return $_SESSION['user_role'] ?? null;
    }

    public static function requireAuth(): void {
        if (!self::check()) {
            Response::json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }
    }

    public static function requireRole(string ...$roles): void {
        self::requireAuth();
        if (!in_array(self::role(), $roles, true)) {
            Response::json(['success' => false, 'error' => 'Forbidden'], 403);
        }
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}

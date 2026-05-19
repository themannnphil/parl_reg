<?php
// ParlReg — Auth Controller
// Endpoints: POST /api/v1/auth/login
//            POST /api/v1/auth/logout
//            GET  /api/v1/auth/me
//            POST /api/v1/auth/forgot-password
//            POST /api/v1/auth/reset-password

class AuthController {
    public function login(): never {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = new Validator($data);
        $v->required('email', 'Email')
          ->email('email')
          ->required('password', 'Password');

        if ($v->fails()) {
            Response::json(['success' => false, 'errors' => $v->errors()], 422);
        }

        // Rate limit: 5 attempts per 10 minutes per IP
        RateLimit::check('login', RateLimit::ip(), LOGIN_MAX_ATTEMPTS, LOGIN_WINDOW_SECONDS);

        $user = DB::row('SELECT * FROM users WHERE email = ? AND is_active = 1',
                        [strtolower(trim($data['email']))]);

        if (!$user || !Auth::verifyPassword($data['password'], $user['password_hash'])) {
            Response::json(['success' => false, 'error' => 'Invalid credentials.'], 401);
        }

        Auth::login($user);

        Response::json([
            'success' => true,
            'user'    => [
                'id'       => $user['id'],
                'fullname' => $user['fullname'],
                'email'    => $user['email'],
                'role'     => $user['role'],
            ],
            'csrf_token' => CSRF::token(),
        ]);
    }

    public function logout(): never {
        Auth::requireAuth();
        Auth::logout();
        Response::json(['success' => true, 'message' => 'Logged out.']);
    }

    public function me(): never {
        Auth::requireAuth();
        $user = Auth::user();
        if (!$user) Response::json(['success' => false, 'error' => 'User not found.'], 404);
        Response::json(['success' => true, 'user' => $user]);
    }

    public function forgotPassword(): never {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = new Validator($data);
        $v->required('email')->email('email');
        if ($v->fails()) Response::json(['success' => false, 'errors' => $v->errors()], 422);

        $user = DB::row('SELECT id, fullname, email FROM users WHERE email = ? AND is_active = 1',
                        [strtolower(trim($data['email']))]);

        // Always respond success to prevent email enumeration
        if ($user) {
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expires   = date('Y-m-d H:i:s', time() + 3600);

            DB::run('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
                    [$user['id'], $tokenHash, $expires]);

            // In production: send reset link via Mailer
            // Mailer::send(['email' => $user['email'], 'name' => $user['fullname']], 'Reset Password', ...);
        }

        Response::json(['success' => true, 'message' => 'If that email exists, a reset link has been sent.']);
    }

    public function resetPassword(): never {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = new Validator($data);
        $v->required('token')->required('password');
        if ($v->fails()) Response::json(['success' => false, 'errors' => $v->errors()], 422);

        $tokenHash = hash('sha256', $data['token']);
        $row = DB::row('SELECT * FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > NOW()',
                       [$tokenHash]);

        if (!$row) Response::json(['success' => false, 'error' => 'Invalid or expired token.'], 400);

        $hash = Auth::hashPassword($data['password']);
        DB::run('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $row['user_id']]);
        DB::run('UPDATE password_resets SET used = 1 WHERE id = ?', [$row['id']]);

        Audit::log('password_reset', 'user', $row['user_id']);
        Response::json(['success' => true, 'message' => 'Password updated.']);
    }
}

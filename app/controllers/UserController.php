<?php
// ParlReg — User Controller
// GET    /api/v1/users         — list users (admin)
// POST   /api/v1/users         — create user
// GET    /api/v1/users/{id}    — get user
// PUT    /api/v1/users/{id}    — update user
// DELETE /api/v1/users/{id}    — deactivate user

class UserController {
    public function index(): never {
        Auth::requireRole('admin');
        $users = DB::all('SELECT id, fullname, email, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC');
        Response::json(['success' => true, 'data' => $users]);
    }

    public function store(): never {
        Auth::requireRole('admin');
        CSRF::verify();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = (new Validator($data))
            ->required('fullname', 'Full Name')
            ->required('email', 'Email')
            ->email('email')
            ->required('password', 'Password')
            ->required('role', 'Role')
            ->in('role', ['admin','organizer','viewer']);
        if ($v->fails()) Response::json(['success' => false, 'errors' => $v->errors()], 422);

        if (DB::row('SELECT id FROM users WHERE email = ?', [$data['email']])) {
            Response::json(['success' => false, 'error' => 'Email already exists.'], 409);
        }

        $id = DB::insert(
            'INSERT INTO users (fullname, email, password_hash, role) VALUES (?,?,?,?)',
            [$data['fullname'], strtolower(trim($data['email'])),
             Auth::hashPassword($data['password']), $data['role']]
        );

        Audit::log('user_create', 'user', $id, $data['email']);
        Response::json(['success' => true, 'data' => ['id' => $id]], 201);
    }

    public function show(int $id): never {
        Auth::requireRole('admin');
        $user = DB::row('SELECT id, fullname, email, role, is_active, last_login, created_at FROM users WHERE id = ?', [$id]);
        if (!$user) Response::json(['success' => false, 'error' => 'User not found.'], 404);
        Response::json(['success' => true, 'data' => $user]);
    }

    public function update(int $id): never {
        Auth::requireRole('admin');
        CSRF::verify();
        $user = DB::row('SELECT id FROM users WHERE id = ?', [$id]);
        if (!$user) Response::json(['success' => false, 'error' => 'User not found.'], 404);

        $data   = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = [];
        $params = [];

        if (!empty($data['fullname'])) { $fields[] = 'fullname = ?'; $params[] = $data['fullname']; }
        if (!empty($data['email']))    { $fields[] = 'email = ?';    $params[] = strtolower(trim($data['email'])); }
        if (!empty($data['role']) && in_array($data['role'], ['admin','organizer','viewer'], true)) {
            $fields[] = 'role = ?'; $params[] = $data['role'];
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?'; $params[] = (int)(bool)$data['is_active'];
        }
        if (!empty($data['password'])) {
            $fields[] = 'password_hash = ?'; $params[] = Auth::hashPassword($data['password']);
        }

        if (empty($fields)) Response::json(['success' => false, 'error' => 'Nothing to update.'], 400);

        $params[] = $id;
        DB::run('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
        Audit::log('user_update', 'user', $id);
        Response::json(['success' => true, 'message' => 'User updated.']);
    }

    public function destroy(int $id): never {
        Auth::requireRole('admin');
        CSRF::verify();
        if ($id === Auth::id()) Response::json(['success' => false, 'error' => 'Cannot deactivate yourself.'], 400);
        DB::run('UPDATE users SET is_active = 0 WHERE id = ?', [$id]);
        Audit::log('user_deactivate', 'user', $id);
        Response::json(['success' => true, 'message' => 'User deactivated.']);
    }
}

// ─────────────────────────────────────────────────────────────────────────────

// ParlReg — Settings Controller
// GET  /api/v1/settings/smtp                — list SMTP profiles
// POST /api/v1/settings/smtp                — create SMTP profile
// PUT  /api/v1/settings/smtp/{id}           — update SMTP profile
// DELETE /api/v1/settings/smtp/{id}         — delete SMTP profile
// POST /api/v1/settings/smtp/{id}/test      — test connection
// GET  /api/v1/audit-log                    — view audit log

class SettingsController {
    public function listSmtp(): never {
        Auth::requireRole('admin');
        $profiles = DB::all('SELECT id, name, host, port, encryption, username, created_at FROM smtp_profiles');
        Response::json(['success' => true, 'data' => $profiles]);
    }

    public function createSmtp(): never {
        Auth::requireRole('admin');
        CSRF::verify();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = (new Validator($data))
            ->required('name', 'Profile Name')
            ->required('host', 'Host')
            ->required('username', 'Username')
            ->required('password', 'Password')
            ->in('encryption', ['tls','ssl','none']);
        if ($v->fails()) Response::json(['success' => false, 'errors' => $v->errors()], 422);

        $id = DB::insert(
            'INSERT INTO smtp_profiles (name, host, port, encryption, username, password_encrypted)
             VALUES (?,?,?,?,?,?)',
            [$data['name'], $data['host'], $data['port'] ?? 587,
             $data['encryption'] ?? 'tls', $data['username'], $data['password']]
        );

        Audit::log('smtp_create', 'smtp_profile', $id);
        Response::json(['success' => true, 'data' => ['id' => $id]], 201);
    }

    public function updateSmtp(int $id): never {
        Auth::requireRole('admin');
        CSRF::verify();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $allowed = ['name','host','port','encryption','username'];
        $fields  = [];
        $params  = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
        }
        if (!empty($data['password'])) { $fields[] = 'password_encrypted = ?'; $params[] = $data['password']; }

        if (empty($fields)) Response::json(['success' => false, 'error' => 'Nothing to update.'], 400);
        $params[] = $id;
        DB::run('UPDATE smtp_profiles SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);

        Audit::log('smtp_update', 'smtp_profile', $id);
        Response::json(['success' => true, 'message' => 'SMTP profile updated.']);
    }

    public function deleteSmtp(int $id): never {
        Auth::requireRole('admin');
        CSRF::verify();
        DB::run('DELETE FROM smtp_profiles WHERE id = ?', [$id]);
        Audit::log('smtp_delete', 'smtp_profile', $id);
        Response::json(['success' => true, 'message' => 'SMTP profile deleted.']);
    }

    public function testSmtp(int $id): never {
        Auth::requireRole('admin');
        $profile = DB::row('SELECT * FROM smtp_profiles WHERE id = ?', [$id]);
        if (!$profile) Response::json(['success' => false, 'error' => 'Profile not found.'], 404);

        $admin = Auth::user();
        $sent  = Mailer::send(['email' => $admin['email'], 'name' => $admin['fullname']],
                              'ParlReg SMTP Test',
                              '<p>SMTP connection test from ParlReg. If you receive this, the profile is working.</p>',
                              $id);

        Response::json(['success' => $sent, 'message' => $sent ? 'Test email sent.' : 'Failed — check error log.']);
    }

    public function auditLog(): never {
        Auth::requireRole('admin');

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;
        $action = $_GET['action'] ?? null;
        $userId = $_GET['user_id'] ?? null;

        $where  = ['1=1'];
        $params = [];
        if ($action) { $where[] = 'a.action LIKE ?'; $params[] = "%$action%"; }
        if ($userId) { $where[] = 'a.user_id = ?';   $params[] = (int)$userId; }

        $whereStr = implode(' AND ', $where);
        $total    = DB::row("SELECT COUNT(*) as cnt FROM audit_log a WHERE $whereStr", $params)['cnt'];
        $rows     = DB::all("SELECT a.*, u.fullname as user_name, u.email as user_email
                             FROM audit_log a
                             LEFT JOIN users u ON u.id = a.user_id
                             WHERE $whereStr
                             ORDER BY a.created_at DESC
                             LIMIT $limit OFFSET $offset", $params);

        Response::json([
            'success'     => true,
            'data'        => $rows,
            'total'       => (int)$total,
            'page'        => $page,
            'per_page'    => $limit,
            'total_pages' => ceil($total / $limit),
        ]);
    }
}

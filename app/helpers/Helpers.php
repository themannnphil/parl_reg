<?php
// ParlReg — Core Helpers: CSRF, RateLimit, Logger, Audit, Response, Validator

// ─── CSRF ─────────────────────────────────────────────────────────────────────
class CSRF {
    public static function token(): string {
        Auth::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verify(): void {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
               ?? $_POST['_csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            Response::json(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        }
    }
}

// ─── Rate Limiter ─────────────────────────────────────────────────────────────
class RateLimit {
    public static function check(string $action, string $identifier, int $max, int $window): void {
        // Clean up expired windows
        DB::run('DELETE FROM rate_limits WHERE action = ? AND window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)',
                [$action, $window]);

        $row = DB::row('SELECT id, attempts FROM rate_limits WHERE identifier = ? AND action = ? AND window_start >= DATE_SUB(NOW(), INTERVAL ? SECOND)',
                       [$identifier, $action, $window]);

        if ($row) {
            if ($row['attempts'] >= $max) {
                Response::json(['success' => false, 'error' => 'Too many attempts. Please wait and try again.'], 429);
            }
            DB::run('UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
        } else {
            DB::run('INSERT INTO rate_limits (identifier, action, attempts, window_start) VALUES (?, ?, 1, NOW())',
                    [$identifier, $action]);
        }
    }

    public static function ip(): string {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

// ─── Logger ───────────────────────────────────────────────────────────────────
class Logger {
    public static function error(string $msg): void {
        self::write('ERROR', $msg, LOG_PATH . '/error.log');
    }
    public static function email(string $msg): void {
        self::write('EMAIL', $msg, LOG_PATH . '/email.log');
    }
    public static function file(string $msg): void {
        self::write('FILE', $msg, LOG_PATH . '/file.log');
    }
    private static function write(string $level, string $msg, string $path): void {
        $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $msg);
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}

// ─── Audit ────────────────────────────────────────────────────────────────────
class Audit {
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $detail = null): void {
        try {
            DB::run('INSERT INTO audit_log (user_id, action, entity_type, entity_id, detail, ip_address)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [Auth::id(), $action, $entityType, $entityId, $detail, RateLimit::ip()]);
        } catch (Throwable $e) {
            Logger::error('Audit log failed: ' . $e->getMessage());
        }
    }
}

// ─── Response ─────────────────────────────────────────────────────────────────
class Response {
    public static function json(array $data, int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

// ─── Validator ────────────────────────────────────────────────────────────────
class Validator {
    private array $errors = [];
    private array $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function required(string $field, string $label = ''): self {
        $label = $label ?: $field;
        if (empty(trim((string)($this->data[$field] ?? '')))) {
            $this->errors[$field] = "$label is required.";
        }
        return $this;
    }

    public function email(string $field): self {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Invalid email address.';
        }
        return $this;
    }

    public function maxLength(string $field, int $max): self {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $max) {
            $this->errors[$field] = "Must not exceed $max characters.";
        }
        return $this;
    }

    public function in(string $field, array $allowed): self {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field] = 'Invalid value.';
        }
        return $this;
    }

    public function slug(string $field): self {
        if (!empty($this->data[$field]) && !preg_match('/^[a-z0-9\-]+$/', $this->data[$field])) {
            $this->errors[$field] = 'Slug may only contain lowercase letters, numbers, and hyphens.';
        }
        return $this;
    }

    public function fails(): bool {
        return !empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }

    public static function sanitize(string $value): string {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    public static function slugify(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\-]+/', '-', $text);
        return trim($text, '-');
    }
}

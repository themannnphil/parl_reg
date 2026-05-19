<?php
// ParlReg — Bootstrap
// Loads .env, config, all helpers, models, controllers

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// ─── Load .env if present ────────────────────────────────────────────────────
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
        putenv(trim($key) . '=' . trim($val));
    }
}

// ─── Config ──────────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/config/config.php';

// ─── Error log path ──────────────────────────────────────────────────────────
ini_set('error_log', LOG_PATH . '/error.log');

// ─── Helpers ─────────────────────────────────────────────────────────────────
require_once BASE_PATH . '/app/helpers/DB.php';
require_once BASE_PATH . '/app/helpers/Auth.php';
require_once BASE_PATH . '/app/helpers/Helpers.php';
require_once BASE_PATH . '/app/helpers/Translator.php';
require_once BASE_PATH . '/app/helpers/FileHandler.php';
require_once BASE_PATH . '/app/helpers/Mailer.php';

// ─── Models ──────────────────────────────────────────────────────────────────
require_once BASE_PATH . '/app/models/Event.php';
require_once BASE_PATH . '/app/models/Registration.php';
require_once BASE_PATH . '/app/models/UploadedFile.php';
require_once BASE_PATH . '/app/models/User.php';
require_once BASE_PATH . '/app/models/FormSchema.php';

// ─── Controllers ─────────────────────────────────────────────────────────────
require_once BASE_PATH . '/app/controllers/AuthController.php';
require_once BASE_PATH . '/app/controllers/EventController.php';
require_once BASE_PATH . '/app/controllers/RegistrationController.php';
require_once BASE_PATH . '/app/controllers/UserController.php';
require_once BASE_PATH . '/app/controllers/PortalController.php';

// ─── PHPMailer (if composer not used, falls back gracefully) ─────────────────
$phpmailerPath = BASE_PATH . '/vendor/autoload.php';
if (file_exists($phpmailerPath)) {
    require_once $phpmailerPath;
} else {
    // Stub classes so the app doesn't crash without PHPMailer installed
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        class_alias('stdClass', 'PHPMailer\PHPMailer\PHPMailer');
    }
}

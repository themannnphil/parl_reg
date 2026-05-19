<?php
// ParlReg — Application Configuration
// Copy this file to config.php and set real values. Never commit secrets.

define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
define('DB_PORT',     getenv('DB_PORT')     ?: '3306');
define('DB_NAME',     getenv('DB_NAME')     ?: 'parlreg');
define('DB_USER',     getenv('DB_USER')     ?: 'root');
define('DB_PASS',     getenv('DB_PASS')     ?: 'Admin@1234!');
define('DB_CHARSET',  'utf8mb4');

define('APP_NAME',    'ParlReg');
define('APP_URL',     getenv('APP_URL') ?: 'http://localhost:8000');
define('APP_ENV',     getenv('APP_ENV') ?: 'development');  // development | production

// Paths
define('BASE_PATH',     dirname(__DIR__));
define('STORAGE_PATH',  BASE_PATH . '/storage/uploads');
define('LOG_PATH',      BASE_PATH . '/storage/logs');
define('LANG_PATH',     BASE_PATH . '/lang');

// Security
define('CSRF_TOKEN_LENGTH', 32);
define('SESSION_LIFETIME',  3600);           // seconds
define('BCRYPT_COST',       12);

// Rate limiting
define('LOGIN_MAX_ATTEMPTS',  5);
define('LOGIN_WINDOW_SECONDS', 600);         // 10 minutes
define('FORM_MAX_ATTEMPTS',   10);
define('FORM_WINDOW_SECONDS', 300);

// Uploads
define('UPLOAD_MAX_MB',        10);
define('UPLOAD_ALLOWED_TYPES', ['application/pdf','image/jpeg','image/png','image/gif',
                                 'application/msword',
                                 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// CAPTCHA (reCAPTCHA v2)
define('RECAPTCHA_SECRET',  getenv('RECAPTCHA_SECRET')  ?: '');
define('RECAPTCHA_SITEKEY', getenv('RECAPTCHA_SITEKEY') ?: '');

// Encryption key for SMTP passwords (32-byte hex)
define('ENCRYPT_KEY', getenv('ENCRYPT_KEY') ?: str_repeat('0', 64));

// API
define('API_PREFIX', '/api/v1');

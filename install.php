#!/usr/bin/env php
<?php
// ParlReg — One-command installer
// Usage: php install.php
// Reads .env, creates DB tables, runs seeder, verifies setup

echo "\n";
echo " ╔══════════════════════════════════════════╗\n";
echo " ║  ParlReg — Installation Script          ║\n";
echo " ╚══════════════════════════════════════════╝\n\n";

// Load .env
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    if (file_exists(__DIR__ . '/.env.example')) {
        copy(__DIR__ . '/.env.example', $envFile);
        echo " [!] .env not found — copied from .env.example\n";
        echo "     Please edit .env with your database credentials and re-run.\n\n";
        exit(1);
    }
    echo " [!] No .env file found. Please create one from .env.example\n\n";
    exit(1);
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line),'#') || !str_contains($line,'=')) continue;
    [$k,$v] = explode('=', $line, 2);
    putenv(trim($k).'='.trim($v));
    $_ENV[trim($k)] = trim($v);
}

require_once __DIR__ . '/config/config.php';

// ─── 1. DB connection test ────────────────────────────────────────────────────
echo " [1] Testing database connection… ";
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT),
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "OK\n";
} catch (PDOException $e) {
    echo "FAILED\n     " . $e->getMessage() . "\n\n";
    exit(1);
}

// ─── 2. Create database if missing ───────────────────────────────────────────
echo " [2] Creating database '".DB_NAME."' if not exists… ";
$pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `".DB_NAME."`");
echo "OK\n";

// ─── 3. Run migration ─────────────────────────────────────────────────────────
echo " [3] Running schema migration… ";
$sql = file_get_contents(__DIR__ . '/database/migrations/001_initial_schema.sql');
// Split on semicolons but respect multi-line statements
$statements = array_filter(array_map('trim', explode(';', $sql)));
foreach ($statements as $stmt) {
    if (empty($stmt)) continue;
    try { $pdo->exec($stmt); }
    catch (PDOException $e) {
        // Ignore "already exists" errors
        if (!str_contains($e->getMessage(), 'already exists')) {
            echo "FAILED\n     " . $e->getMessage() . "\n     Statement: " . substr($stmt, 0, 80) . "\n\n";
            exit(1);
        }
    }
}
echo "OK\n";

// ─── 4. Storage directories ───────────────────────────────────────────────────
echo " [4] Creating storage directories… ";
foreach ([STORAGE_PATH, LOG_PATH] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0750, true);
}
echo "OK\n";

// ─── 5. Run seeder ────────────────────────────────────────────────────────────
echo " [5] Running database seeder…\n";
require_once __DIR__ . '/app/helpers/DB.php';
require_once __DIR__ . '/app/helpers/Auth.php';
require_once __DIR__ . '/app/helpers/Helpers.php';
require_once __DIR__ . '/app/helpers/Translator.php';
require_once __DIR__ . '/app/helpers/FileHandler.php';
require_once __DIR__ . '/app/helpers/Mailer.php';
require_once __DIR__ . '/app/models/Event.php';
require_once __DIR__ . '/app/models/Registration.php';
require_once __DIR__ . '/app/models/UploadedFile.php';
require_once __DIR__ . '/app/models/User.php';
require_once __DIR__ . '/app/models/FormSchema.php';
require_once __DIR__ . '/database/seeds/seed.php';

// ─── 6. Check composer ────────────────────────────────────────────────────────
echo "\n [6] Checking PHPMailer (composer)… ";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "OK\n";
} else {
    echo "NOT INSTALLED\n";
    echo "     Run: composer install\n";
    echo "     (Email sending will be disabled until PHPMailer is installed)\n";
}

// ─── Done ─────────────────────────────────────────────────────────────────────
$appUrl = getenv('APP_URL') ?: 'http://localhost:8000';
echo "\n";
echo " ✓ Installation complete!\n\n";
echo " Start dev server:  php -S localhost:8000 -t public/\n";
echo " Run tests:         php tests/api_test.php $appUrl\n\n";
echo " Admin login:       $appUrl/admin\n";
echo "   Email:           admin\@parliament.local\n";
echo "   Password:        Admin\@ParlReg1   ← CHANGE THIS IMMEDIATELY\n\n";
echo " Sample portal:     $appUrl/events/inter-parliamentary-forum-2026\n\n";

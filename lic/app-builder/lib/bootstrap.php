<?php

if (session_status() === PHP_SESSION_NONE) {
    // 30 days session cookie lifetime
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// Timezone
date_default_timezone_set('UTC');

// Debug mode support via query parameter or environment
if (isset($_GET['debug']) || (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true')) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// Helper to load environment variables from .env
function app_builder_load_env(): array
{
    static $env = null;
    if ($env !== null) {
        return $env;
    }
    $env = [];
    $candidates = [
        __DIR__ . '/../.env',
        __DIR__ . '/../../.env',
        __DIR__ . '/../../../.env',
        __DIR__ . '/.env',
    ];
    foreach ($candidates as $candidate) {
        if (file_exists($candidate) && is_readable($candidate)) {
            $lines = file($candidate, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    [$k, $v] = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v, " \t\n\r\0\x0B\"'");
                    $env[$k] = $v;
                }
            }
            break;
        }
    }
    return $env;
}

// Database Connection with automatic environment detection & fallback resilience
if (!function_exists('db')) {
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $env = app_builder_load_env();

    // Priority 1: .env configuration
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '3306';
    $name = $env['DB_DATABASE'] ?? 'saas-db';
    $user = $env['DB_USERNAME'] ?? 'saas-db';
    $pass = $env['DB_PASSWORD'] ?? 'J5hyUHkgwhVgS6nJm7yA';

    // Priority 2: Standalone License Server config if available
    $licenseConfigCandidates = [
        __DIR__ . '/../../config/config.php',
        __DIR__ . '/../config/config.php',
        dirname(__DIR__, 2) . '/config/config.php',
    ];
    foreach ($licenseConfigCandidates as $licenseConfig) {
        if (file_exists($licenseConfig)) {
            require_once $licenseConfig;
            if (defined('DB_HOST')) $host = DB_HOST;
            if (defined('DB_PORT')) $port = DB_PORT;
            if (defined('DB_NAME')) $name = DB_NAME;
            if (defined('DB_USER')) $user = DB_USER;
            if (defined('DB_PASS')) $pass = DB_PASS;
            break;
        }
    }

    // List of candidate credentials to try in sequence
    $credentials = [
        ['host' => $host, 'port' => $port, 'name' => $name, 'user' => $user, 'pass' => $pass],
        ['host' => '127.0.0.1', 'port' => '3306', 'name' => 'u356050643_license_mngr', 'user' => 'u356050643_license_mngr', 'pass' => 'N4jJ8R2pyZWTpp4oghrq'],
        ['host' => '127.0.0.1', 'port' => '3306', 'name' => 'saas-db', 'user' => 'saas-db', 'pass' => 'J5hyUHkgwhVgS6nJm7yA'],
    ];

    $lastEx = null;
    foreach ($credentials as $cred) {
        try {
            $pdo = new PDO(
                "mysql:host={$cred['host']};port={$cred['port']};dbname={$cred['name']};charset=utf8mb4",
                $cred['user'],
                $cred['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            ensure_app_builder_schema($pdo);
            return $pdo;
        } catch (Throwable $e) {
            $lastEx = $e;
        }
    }

    throw new RuntimeException("Database connection error: Could not connect to database '{$name}' on '{$host}:{$port}'. Error: " . ($lastEx ? $lastEx->getMessage() : 'Unknown error'));
}
}

// Automatically ensures database schema has all needed tables and columns
function ensure_app_builder_schema(?PDO $pdo = null, bool $force = false): void
{
    static $done = false;
    if ($done && !$force) return;
    $done = true;
    try {
        $pdo = $pdo ?: db();
    } catch (Throwable $e) {
        return;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `app_builds` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `build_uid` VARCHAR(64) NOT NULL UNIQUE,
          `batch_id` VARCHAR(64) NULL,
          `license_key` VARCHAR(64) NOT NULL,
          `license_id` INT NULL,
          `order_id` INT NULL,
          `order_reference` VARCHAR(191) NULL,
          `client_email` VARCHAR(191) NOT NULL,
          `platform` ENUM('android', 'web', 'windows', 'ios') NOT NULL,
          `source_type` ENUM('latest_github', 'uploaded_zip') NOT NULL DEFAULT 'latest_github',
          `app_name` VARCHAR(191) NOT NULL DEFAULT 'Zoom Sales POS',
          `package_id` VARCHAR(191) NOT NULL DEFAULT 'com.zoomnearby.zoompos',
          `build_version` VARCHAR(64) NOT NULL DEFAULT '1.0.0',
          `server_url` VARCHAR(255) NOT NULL DEFAULT 'https://saas.zoomnearby.com',
          `primary_color` VARCHAR(32) NOT NULL DEFAULT '#4F46E5',
          `custom_logo_path` VARCHAR(255) NULL,
          `branding_json` JSON NULL,
          `status` ENUM('queued', 'preparing', 'building', 'completed', 'failed', 'cancelled', 'expired') NOT NULL DEFAULT 'queued',
          `github_run_id` BIGINT NULL,
          `github_workflow_id` VARCHAR(128) NULL,
          `artifact_path` VARCHAR(255) NULL,
          `artifact_filename` VARCHAR(191) NULL,
          `artifact_size_bytes` BIGINT NULL,
          `error_message` TEXT NULL,
          `email_sent` TINYINT(1) NOT NULL DEFAULT 0,
          `build_duration_seconds` INT NULL,
          `started_at` DATETIME NULL,
          `completed_at` DATETIME NULL,
          `expires_at` DATETIME NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_license` (`license_key`),
          INDEX `idx_license_id` (`license_id`),
          INDEX `idx_order_ref` (`order_reference`),
          INDEX `idx_batch_id` (`batch_id`),
          INDEX `idx_email` (`client_email`),
          INDEX `idx_status` (`status`),
          INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}

    // Ensure columns exist on app_builds
    $neededCols = [
        'batch_id' => 'VARCHAR(64) NULL AFTER `build_uid`',
        'license_id' => 'INT NULL AFTER `license_key`',
        'order_id' => 'INT NULL AFTER `license_id`',
        'order_reference' => 'VARCHAR(191) NULL AFTER `order_id`',
        'build_version' => "VARCHAR(64) NOT NULL DEFAULT '1.0.0' AFTER `package_id`",
        'branding_json' => 'JSON NULL AFTER `custom_logo_path`',
        'source_type' => "ENUM('latest_github', 'uploaded_zip') NOT NULL DEFAULT 'latest_github'",
        'artifact_filename' => 'VARCHAR(191) NULL',
        'artifact_size_bytes' => 'BIGINT NULL',
        'error_message' => 'TEXT NULL',
        'email_sent' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'build_duration_seconds' => 'INT NULL',
        'started_at' => 'DATETIME NULL',
        'completed_at' => 'DATETIME NULL',
        'expires_at' => 'DATETIME NULL',
    ];

    foreach ($neededCols as $col => $def) {
        try {
            $pdo->query("SELECT `{$col}` FROM `app_builds` LIMIT 0");
        } catch (Throwable $e) {
            try {
                $pdo->exec("ALTER TABLE `app_builds` ADD COLUMN `{$col}` {$def}");
            } catch (Throwable $e2) {
                if (str_contains($def, 'JSON')) {
                    try { $pdo->exec("ALTER TABLE `app_builds` ADD COLUMN `{$col}` TEXT NULL"); } catch (Throwable $e3) {}
                }
            }
        }
    }

    // Ensure builder_email_sent on payments table
    try {
        $pdo->query("SELECT `builder_email_sent` FROM `payments` LIMIT 0");
    } catch (Throwable $e) {
        try {
            $pdo->exec("ALTER TABLE `payments` ADD COLUMN `builder_email_sent` TINYINT(1) NOT NULL DEFAULT 0");
        } catch (Throwable $e2) {}
    }

    // Ensure build limit is cleared on any non-core modules or extensions
    try {
        $pdo->exec("UPDATE `licenses` SET `app_builder_monthly_limit` = NULL WHERE `product_slug` NOT IN ('core', 'main', 'pos', 'zoom-pos') AND `app_builder_monthly_limit` IS NOT NULL");
    } catch (Throwable $e) {}

    // Ensure payment_reference exists on licenses
    try {
        $pdo->query('SELECT payment_reference FROM licenses LIMIT 0');
    } catch (Throwable $e) {
        try {
            $pdo->exec('ALTER TABLE `licenses` ADD COLUMN `payment_reference` VARCHAR(191) NULL');
        } catch (Throwable $e2) {}
    }

    $done = true;
}

// Get setting from settings table with fallback to .env and default
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $stmt = db()->query('SELECT k, v FROM settings');
            while ($row = $stmt->fetch()) {
                $cache[$row['k']] = $row['v'];
            }
        } catch (Throwable $e) {
            // Table might not exist yet
        }
    }
    if (array_key_exists($key, $cache) && $cache[$key] !== '' && $cache[$key] !== null) {
        return $cache[$key];
    }

    // Check .env configuration
    $env = app_builder_load_env();
    $upper = strtoupper(str_replace('-', '_', $key));
    if (isset($env[$upper]) && $env[$upper] !== '') {
        return $env[$upper];
    }
    if (isset($env[$key]) && $env[$key] !== '') {
        return $env[$key];
    }

    return $default;
}

// Set setting value
function set_setting(string $key, ?string $value): void
{
    db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')
        ->execute([$key, (string)$value]);
}

// HTML escape
if (!function_exists('e')) {
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
}

// JSON Output with CORS
if (!function_exists('json_out')) {
function json_out(int $code, array $payload): void
{
    http_response_code($code);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}
}

// Read JSON Body
if (!function_exists('read_json_body')) {
function read_json_body(): array
{
    $data = json_decode((string)file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}
}

// Time ago helper
if (!function_exists('time_ago')) {
function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', strtotime($datetime));
}
}

// Format bytes
if (!function_exists('format_bytes')) {
function format_bytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
}

// CSRF Helpers
function csrf_token(): string
{
    if (empty($_SESSION['builder_csrf'])) {
        $_SESSION['builder_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['builder_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), (string)$sent)) {
        json_out(403, ['success' => false, 'message' => 'CSRF validation failed. Refresh the page and try again.']);
    }
}

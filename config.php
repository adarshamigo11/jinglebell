<?php
// =====================================================
// TradeZenfy - Configuration File
// =====================================================
// Railway.app environment variables are auto-detected
// Local XAMPP uses hardcoded values
// =====================================================

// Database: Railway env vars > local defaults
define('DB_HOST', getenv('MYSQLHOST')     ?: 'localhost');
define('DB_USER', getenv('MYSQLUSER')     ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'jinglebell');
define('DB_PORT', getenv('MYSQLPORT')     ?: '3306');

// Site URL: Railway provides RAILWAY_PUBLIC_DOMAIN
define('SITE_URL', getenv('RAILWAY_PUBLIC_DOMAIN')
    ? 'https://' . getenv('RAILWAY_PUBLIC_DOMAIN')
    : 'http://localhost/jinglebell');

// Environment: production on Railway, development locally
define('ENVIRONMENT', getenv('RAILWAY_ENVIRONMENT') ?: 'development');
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}


define('SITE_NAME', 'TradeZenfy');
define('TIMEZONE',  'Asia/Kolkata');

define('UPLOAD_DIR',     __DIR__ . '/uploads/');
define('PAYMENT_UPLOAD', __DIR__ . '/uploads/payments/');
define('QR_UPLOAD',      __DIR__ . '/uploads/qr_codes/');

define('SESSION_TIMEOUT', 3600); // 1 hour

date_default_timezone_set(TIMEZONE);

// ── Database Connection ──────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
        }
    }
    return $pdo;
}

// ── Session helpers ──────────────────────────────────
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path'     => '/',
            'secure'   => $isHttps,  // Auto-detect HTTPS
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// ── Admin activity logger ────────────────────────────
function logAdminAction(int $adminId, string $action, string $entityType = '', int $entityId = 0, string $details = ''): void {
    try {
        $db   = getDB();
        $stmt = $db->prepare("
            INSERT INTO admin_activity_log (admin_id, action, entity_type, entity_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$adminId, $action, $entityType, $entityId ?: null, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        // Silently fail — log should never break the main flow
    }
}

// ── JSON response helper ─────────────────────────────
function jsonResponse(bool $success, string $message, array $data = []): never {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// ── Sanitise input ───────────────────────────────────
function clean(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

// ── Format currency ──────────────────────────────────
function formatINR(float $amount): string {
    return '₹' . number_format($amount, 2);
}

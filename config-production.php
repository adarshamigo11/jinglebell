<?php
// =====================================================
// Alpha Trading - Production Configuration
// =====================================================
// ⚠️ IMPORTANT: Update these values for InfinityFree hosting

// Database Configuration (InfinityFree)
define('DB_HOST', 'sql113.infinityfree.com');
define('DB_USER', 'if0_42133834');
define('DB_PASS', 'j5CxpQUMomG');
define('DB_NAME', 'if0_42133834_tradezenfy');

// Site URL
define('SITE_URL', 'https://tradezenfy.xo.je');

// Site Settings
define('SITE_NAME', 'TradeZenfy');
define('TIMEZONE',  'Asia/Kolkata');

// Upload Directories
define('UPLOAD_DIR',     __DIR__ . '/uploads/');
define('PAYMENT_UPLOAD', __DIR__ . '/uploads/payments/');
define('QR_UPLOAD',      __DIR__ . '/uploads/qr_codes/');

// Session Settings
define('SESSION_TIMEOUT', 3600); // 1 hour

// Set timezone
date_default_timezone_set(TIMEZONE);

// ── Database Connection ──────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // InfinityFree sometimes needs longer timeouts
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true,  // InfinityFree works better with this
                PDO::ATTR_TIMEOUT            => 30,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            // Log error for debugging
            error_log('Database connection failed: ' . $e->getMessage());
            die(json_encode([
                'success' => false, 
                'message' => 'Database connection failed. Please check your credentials and try again.',
                'error' => $e->getMessage() // Remove this line in production
            ]));
        }
    }
    return $pdo;
}

// ── Session helpers ──────────────────────────────────
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path'     => '/',
            'secure'   => true,   // ✅ Set to true for HTTPS (InfinityFree)
            'httponly' => true,
            'samesite' => 'Strict',
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

<?php
// =====================================================
// Trade-Zenfy - Middleware (Auth only, no geofencing)
// =====================================================

require_once __DIR__ . '/../config.php';

startSecureSession();

// ── Require logged-in user ───────────────────────────
function requireUser(): array {
    startSecureSession();
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'user') {
        header('Location: ' . SITE_URL . '/login.php?reason=auth');
        exit;
    }
    // Check session timeout
    if (!empty($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: ' . SITE_URL . '/login.php?reason=timeout');
        exit;
    }
    $_SESSION['last_active'] = time();

    // Verify user still approved and not blocked
    $db   = getDB();
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, username, status, current_balance, portfolio_value, total_pnl, is_blocked FROM account_registrations WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'approved' || $user['is_blocked']) {
        session_destroy();
        header('Location: ' . SITE_URL . '/login.php?reason=blocked');
        exit;
    }
    return $user;
}

// ── Require logged-in admin ──────────────────────────
function requireAdmin(): array {
    startSecureSession();
    if (empty($_SESSION['admin_id']) || empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        header('Location: ' . SITE_URL . '/admin/login.php?reason=auth');
        exit;
    }
    if (!empty($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: ' . SITE_URL . '/admin/login.php?reason=timeout');
        exit;
    }
    $_SESSION['last_active'] = time();

    $db   = getDB();
    $stmt = $db->prepare("SELECT id, username, name, email, role FROM admins WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!$admin) {
        session_destroy();
        header('Location: ' . SITE_URL . '/admin/login.php?reason=auth');
        exit;
    }
    return $admin;
}

// ── Redirect already-logged-in users ────────────────
function redirectIfLoggedIn(): void {
    startSecureSession();
    if (!empty($_SESSION['user_id']) && $_SESSION['user_role'] === 'user') {
        header('Location: ' . SITE_URL . '/user/dashboard.php');
        exit;
    }
    if (!empty($_SESSION['admin_id']) && $_SESSION['user_role'] === 'admin') {
        header('Location: ' . SITE_URL . '/admin/index.php');
        exit;
    }
}

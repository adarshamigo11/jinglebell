<?php
// =====================================================
// Trade-Zenfy - Logout
// =====================================================

require_once __DIR__ . '/config.php';

startSecureSession();

$role = $_SESSION['user_role'] ?? 'user';

// Log admin logout
if ($role === 'admin' && !empty($_SESSION['admin_id'])) {
    logAdminAction($_SESSION['admin_id'], 'LOGOUT', 'admin', $_SESSION['admin_id'], 'Admin logged out');
}

// Destroy session completely
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

// Redirect based on role
if ($role === 'admin') {
    header('Location: admin/login.php?msg=logged_out');
} else {
    header('Location: login.php?msg=logged_out');
}
exit;

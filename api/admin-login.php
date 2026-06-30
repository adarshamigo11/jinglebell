<?php
// =====================================================
// Trade-Zenfy - Admin Login API
// POST /api/admin-login.php
// =====================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$username = strtolower(trim($input['username'] ?? ''));
$password = $input['password'] ?? '';

if (!$username || !$password) {
    jsonResponse(false, 'Username and password are required.');
}

$db   = getDB();
$stmt = $db->prepare("SELECT id, username, name, email, password, role, is_active FROM admins WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password'])) {
    jsonResponse(false, 'Invalid credentials.');
}

if (!$admin['is_active']) {
    jsonResponse(false, 'Admin account is inactive.');
}

// Update last login
$db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

startSecureSession();
session_regenerate_id(true);

$_SESSION['admin_id']   = $admin['id'];
$_SESSION['user_role']  = 'admin';
$_SESSION['admin_name'] = $admin['name'];
$_SESSION['admin_role'] = $admin['role'];
$_SESSION['last_active'] = time();

logAdminAction($admin['id'], 'LOGIN', 'admin', $admin['id'], 'Admin logged in');

jsonResponse(true, 'Login successful.', [
    'admin_id' => (int)$admin['id'],
    'redirect' => SITE_URL . '/admin/index.php',
]);

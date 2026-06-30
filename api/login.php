<?php
// =====================================================
// Trade-Zenfy - User Login API
// POST /api/login.php
// =====================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$login    = strtolower(trim($input['username'] ?? ''));
$password = $input['password'] ?? '';

if (!$login || !$password) {
    jsonResponse(false, 'Username/email and password are required.');
}

$db   = getDB();
$stmt = $db->prepare("
    SELECT id, first_name, last_name, username, email, password, status, is_blocked
    FROM account_registrations
    WHERE email = ? OR username = ?
    LIMIT 1
");
$stmt->execute([$login, $login]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    jsonResponse(false, 'Invalid credentials.');
}

if ($user['is_blocked']) {
    jsonResponse(false, 'Your account has been blocked. Please contact support.');
}

if ($user['status'] === 'pending') {
    jsonResponse(false, 'Your account is pending admin approval. Please check back soon.');
}

if ($user['status'] === 'rejected') {
    jsonResponse(false, 'Your account application was rejected. Please contact support.');
}

// ── Start session ────────────────────────────────────
startSecureSession();
session_regenerate_id(true);

$_SESSION['user_id']    = $user['id'];
$_SESSION['user_role']  = 'user';
$_SESSION['username']   = $user['username'];
$_SESSION['first_name'] = $user['first_name'];
$_SESSION['last_active'] = time();

jsonResponse(true, 'Login successful.', [
    'user_id'    => (int)$user['id'],
    'first_name' => $user['first_name'],
    'redirect'   => SITE_URL . '/user/dashboard.php',
]);

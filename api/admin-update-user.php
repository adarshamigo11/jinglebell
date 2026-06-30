<?php
// =====================================================
// Trade-Zenfy - Admin Update User Status API
// POST /api/admin-update-user.php
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$userId = (int)($input['user_id'] ?? 0);
$status = $input['status'] ?? '';
$remark = clean($input['remark'] ?? '');

if (!$userId) {
    jsonResponse(false, 'Invalid user ID.');
}

$allowed = ['approved', 'rejected', 'pending'];
if (!in_array($status, $allowed, true)) {
    jsonResponse(false, 'Invalid status.');
}

$db   = getDB();
$stmt = $db->prepare("SELECT id, first_name, last_name, status FROM account_registrations WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    jsonResponse(false, 'User not found.');
}

$db->prepare("UPDATE account_registrations SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$status, $userId]);

logAdminAction(
    $admin['id'],
    'USER_STATUS_' . strtoupper($status),
    'account_registrations',
    $userId,
    "Changed status to $status for {$user['first_name']} {$user['last_name']}" . ($remark ? " | Remark: $remark" : '')
);

jsonResponse(true, "User status updated to $status.");

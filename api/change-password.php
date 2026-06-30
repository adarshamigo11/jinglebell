<?php
// =====================================================
// Trade-Zenfy - Change Password API
// POST /api/change-password.php
// =====================================================
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
header('Content-Type: application/json');

$input   = json_decode(file_get_contents('php://input'), true) ?: [];
$current = $input['current_password'] ?? '';
$new     = $input['new_password'] ?? '';

if (!$current || !$new) jsonResponse(false, 'Both fields are required.');
if (strlen($new) < 8)   jsonResponse(false, 'New password must be at least 8 characters.');

$db   = getDB();
$stmt = $db->prepare("SELECT password FROM account_registrations WHERE id = ?");
$stmt->execute([$user['id']]);
$row  = $stmt->fetch();

if (!password_verify($current, $row['password'])) {
    jsonResponse(false, 'Current password is incorrect.');
}

$db->prepare("UPDATE account_registrations SET password=?, updated_at=NOW() WHERE id=?")
   ->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);

jsonResponse(true, 'Password changed successfully.');

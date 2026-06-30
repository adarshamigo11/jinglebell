<?php
// POST /api/admin-toggle-admin.php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
if ($admin['role'] !== 'super_admin') jsonResponse(false, 'Permission denied.');
header('Content-Type: application/json');

$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$targetId = (int)($input['admin_id'] ?? 0);
$isActive = (int)($input['is_active'] ?? 0);

if (!$targetId) jsonResponse(false, 'Invalid admin ID.');
if ($targetId === $admin['id']) jsonResponse(false, 'Cannot deactivate yourself.');

getDB()->prepare("UPDATE admins SET is_active=?, updated_at=NOW() WHERE id=?")->execute([$isActive, $targetId]);
logAdminAction($admin['id'], 'ADMIN_'.($isActive?'ACTIVATED':'DEACTIVATED'), 'admins', $targetId, '');
jsonResponse(true, 'Admin status updated.');

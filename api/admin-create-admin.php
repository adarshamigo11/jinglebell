<?php
// POST /api/admin-create-admin.php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
if ($admin['role'] !== 'super_admin') jsonResponse(false, 'Permission denied.');
header('Content-Type: application/json');

$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$name     = clean($input['name'] ?? '');
$username = strtolower(clean($input['username'] ?? ''));
$email    = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';
$role     = in_array($input['role'] ?? '', ['admin','super_admin']) ? $input['role'] : 'admin';

if (!$name || !$username || !$email || !$password) jsonResponse(false, 'All fields are required.');
if (strlen($password) < 8) jsonResponse(false, 'Password must be at least 8 characters.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email address.');

$db = getDB();
$check = $db->prepare("SELECT id FROM admins WHERE username=? OR email=?");
$check->execute([$username, $email]);
if ($check->fetch()) jsonResponse(false, 'Username or email already exists.');

$db->prepare("INSERT INTO admins (username, password, email, name, role) VALUES (?,?,?,?,?)")
   ->execute([$username, password_hash($password, PASSWORD_BCRYPT), $email, $name, $role]);

logAdminAction($admin['id'], 'ADMIN_CREATED', 'admins', (int)$db->lastInsertId(), "Created admin: $username");
jsonResponse(true, 'Admin created successfully.');

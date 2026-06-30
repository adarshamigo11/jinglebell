<?php
// =====================================================
// Trade-Zenfy - Admin Adjust User Balance API
// POST /api/admin-adjust-balance.php
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$userId = (int)($input['user_id'] ?? 0);
$type   = $input['type'] ?? ''; // 'credit' or 'debit'
$amount = (float)($input['amount'] ?? 0);
$reason = clean($input['reason'] ?? '');

if (!$userId) jsonResponse(false, 'Invalid user ID.');
if (!in_array($type, ['credit', 'debit'], true)) jsonResponse(false, 'Type must be credit or debit.');
if ($amount <= 0) jsonResponse(false, 'Amount must be greater than 0.');
if (!$reason) jsonResponse(false, 'Reason is required.');

$db   = getDB();
$stmt = $db->prepare("SELECT id, first_name, last_name, current_balance FROM account_registrations WHERE id = ? AND status = 'approved'");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) jsonResponse(false, 'User not found or not approved.');

if ($type === 'debit' && $amount > (float)$user['current_balance']) {
    jsonResponse(false, 'Cannot debit more than available balance (₹' . number_format($user['current_balance'], 2) . ').');
}

$operator = $type === 'credit' ? '+' : '-';
$db->prepare("UPDATE account_registrations SET current_balance = current_balance $operator ?, updated_at = NOW() WHERE id = ?")
   ->execute([$amount, $userId]);

logAdminAction(
    $admin['id'],
    'BALANCE_' . strtoupper($type),
    'account_registrations',
    $userId,
    "{$user['first_name']} {$user['last_name']} | ₹$amount | $reason"
);

jsonResponse(true, "Balance " . ucfirst($type) . "ed successfully.");

<?php
// =====================================================
// Trade-Zenfy - Admin Verify Withdrawal API
// POST /api/admin-verify-withdrawal.php
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$wdId   = (int)($input['withdrawal_id'] ?? 0);
$status = $input['status'] ?? '';
$remark = clean($input['remark'] ?? '');

if (!$wdId) jsonResponse(false, 'Invalid withdrawal ID.');
if (!in_array($status, ['approved', 'rejected'], true)) jsonResponse(false, 'Invalid status.');

$db   = getDB();
$stmt = $db->prepare("SELECT w.*, a.first_name, a.last_name FROM withdrawals w JOIN account_registrations a ON a.id = w.user_id WHERE w.id = ?");
$stmt->execute([$wdId]);
$wd = $stmt->fetch();

if (!$wd) jsonResponse(false, 'Withdrawal not found.');
if ($wd['status'] !== 'pending') jsonResponse(false, 'Already processed.');

$db->beginTransaction();
try {
    $db->prepare("UPDATE withdrawals SET status = ?, admin_remark = ?, approved_by = ?, approved_at = NOW() WHERE id = ?")
       ->execute([$status, $remark, $admin['id'], $wdId]);

    // If rejected, refund the balance back
    if ($status === 'rejected') {
        $db->prepare("UPDATE account_registrations SET current_balance = current_balance + ?, updated_at = NOW() WHERE id = ?")
           ->execute([$wd['amount'], $wd['user_id']]);
    }

    $db->commit();

    logAdminAction(
        $admin['id'],
        'WITHDRAWAL_' . strtoupper($status),
        'withdrawals',
        $wdId,
        "{$wd['first_name']} {$wd['last_name']} | ₹{$wd['amount']} | " . ($remark ?: 'No remark')
    );

    jsonResponse(true, "Withdrawal " . ucfirst($status) . " successfully.");
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Transaction failed. Please try again.');
}

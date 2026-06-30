<?php
// =====================================================
// Trade-Zenfy - Admin Verify Deposit API
// POST /api/admin-verify-payment.php
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$payId    = (int)($input['payment_id'] ?? 0);
$status   = $input['status'] ?? '';
$remark   = clean($input['remark'] ?? '');

if (!$payId) jsonResponse(false, 'Invalid payment ID.');
if (!in_array($status, ['approved', 'rejected'], true)) jsonResponse(false, 'Invalid status.');

$db   = getDB();
$stmt = $db->prepare("SELECT p.*, a.current_balance, a.first_name, a.last_name FROM payments p JOIN account_registrations a ON a.id = p.user_id WHERE p.id = ?");
$stmt->execute([$payId]);
$payment = $stmt->fetch();

if (!$payment) jsonResponse(false, 'Payment not found.');
if ($payment['status'] !== 'pending') jsonResponse(false, 'This payment has already been processed.');

$db->beginTransaction();
try {
    // Update payment record
    $db->prepare("UPDATE payments SET status = ?, admin_remark = ?, approved_by = ?, approved_at = NOW() WHERE id = ?")
       ->execute([$status, $remark, $admin['id'], $payId]);

    // Credit balance if approved
    if ($status === 'approved') {
        $db->prepare("UPDATE account_registrations SET current_balance = current_balance + ?, updated_at = NOW() WHERE id = ?")
           ->execute([$payment['amount'], $payment['user_id']]);
    }

    $db->commit();

    logAdminAction(
        $admin['id'],
        'DEPOSIT_' . strtoupper($status),
        'payments',
        $payId,
        "{$payment['first_name']} {$payment['last_name']} | ₹{$payment['amount']} | " . ($remark ?: 'No remark')
    );

    jsonResponse(true, "Deposit " . ucfirst($status) . " successfully.");

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Transaction failed. Please try again.');
}

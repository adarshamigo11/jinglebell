<?php
// =====================================================
// Trade-Zenfy - Create Withdrawal Request API
// POST /api/create-withdrawal.php
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$amount = (float)($input['amount'] ?? 0);
$method = clean($input['method'] ?? ''); // 'bank' or 'upi'

if ($amount < 100) {
    jsonResponse(false, 'Minimum withdrawal amount is ₹100.');
}
if (!in_array($method, ['bank', 'upi'], true)) {
    jsonResponse(false, 'Invalid withdrawal method.');
}

$db = getDB();

// Fetch fresh balance
$stmt = $db->prepare("SELECT current_balance FROM account_registrations WHERE id = ?");
$stmt->execute([$user['id']]);
$fresh = $stmt->fetch();

if ($amount > (float)$fresh['current_balance']) {
    jsonResponse(false, 'Insufficient balance. Available: ₹' . number_format($fresh['current_balance'], 2));
}

// Check no pending withdrawal already
$pending = $db->prepare("SELECT id FROM withdrawals WHERE user_id = ? AND status = 'pending'");
$pending->execute([$user['id']]);
if ($pending->fetch()) {
    jsonResponse(false, 'You already have a pending withdrawal request. Please wait for it to be processed.');
}

$bankName  = null; $accountNo = null; $ifsc = null; $upiId = null;

if ($method === 'bank') {
    $bankName  = clean($input['bank_name'] ?? '');
    $accountNo = clean($input['account_number'] ?? '');
    $ifsc      = clean($input['ifsc_code'] ?? '');
    if (!$bankName || !$accountNo || !$ifsc) {
        jsonResponse(false, 'Bank name, account number, and IFSC code are required.');
    }
} else {
    $upiId = clean($input['upi_id'] ?? '');
    if (!$upiId) jsonResponse(false, 'UPI ID is required.');
}

$db->beginTransaction();
try {
    // Deduct balance immediately (hold it)
    $db->prepare("UPDATE account_registrations SET current_balance = current_balance - ?, updated_at = NOW() WHERE id = ?")
       ->execute([$amount, $user['id']]);

    // Create withdrawal record
    $db->prepare("INSERT INTO withdrawals (user_id, amount, bank_name, account_number, ifsc_code, upi_id, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')")
       ->execute([$user['id'], $amount, $bankName, $accountNo, $ifsc, $upiId]);

    $db->commit();
    jsonResponse(true, 'Withdrawal request submitted. Funds will be transferred after admin approval.', [
        'withdrawal_id' => (int)$db->lastInsertId()
    ]);
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Failed to process request. Please try again.');
}

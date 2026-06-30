<?php
// =====================================================
// Trade-Zenfy - Save Payment Details API
// POST /api/save-payment-details.php
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input      = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$bankName   = clean($input['bank_name'] ?? '');
$accountNo  = clean($input['account_number'] ?? '');
$ifsc       = clean($input['ifsc_code'] ?? '');
$upiId      = clean($input['upi_id'] ?? '');
$accountType = in_array($input['account_type'] ?? 'savings', ['savings', 'current']) ? $input['account_type'] : 'savings';

if (!$bankName && !$upiId) {
    jsonResponse(false, 'Provide at least bank details or UPI ID.');
}

$db   = getDB();
$stmt = $db->prepare("SELECT id FROM user_payment_details WHERE user_id = ?");
$stmt->execute([$user['id']]);
$existing = $stmt->fetch();

if ($existing) {
    $db->prepare("UPDATE user_payment_details SET bank_name=?, account_number=?, ifsc_code=?, upi_id=?, account_type=?, updated_at=NOW() WHERE user_id=?")
       ->execute([$bankName, $accountNo, $ifsc, $upiId, $accountType, $user['id']]);
} else {
    $db->prepare("INSERT INTO user_payment_details (user_id, bank_name, account_number, ifsc_code, upi_id, account_type) VALUES (?,?,?,?,?,?)")
       ->execute([$user['id'], $bankName, $accountNo, $ifsc, $upiId, $accountType]);
}

jsonResponse(true, 'Payment details saved successfully.');

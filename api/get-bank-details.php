<?php
// =====================================================
// Trade-Zenfy - Get Bank Details API
// GET /api/get-bank-details.php
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();

header('Content-Type: application/json');

$db   = getDB();
$stmt = $db->prepare("SELECT bank_name, account_number, ifsc_code, upi_id, account_type FROM user_payment_details WHERE user_id = ?");
$stmt->execute([$user['id']]);
$details = $stmt->fetch();

if (!$details) {
    jsonResponse(true, 'No payment details found.', ['details' => null]);
}

jsonResponse(true, 'OK', ['details' => $details]);

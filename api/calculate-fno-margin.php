<?php
/**
 * Calculate F&O Margin API
 */

require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
header('Content-Type: application/json');

$db = getDB();

$contractId = intval($_GET['contract_id'] ?? 0);
$quantity = intval($_GET['quantity'] ?? 0);
$orderType = strtoupper($_GET['order_type'] ?? 'BUY');

if (!$contractId || !$quantity) {
    jsonResponse(false, 'Contract ID and quantity required');
}

// Get contract
$stmt = $db->prepare("SELECT * FROM fno_contracts WHERE id = ? AND is_active = 1");
$stmt->execute([$contractId]);
$contract = $stmt->fetch();

if (!$contract) {
    jsonResponse(false, 'Contract not found');
}

// Calculate
$lots = $quantity / $contract['lot_size'];
$totalValue = $quantity * $contract['current_price'];
$marginUsed = 0;
$premiumPaid = 0;

if ($contract['contract_type'] === 'FUTURES') {
    $marginUsed = $totalValue * 0.20; // 20% margin
} else {
    if ($orderType === 'BUY') {
        $premiumPaid = $contract['premium'] * $quantity;
        $marginUsed = $premiumPaid;
    } else {
        $marginUsed = $totalValue * 0.30; // 30% for selling
    }
}

jsonResponse(true, 'Margin calculated', [
    'contract' => $contract['symbol'],
    'type' => $contract['contract_type'],
    'quantity' => $quantity,
    'lots' => $lots,
    'price' => $contract['current_price'],
    'total_value' => $totalValue,
    'margin_required' => $marginUsed,
    'premium' => $premiumPaid,
    'user_balance' => $user['current_balance'],
    'sufficient' => $user['current_balance'] >= $marginUsed
]);

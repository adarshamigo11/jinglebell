<?php
/**
 * Place F&O Order API
 * Handles Futures and Options order placement
 */

require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method');
}

$input = json_decode(file_get_contents('php://input'), true);
$contractId = intval($input['contract_id'] ?? 0);
$orderType = strtoupper($input['order_type'] ?? '');  // BUY or SELL
$quantity = intval($input['quantity'] ?? 0);
$stopLoss = floatval($input['stop_loss'] ?? 0);
$target = floatval($input['target'] ?? 0);

// Validation
if (!$contractId) {
    jsonResponse(false, 'Contract ID required');
}

if (!in_array($orderType, ['BUY', 'SELL'])) {
    jsonResponse(false, 'Invalid order type');
}

if ($quantity <= 0) {
    jsonResponse(false, 'Invalid quantity');
}

$db = getDB();

// Get contract details
$stmt = $db->prepare("SELECT * FROM fno_contracts WHERE id = ? AND is_active = 1");
$stmt->execute([$contractId]);
$contract = $stmt->fetch();

if (!$contract) {
    jsonResponse(false, 'Contract not found or inactive');
}

// Validate quantity is multiple of lot size
if ($quantity % $contract['lot_size'] !== 0) {
    jsonResponse(false, "Quantity must be in multiples of lot size ({$contract['lot_size']})");
}

// Calculate costs
$lots = $quantity / $contract['lot_size'];
$totalValue = $quantity * $contract['current_price'];
$marginUsed = 0;
$premiumPaid = 0;

if ($contract['contract_type'] === 'FUTURES') {
    // Futures: 20% margin required
    $marginUsed = $totalValue * 0.20;
} else {
    // Options: Premium only for buying
    if ($orderType === 'BUY') {
        $premiumPaid = $contract['premium'] * $quantity;
        $marginUsed = $premiumPaid;
    } else {
        // Options selling: Higher margin (30% of underlying)
        $marginUsed = $totalValue * 0.30;
    }
}

// Check user balance
if ($user['current_balance'] < $marginUsed) {
    jsonResponse(false, "Insufficient balance. Required: ₹" . number_format($marginUsed, 2));
}

try {
    $db->beginTransaction();
    
    // Deduct margin/premium from balance
    $stmt = $db->prepare("
        UPDATE account_registrations 
        SET current_balance = current_balance - ?
        WHERE id = ?
    ");
    $stmt->execute([$marginUsed, $user['id']]);
    
    // Create order
    $stmt = $db->prepare("
        INSERT INTO fno_orders (
            user_id, contract_id, order_type, contract_type, quantity,
            entry_price, lot_size, premium_paid, margin_used, status,
            stop_loss, target, executed_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'EXECUTED', ?, ?, NOW())
    ");
    $stmt->execute([
        $user['id'], $contractId, $orderType, $contract['contract_type'],
        $quantity, $contract['current_price'], $contract['lot_size'],
        $premiumPaid, $marginUsed, $stopLoss, $target
    ]);
    
    $orderId = $db->lastInsertId();
    
    // Create position
    $stmt = $db->prepare("
        INSERT INTO fno_positions (
            user_id, contract_id, order_id, contract_type, position_type,
            quantity, entry_price, current_price, margin_used
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'], $contractId, $orderId, $contract['contract_type'],
        $orderType, $quantity, $contract['current_price'], $contract['current_price'], $marginUsed
    ]);
    
    // Log transaction
    $transType = $contract['contract_type'] === 'FUTURES' ? 'MARGIN_COLLECTED' : 'PREMIUM_PAID';
    $stmt = $db->prepare("
        INSERT INTO fno_transactions (user_id, order_id, transaction_type, amount, description)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'], $orderId, $transType, $marginUsed,
        "{$orderType} {$contract['contract_type']} - {$contract['symbol']} x {$quantity}"
    ]);
    
    $db->commit();
    
    jsonResponse(true, 'Order placed successfully!', [
        'order_id' => $orderId,
        'contract' => $contract['symbol'],
        'type' => $contract['contract_type'],
        'quantity' => $quantity,
        'lots' => $lots,
        'entry_price' => $contract['current_price'],
        'margin_used' => $marginUsed,
        'premium_paid' => $premiumPaid,
        'expiry_date' => $contract['expiry_date']
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Failed to place order: ' . $e->getMessage());
}

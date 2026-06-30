<?php
/**
 * Close F&O Position API
 */

require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method');
}

$input = json_decode(file_get_contents('php://input'), true);
$positionId = intval($input['position_id'] ?? 0);

if (!$positionId) {
    jsonResponse(false, 'Position ID required');
}

$db = getDB();

// Get position details
$stmt = $db->prepare("
    SELECT fp.*, fc.symbol, fc.current_price
    FROM fno_positions fp
    JOIN fno_contracts fc ON fc.id = fp.contract_id
    WHERE fp.id = ? AND fp.user_id = ? AND fp.is_active = 1
");
$stmt->execute([$positionId, $user['id']]);
$position = $stmt->fetch();

if (!$position) {
    jsonResponse(false, 'Position not found');
}

// Calculate P&L
$currentPrice = $position['current_price'];
$pnl = 0;

if ($position['contract_type'] === 'FUTURES') {
    if ($position['position_type'] === 'BUY') {
        $pnl = ($currentPrice - $position['entry_price']) * $position['quantity'];
    } else {
        $pnl = ($position['entry_price'] - $currentPrice) * $position['quantity'];
    }
} else {
    // Options: P&L based on premium difference
    $premiumDiff = $currentPrice - $position['entry_price'];
    $pnl = $position['position_type'] === 'BUY' ? $premiumDiff * $position['quantity'] : -$premiumDiff * $position['quantity'];
}

try {
    $db->beginTransaction();
    
    // Update order
    $stmt = $db->prepare("
        UPDATE fno_orders 
        SET status = 'CLOSED', exit_price = ?, pnl = ?, closed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$currentPrice, $pnl, $position['order_id']]);
    
    // Deactivate position
    $stmt = $db->prepare("
        UPDATE fno_positions SET is_active = 0 WHERE id = ?
    ");
    $stmt->execute([$positionId]);
    
    // Refund margin + P&L
    $refundAmount = $position['margin_used'] + $pnl;
    
    $stmt = $db->prepare("
        UPDATE account_registrations 
        SET current_balance = current_balance + ?
        WHERE id = ?
    ");
    $stmt->execute([$refundAmount, $user['id']]);
    
    // Log transaction
    $stmt = $db->prepare("
        INSERT INTO fno_transactions (user_id, order_id, transaction_type, amount, description)
        VALUES (?, ?, 'P&L_SETTLEMENT', ?, ?)
    ");
    $stmt->execute([
        $user['id'], $position['order_id'], $refundAmount,
        "Position closed - P&L: ₹" . number_format($pnl, 2)
    ]);
    
    $db->commit();
    
    jsonResponse(true, 'Position closed successfully!', [
        'position_id' => $positionId,
        'pnl' => $pnl,
        'refund_amount' => $refundAmount
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Failed to close position: ' . $e->getMessage());
}

<?php
// =====================================================
// TradeZenfy Platform - Admin Edit Stock Order
// POST /api/admin-edit-stock-order.php
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$orderId = (int)($input['order_id'] ?? 0);

if (!$orderId) {
    jsonResponse(false, 'Invalid order ID.');
}

$db = getDB();

// Fetch order details
$stmt = $db->prepare("
    SELECT o.*, s.symbol, s.name as stock_name, ar.email, ar.current_balance
    FROM user_orders o
    JOIN stocks s ON s.id = o.stock_id
    JOIN account_registrations ar ON ar.id = o.user_id
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    jsonResponse(false, 'Order not found.');
}

// Build dynamic update query based on provided fields
$updates = [];
$params = [];

if (isset($input['price']) && $input['price'] !== null) {
    $newPrice = (float)$input['price'];
    if ($newPrice <= 0) {
        jsonResponse(false, 'Price must be greater than 0.');
    }
    $updates[] = 'price = ?';
    $params[] = $newPrice;
    
    // Recalculate total amount
    $newTotal = round($order['quantity'] * $newPrice, 2);
    $updates[] = 'total_amount = ?';
    $params[] = $newTotal;
    
    // Update holdings if order is EXECUTED
    if ($order['status'] === 'EXECUTED') {
        $priceDiff = $newPrice - (float)$order['price'];
        
        if ($order['order_type'] === 'BUY') {
            // Update average buy price in holdings
            $holdStmt = $db->prepare("
                SELECT * FROM user_holdings 
                WHERE user_id = ? AND stock_id = ? FOR UPDATE
            ");
            $holdStmt->execute([$order['user_id'], $order['stock_id']]);
            $holding = $holdStmt->fetch();
            
            if ($holding) {
                $totalDiff = $priceDiff * $order['quantity'];
                $newInvested = (float)$holding['invested_amount'] + $totalDiff;
                $newAvgPrice = $newInvested / $holding['quantity'];
                
                $db->prepare("
                    UPDATE user_holdings
                    SET average_price = ?,
                        invested_amount = ?,
                        current_value = current_price * quantity,
                        pnl = (current_price - ?) * quantity,
                        pnl_percent = ((current_price - ?) / ?) * 100,
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([
                    $newAvgPrice, $newInvested,
                    $newAvgPrice, $newAvgPrice, $newAvgPrice,
                    $holding['id']
                ]);
                
                // Update user totals
                $db->prepare("
                    UPDATE account_registrations
                    SET total_invested = total_invested + ?,
                        portfolio_value = (SELECT COALESCE(SUM(current_value),0) FROM user_holdings WHERE user_id = ?),
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([$totalDiff, $order['user_id'], $order['user_id']]);
            }
        }
    }
}

if (isset($input['quantity']) && $input['quantity'] !== null) {
    $newQty = (int)$input['quantity'];
    if ($newQty <= 0) {
        jsonResponse(false, 'Quantity must be at least 1.');
    }
    $updates[] = 'quantity = ?';
    $params[] = $newQty;
    
    // Recalculate total if price exists
    if (isset($input['price'])) {
        $newTotal = round($newQty * (float)$input['price'], 2);
    } else {
        $newTotal = round($newQty * (float)$order['price'], 2);
    }
    $updates[] = 'total_amount = ?';
    $params[] = $newTotal;
}

if (isset($input['status']) && $input['status'] !== null) {
    $newStatus = strtoupper($input['status']);
    if (!in_array($newStatus, ['PENDING', 'EXECUTED', 'CANCELLED', 'REJECTED'], true)) {
        jsonResponse(false, 'Invalid status. Must be PENDING, EXECUTED, CANCELLED, or REJECTED.');
    }
    $updates[] = 'status = ?';
    $params[] = $newStatus;
}

if (isset($input['admin_note']) && $input['admin_note'] !== null) {
    $updates[] = 'admin_remark = ?';
    $params[] = clean($input['admin_note']);
}

// Always update admin tracking
$updates[] = 'modified_by_admin = ?';
$params[] = $admin['id'];

$updates[] = 'admin_modified_at = NOW()';

if (empty($updates)) {
    jsonResponse(false, 'No fields to update.');
}

// Add order ID to params
$params[] = $orderId;

// Execute update
$db->beginTransaction();
try {
    $sql = "UPDATE user_orders SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // Log admin action
    $changes = [];
    if (isset($input['price'])) $changes[] = "price: {$order['price']} → {$input['price']}";
    if (isset($input['quantity'])) $changes[] = "qty: {$order['quantity']} → {$input['quantity']}";
    if (isset($input['status'])) $changes[] = "status: {$order['status']} → {$input['status']}";
    
    logAdminAction(
        $admin['id'], 
        'ORDER_MODIFIED', 
        'user_orders', 
        $orderId, 
        "Edited order {$order['symbol']} {$order['order_type']}: " . implode(', ', $changes)
    );
    
    $db->commit();
    
    jsonResponse(true, 'Order updated successfully.', [
        'order_id' => $orderId,
        'changes' => $changes
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Failed to update order: ' . $e->getMessage());
}

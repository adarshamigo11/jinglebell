<?php
// =====================================================
// Trade-Zenfy - Place Order API
// POST /api/place-order.php
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input     = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$stockId   = (int)($input['stock_id'] ?? 0);
$orderType = strtoupper(trim($input['order_type'] ?? ''));
$quantity  = (int)($input['quantity'] ?? 0);
$price     = (float)($input['price'] ?? 0);
$orderMode = strtoupper(trim($input['order_mode'] ?? 'MARKET'));

if (!$stockId)                                          jsonResponse(false, 'Invalid stock.');
if (!in_array($orderType, ['BUY', 'SELL'], true))       jsonResponse(false, 'Order type must be BUY or SELL.');
if ($quantity <= 0)                                     jsonResponse(false, 'Quantity must be at least 1.');
if ($price <= 0)                                        jsonResponse(false, 'Price must be greater than 0.');
if (!in_array($orderMode, ['MARKET', 'LIMIT'], true))   jsonResponse(false, 'Invalid order mode.');

$db = getDB();

// Validate stock exists and is active
$stmt = $db->prepare("SELECT id, symbol, name, is_active FROM stocks WHERE id = ?");
$stmt->execute([$stockId]);
$stock = $stmt->fetch();
if (!$stock || !$stock['is_active']) jsonResponse(false, 'Stock not found or inactive.');

$totalAmount = round($quantity * $price, 2);

$db->beginTransaction();
try {
    if ($orderType === 'BUY') {
        // Check sufficient balance
        $balStmt = $db->prepare("SELECT current_balance FROM account_registrations WHERE id = ? FOR UPDATE");
        $balStmt->execute([$user['id']]);
        $bal = $balStmt->fetchColumn();

        if ($totalAmount > (float)$bal) {
            $db->rollBack();
            jsonResponse(false, 'Insufficient balance. Available: ₹' . number_format($bal, 2) . ', Required: ₹' . number_format($totalAmount, 2));
        }

        // Block funds
        $db->prepare("UPDATE account_registrations SET current_balance = current_balance - ? WHERE id = ?")
           ->execute([$totalAmount, $user['id']]);

    } else { // SELL
        // Check holdings
        $holdStmt = $db->prepare("SELECT quantity FROM user_holdings WHERE user_id = ? AND stock_id = ?");
        $holdStmt->execute([$user['id'], $stockId]);
        $holding = $holdStmt->fetch();

        if (!$holding || $holding['quantity'] < $quantity) {
            $db->rollBack();
            $available = $holding ? $holding['quantity'] : 0;
            jsonResponse(false, "Insufficient holdings. You hold $available share(s) of {$stock['symbol']}.");
        }
    }

    // Create order with INSTANT EXECUTION (no admin approval needed)
    $status = 'EXECUTED';
    $executedAt = date('Y-m-d H:i:s');
    
    $db->prepare("
        INSERT INTO user_orders (user_id, stock_id, order_type, quantity, price, order_mode, total_amount, status, executed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$user['id'], $stockId, $orderType, $quantity, $price, $orderMode, $totalAmount, $status, $executedAt]);

    $orderId = $db->lastInsertId();
    
    // Execute the order immediately (same logic as admin-execute-order.php)
    if ($orderType === 'BUY') {
        // Funds already blocked - now create/update holdings
        $holdStmt = $db->prepare("SELECT id, quantity, average_price, invested_amount FROM user_holdings WHERE user_id = ? AND stock_id = ?");
        $holdStmt->execute([$user['id'], $stockId]);
        $existing = $holdStmt->fetch();

        if ($existing) {
            $newQty = $existing['quantity'] + $quantity;
            $newInvested = (float)$existing['invested_amount'] + $totalAmount;
            $newAvgPrice = $newInvested / $newQty;

            // UPDATE user_holdings - Count placeholders carefully
            // SET: quantity, average_price, invested_amount, current_price, current_value
            //     pnl, pnl_percent, updated_at, WHERE id
            // 5 SET cols + 2 pnl placeholders + 1 pnl_percent placeholder + 1 WHERE = 9 total
            $db->prepare("
                UPDATE user_holdings
                SET quantity = ?,
                    average_price = ?,
                    invested_amount = ?,
                    current_price = ?,
                    current_value = ?,
                    pnl = (? - ?) * quantity,
                    pnl_percent = ((? - ?) / ?) * 100,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $newQty,                                          // 1: quantity
                $newAvgPrice,                                     // 2: average_price
                $newInvested,                                     // 3: invested_amount
                $price,                                           // 4: current_price
                $newQty * $price,                                 // 5: current_value
                $price,                                           // 6: pnl calculation (current price)
                $newAvgPrice,                                     // 7: pnl calculation (avg price)
                $price,                                           // 8: pnl_percent calculation (current price)
                $newAvgPrice,                                     // 9: pnl_percent calculation (avg price)
                $newQty,                                          // 10: pnl_percent calculation (divisor)
                $existing['id']                                    // 11: WHERE id
            ]);
        } else {
            $db->prepare("
                INSERT INTO user_holdings (user_id, stock_id, quantity, average_price, current_price, invested_amount, current_value, pnl, pnl_percent)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)
            ")->execute([
                $user['id'], $stockId,
                $quantity, $price, $price,
                $totalAmount, $totalAmount
            ]);
        }

        // Update user totals
        $db->prepare("
            UPDATE account_registrations
            SET total_invested = total_invested + ?,
                portfolio_value = (SELECT COALESCE(SUM(current_value),0) FROM user_holdings WHERE user_id = ?),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$totalAmount, $user['id'], $user['id']]);

    } else { // SELL
        // Get holding
        $holdStmt = $db->prepare("SELECT * FROM user_holdings WHERE user_id = ? AND stock_id = ? FOR UPDATE");
        $holdStmt->execute([$user['id'], $stockId]);
        $holding = $holdStmt->fetch();

        $realizedPnl = ($price - $holding['average_price']) * $quantity;
        $newQty = $holding['quantity'] - $quantity;
        $saleAmount = $totalAmount;

        if ($newQty <= 0) {
            $db->prepare("DELETE FROM user_holdings WHERE id = ?")->execute([$holding['id']]);
        } else {
            $newInvested = $holding['average_price'] * $newQty;
            
            // UPDATE user_holdings for SELL
            // SET: quantity, invested_amount, current_value, pnl, pnl_percent, updated_at, WHERE id
            // 3 SET cols + 2 pnl placeholders + 1 pnl_percent placeholder + 1 WHERE = 7 total
            $db->prepare("
                UPDATE user_holdings
                SET quantity = ?,
                    invested_amount = ?,
                    current_value = ?,
                    pnl = (? - average_price) * ?,
                    pnl_percent = ((? - average_price) / average_price) * 100,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $newQty,                    // 1: quantity
                $newInvested,              // 2: invested_amount
                $newQty * $price,          // 3: current_value
                $price,                    // 4: pnl calculation (sell price)
                $newQty,                   // 5: pnl multiplier
                $price,                    // 6: pnl_percent calculation (sell price)
                $holding['id']             // 7: WHERE id
            ]);
        }

        // Credit sale proceeds
        $db->prepare("
            UPDATE account_registrations
            SET current_balance = current_balance + ?,
                total_pnl = total_pnl + ?,
                total_invested = GREATEST(0, total_invested - ?),
                portfolio_value = (SELECT COALESCE(SUM(current_value),0) FROM user_holdings WHERE user_id = ?),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$saleAmount, $realizedPnl, $holding['average_price'] * $quantity, $user['id'], $user['id']]);

        // Record trade history for SELL
        $db->prepare("
            INSERT INTO trade_history (user_id, stock_id, order_id, symbol, order_type, trade_type, quantity, price, total_amount, realized_pnl)
            VALUES (?, ?, ?, ?, 'SELL', 'SELL', ?, ?, ?, ?)
        ")->execute([$user['id'], $stockId, $orderId, $stock['symbol'], $quantity, $price, $totalAmount, $realizedPnl]);
    }

    // Record trade history for BUY
    if ($orderType === 'BUY') {
        $db->prepare("
            INSERT INTO trade_history (user_id, stock_id, order_id, symbol, order_type, trade_type, quantity, price, total_amount, realized_pnl)
            VALUES (?, ?, ?, ?, 'BUY', 'BUY', ?, ?, ?, ?)
        ")->execute([$user['id'], $stockId, $orderId, $stock['symbol'], $quantity, $price, $totalAmount, 0]);
    }

    $db->commit();

    jsonResponse(true, "$orderType order placed and executed successfully!", [
        'order_id'     => (int)$orderId,
        'symbol'       => $stock['symbol'],
        'order_type'   => $orderType,
        'quantity'     => $quantity,
        'price'        => $price,
        'total_amount' => $totalAmount,
        'status'       => 'EXECUTED'
    ]);

} catch (Exception $e) {
    $db->rollBack();
    error_log('Order placement error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    jsonResponse(false, 'Failed to place order. Please try again. Error: ' . $e->getMessage());
}
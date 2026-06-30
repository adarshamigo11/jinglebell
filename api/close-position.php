<?php
/**
 * Close Position API
 * Sells all shares of a stock at current market price (LTP)
 * Updates balance, holdings, realized P&L, and trade history
 */
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$stockId = (int)($input['stock_id'] ?? 0);

if (!$stockId) {
    jsonResponse(false, 'Invalid stock ID.');
}

try {
    $db = getDB();
    $db->beginTransaction();

    // Get holding
    $holdStmt = $db->prepare("
        SELECT h.*, s.symbol, s.name, s.exchange
        FROM user_holdings h
        JOIN stocks s ON s.id = h.stock_id
        WHERE h.user_id = ? AND h.stock_id = ? AND h.quantity > 0
    ");
    $holdStmt->execute([$user['id'], $stockId]);
    $holding = $holdStmt->fetch();

    if (!$holding) {
        jsonResponse(false, 'No open position found for this stock.');
    }

    // Get current LTP from cache or stock table
    $priceStmt = $db->prepare("
        SELECT COALESCE(c.ltp, s.ltp) AS ltp
        FROM stocks s
        LEFT JOIN stock_price_cache c ON c.stock_id = s.id
        WHERE s.id = ?
    ");
    $priceStmt->execute([$stockId]);
    $ltpRow = $priceStmt->fetch();
    $price = (float)($ltpRow['ltp'] ?? 0);

    if ($price <= 0) {
        jsonResponse(false, 'Cannot close position: no valid market price available.');
    }

    $quantity     = (int)$holding['quantity'];
    $avgPrice     = (float)$holding['average_price'];
    $totalAmount  = $price * $quantity;
    $realizedPnl  = ($price - $avgPrice) * $quantity;
    $orderMode    = 'MARKET';
    $status       = 'EXECUTED';
    $executedAt   = date('Y-m-d H:i:s');

    // 1. Create order record
    $db->prepare("
        INSERT INTO user_orders (user_id, stock_id, order_type, quantity, price, order_mode, total_amount, status, executed_at)
        VALUES (?, ?, 'SELL', ?, ?, ?, ?, ?, ?)
    ")->execute([$user['id'], $stockId, $quantity, $price, $orderMode, $totalAmount, $status, $executedAt]);

    $orderId = $db->lastInsertId();

    // 2. Remove holding (all shares sold)
    $db->prepare("DELETE FROM user_holdings WHERE id = ?")->execute([$holding['id']]);

    // 3. Credit sale proceeds to user balance
    $db->prepare("
        UPDATE account_registrations
        SET current_balance = current_balance + ?,
            total_pnl = total_pnl + ?,
            total_invested = GREATEST(0, total_invested - ?),
            portfolio_value = (SELECT COALESCE(SUM(current_value),0) FROM user_holdings WHERE user_id = ?),
            updated_at = NOW()
        WHERE id = ?
    ")->execute([$totalAmount, $realizedPnl, $avgPrice * $quantity, $user['id'], $user['id']]);

    // 4. Record trade history
    $db->prepare("
        INSERT INTO trade_history (user_id, stock_id, order_id, symbol, order_type, trade_type, quantity, price, total_amount, realized_pnl)
        VALUES (?, ?, ?, ?, 'SELL', 'CLOSE', ?, ?, ?, ?)
    ")->execute([$user['id'], $stockId, $orderId, $holding['symbol'], $quantity, $price, $totalAmount, $realizedPnl]);

    $db->commit();

    jsonResponse(true, "Position closed! Sold {$quantity} share(s) of {$holding['symbol']} at ₹" . number_format($price, 2), [
        'stock_id'      => $stockId,
        'symbol'        => $holding['symbol'],
        'quantity'      => $quantity,
        'sell_price'    => $price,
        'total_amount'  => $totalAmount,
        'realized_pnl'  => $realizedPnl,
        'order_id'      => (int)$orderId
    ]);

} catch (Exception $e) {
    $db->rollBack();
    error_log('Close position error: ' . $e->getMessage());
    jsonResponse(false, 'Failed to close position: ' . $e->getMessage());
}
?>

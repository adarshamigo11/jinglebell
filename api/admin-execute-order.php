<?php
// =====================================================
// Trade-Zenfy - Admin Execute Order API
// POST /api/admin-execute-order.php
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$orderId = (int)($input['order_id'] ?? 0);
$action  = $input['action'] ?? ''; // 'execute' or 'reject'
$remark  = clean($input['remark'] ?? '');

if (!$orderId) jsonResponse(false, 'Invalid order ID.');
if (!in_array($action, ['execute', 'reject'], true)) jsonResponse(false, 'Invalid action.');

$db   = getDB();
$stmt = $db->prepare("SELECT o.*, a.current_balance FROM user_orders o JOIN account_registrations a ON a.id = o.user_id WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order)                         jsonResponse(false, 'Order not found.');
if ($order['status'] !== 'PENDING')  jsonResponse(false, 'Order is not pending.');

$db->beginTransaction();
try {
    if ($action === 'reject') {
        $db->prepare("UPDATE user_orders SET status='REJECTED', admin_remark=?, executed_by=?, executed_at=NOW() WHERE id=?")
           ->execute([$remark, $admin['id'], $orderId]);

        // Refund blocked funds for BUY
        if ($order['order_type'] === 'BUY') {
            $db->prepare("UPDATE account_registrations SET current_balance = current_balance + ? WHERE id = ?")
               ->execute([$order['total_amount'], $order['user_id']]);
        }

        $db->commit();
        logAdminAction($admin['id'], 'ORDER_REJECTED', 'user_orders', $orderId, "Rejected {$order['order_type']} {$order['quantity']} {$order['symbol']} @ ₹{$order['price']}");
        jsonResponse(true, 'Order rejected successfully.');
    }

    // ── EXECUTE ──────────────────────────────────────
    if ($order['order_type'] === 'BUY') {
        // Funds already blocked at order placement — just finalize

        // Upsert user_holdings
        $holdStmt = $db->prepare("SELECT id, quantity, average_price, invested_amount FROM user_holdings WHERE user_id = ? AND stock_id = ?");
        $holdStmt->execute([$order['user_id'], $order['stock_id']]);
        $existing = $holdStmt->fetch();

        if ($existing) {
            $newQty        = $existing['quantity'] + $order['quantity'];
            $newInvested   = (float)$existing['invested_amount'] + (float)$order['total_amount'];
            $newAvgPrice   = $newInvested / $newQty;

            $db->prepare("
                UPDATE user_holdings
                SET quantity=?, average_price=?, invested_amount=?, current_price=?, current_value=?,
                    pnl = (? - ?) * quantity,
                    pnl_percent = ((? - ?) / ?) * 100,
                    updated_at=NOW()
                WHERE id=?
            ")->execute([
                $newQty, $newAvgPrice, $newInvested,
                $order['price'], $newQty * $order['price'],
                $order['price'], $newAvgPrice,
                $order['price'], $newAvgPrice, $newAvgPrice,
                $existing['id']
            ]);
        } else {
            $db->prepare("
                INSERT INTO user_holdings (user_id, stock_id, quantity, average_price, current_price, invested_amount, current_value, pnl, pnl_percent)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)
            ")->execute([
                $order['user_id'], $order['stock_id'],
                $order['quantity'], $order['price'], $order['price'],
                $order['total_amount'], $order['total_amount']
            ]);
        }

        // Update user totals
        $db->prepare("
            UPDATE account_registrations
            SET total_invested = total_invested + ?,
                portfolio_value = (SELECT COALESCE(SUM(current_value),0) FROM user_holdings WHERE user_id = ?),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$order['total_amount'], $order['user_id'], $order['user_id']]);

    } else { // SELL
        // Get holding
        $holdStmt = $db->prepare("SELECT * FROM user_holdings WHERE user_id = ? AND stock_id = ? FOR UPDATE");
        $holdStmt->execute([$order['user_id'], $order['stock_id']]);
        $holding = $holdStmt->fetch();

        if (!$holding || $holding['quantity'] < $order['quantity']) {
            $db->rollBack();
            jsonResponse(false, 'User does not have sufficient holdings to execute this sell order.');
        }

        $realizedPnl = ($order['price'] - $holding['average_price']) * $order['quantity'];
        $newQty      = $holding['quantity'] - $order['quantity'];
        $saleAmount  = (float)$order['total_amount'];

        if ($newQty <= 0) {
            $db->prepare("DELETE FROM user_holdings WHERE id = ?")->execute([$holding['id']]);
        } else {
            $newInvested = $holding['average_price'] * $newQty;
            $db->prepare("
                UPDATE user_holdings
                SET quantity=?, invested_amount=?, current_value=?,
                    pnl = (? - average_price) * ?,
                    pnl_percent = ((? - average_price) / average_price) * 100,
                    updated_at=NOW()
                WHERE id=?
            ")->execute([$newQty, $newInvested, $newQty * $order['price'], $order['price'], $newQty, $order['price'], $holding['id']]);
        }

        // Credit sale proceeds
        $db->prepare("
            UPDATE account_registrations
            SET current_balance  = current_balance + ?,
                total_pnl        = total_pnl + ?,
                total_invested   = GREATEST(0, total_invested - ?),
                portfolio_value  = (SELECT COALESCE(SUM(current_value),0) FROM user_holdings WHERE user_id = ?),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$saleAmount, $realizedPnl, $holding['average_price'] * $order['quantity'], $order['user_id'], $order['user_id']]);

        // Record trade history with realized P&L
        $db->prepare("
            INSERT INTO trade_history (user_id, order_id, stock_id, symbol, order_type, quantity, price, total_amount, realized_pnl, executed_by, executed_at)
            VALUES (?, ?, ?, ?, 'SELL', ?, ?, ?, ?, ?, NOW())
        ")->execute([$order['user_id'], $orderId, $order['stock_id'], $order['symbol'], $order['quantity'], $order['price'], $order['total_amount'], $realizedPnl, $admin['id']]);
    }

    // Mark order as executed
    $db->prepare("UPDATE user_orders SET status='EXECUTED', admin_remark=?, executed_by=?, executed_at=NOW() WHERE id=?")
       ->execute([$remark, $admin['id'], $orderId]);

    // Record trade history for BUY too
    if ($order['order_type'] === 'BUY') {
        $db->prepare("
            INSERT INTO trade_history (user_id, order_id, stock_id, symbol, order_type, quantity, price, total_amount, realized_pnl, executed_by, executed_at)
            VALUES (?, ?, ?, ?, 'BUY', ?, ?, ?, 0, ?, NOW())
        ")->execute([$order['user_id'], $orderId, $order['stock_id'], $order['symbol'], $order['quantity'], $order['price'], $order['total_amount'], $admin['id']]);
    }

    $db->commit();

    logAdminAction($admin['id'], 'ORDER_EXECUTED', 'user_orders', $orderId, "Executed {$order['order_type']} {$order['quantity']} {$order['symbol']} @ ₹{$order['price']}");
    jsonResponse(true, "Order executed successfully.", ['order_id' => $orderId]);

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Execution failed: ' . $e->getMessage());
}

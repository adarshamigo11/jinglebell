<?php
// =====================================================
// Trade-Zenfy - Cancel Order API
// POST /api/cancel-order.php
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$orderId = (int)($input['order_id'] ?? 0);

if (!$orderId) jsonResponse(false, 'Invalid order ID.');

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM user_orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();

if (!$order)                          jsonResponse(false, 'Order not found.');
if ($order['status'] !== 'PENDING')   jsonResponse(false, 'Only pending orders can be cancelled.');

$db->beginTransaction();
try {
    $db->prepare("UPDATE user_orders SET status = 'CANCELLED' WHERE id = ?")
       ->execute([$orderId]);

    // Refund blocked funds for BUY orders
    if ($order['order_type'] === 'BUY') {
        $db->prepare("UPDATE account_registrations SET current_balance = current_balance + ? WHERE id = ?")
           ->execute([$order['total_amount'], $user['id']]);
    }

    $db->commit();
    jsonResponse(true, 'Order cancelled successfully. Funds have been returned to your balance.');

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Failed to cancel order. Please try again.');
}

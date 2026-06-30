<?php
// =====================================================
// Trade-Zenfy - Admin Delete Stock API
// POST /api/admin-delete-stock.php
// Soft delete - removes from stocks page but keeps in holdings
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$stockId = (int)($input['stock_id'] ?? 0);

if (!$stockId) {
    jsonResponse(false, 'Stock ID is required.');
}

$db = getDB();

// Check if stock exists
$check = $db->prepare("SELECT id, symbol, name FROM stocks WHERE id = ?");
$check->execute([$stockId]);
$stock = $check->fetch();

if (!$stock) {
    jsonResponse(false, 'Stock not found.');
}

// Check if any users have holdings in this stock
$holdingsCheck = $db->prepare("SELECT COUNT(*) FROM user_holdings WHERE stock_id = ? AND quantity > 0");
$holdingsCheck->execute([$stockId]);
$activeHoldings = $holdingsCheck->fetchColumn();

// Check if there are any pending orders for this stock
$ordersCheck = $db->prepare("SELECT COUNT(*) FROM user_orders WHERE stock_id = ? AND status = 'PENDING'");
$ordersCheck->execute([$stockId]);
$pendingOrders = $ordersCheck->fetchColumn();

if ($pendingOrders > 0) {
    jsonResponse(false, 'Cannot delete stock with pending orders. Please execute or cancel pending orders first.');
}

try {
    $db->beginTransaction();
    
    // Soft delete: Mark as inactive and set a deleted flag
    // This removes it from the stocks page but keeps it in holdings
    $db->prepare("
        UPDATE stocks 
        SET is_active = 0, 
            deleted_at = NOW(),
            deleted_by = ?
        WHERE id = ?
    ")->execute([$admin['id'], $stockId]);
    
    // Log the action
    logAdminAction($admin['id'], 'STOCK_DELETED', 'stocks', $stockId, 
        "Stock {$stock['symbol']} ({$stock['name']}) deleted. Active holdings: $activeHoldings");
    
    $db->commit();
    
    $message = "Stock {$stock['symbol']} deleted successfully.";
    if ($activeHoldings > 0) {
        $message .= " Note: $activeHoldings users still hold this stock in their portfolios.";
    }
    
    jsonResponse(true, $message, [
        'stock_id' => $stockId,
        'symbol' => $stock['symbol'],
        'active_holdings' => (int)$activeHoldings
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Failed to delete stock: ' . $e->getMessage());
}

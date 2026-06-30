<?php
// =====================================================
// Trade-Zenfy - Admin Toggle Stock Active API
// POST /api/admin-toggle-stock.php
// =====================================================
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
header('Content-Type: application/json');

$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$stockId  = (int)($input['stock_id'] ?? 0);
$isActive = (int)($input['is_active'] ?? 0);

if (!$stockId) jsonResponse(false, 'Invalid stock ID.');

$db   = getDB();
$stmt = $db->prepare("SELECT id, symbol FROM stocks WHERE id = ?");
$stmt->execute([$stockId]);
$stock = $stmt->fetch();
if (!$stock) jsonResponse(false, 'Stock not found.');

$db->prepare("UPDATE stocks SET is_active = ?, updated_at = NOW() WHERE id = ?")->execute([$isActive, $stockId]);

logAdminAction($admin['id'], 'STOCK_' . ($isActive ? 'ACTIVATED' : 'DEACTIVATED'), 'stocks', $stockId, "{$stock['symbol']} " . ($isActive ? 'activated' : 'deactivated'));
jsonResponse(true, 'Stock status updated.');

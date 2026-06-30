<?php
// =====================================================
// Trade-Zenfy - Update Stock Price from Finnhub
// POST /api/update-stock-price.php
// Called by frontend JS after receiving Finnhub WebSocket data
// =====================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

// Basic auth via session (must be logged in as user or admin)
startSecureSession();
if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
    jsonResponse(false, 'Unauthorized.');
}

$input  = json_decode(file_get_contents('php://input'), true);
$symbol = strtoupper(trim($input['symbol'] ?? ''));
$ltp    = (float)($input['ltp'] ?? 0);
$volume = (int)($input['volume'] ?? 0);

if (!$symbol || $ltp <= 0) {
    jsonResponse(false, 'Symbol and LTP required.');
}

$db   = getDB();
$stmt = $db->prepare("SELECT id, previous_close FROM stocks WHERE symbol = ? AND is_active = 1");
$stmt->execute([$symbol]);
$stock = $stmt->fetch();

if (!$stock) {
    jsonResponse(false, 'Stock not found.');
}

$prevClose     = (float)$stock['previous_close'];
$changeValue   = round($ltp - $prevClose, 2);
$changePct     = $prevClose > 0 ? round(($changeValue / $prevClose) * 100, 2) : 0;

// Update stock table
$db->prepare("
    UPDATE stocks
    SET ltp = ?, change_value = ?, change_percent = ?, volume = IF(? > 0, ?, volume), updated_at = NOW()
    WHERE id = ?
")->execute([$ltp, $changeValue, $changePct, $volume, $volume, $stock['id']]);

// Upsert price cache
$db->prepare("
    INSERT INTO stock_price_cache (stock_id, ltp, change_percent, volume, updated_at)
    VALUES (?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE ltp=VALUES(ltp), change_percent=VALUES(change_percent), volume=VALUES(volume), updated_at=NOW()
")->execute([$stock['id'], $ltp, $changePct, $volume]);

// Update all user holdings for this stock
$db->prepare("
    UPDATE user_holdings
    SET current_price          = ?,
        current_value          = quantity * ?,
        pnl         = (? - average_price) * quantity,
        pnl_percent = ((? - average_price) / average_price) * 100,
        updated_at             = NOW()
    WHERE stock_id = ? AND quantity > 0
")->execute([$ltp, $ltp, $ltp, $ltp, $stock['id']]);

// Refresh portfolio_value for affected users
$db->prepare("
    UPDATE account_registrations ar
    SET portfolio_value = (
        SELECT COALESCE(SUM(current_value), 0)
        FROM user_holdings
        WHERE user_id = ar.id AND quantity > 0
    )
    WHERE id IN (
        SELECT DISTINCT user_id FROM user_holdings WHERE stock_id = ? AND quantity > 0
    )
")->execute([$stock['id']]);

jsonResponse(true, 'Price updated.', ['ltp' => $ltp, 'change_percent' => $changePct]);

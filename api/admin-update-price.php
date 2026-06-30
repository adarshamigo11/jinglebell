<?php
// =====================================================
// Trade-Zenfy - Admin Manual Price Update API
// POST /api/admin-update-price.php
// =====================================================
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
header('Content-Type: application/json');

$input   = json_decode(file_get_contents('php://input'), true) ?: [];
$stockId = (int)($input['stock_id'] ?? 0);
$ltp     = (float)($input['ltp'] ?? 0);

if (!$stockId) jsonResponse(false, 'Invalid stock ID.');
if ($ltp <= 0) jsonResponse(false, 'LTP must be greater than 0.');

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM stocks WHERE id = ?");
$stmt->execute([$stockId]);
$stock = $stmt->fetch();
if (!$stock) jsonResponse(false, 'Stock not found.');

$prevClose   = (float)($input['prev_close'] ?? $stock['previous_close']);
$openPrice   = (float)($input['open_price'] ?? $stock['open_price']);
$highPrice   = (float)($input['high_price'] ?? $stock['high_price']);
$lowPrice    = (float)($input['low_price'] ?? $stock['low_price']);
$volume      = (int)($input['volume'] ?? $stock['volume']);
$changeVal   = round($ltp - $prevClose, 2);
$changePct   = $prevClose > 0 ? round(($changeVal / $prevClose) * 100, 2) : 0;

// High/Low auto-update
if ($highPrice > 0) $highPrice = max($highPrice, $ltp);
else $highPrice = $ltp;
if ($lowPrice > 0)  $lowPrice  = min($lowPrice, $ltp);
else $lowPrice = $ltp;

$db->prepare("
    UPDATE stocks SET ltp=?, open_price=?, high_price=?, low_price=?, previous_close=?,
           change_value=?, change_percent=?, volume=?, updated_at=NOW() WHERE id=?
")->execute([$ltp, $openPrice, $highPrice, $lowPrice, $prevClose, $changeVal, $changePct, $volume, $stockId]);

$db->prepare("
    INSERT INTO stock_price_cache (stock_id, ltp, open_price, high_price, low_price, close_price, change_percent, volume, updated_at)
    VALUES (?,?,?,?,?,?,?,?,NOW())
    ON DUPLICATE KEY UPDATE ltp=VALUES(ltp),open_price=VALUES(open_price),high_price=VALUES(high_price),
        low_price=VALUES(low_price),close_price=VALUES(close_price),change_percent=VALUES(change_percent),
        volume=VALUES(volume),updated_at=NOW()
")->execute([$stockId, $ltp, $openPrice, $highPrice, $lowPrice, $prevClose, $changePct, $volume]);

// Update holdings
$db->prepare("
    UPDATE user_holdings
    SET current_price=?, current_value=quantity*?,
        pnl=(? - average_price)*quantity,
        pnl_percent=((? - average_price)/average_price)*100,
        updated_at=NOW()
    WHERE stock_id=? AND quantity>0
")->execute([$ltp, $ltp, $ltp, $ltp, $stockId]);

// Refresh portfolio values
$db->prepare("
    UPDATE account_registrations ar
    SET portfolio_value=(SELECT COALESCE(SUM(current_value),0) FROM user_holdings WHERE user_id=ar.id AND quantity>0)
    WHERE id IN (SELECT DISTINCT user_id FROM user_holdings WHERE stock_id=? AND quantity>0)
")->execute([$stockId]);

logAdminAction($admin['id'], 'PRICE_UPDATED', 'stocks', $stockId, "{$stock['symbol']} LTP set to ₹$ltp");
jsonResponse(true, 'Price updated successfully.', ['ltp' => $ltp, 'change_percent' => $changePct]);

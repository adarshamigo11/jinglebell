<?php
// =====================================================
// Trade-Zenfy - Toggle Watchlist API
// POST /api/toggle-watchlist.php
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$stockId = (int)($input['stock_id'] ?? 0);

if (!$stockId) jsonResponse(false, 'Invalid stock ID.');

$db   = getDB();
$stmt = $db->prepare("SELECT id FROM stocks WHERE id = ? AND is_active = 1");
$stmt->execute([$stockId]);
if (!$stmt->fetch()) jsonResponse(false, 'Stock not found.');

// Check if already in watchlist
$check = $db->prepare("SELECT id FROM user_watchlist WHERE user_id = ? AND stock_id = ?");
$check->execute([$user['id'], $stockId]);

if ($check->fetch()) {
    $db->prepare("DELETE FROM user_watchlist WHERE user_id = ? AND stock_id = ?")->execute([$user['id'], $stockId]);
    jsonResponse(true, 'Removed from watchlist.', ['action' => 'removed']);
} else {
    // Max 20 stocks in watchlist
    $count = $db->prepare("SELECT COUNT(*) FROM user_watchlist WHERE user_id = ?");
    $count->execute([$user['id']]);
    if ($count->fetchColumn() >= 20) {
        jsonResponse(false, 'Watchlist is full. Maximum 20 stocks allowed.');
    }
    $db->prepare("INSERT INTO user_watchlist (user_id, stock_id) VALUES (?, ?)")->execute([$user['id'], $stockId]);
    jsonResponse(true, 'Added to watchlist.', ['action' => 'added']);
}

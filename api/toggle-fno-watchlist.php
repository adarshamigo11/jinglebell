<?php
/**
 * Toggle F&O Watchlist API
 */

require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method');
}

$input = json_decode(file_get_contents('php://input'), true);
$contractId = intval($input['contract_id'] ?? 0);

if (!$contractId) {
    jsonResponse(false, 'Contract ID required');
}

$db = getDB();

// Check if in watchlist
$stmt = $db->prepare("SELECT id FROM fno_watchlist WHERE user_id = ? AND contract_id = ?");
$stmt->execute([$user['id'], $contractId]);
$existing = $stmt->fetch();

if ($existing) {
    // Remove
    $stmt = $db->prepare("DELETE FROM fno_watchlist WHERE user_id = ? AND contract_id = ?");
    $stmt->execute([$user['id'], $contractId]);
    jsonResponse(true, 'Removed from watchlist', ['action' => 'removed']);
} else {
    // Add
    $stmt = $db->prepare("INSERT INTO fno_watchlist (user_id, contract_id) VALUES (?, ?)");
    $stmt->execute([$user['id'], $contractId]);
    jsonResponse(true, 'Added to watchlist', ['action' => 'added']);
}

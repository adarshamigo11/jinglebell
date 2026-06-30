<?php
/**
 * Toggle F&O Contract Status API
 */

require_once __DIR__ . '/../includes/middleware.php';
requireAdmin();
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

// Get current status
$stmt = $db->prepare("SELECT is_active FROM fno_contracts WHERE id = ?");
$stmt->execute([$contractId]);
$contract = $stmt->fetch();

if (!$contract) {
    jsonResponse(false, 'Contract not found');
}

$newStatus = $contract['is_active'] ? 0 : 1;

$stmt = $db->prepare("UPDATE fno_contracts SET is_active = ? WHERE id = ?");
$stmt->execute([$newStatus, $contractId]);

jsonResponse(true, 'Contract status updated', ['is_active' => $newStatus]);

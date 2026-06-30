<?php
/**
 * Get Option Chain API
 * Returns all options for a specific stock and expiry
 */

require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
header('Content-Type: application/json');

$db = getDB();

$symbol = $_GET['symbol'] ?? '';
$expiry = $_GET['expiry'] ?? '';

if (!$symbol || !$expiry) {
    jsonResponse(false, 'Symbol and expiry required');
}

// Get option chain
$stmt = $db->prepare("
    SELECT * FROM fno_contracts 
    WHERE symbol = ? AND expiry_date = ? AND contract_type IN ('CALL', 'PUT') AND is_active = 1
    ORDER BY strike_price, contract_type DESC
");
$stmt->execute([$symbol, $expiry]);
$options = $stmt->fetchAll();

// Get futures price
$futStmt = $db->prepare("
    SELECT current_price FROM fno_contracts 
    WHERE symbol = ? AND expiry_date = ? AND contract_type = 'FUTURES' AND is_active = 1
");
$futStmt->execute([$symbol, $expiry]);
$futuresPrice = $futStmt->fetchColumn();

// Group by strike price
$optionChain = [];
foreach ($options as $opt) {
    $strike = $opt['strike_price'];
    if (!isset($optionChain[$strike])) {
        $optionChain[$strike] = ['strike_price' => $strike];
    }
    $optionChain[$strike][$opt['contract_type']] = $opt;
}

jsonResponse(true, 'Option chain fetched', [
    'symbol' => $symbol,
    'expiry' => $expiry,
    'futures_price' => $futuresPrice,
    'option_chain' => array_values($optionChain)
]);

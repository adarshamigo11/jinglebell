<?php
/**
 * Get F&O Contracts API
 */

require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
header('Content-Type: application/json');

$db = getDB();

$filter = $_GET['filter'] ?? 'all'; // all, futures, options
$symbol = $_GET['symbol'] ?? '';
$expiry = $_GET['expiry'] ?? '';

$query = "SELECT * FROM fno_contracts WHERE is_active = 1";
$params = [];

if ($filter === 'futures') {
    $query .= " AND contract_type = 'FUTURES'";
} elseif ($filter === 'options') {
    $query .= " AND contract_type IN ('CALL', 'PUT')";
}

if ($symbol) {
    $query .= " AND symbol LIKE ?";
    $params[] = "%$symbol%";
}

if ($expiry) {
    $query .= " AND expiry_date = ?";
    $params[] = $expiry;
}

$query .= " ORDER BY symbol, contract_type, strike_price";

$stmt = $db->prepare($query);
$stmt->execute($params);
$contracts = $stmt->fetchAll();

// Get unique expiry dates
$expiryStmt = $db->query("SELECT DISTINCT expiry_date FROM fno_contracts WHERE is_active = 1 ORDER BY expiry_date");
$expiries = $expiryStmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique symbols
$symbolStmt = $db->query("SELECT DISTINCT symbol FROM fno_contracts WHERE is_active = 1 ORDER BY symbol");
$symbols = $symbolStmt->fetchAll(PDO::FETCH_COLUMN);

jsonResponse(true, 'Contracts fetched', [
    'contracts' => $contracts,
    'expiries' => $expiries,
    'symbols' => $symbols
]);

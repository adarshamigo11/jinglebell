<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $pdo = getDB();
    
    // Fetch all active cryptocurrencies
    $stmt = $pdo->query("
        SELECT 
            s.id,
            s.symbol,
            s.name,
            s.exchange,
            s.sector,
            s.ltp,
            s.previous_close
        FROM stocks s
        WHERE s.is_active = 1 
        AND s.sector = 'Cryptocurrency'
        ORDER BY s.symbol ASC
    ");
    
    $cryptos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate change and change percent
    foreach ($cryptos as &$crypto) {
        $ltp = floatval($crypto['ltp'] ?? 0);
        $prevClose = floatval($crypto['previous_close'] ?? $ltp);
        
        $change = $ltp - $prevClose;
        $changePercent = $prevClose > 0 ? ($change / $prevClose) * 100 : 0;
        
        $crypto['change'] = number_format($change, 2, '.', '');
        $crypto['change_percent'] = number_format($changePercent, 2, '.', '');
    }
    
    echo json_encode([
        'success' => true,
        'cryptos' => $cryptos
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get-cryptos.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in get-cryptos.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

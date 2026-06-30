<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $pdo = getDB();
    
    // Fetch all active commodities with cache prices
    $stmt = $pdo->query("
        SELECT 
            s.id,
            s.symbol,
            s.name,
            s.exchange,
            s.sector,
            COALESCE(c.ltp, s.ltp) as ltp,
            COALESCE(c.close_price, s.previous_close) as previous_close,
            COALESCE(c.open_price, 0) as open_price,
            COALESCE(c.high_price, 0) as high_price,
            COALESCE(c.low_price, 0) as low_price
        FROM stocks s
        LEFT JOIN stock_price_cache c ON c.stock_id = s.id
        WHERE s.is_active = 1 
        AND s.sector = 'Commodity'
        ORDER BY s.symbol ASC
    ");
    
    $commodities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate change and change percent
    foreach ($commodities as &$cmd) {
        $ltp = floatval($cmd['ltp'] ?? 0);
        $prevClose = floatval($cmd['previous_close'] ?? $ltp);
        
        $change = $ltp - $prevClose;
        $changePercent = $prevClose > 0 ? ($change / $prevClose) * 100 : 0;
        
        $cmd['change'] = number_format($change, 2, '.', '');
        $cmd['change_percent'] = number_format($changePercent, 2, '.', '');
    }
    
    echo json_encode([
        'success' => true,
        'commodities' => $commodities
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get-commodities.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in get-commodities.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

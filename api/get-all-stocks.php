<?php
/**
 * Get All Stocks - Returns all available stocks from database
 * Used by dashboard stocks tab to display full stock list
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $db = getDB();
    
    // Fetch all active stocks (excluding indices - they're in their own tab)
    $stmt = $db->query("
        SELECT 
            s.id,
            s.symbol,
            s.name,
            s.exchange,
            s.sector,
            COALESCE(c.ltp, s.ltp) AS ltp,
            COALESCE(c.change_percent, s.change_percent) AS change_percent,
            COALESCE(c.volume, IFNULL(s.volume, 0)) AS volume,
            COALESCE(c.high_price, IFNULL(s.high_price, 0)) AS high_price,
            COALESCE(c.low_price, IFNULL(s.low_price, 0)) AS low_price,
            COALESCE(c.open_price, IFNULL(s.open_price, 0)) AS open_price,
            COALESCE(c.close_price, s.previous_close) AS previous_close,
            COALESCE(c.source, 'database') AS source,
            COALESCE(c.updated_at, NOW()) AS updated_at
        FROM stocks s
        LEFT JOIN stock_price_cache c ON c.stock_id = s.id
        WHERE s.is_active = 1 
          AND s.sector NOT IN ('Index', 'Forex', 'Crypto')
        ORDER BY s.sector ASC, s.name ASC
    ");
    
    $stocks = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ltp = round((float)$row['ltp'], 2);
        $prevClose = round((float)$row['previous_close'], 2);
        $changePct = round((float)$row['change_percent'], 2);
        $change = $prevClose > 0 ? round($ltp - $prevClose, 2) : 0;
        
        $stocks[] = [
            'id'             => (int)$row['id'],
            'symbol'         => $row['symbol'],
            'name'           => $row['name'],
            'exchange'       => $row['exchange'],
            'sector'         => $row['sector'],
            'price'          => $ltp,
            'change_percent' => $changePct,
            'change'         => $change,
            'volume'         => (int)$row['volume'],
            'high'           => round((float)$row['high_price'], 2),
            'low'            => round((float)$row['low_price'], 2),
            'open'           => round((float)$row['open_price'], 2),
            'previous_close' => $prevClose,
            'is_live'        => ($row['source'] === 'angel_one'),
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count'   => count($stocks),
        'stocks'  => $stocks
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to load stocks: ' . $e->getMessage()
    ]);
}
?>

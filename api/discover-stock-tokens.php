<?php
/**
 * Download Angel One master scrip file and find tokens for our stocks
 * DELETE after use
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
set_time_limit(120);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT symbol, name, exchange FROM stocks WHERE is_active = 1 AND sector NOT IN ('Index', 'Forex', 'Crypto')");
    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Download Angel One master scrip file
    $masterUrl = 'https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json';
    $ch = curl_init($masterUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0']
    ]);
    $json = curl_exec($ch);
    $err = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($err || !$json) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to download master file',
            'curl_error' => $err,
            'http_code' => $info['http_code'],
            'size' => strlen($json)
        ]);
        exit;
    }

    $master = json_decode($json, true);
    if (!$master) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to parse master file',
            'json_sample' => substr($json, 0, 200),
            'size' => strlen($json)
        ]);
        exit;
    }

    // Build lookup
    $tokenMap = [];
    foreach ($master as $item) {
        $exch = $item['exch_seg'] ?? '';
        $symbol = $item['symbol'] ?? '';
        $name = $item['name'] ?? '';
        $token = $item['token'] ?? '';
        $tradingsymbol = $item['tradingsymbol'] ?? '';
        
        if (!$token) continue;
        
        $tokenMap[$exch . ':' . $symbol] = [
            'token' => $token,
            'tradingsymbol' => $tradingsymbol,
            'name' => $name
        ];
        $tokenMap[$exch . ':' . $tradingsymbol] = [
            'token' => $token,
            'tradingsymbol' => $tradingsymbol,
            'name' => $name
        ];
    }

    // Match our stocks
    $results = [];
    foreach ($stocks as $stock) {
        $symbol = $stock['symbol'];
        $exchange = ($stock['exchange'] === 'BSE') ? 'BSE' : 'NSE';
        $cleanSymbol = preg_replace('/\.(NS|BO|MF)$/i', '', $symbol);
        
        $key = $exchange . ':' . $cleanSymbol;
        if (isset($tokenMap[$key])) {
            $results[$symbol] = $tokenMap[$key];
        } else {
            $results[$symbol] = null;
        }
    }

    echo json_encode([
        'success' => true,
        'master_count' => count($master),
        'matched' => count(array_filter($results)),
        'tokens' => $results
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
?>

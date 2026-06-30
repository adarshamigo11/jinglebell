<?php
// =====================================================
// Trade-Zenfy - Yahoo Finance API Proxy
// Fetches stock data server-side to avoid CORS issues
// GET /api/yahoo-proxy.php?symbol=RELIANCE
// =====================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get symbol from request
$symbol = isset($_GET['symbol']) ? strtoupper(trim($_GET['symbol'])) : '';
$symbols = isset($_GET['symbols']) ? explode(',', $_GET['symbols']) : [];

if (!$symbol && empty($symbols)) {
    jsonResponse(false, 'Symbol required');
}

// If single symbol, convert to array
if ($symbol && empty($symbols)) {
    $symbols = [$symbol];
}

// Clean symbols
$symbols = array_map('trim', $symbols);
$symbols = array_map('strtoupper', $symbols);

$results = [];

foreach ($symbols as $sym) {
    // Convert to Yahoo Finance format
    $yahooSymbol = $sym;
    
    // Indices starting with ^ should not be modified
    if (str_starts_with($yahooSymbol, '^')) {
        // Keep as-is for indices
    } elseif (!str_ends_with($yahooSymbol, '.NS') && !str_ends_with($yahooSymbol, '.BO')) {
        $yahooSymbol .= '.NS';
    }
    
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$yahooSymbol}?interval=1d&range=1d";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        
        if ($data && isset($data['chart']['result'][0])) {
            $result = $data['chart']['result'][0];
            $meta = $result['meta'];
            
            // Get latest price
            $timestamps = $result['timestamp'] ?? [];
            $prices = $result['indicators']['quote'][0]['close'] ?? [];
            $volumes = $result['indicators']['quote'][0]['volume'] ?? [];
            
            $lastIndex = count($prices) - 1;
            $currentPrice = $meta['regularMarketPrice'] ?? ($prices[$lastIndex] ?? 0);
            $previousClose = $meta['previousClose'] ?? $meta['chartPreviousClose'] ?? 0;
            $volume = $meta['regularMarketVolume'] ?? ($volumes[$lastIndex] ?? 0);
            
            $change = $currentPrice - $previousClose;
            $changePercent = $previousClose > 0 ? ($change / $previousClose) * 100 : 0;
            
            $results[$sym] = [
                'symbol' => $sym,
                'ltp' => round($currentPrice, 2),
                'change' => round($change, 2),
                'changePercent' => round($changePercent, 2),
                'volume' => (int)$volume,
                'open' => $meta['regularMarketOpen'] ?? 0,
                'high' => $meta['regularMarketDayHigh'] ?? 0,
                'low' => $meta['regularMarketDayLow'] ?? 0,
                'previousClose' => $previousClose,
                'timestamp' => time(),
                'currency' => $meta['currency'] ?? 'INR',
                'exchange' => $meta['exchangeName'] ?? 'NSE'
            ];
        }
    }
    
    // Small delay to be nice to Yahoo's servers
    usleep(100000); // 100ms
}

if (empty($results)) {
    jsonResponse(false, 'No data available');
}

jsonResponse(true, 'Data fetched', ['quotes' => $results]);

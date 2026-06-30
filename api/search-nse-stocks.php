<?php
// =====================================================
// Trade-Zenfy - Search NSE Stocks API
// GET /api/search-nse-stocks.php?q=TATA
// Returns list of matching NSE stocks from Yahoo Finance
// =====================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Allow both admin and user requests
startSecureSession();
if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
    jsonResponse(false, 'Unauthorized.');
}

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    jsonResponse(false, 'Query must be at least 2 characters');
}

// Search Yahoo Finance
$searchUrl = 'https://query1.finance.yahoo.com/v1/finance/search?q=' . urlencode($query) . '&quotesCount=20&newsCount=0';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $searchUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    jsonResponse(false, 'Failed to fetch from Yahoo Finance');
}

$data = json_decode($response, true);
if (!$data || !isset($data['quotes'])) {
    jsonResponse(false, 'Invalid response from Yahoo Finance');
}

// Filter for NSE stocks only and format results
$stocks = [];
foreach ($data['quotes'] as $quote) {
    // Only include NSE (India) stocks
    $exchange = $quote['exchange'] ?? '';
    $symbol = $quote['symbol'] ?? '';
    
    // Check if it's an NSE stock (NSI = NSE, BOM = BSE)
    if ($exchange === 'NSI' || str_ends_with($symbol, '.NS')) {
        $cleanSymbol = str_replace('.NS', '', $symbol);
        
        // Skip if already in our database (only check active stocks)
        $db = getDB();
        $exists = $db->prepare("SELECT id FROM stocks WHERE symbol = ? AND is_active = 1");
        $exists->execute([$cleanSymbol]);
        $isExisting = $exists->fetch();
        
        $stocks[] = [
            'symbol' => $cleanSymbol,
            'name' => $quote['shortname'] ?? $quote['longname'] ?? $cleanSymbol,
            'exchange' => 'NSE',
            'sector' => $quote['sector'] ?? '',
            'industry' => $quote['industry'] ?? '',
            'type' => $quote['quoteType'] ?? 'EQUITY',
            'existing' => $isExisting ? true : false
        ];
    }
}

jsonResponse(true, 'Stocks found', ['stocks' => $stocks]);

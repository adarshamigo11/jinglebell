<?php
/**
 * Get Index Chart Data
 * Fetches historical chart data for index detail page
 */

require_once __DIR__ . '/../includes/middleware.php';

header('Content-Type: application/json');

$symbol = $_GET['symbol'] ?? '';
$range = $_GET['range'] ?? '1M';

// Validate symbol
if (!$symbol) {
    echo json_encode(['success' => false, 'error' => 'Symbol is required']);
    exit;
}

// Validate range
$validRanges = ['1D', '5D', '1M', '6M', '1Y', '5Y', 'ALL'];
if (!in_array($range, $validRanges)) {
    $range = '1M';
}

// Determine interval based on range
$intervalMap = [
    '1D' => '5m',
    '5D' => '15m',
    '1M' => '1d',
    '6M' => '1d',
    '1Y' => '1wk',
    '5Y' => '1wk',
    'ALL' => '1mo'
];

$interval = $intervalMap[$range];

// Fetch chart data from Yahoo Finance
// Use a cookie jar to maintain session
$cookieFile = tempnam(sys_get_temp_dir(), 'yahoo_cookie_');

// First visit Yahoo Finance to get cookies
$initUrl = 'https://finance.yahoo.com/quote/' . $symbol;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $initUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_exec($ch);
curl_close($ch);

// Now get crumb token with cookies
$crumbUrl = 'https://query2.finance.yahoo.com/v1/test/getcrumb';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $crumbUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$crumb = curl_exec($ch);
curl_close($ch);

// Now fetch chart data with crumb and cookies
$url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval={$interval}&range={$range}";
if ($crumb && strpos($crumb, '{') === false) {
    $url .= "&crumb=" . urlencode($crumb);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept: application/json',
    'Accept-Language: en-US,en;q=0.9'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Clean up cookie file
@unlink($cookieFile);

if ($httpCode !== 200 || !$response) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch chart data']);
    exit;
}

$data = json_decode($response, true);

if (!isset($data['chart']['result'][0])) {
    echo json_encode(['success' => false, 'error' => 'Invalid response from Yahoo Finance']);
    exit;
}

$result = $data['chart']['result'][0];
$meta = $result['meta'];
$timestamps = $result['timestamp'] ?? [];
$quotes = $result['indicators']['quote'][0] ?? [];

// Prepare chart data
$chartData = [];
for ($i = 0; $i < count($timestamps); $i++) {
    $chartData[] = [
        'timestamp' => $timestamps[$i],
        'open' => $quotes['open'][$i] ?? null,
        'high' => $quotes['high'][$i] ?? null,
        'low' => $quotes['low'][$i] ?? null,
        'close' => $quotes['close'][$i] ?? null,
        'volume' => $quotes['volume'][$i] ?? 0
    ];
}

// Filter valid data points
$validData = array_filter($chartData, fn($p) => $p['close'] !== null);

if (count($validData) < 2) {
    echo json_encode([
        'success' => false,
        'error' => 'Insufficient data for chart. Try a different time range.',
        'dataCount' => count($validData)
    ]);
    exit;
}

// Calculate stats
$currentPrice = $meta['regularMarketPrice'] ?? 0;
$previousClose = $meta['chartPreviousClose'] ?? $meta['previousClose'] ?? 0;
$change = $currentPrice - $previousClose;
$changePercent = $previousClose > 0 ? ($change / $previousClose) * 100 : 0;

$allHighs = array_filter($quotes['high'] ?? []);
$allLows = array_filter($quotes['low'] ?? []);

$periodHigh = !empty($allHighs) ? max($allHighs) : 0;
$periodLow = !empty($allLows) ? min($allLows) : 0;

echo json_encode([
    'success' => true,
    'symbol' => $symbol,
    'range' => $range,
    'isPositive' => $change >= 0,
    'meta' => [
        'currentPrice' => $currentPrice,
        'previousClose' => $previousClose,
        'change' => $change,
        'changePercent' => $changePercent,
        'periodHigh' => $periodHigh,
        'periodLow' => $periodLow,
        '52WeekHigh' => $meta['fiftyTwoWeekHigh'] ?? 0,
        '52WeekLow' => $meta['fiftyTwoWeekLow'] ?? 0
    ],
    'data' => $chartData
]);

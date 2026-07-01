<?php
/**
 * Get Chart Data - Angel One + Yahoo Finance Fallback
 * Fetches historical OHLC data for stock/index charts
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$symbol = $_GET['symbol'] ?? '';
$range = $_GET['range'] ?? '1M';

if (!$symbol) {
    echo json_encode(['success' => false, 'error' => 'Symbol is required']);
    exit;
}

// Validate range
$validRanges = ['1D', '5D', '1M', '6M', '1Y', '5Y', 'ALL'];
if (!in_array($range, $validRanges)) $range = '1M';

// Try Angel One first, fallback to Yahoo Finance
$chartData = null;

// Check if Angel One is active
try {
    $db = getDB();
    $prefStmt = $db->prepare("SELECT provider, is_enabled FROM data_provider_preferences WHERE asset_type = 'stocks'");
    $prefStmt->execute();
    $pref = $prefStmt->fetch();
    
    if ($pref && $pref['provider'] === 'angel_one' && $pref['is_enabled']) {
        $settingsStmt = $db->query("SELECT * FROM api_settings WHERE provider = 'angel_one' AND is_active = 1");
        $settings = $settingsStmt->fetch();
        
        if ($settings && $settings['api_key'] && $settings['client_id'] && $settings['password']) {
            $chartData = fetchAngelOneChartData($settings, $symbol, $range);
        }
    }
} catch (Exception $e) {
    // Fallback to Yahoo Finance
}

// Fallback to Yahoo Finance if Angel One didn't work
if ($chartData === null) {
    $chartData = fetchYahooChartData($symbol, $range);
}

if ($chartData === null || empty($chartData['data'])) {
    echo json_encode(['success' => false, 'error' => 'Insufficient data for chart. Try a different time range.']);
    exit;
}

echo json_encode($chartData);
exit;

// ══════════════════════════════════════════════════════
// Angel One Historical Candle Data
// ══════════════════════════════════════════════════════

function fetchAngelOneChartData($settings, $yahooSymbol, $range) {
    // Login to get JWT
    $jwtToken = angelOneLogin($settings);
    if (!$jwtToken) return null;
    
    // Convert symbol
    $angelSymbol = convertToAngelSymbol($yahooSymbol);
    
    // Detect exchange: Sensex uses BSE, .BO uses BSE, everything else NSE
    $exchange = 'NSE';
    if ($yahooSymbol === '^BSESN' || $yahooSymbol === 'Sensex' || preg_match('/\.BO$/i', $yahooSymbol)) {
        $exchange = 'BSE';
    }
    $exchangeCode = $exchange;
    
    // Determine date range and interval
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $fromDate = clone $now;
    
    switch ($range) {
        case '1D':  $fromDate->modify('-1 day');    $interval = 'ONE_MINUTE'; break;
        case '5D':  $fromDate->modify('-5 days');   $interval = 'FIVE_MINUTE'; break;
        case '1M':  $fromDate->modify('-1 month');  $interval = 'ONE_DAY'; break;
        case '6M':  $fromDate->modify('-6 months'); $interval = 'ONE_DAY'; break;
        case '1Y':  $fromDate->modify('-1 year');   $interval = 'ONE_DAY'; break;
        case '5Y':  $fromDate->modify('-5 years');  $interval = 'ONE_WEEK'; break;
        case 'ALL': $fromDate = new DateTime('2010-01-01'); $interval = 'ONE_MONTH'; break;
        default:    $fromDate->modify('-1 month');  $interval = 'ONE_DAY'; break;
    }
    
    $from = $fromDate->format('Y-m-d 09:15');
    $to = $now->format('Y-m-d 15:30');
    
    // Angel One historical candle data API
    $payload = json_encode([
        'exchange' => $exchangeCode,
        'symboltoken' => getAngelSymbolToken($settings, $jwtToken, $angelSymbol, $exchangeCode),
        'interval' => $interval,
        'fromdate' => $from,
        'todate' => $to
    ]);
    
    $url = 'https://apiconnect.angelone.in/rest/secure/angelbroking/historical/v1/getCandleData';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-UserType: USER',
        'X-SourceID: WEB',
        'X-ClientLocalIP: CLIENT_LOCAL_IP',
        'X-ClientPublicIP: CLIENT_PUBLIC_IP',
        'X-MACAddress: MAC_ADDRESS',
        'X-PrivateKey: ' . $settings['api_key'],
        'Authorization: Bearer ' . $jwtToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) return null;
    
    $data = json_decode($response, true);
    if (!$data || empty($data['status']) || empty($data['data'])) return null;
    
    // Parse Angel One candle data: [timestamp, open, high, low, close, volume]
    $candles = $data['data'];
    if (count($candles) < 2) return null;
    
    $chartData = [];
    $firstClose = 0;
    $lastClose = 0;
    $periodHigh = 0;
    $periodLow = PHP_FLOAT_MAX;
    
    foreach ($candles as $candle) {
        if (!is_array($candle) || count($candle) < 5) continue;
        
        $ts = strtotime($candle[0]);
        $open = (float)$candle[1];
        $high = (float)$candle[2];
        $low = (float)$candle[3];
        $close = (float)$candle[4];
        $volume = isset($candle[5]) ? (int)$candle[5] : 0;
        
        if ($close <= 0) continue;
        
        if ($firstClose == 0) $firstClose = $close;
        $lastClose = $close;
        $periodHigh = max($periodHigh, $high);
        $periodLow = min($periodLow, $low);
        
        $chartData[] = [
            'timestamp' => $ts,
            'open' => $open,
            'high' => $high,
            'low' => $low,
            'close' => $close,
            'volume' => $volume
        ];
    }
    
    if (count($chartData) < 2) return null;
    
    $change = $lastClose - $firstClose;
    $changePercent = $firstClose > 0 ? ($change / $firstClose) * 100 : 0;
    
    return [
        'success' => true,
        'symbol' => $yahooSymbol,
        'range' => $range,
        'source' => 'angel_one',
        'isPositive' => $change >= 0,
        'meta' => [
            'currentPrice' => $lastClose,
            'previousClose' => $firstClose,
            'change' => $change,
            'changePercent' => $changePercent,
            'periodHigh' => $periodHigh,
            'periodLow' => $periodLow == PHP_FLOAT_MAX ? 0 : $periodLow
        ],
        'data' => $chartData
    ];
}

function getAngelSymbolToken($settings, $jwtToken, $angelSymbol, $exchange) {
    // Hardcoded index tokens (scrip search is unreliable on cloud hosting)
    $indexTokenMap = [
        'Nifty 50'                  => '99926000',
        'BankNifty'                 => '99926009',
        'NIFTY IT'                  => '99926008',
        'NIFTY FINANCIAL SERVICES'  => '99926037',
        'Nifty Midcap 100'          => '99926011',
        'Nifty Smallcap 100'        => '99926027',
        'NIFTY AUTO'                => '99926026',
        'NIFTY PHARMA'              => '99926032',
        'NIFTY METAL'               => '99926030',
        'Nifty Energy'              => '99926020',
        'Nifty PSU Bank'            => '99926025',
        'India VIX'                 => '99926017',
        'Sensex'                    => '99919000',
    ];
    
    $upperSymbol = strtoupper($angelSymbol);
    foreach ($indexTokenMap as $name => $token) {
        if (strtoupper($name) === $upperSymbol) {
            return $token;
        }
    }
    
    // Fallback to scrip search for stocks
    $searchTerm = $angelSymbol;
    $payload = json_encode([
        'searchsymbol' => $searchTerm,
        'exchange' => $exchange
    ]);
    
    $url = 'https://apiconnect.angelone.in/rest/secure/angelbroking/search/scrip/';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-UserType: USER',
        'X-SourceID: WEB',
        'X-ClientLocalIP: CLIENT_LOCAL_IP',
        'X-ClientPublicIP: CLIENT_PUBLIC_IP',
        'X-MACAddress: MAC_ADDRESS',
        'X-PrivateKey: ' . $settings['api_key'],
        'Authorization: Bearer ' . $jwtToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($data && !empty($data['status']) && !empty($data['data'])) {
        foreach ($data['data'] as $item) {
            if (isset($item['tradingsymbol']) && strcasecmp($item['tradingsymbol'], $angelSymbol) === 0) {
                return $item['token'] ?? '';
            }
        }
        foreach ($data['data'] as $item) {
            if (isset($item['tradingsymbol']) && stripos($item['tradingsymbol'], $angelSymbol) !== false) {
                return $item['token'] ?? '';
            }
        }
        return $data['data'][0]['token'] ?? '';
    }
    
    return '';
}

function convertToAngelSymbol($yahooSymbol) {
    // Handle indices - comprehensive map including display names
    $indexMap = [
        '^NSEI'        => 'Nifty 50',
        '^BSESN'       => 'Sensex',
        '^NSEBANK'     => 'BankNifty',
        '^CNXIT'       => 'NIFTY IT',
        '^CNXFIN'      => 'NIFTY FINANCIAL SERVICES',
        '^NSEMDCP100'  => 'Nifty Midcap 100',
        '^NSESMLCP100' => 'Nifty Smallcap 100',
        '^CNXAUTO'     => 'NIFTY AUTO',
        '^CNXPHARMA'   => 'NIFTY PHARMA',
        '^CNXMETAL'    => 'NIFTY METAL',
        // Also handle display names passed from dashboard
        'Nifty 50'     => 'Nifty 50',
        'Bank Nifty'   => 'BankNifty',
        'Sensex'       => 'Sensex',
        'Nifty IT'     => 'NIFTY IT',
        'Fin Nifty'    => 'NIFTY FINANCIAL SERVICES',
        'Midcap 100'   => 'Nifty Midcap 100',
        'Smallcap 100' => 'Nifty Smallcap 100',
        'Nifty Auto'   => 'NIFTY AUTO',
        'Nifty Pharma' => 'NIFTY PHARMA',
        'Nifty Metal'  => 'NIFTY METAL',
    ];
    if (isset($indexMap[$yahooSymbol])) return $indexMap[$yahooSymbol];
    
    // Convert RELIANCE.NS → RELIANCE
    $symbol = preg_replace('/\.(NS|BO|MF)$/i', '', $yahooSymbol);
    return $symbol;
}

// ══════════════════════════════════════════════════════
// Angel One Login (shared function)
// ══════════════════════════════════════════════════════

function angelOneLogin($settings) {
    $totp = '';
    if (!empty($settings['totp_secret'])) {
        $totp = generateTOTPCode($settings['totp_secret']);
    }
    
    $payload = json_encode([
        'clientcode' => $settings['client_id'],
        'password' => $settings['password'],
        'totp' => $totp
    ]);
    
    $ch = curl_init('https://apiconnect.angelone.in/rest/auth/angelbroking/user/v1/loginByPassword');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json', 'Accept: application/json',
        'X-UserType: USER', 'X-SourceID: WEB',
        'X-ClientLocalIP: CLIENT_LOCAL_IP', 'X-ClientPublicIP: CLIENT_PUBLIC_IP',
        'X-MACAddress: MAC_ADDRESS', 'X-PrivateKey: ' . $settings['api_key']
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    return ($data && !empty($data['data']['jwtToken'])) ? $data['data']['jwtToken'] : null;
}

// ══════════════════════════════════════════════════════
// Yahoo Finance Fallback
// ══════════════════════════════════════════════════════

function fetchYahooChartData($symbol, $range) {
    $intervalMap = ['1D'=>'5m','5D'=>'15m','1M'=>'1d','6M'=>'1d','1Y'=>'1wk','5Y'=>'1wk','ALL'=>'1mo'];
    $interval = $intervalMap[$range] ?? '1d';
    
    $cookieFile = tempnam(sys_get_temp_dir(), 'yahoo_cookie_');
    
    // Get cookies
    $ch = curl_init('https://finance.yahoo.com/quote/' . $symbol);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
    
    // Get crumb
    $ch = curl_init('https://query2.finance.yahoo.com/v1/test/getcrumb');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $crumb = curl_exec($ch);
    curl_close($ch);
    
    // Fetch chart
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval={$interval}&range={$range}";
    if ($crumb && strpos($crumb, '{') === false) $url .= "&crumb=" . urlencode($crumb);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    @unlink($cookieFile);
    
    if ($httpCode !== 200 || !$response) return null;
    
    $data = json_decode($response, true);
    if (!isset($data['chart']['result'][0])) return null;
    
    $result = $data['chart']['result'][0];
    $meta = $result['meta'];
    $timestamps = $result['timestamp'] ?? [];
    $quotes = $result['indicators']['quote'][0] ?? [];
    
    $chartData = [];
    for ($i = 0; $i < count($timestamps); $i++) {
        $close = $quotes['close'][$i] ?? null;
        if ($close === null) continue;
        $chartData[] = [
            'timestamp' => $timestamps[$i],
            'open' => $quotes['open'][$i] ?? null,
            'high' => $quotes['high'][$i] ?? null,
            'low' => $quotes['low'][$i] ?? null,
            'close' => $close,
            'volume' => $quotes['volume'][$i] ?? 0
        ];
    }
    
    if (count($chartData) < 2) return null;
    
    $currentPrice = $meta['regularMarketPrice'] ?? 0;
    $previousClose = $meta['chartPreviousClose'] ?? $meta['previousClose'] ?? 0;
    $change = $currentPrice - $previousClose;
    $changePercent = $previousClose > 0 ? ($change / $previousClose) * 100 : 0;
    
    return [
        'success' => true,
        'symbol' => $symbol,
        'range' => $range,
        'source' => 'yahoo',
        'isPositive' => $change >= 0,
        'meta' => [
            'currentPrice' => $currentPrice,
            'previousClose' => $previousClose,
            'change' => $change,
            'changePercent' => $changePercent,
            'periodHigh' => $meta['fiftyTwoWeekHigh'] ?? 0,
            'periodLow' => $meta['fiftyTwoWeekLow'] ?? 0
        ],
        'data' => $chartData
    ];
}

// ══════════════════════════════════════════════════════
// TOTP Helpers
// ══════════════════════════════════════════════════════

function generateTOTPCode($secret) {
    $secret = strtoupper(str_replace(' ', '', $secret));
    $decoded = base32DecodeSecret($secret);
    if ($decoded === false) return '';
    $timeStep = floor(time() / 30);
    $timeBytes = pack('N*', 0, $timeStep);
    $hmac = hash_hmac('sha1', $timeBytes, $decoded, true);
    $offset = ord($hmac[19]) & 0x0F;
    $code = (((ord($hmac[$offset]) & 0x7F) << 24) | ((ord($hmac[$offset+1]) & 0xFF) << 16) | ((ord($hmac[$offset+2]) & 0xFF) << 8) | (ord($hmac[$offset+3]) & 0xFF)) % 1000000;
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

function base32DecodeSecret($input) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(rtrim($input, '='));
    $buffer = 0; $bitsLeft = 0; $output = '';
    for ($i = 0; $i < strlen($input); $i++) {
        $val = strpos($alphabet, $input[$i]);
        if ($val === false) return false;
        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) { $bitsLeft -= 8; $output .= chr(($buffer >> $bitsLeft) & 0xFF); }
    }
    return $output;
}
?>

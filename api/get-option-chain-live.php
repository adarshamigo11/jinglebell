<?php
/**
 * Get Live Option Chain from Angel One
 * Returns option chain for a given underlying symbol
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

$symbol = $_GET['symbol'] ?? '';
$expiry = $_GET['expiry'] ?? '';

if (!$symbol) {
    echo json_encode(['success' => false, 'error' => 'Symbol required']);
    exit;
}

// Underlying token map (same as stocks)
$STOCK_TOKEN_MAP = [
    'RELIANCE'   => ['token' => '2885',  'exchange' => 'NSE'],
    'TCS'        => ['token' => '11536', 'exchange' => 'NSE'],
    'INFY'       => ['token' => '1594',  'exchange' => 'NSE'],
    'HDFCBANK'   => ['token' => '1333',  'exchange' => 'NSE'],
    'ICICIBANK'  => ['token' => '4963',  'exchange' => 'NSE'],
    'SBIN'       => ['token' => '3045',  'exchange' => 'NSE'],
    'BHARTIARTL' => ['token' => '10604', 'exchange' => 'NSE'],
    'ITC'        => ['token' => '1660',  'exchange' => 'NSE'],
    'LT'         => ['token' => '11483', 'exchange' => 'NSE'],
    'HINDUNILVR' => ['token' => '1394',  'exchange' => 'NSE'],
    'NIFTY'      => ['token' => '99926000', 'exchange' => 'NSE'],
    'BANKNIFTY'  => ['token' => '99926009', 'exchange' => 'NSE'],
];

if (!isset($STOCK_TOKEN_MAP[$symbol])) {
    echo json_encode(['success' => false, 'error' => 'Symbol not supported']);
    exit;
}

$und = $STOCK_TOKEN_MAP[$symbol];

try {
    $db = getDB();
    $settingsStmt = $db->query("SELECT * FROM api_settings WHERE provider = 'angel_one' AND is_active = 1 LIMIT 1");
    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings || empty($settings['api_key']) || empty($settings['client_id']) || empty($settings['password'])) {
        echo json_encode(['success' => false, 'error' => 'Angel One credentials not configured']);
        exit;
    }
    
    $jwtToken = angelOneLogin($settings);
    if (!$jwtToken) {
        echo json_encode(['success' => false, 'error' => 'Angel One login failed']);
        exit;
    }
    
    // Fetch option chain from Angel One
    $url = 'https://apiconnect.angelone.in/rest/secure/angelbroking/market/v1/option-chain';
    $payload = json_encode([
        'exchange' => $und['exchange'],
        'tradingsymbol' => $symbol,
        'symboltoken' => $und['token']
    ]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json', 'Accept: application/json',
        'X-UserType: USER', 'X-SourceID: WEB',
        'X-ClientLocalIP: CLIENT_LOCAL_IP', 'X-ClientPublicIP: CLIENT_PUBLIC_IP',
        'X-MACAddress: MAC_ADDRESS', 'X-PrivateKey: ' . $settings['api_key'],
        'Authorization: Bearer ' . $jwtToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode !== 200 || !$data || empty($data['data'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch option chain',
            'http_code' => $httpCode,
            'response' => $data
        ]);
        exit;
    }
    
    // Process option chain
    $chain = $data['data'];
    $expiryDates = [];
    $strikes = [];
    
    foreach ($chain as $opt) {
        $expiryDate = $opt['expiry'] ?? '';
        if (!$expiryDate) continue;
        
        // Convert expiry format 28JUL2026 -> 2026-07-28
        $dt = DateTime::createFromFormat('dMY', $expiryDate);
        $formattedExpiry = $dt ? $dt->format('Y-m-d') : $expiryDate;
        
        $expiryDates[$formattedExpiry] = true;
        if ($expiry && $formattedExpiry !== $expiry) continue;
        
        $strike = (float)($opt['strike'] ?? 0);
        if ($strike <= 0) continue;
        // Strike comes scaled by 100 in some cases
        if ($strike > 100000) $strike = $strike / 100;
        
        if (!isset($strikes[$strike])) {
            $strikes[$strike] = ['strike' => $strike, 'CALL' => null, 'PUT' => null];
        }
        
        $type = ($opt['optiontype'] ?? '') === 'PE' ? 'PUT' : 'CALL';
        $strikes[$strike][$type] = [
            'token' => $opt['symbolToken'] ?? '',
            'ltp' => (float)($opt['ltp'] ?? 0),
            'change' => (float)($opt['netChange'] ?? 0),
            'change_percent' => (float)($opt['percentChange'] ?? 0),
            'volume' => (int)($opt['tradedVolume'] ?? 0),
            'oi' => (int)($opt['openInterest'] ?? 0),
            'bid' => (float)($opt['bidprice'] ?? 0),
            'ask' => (float)($opt['askprice'] ?? 0),
        ];
    }
    
    ksort($strikes);
    $expiryDates = array_keys($expiryDates);
    sort($expiryDates);
    
    echo json_encode([
        'success' => true,
        'symbol' => $symbol,
        'expiry' => $expiry,
        'expiry_dates' => $expiryDates,
        'spot_price' => (float)($data['spotPrice'] ?? 0),
        'option_chain' => array_values($strikes)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function angelOneLogin($settings) {
    $totp = generateTOTPCode($settings['totp_secret'] ?? '');
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

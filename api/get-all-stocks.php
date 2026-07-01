<?php
/**
 * Get All Stocks - Returns all available stocks with live Angel One data
 * Used by dashboard stocks tab to display full stock list
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

// Hardcoded Angel One tokens for stocks (scrip search is blocked on Railway)
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
];

try {
    $db = getDB();
    
    // Fetch all active stocks
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
    
    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Try to fetch live data from Angel One
    $liveData = [];
    $source = 'database';
    try {
        $settingsStmt = $db->query("SELECT * FROM api_settings WHERE provider = 'angel_one' AND is_active = 1 LIMIT 1");
        $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings && !empty($settings['api_key']) && !empty($settings['client_id']) && !empty($settings['password'])) {
            $liveData = fetchAngelOneLiveQuotes($settings, $STOCK_TOKEN_MAP);
            if (!empty($liveData)) $source = 'angel_one';
        }
    } catch (Exception $e) { /* silent fallback */ }
    
    $result = [];
    $cacheStmt = null;
    
    foreach ($stocks as $row) {
        $symbol = $row['symbol'];
        $stockId = (int)$row['id'];
        
        if (isset($liveData[$symbol])) {
            $live = $liveData[$symbol];
            $ltp = round((float)$live['ltp'], 2);
            $prevClose = round((float)($live['close'] ?? $row['previous_close']), 2);
            $open = round((float)($live['open'] ?? $row['open_price']), 2);
            $high = round((float)($live['high'] ?? $row['high_price']), 2);
            $low = round((float)($live['low'] ?? $row['low_price']), 2);
            $volume = (int)($live['volume'] ?? $row['volume']);
            $changePct = $prevClose > 0 ? round((($ltp - $prevClose) / $prevClose) * 100, 2) : (float)$row['change_percent'];
            $change = round($ltp - $prevClose, 2);
            $isLive = true;
            
            // Update cache
            try {
                if (!$cacheStmt) {
                    $cacheStmt = $db->prepare("
                        INSERT INTO stock_price_cache (stock_id, ltp, open_price, high_price, low_price, close_price, volume, change_percent, source, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'angel_one', NOW())
                        ON DUPLICATE KEY UPDATE
                            ltp=VALUES(ltp), open_price=VALUES(open_price), high_price=VALUES(high_price),
                            low_price=VALUES(low_price), close_price=VALUES(close_price),
                            volume=VALUES(volume), change_percent=VALUES(change_percent),
                            source='angel_one', updated_at=NOW()
                    ");
                }
                $cacheStmt->execute([$stockId, $ltp, $open, $high, $low, $prevClose, $volume, $changePct]);
            } catch (Exception $e) { /* silent */ }
        } else {
            $ltp = round((float)$row['ltp'], 2);
            $prevClose = round((float)$row['previous_close'], 2);
            $open = round((float)$row['open_price'], 2);
            $high = round((float)$row['high_price'], 2);
            $low = round((float)$row['low_price'], 2);
            $volume = (int)$row['volume'];
            $changePct = round((float)$row['change_percent'], 2);
            $change = $prevClose > 0 ? round($ltp - $prevClose, 2) : 0;
            $isLive = false;
        }
        
        $result[] = [
            'id'             => $stockId,
            'symbol'         => $symbol,
            'name'           => $row['name'],
            'exchange'       => $row['exchange'],
            'sector'         => $row['sector'],
            'price'          => $ltp,
            'change_percent' => $changePct,
            'change'         => $change,
            'volume'         => $volume,
            'high'           => $high,
            'low'            => $low,
            'open'           => $open,
            'previous_close' => $prevClose,
            'is_live'        => $isLive,
        ];
    }
    
    echo json_encode([
        'success' => true,
        'source'  => $source,
        'count'   => count($result),
        'stocks'  => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to load stocks: ' . $e->getMessage()
    ]);
}

// Fetch live quotes using hardcoded tokens
function fetchAngelOneLiveQuotes($settings, $tokenMap) {
    $jwtToken = angelOneLogin($settings);
    if (!$jwtToken) return [];
    
    $exchangeTokens = ['NSE' => [], 'BSE' => []];
    $symbolByToken = [];
    
    foreach ($tokenMap as $symbol => $info) {
        $exchangeTokens[$info['exchange']][] = $info['token'];
        $symbolByToken[$info['token']] = $symbol;
    }
    
    $quotes = [];
    $url = 'https://apiconnect.angelone.in/rest/secure/angelbroking/market/v1/quote/';
    
    foreach ($exchangeTokens as $exchange => $tokens) {
        if (empty($tokens)) continue;
        
        $payload = json_encode([
            'mode' => 'FULL',
            'exchangeTokens' => [$exchange => $tokens]
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if ($data && !empty($data['data']['fetched'])) {
            foreach ($data['data']['fetched'] as $quote) {
                $token = $quote['symbolToken'] ?? $quote['token'] ?? '';
                $symbol = $symbolByToken[$token] ?? '';
                if ($symbol && isset($quote['ltp'])) {
                    $quotes[$symbol] = [
                        'ltp' => (float)$quote['ltp'],
                        'open' => (float)($quote['open'] ?? 0),
                        'high' => (float)($quote['high'] ?? 0),
                        'low' => (float)($quote['low'] ?? 0),
                        'close' => (float)($quote['close'] ?? 0),
                        'volume' => (int)($quote['tradeVolume'] ?? 0),
                    ];
                }
            }
        }
    }
    
    return $quotes;
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

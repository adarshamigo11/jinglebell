<?php
/**
 * Get Stock Prices - Angel One (live) + Database (fallback)
 * Uses scrip search to get tokens, then fetches quotes
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

// Accept symbols via GET (comma-separated) or refresh all active stocks
$symbols = array_filter(array_map('trim', explode(',', $_GET['symbols'] ?? '')));
$refreshAll = isset($_GET['refresh_all']) && $_GET['refresh_all'] === '1';

$db = getDB();

if ($refreshAll) {
    // Fetch all active stocks (excluding indices - they have their own endpoint)
    $stmt = $db->query("
        SELECT s.id, s.symbol, s.name, s.exchange, s.sector,
               COALESCE(c.ltp, s.ltp) AS ltp,
               COALESCE(c.open_price, s.open_price) AS open_price,
               COALESCE(c.high_price, s.high_price) AS high_price,
               COALESCE(c.low_price, s.low_price) AS low_price,
               COALESCE(c.close_price, s.previous_close) AS previous_close,
               COALESCE(c.change_percent, s.change_percent) AS change_percent,
               COALESCE(c.volume, s.volume) AS volume
        FROM stocks s
        LEFT JOIN stock_price_cache c ON c.stock_id = s.id
        WHERE s.is_active = 1 AND s.sector NOT IN ('Index')
        ORDER BY s.symbol ASC
        LIMIT 100
    ");
    $prices = $stmt->fetchAll();
} else {
    if (empty($symbols)) jsonResponse(false, 'No symbols provided. Use ?symbols=RELIANCE.NS,TCS.NS or ?refresh_all=1');
    $symbols = array_slice($symbols, 0, 50);
    $in = implode(',', array_fill(0, count($symbols), '?'));
    $stmt = $db->prepare("
        SELECT s.id, s.symbol, s.name, s.exchange, s.sector,
               COALESCE(c.ltp, s.ltp) AS ltp,
               COALESCE(c.open_price, s.open_price) AS open_price,
               COALESCE(c.high_price, s.high_price) AS high_price,
               COALESCE(c.low_price, s.low_price) AS low_price,
               COALESCE(c.close_price, s.previous_close) AS previous_close,
               COALESCE(c.change_percent, s.change_percent) AS change_percent,
               COALESCE(c.volume, s.volume) AS volume
        FROM stocks s
        LEFT JOIN stock_price_cache c ON c.stock_id = s.id
        WHERE s.symbol IN ($in) AND s.is_active = 1
    ");
    $stmt->execute($symbols);
    $prices = $stmt->fetchAll();
}

if (empty($prices)) {
    echo json_encode(['success' => true, 'prices' => [], 'source' => 'empty']);
    exit;
}

// Build lookup
$stockMap = [];
foreach ($prices as $p) { $stockMap[$p['symbol']] = (int)$p['id']; }

// ── Try Angel One for live data ──
$liveData = [];
$source = 'database';
try {
    $prefStmt = $db->prepare("SELECT provider, is_enabled FROM data_provider_preferences WHERE asset_type = 'stocks'");
    $prefStmt->execute();
    $pref = $prefStmt->fetch();
    
    if ($pref && $pref['provider'] === 'angel_one' && $pref['is_enabled']) {
        $settingsStmt = $db->query("SELECT * FROM api_settings WHERE provider = 'angel_one' AND is_active = 1");
        $settings = $settingsStmt->fetch();
        
        if ($settings && !empty($settings['api_key']) && !empty($settings['client_id']) && !empty($settings['password'])) {
            $liveData = fetchAngelOneLiveQuotes($settings, array_keys($stockMap));
            if (!empty($liveData)) $source = 'angel_one';
        }
    }
} catch (Exception $e) { /* silent fallback */ }

// ── Build response + update cache ──
$result = [];
$cacheStmt = null;
foreach ($prices as $p) {
    $symbol = $p['symbol'];
    $stockId = (int)$stockMap[$symbol];
    
    if (isset($liveData[$symbol])) {
        $live = $liveData[$symbol];
        $ltp    = (float)$live['ltp'];
        $prev   = (float)($live['close'] ?? $p['previous_close']);
        $open   = (float)($live['open'] ?? $p['open_price']);
        $high   = (float)($live['high'] ?? $p['high_price']);
        $low    = (float)($live['low'] ?? $p['low_price']);
        $volume = (int)($live['volume'] ?? $p['volume']);
        $change = $prev > 0 ? round((($ltp - $prev) / $prev) * 100, 2) : (float)$p['change_percent'];
        
        // Update cache
        try {
            if (!$cacheStmt) {
                $cacheStmt = $db->prepare("
                    INSERT INTO stock_price_cache (stock_id, ltp, open_price, high_price, low_price, close_price, volume, change_percent, is_live, source, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'angel_one', NOW())
                    ON DUPLICATE KEY UPDATE
                        ltp=VALUES(ltp), open_price=VALUES(open_price), high_price=VALUES(high_price),
                        low_price=VALUES(low_price), close_price=VALUES(close_price),
                        volume=VALUES(volume), change_percent=VALUES(change_percent),
                        is_live=1, source='angel_one', updated_at=NOW()
                ");
            }
            $cacheStmt->execute([$stockId, $ltp, $open, $high, $low, $prev, $volume, $change]);
        } catch (Exception $e) { /* silent */ }
    } else {
        $ltp    = (float)$p['ltp'];
        $prev   = (float)$p['previous_close'];
        $open   = (float)$p['open_price'];
        $high   = (float)$p['high_price'];
        $low    = (float)$p['low_price'];
        $volume = (int)$p['volume'];
        $change = $prev > 0 ? round((($ltp - $prev) / $prev) * 100, 2) : (float)$p['change_percent'];
    }
    
    $result[$symbol] = [
        'id' => $stockId, 'symbol' => $symbol, 'name' => $p['name'],
        'exchange' => $p['exchange'], 'sector' => $p['sector'],
        'ltp' => $ltp, 'open' => $open, 'high' => $high, 'low' => $low,
        'prev_close' => $prev, 'change_percent' => $change,
        'change_value' => round($ltp - $prev, 2), 'volume' => $volume,
        'is_live' => isset($liveData[$symbol]),
    ];
}

echo json_encode(['success' => true, 'source' => $source, 'count' => count($result), 'prices' => $result]);
exit;

// ══════════════════════════════════════════════════════════════
// Angel One: Scrip Search → Get Tokens → Fetch Quotes
// ══════════════════════════════════════════════════════════════

function fetchAngelOneLiveQuotes($settings, $yahooSymbols) {
    $jwtToken = angelOneLogin($settings);
    if (!$jwtToken) return [];
    
    // Step 1: Search for tokens
    $tokenMap = [];  // numericToken => yahooSymbol
    $exchangeTokens = ['NSE' => [], 'BSE' => []];
    
    foreach ($yahooSymbols as $symbol) {
        $clean = preg_replace('/\.(NS|BO|MF)$/i', '', $symbol);
        $exchange = preg_match('/\.BO$/i', $symbol) ? 'BSE' : 'NSE';
        
        // Try scrip search
        $token = scripSearch($settings, $jwtToken, $clean, $exchange);
        if ($token) {
            $tokenMap[$token] = ['yahoo' => $symbol, 'exchange' => $exchange];
            $exchangeTokens[$exchange][] = $token;
        }
    }
    
    if (empty($tokenMap)) return [];
    
    // Step 2: Fetch quotes using numeric tokens
    $url = 'https://apiconnect.angelone.in/rest/secure/angelbroking/market/v1/quote/';
    $quotes = [];
    
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
                $token = $quote['token'] ?? '';
                $info = $tokenMap[$token] ?? null;
                if ($info && isset($quote['ltp'])) {
                    $quotes[$info['yahoo']] = [
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

function scripSearch($settings, $jwtToken, $symbol, $exchange) {
    $payload = json_encode(['searchsymbol' => $symbol, 'exchange' => $exchange]);
    
    $ch = curl_init('https://apiconnect.angelone.in/rest/secure/angelbroking/search/scrip/');
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($data && !empty($data['data'])) {
        // Find exact match
        foreach ($data['data'] as $result) {
            if (strcasecmp($result['symbol'] ?? '', $symbol . '-' . $exchange) === 0 || 
                strcasecmp($result['tradingsymbol'] ?? '', $symbol) === 0) {
                return $result['token'] ?? '';
            }
        }
        // Fallback to first result
        return $data['data'][0]['token'] ?? '';
    }
    return '';
}

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

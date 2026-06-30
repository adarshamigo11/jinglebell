<?php
/**
 * Top Movers API - Refreshes prices from Angel One, then returns sorted movers
 * Fixed: correct API URL, correct login payload, correct column names
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

$category = $_GET['category'] ?? 'gainers';
$validCategories = ['gainers', 'losers', 'most-active'];
if (!in_array($category, $validCategories)) {
    echo json_encode(['success' => false, 'error' => 'Invalid category']);
    exit;
}

$db = getDB();

// Step 1: Trigger price refresh from Angel One
triggerPriceRefresh($db);

// Step 2: Fetch sorted movers from database
if ($category === 'gainers') {
    $stmt = $db->prepare("
        SELECT s.id, s.symbol, s.name, s.previous_close,
               COALESCE(c.ltp, s.ltp) as live_ltp,
               COALESCE(c.change_percent, s.change_percent) as live_chg,
               COALESCE(c.volume, 0) as live_vol,
               COALESCE(c.open_price, 0) as live_open,
               COALESCE(c.high_price, 0) as live_high,
               COALESCE(c.low_price, 0) as live_low
        FROM stocks s
        LEFT JOIN stock_price_cache c ON c.stock_id = s.id
        WHERE s.is_active = 1 
          AND s.sector NOT IN ('Commodity', 'Index', 'Cryptocurrency')
          AND (COALESCE(c.change_percent, s.change_percent) > 0)
        ORDER BY live_chg DESC
        LIMIT 10
    ");
} elseif ($category === 'losers') {
    $stmt = $db->prepare("
        SELECT s.id, s.symbol, s.name, s.previous_close,
               COALESCE(c.ltp, s.ltp) as live_ltp,
               COALESCE(c.change_percent, s.change_percent) as live_chg,
               COALESCE(c.volume, 0) as live_vol,
               COALESCE(c.open_price, 0) as live_open,
               COALESCE(c.high_price, 0) as live_high,
               COALESCE(c.low_price, 0) as live_low
        FROM stocks s
        LEFT JOIN stock_price_cache c ON c.stock_id = s.id
        WHERE s.is_active = 1 
          AND s.sector NOT IN ('Commodity', 'Index', 'Cryptocurrency')
          AND (COALESCE(c.change_percent, s.change_percent) < 0)
        ORDER BY live_chg ASC
        LIMIT 10
    ");
} else {
    $stmt = $db->prepare("
        SELECT s.id, s.symbol, s.name, s.previous_close,
               COALESCE(c.ltp, s.ltp) as live_ltp,
               COALESCE(c.change_percent, s.change_percent) as live_chg,
               COALESCE(c.volume, 0) as live_vol,
               COALESCE(c.open_price, 0) as live_open,
               COALESCE(c.high_price, 0) as live_high,
               COALESCE(c.low_price, 0) as live_low
        FROM stocks s
        LEFT JOIN stock_price_cache c ON c.stock_id = s.id
        WHERE s.is_active = 1 
          AND s.sector NOT IN ('Commodity', 'Index', 'Cryptocurrency')
        ORDER BY live_vol DESC
        LIMIT 10
    ");
}

$stmt->execute();
$stocks = $stmt->fetchAll();

if (empty($stocks)) {
    echo json_encode(['success' => false, 'error' => 'No stocks in database. Add stocks via Admin panel.']);
    exit;
}

$movers = [];
foreach ($stocks as $stock) {
    $price = (float)($stock['live_ltp'] ?? $stock['ltp']);
    $changePercent = (float)($stock['live_chg'] ?? $stock['change_percent']);
    $prevClose = (float)($stock['previous_close'] ?? 0);
    
    $movers[] = [
        'id' => $stock['id'],
        'symbol' => $stock['symbol'],
        'name' => $stock['name'],
        'price' => $price,
        'change' => round($price - $prevClose, 2),
        'changePercent' => $changePercent,
        'volume' => (int)($stock['live_vol'] ?? 0)
    ];
}

echo json_encode([
    'success' => true,
    'category' => $category,
    'count' => count($movers),
    'movers' => $movers
]);

// =====================================================
// Trigger a price refresh from Angel One
// =====================================================

function triggerPriceRefresh($db) {
    try {
        // Check if we refreshed recently (within 60 seconds)
        $stmt = $db->query("SELECT MAX(updated_at) as last_update FROM stock_price_cache WHERE is_live = 1");
        $row = $stmt->fetch();
        if ($row && $row['last_update']) {
            $lastUpdate = strtotime($row['last_update']);
            if (time() - $lastUpdate < 60) return;
        }
    } catch (Exception $e) { /* proceed */ }
    
    try {
        // Check if Angel One is active
        $prefStmt = $db->prepare("SELECT provider, is_enabled FROM data_provider_preferences WHERE asset_type = 'stocks'");
        $prefStmt->execute();
        $pref = $prefStmt->fetch();
        
        if (!$pref || $pref['provider'] !== 'angel_one' || !$pref['is_enabled']) return;
        
        $settingsStmt = $db->query("SELECT * FROM api_settings WHERE provider = 'angel_one' AND is_active = 1");
        $settings = $settingsStmt->fetch();
        
        if (!$settings || empty($settings['api_key']) || empty($settings['client_id']) || empty($settings['password'])) return;
        
        // Get top 20 stocks to refresh
        $stmt = $db->query("
            SELECT id, symbol FROM stocks 
            WHERE is_active = 1 AND sector NOT IN ('Index', 'Cryptocurrency', 'Commodity')
            LIMIT 20
        ");
        $stocks = $stmt->fetchAll();
        if (empty($stocks)) return;
        
        $symbols = array_column($stocks, 'symbol');
        $stockIds = array_column($stocks, 'id');
        $symbolToId = array_combine($symbols, $stockIds);
        
        // Login to Angel One
        $jwtToken = angelOneLogin($settings);
        if (!$jwtToken) return;
        
        // Search tokens for each symbol
        $tokenMap = [];
        foreach ($symbols as $symbol) {
            $clean = preg_replace('/\.(NS|BO|MF)$/i', '', $symbol);
            $exchange = preg_match('/\.BO$/i', $symbol) ? 'BSE' : 'NSE';
            $token = scripSearch($settings, $jwtToken, $clean, $exchange);
            if ($token) {
                $tokenMap[$token] = ['symbol' => $symbol, 'exchange' => $exchange];
            }
        }
        
        if (empty($tokenMap)) return;
        
        // Fetch quotes
        $exchangeTokens = ['NSE' => [], 'BSE' => []];
        foreach ($tokenMap as $token => $info) {
            $exchangeTokens[$info['exchange']][] = $token;
        }
        
        foreach ($exchangeTokens as $exchange => $tokens) {
            if (empty($tokens)) continue;
            
            $payload = json_encode(['mode' => 'FULL', 'exchangeTokens' => [$exchange => $tokens]]);
            $ch = curl_init('https://apiconnect.angelone.in/rest/secure/angelbroking/market/v1/quote/');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-UserType: USER',
                'X-SourceID: WEB',
                'X-ClientLocalIP: 127.0.0.1',
                'X-ClientPublicIP: 127.0.0.1',
                'X-MACAddress: 00:00:00:00:00:00',
                'X-PrivateKey: ' . $settings['api_key'],
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
                    if (!$info) continue;
                    
                    $ltp = (float)($quote['ltp'] ?? 0);
                    $prev = (float)($quote['close'] ?? 0);
                    $change = $prev > 0 ? round((($ltp - $prev) / $prev) * 100, 2) : 0;
                    
                    try {
                        $db->prepare("
                            INSERT INTO stock_price_cache (stock_id, ltp, open_price, high_price, low_price, close_price, volume, change_percent, is_live, source, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'angel_one', NOW())
                            ON DUPLICATE KEY UPDATE
                                ltp=VALUES(ltp), open_price=VALUES(open_price), high_price=VALUES(high_price),
                                low_price=VALUES(low_price), close_price=VALUES(close_price),
                                volume=VALUES(volume), change_percent=VALUES(change_percent),
                                is_live=1, source='angel_one', updated_at=NOW()
                        ")->execute([
                            $symbolToId[$info['symbol']],
                            $ltp,
                            (float)($quote['open'] ?? 0),
                            (float)($quote['high'] ?? 0),
                            (float)($quote['low'] ?? 0),
                            $prev,
                            (int)($quote['tradeVolume'] ?? 0),
                            $change
                        ]);
                    } catch (Exception $e) { /* silent */ }
                }
            }
        }
    } catch (Exception $e) { /* silent */ }
}

// =====================================================
// Angel One helper functions
// =====================================================

function scripSearch($settings, $jwtToken, $symbol, $exchange) {
    $payload = json_encode(['searchsymbol' => $symbol, 'exchange' => $exchange]);
    $ch = curl_init('https://apiconnect.angelone.in/rest/secure/angelbroking/search/scrip/');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-UserType: USER',
        'X-SourceID: WEB',
        'X-ClientLocalIP: 127.0.0.1',
        'X-ClientPublicIP: 127.0.0.1',
        'X-MACAddress: 00:00:00:00:00:00',
        'X-PrivateKey: ' . $settings['api_key'],
        'Authorization: Bearer ' . $jwtToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    if ($data && !empty($data['data'])) {
        foreach ($data['data'] as $r) {
            if (strcasecmp($r['tradingsymbol'] ?? '', $symbol) === 0) return $r['token'] ?? '';
        }
        return $data['data'][0]['token'] ?? '';
    }
    return '';
}

function angelOneLogin($settings) {
    $totp = '';
    if (!empty($settings['totp_secret'])) {
        $secret = strtoupper(str_replace(' ', '', $settings['totp_secret']));
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(rtrim($secret, '='));
        $buffer = 0; $bitsLeft = 0; $decoded = '';
        for ($i = 0; $i < strlen($input); $i++) {
            $val = strpos($alphabet, $input[$i]);
            if ($val === false) return null;
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) { $bitsLeft -= 8; $decoded .= chr(($buffer >> $bitsLeft) & 0xFF); }
        }
        $timeStep = floor(time() / 30);
        $timeBytes = pack('N*', 0, $timeStep);
        $hmac = hash_hmac('sha1', $timeBytes, $decoded, true);
        $offset = ord($hmac[19]) & 0x0F;
        $code = (((ord($hmac[$offset]) & 0x7F) << 24) | ((ord($hmac[$offset+1]) & 0xFF) << 16) | ((ord($hmac[$offset+2]) & 0xFF) << 8) | (ord($hmac[$offset+3]) & 0xFF)) % 1000000;
        $totp = str_pad($code, 6, '0', STR_PAD_LEFT);
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
        'Content-Type: application/json',
        'Accept: application/json',
        'X-UserType: USER',
        'X-SourceID: WEB',
        'X-ClientLocalIP: 127.0.0.1',
        'X-ClientPublicIP: 127.0.0.1',
        'X-MACAddress: 00:00:00:00:00:00',
        'X-PrivateKey: ' . $settings['api_key']
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return ($data && !empty($data['data']['jwtToken'])) ? $data['data']['jwtToken'] : null;
}

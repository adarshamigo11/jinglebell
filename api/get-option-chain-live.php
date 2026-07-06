<?php
/**
 * Get Option Chain - Returns option chain from stored contracts with live Angel One prices
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

$symbol = $_GET['symbol'] ?? '';
$expiry = $_GET['expiry'] ?? '';

if (!$symbol) {
    echo json_encode(['success' => false, 'error' => 'Symbol required']);
    exit;
}

try {
    $db = getDB();
    
    // Get distinct expiry dates for this symbol
    $expStmt = $db->prepare("SELECT DISTINCT expiry_date FROM fno_contracts WHERE symbol = ? AND contract_type IN ('CALL', 'PUT') ORDER BY expiry_date");
    $expStmt->execute([$symbol]);
    $expiryDates = $expStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($expiryDates)) {
        echo json_encode(['success' => false, 'error' => 'No option contracts found. Run discover-fno-tokens.php first.']);
        exit;
    }
    
    if (!$expiry) $expiry = $expiryDates[0];
    
    // Get options for this symbol and expiry
    $stmt = $db->prepare("
        SELECT id, symbol, contract_type, strike_price, expiry_date, lot_size, token, exchange,
               current_price, previous_close, change_percent, high_price, low_price, volume, open_interest
        FROM fno_contracts
        WHERE symbol = ? AND expiry_date = ? AND contract_type IN ('CALL', 'PUT') AND token IS NOT NULL
        ORDER BY strike_price, contract_type DESC
    ");
    $stmt->execute([$symbol, $expiry]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($options)) {
        echo json_encode(['success' => false, 'error' => 'No options for selected expiry']);
        exit;
    }
    
    // Try to fetch live prices from Angel One
    $source = 'database';
    $liveData = [];
    try {
        $settingsStmt = $db->query("SELECT * FROM api_settings WHERE provider = 'angel_one' AND is_active = 1 LIMIT 1");
        $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings && !empty($settings['api_key']) && !empty($settings['client_id']) && !empty($settings['password'])) {
            $liveData = fetchAngelOneOptionQuotes($settings, $options);
            if (!empty($liveData)) $source = 'angel_one';
        }
    } catch (Exception $e) { /* silent fallback */ }
    
    // Group by strike
    $strikes = [];
    $spotPrice = 0;
    
    foreach ($options as $opt) {
        $id = (int)$opt['id'];
        $type = $opt['contract_type']; // CALL or PUT
        $strike = (float)$opt['strike_price'];
        
        if (isset($liveData[$id])) {
            $live = $liveData[$id];
            $ltp = round((float)$live['ltp'], 2);
            $change = round((float)($live['change'] ?? 0), 2);
            $changePct = round((float)($live['change_percent'] ?? 0), 2);
            $volume = (int)($live['volume'] ?? 0);
            $oi = (int)($live['oi'] ?? 0);
            $oiChange = (int)($live['oi_change'] ?? 0);
            $iv = round((float)($live['iv'] ?? 0), 2);
            $bid = round((float)($live['bid'] ?? 0), 2);
            $bidQty = (int)($live['bid_qty'] ?? 0);
            $ask = round((float)($live['ask'] ?? 0), 2);
            $askQty = (int)($live['ask_qty'] ?? 0);
            $isLive = true;
            
            // Update cache
            try {
                $upd = $db->prepare("
                    UPDATE fno_contracts SET
                        current_price = ?, change_percent = ?, volume = ?, open_interest = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $upd->execute([$ltp, $changePct, $volume, $oi, $id]);
            } catch (Exception $e) { /* silent */ }
        } else {
            $ltp = round((float)$opt['current_price'], 2);
            $change = 0;
            $changePct = round((float)$opt['change_percent'], 2);
            $volume = (int)$opt['volume'];
            $oi = (int)$opt['open_interest'];
            $oiChange = 0;
            $iv = 0;
            $bid = 0;
            $bidQty = 0;
            $ask = 0;
            $askQty = 0;
            $isLive = false;
        }
        
        $strikeKey = (string)$strike;
        
        if (!isset($strikes[$strikeKey])) {
            $strikes[$strikeKey] = ['strike' => $strike, 'CALL' => null, 'PUT' => null];
        }
        
        $strikes[$strikeKey][$type] = [
            'ltp' => $ltp,
            'change' => $change,
            'change_percent' => $changePct,
            'volume' => $volume,
            'oi' => $oi,
            'oi_change' => $oiChange,
            'iv' => $iv,
            'bid' => $bid,
            'bid_qty' => $bidQty,
            'ask' => $ask,
            'ask_qty' => $askQty,
            'is_live' => $isLive
        ];
        
        // Estimate spot price as median of ATM strikes
        if ($spotPrice === 0 && $ltp > 0) {
            $spotPrice = $strike;
        }
    }
    
    ksort($strikes);
    
    echo json_encode([
        'success' => true,
        'symbol' => $symbol,
        'expiry' => $expiry,
        'expiry_dates' => $expiryDates,
        'spot_price' => $spotPrice,
        'source' => $source,
        'option_chain' => array_values($strikes)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function fetchAngelOneOptionQuotes($settings, $options) {
    $jwtToken = angelOneLogin($settings);
    if (!$jwtToken) return [];
    
    $exchangeTokens = [];
    $optionByToken = [];
    
    foreach ($options as $opt) {
        $exch = $opt['exchange'] ?: 'NFO';
        $token = $opt['token'];
        if (!$token) continue;
        $exchangeTokens[$exch][] = $token;
        $optionByToken[$token] = $opt['id'];
    }
    
    $quotes = [];
    $url = 'https://apiconnect.angelone.in/rest/secure/angelbroking/market/v1/quote/';
    
    foreach ($exchangeTokens as $exchange => $tokens) {
        if (empty($tokens)) continue;
        
        $chunks = array_chunk($tokens, 50);
        foreach ($chunks as $chunk) {
            $payload = json_encode([
                'mode' => 'FULL',
                'exchangeTokens' => [$exchange => $chunk]
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
            curl_close($ch);
            
            $data = json_decode($response, true);
            if ($data && !empty($data['data']['fetched'])) {
                foreach ($data['data']['fetched'] as $quote) {
                    $token = $quote['symbolToken'] ?? $quote['token'] ?? '';
                    $optionId = $optionByToken[$token] ?? 0;
                    if ($optionId && isset($quote['ltp'])) {
                        $quotes[$optionId] = [
                            'ltp' => (float)$quote['ltp'],
                            'change' => (float)($quote['netChange'] ?? 0),
                            'change_percent' => (float)($quote['percentChange'] ?? 0),
                            'volume' => (int)($quote['tradeVolume'] ?? 0),
                            'oi' => (int)($quote['openInterest'] ?? 0),
                            'oi_change' => (int)($quote['oiChange'] ?? 0),
                            'iv' => (float)($quote['impliedVolatility'] ?? 0),
                            'bid' => (float)($quote['bidprice'] ?? 0),
                            'bid_qty' => (int)($quote['bidQty'] ?? 0),
                            'ask' => (float)($quote['askprice'] ?? 0),
                            'ask_qty' => (int)($quote['askQty'] ?? 0),
                        ];
                    }
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

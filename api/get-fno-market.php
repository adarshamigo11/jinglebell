<?php
/**
 * Get F&O Market - Returns futures with live Angel One prices
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $db = getDB();
    
    // Get active futures contracts with tokens
    $stmt = $db->query("
        SELECT id, symbol, stock_name, contract_type, strike_price, expiry_date, lot_size, token, exchange,
               current_price, previous_close, change_percent, high_price, low_price, volume
        FROM fno_contracts
        WHERE is_active = 1 AND contract_type = 'FUTURES' AND token IS NOT NULL
        ORDER BY symbol
    ");
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($contracts)) {
        echo json_encode(['success' => true, 'source' => 'database', 'count' => 0, 'contracts' => []]);
        exit;
    }
    
    // Try Angel One live data
    $source = 'database';
    $liveData = [];
    try {
        $settingsStmt = $db->query("SELECT * FROM api_settings WHERE provider = 'angel_one' AND is_active = 1 LIMIT 1");
        $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings && !empty($settings['api_key']) && !empty($settings['client_id']) && !empty($settings['password'])) {
            $liveData = fetchAngelOneFnoQuotes($settings, $contracts);
            if (!empty($liveData)) $source = 'angel_one';
        }
    } catch (Exception $e) { /* silent fallback */ }
    
    $result = [];
    $cacheStmt = null;
    
    foreach ($contracts as $contract) {
        $id = (int)$contract['id'];
        $symbol = $contract['symbol'];
        
        if (isset($liveData[$id])) {
            $live = $liveData[$id];
            $ltp = round((float)$live['ltp'], 2);
            $prevClose = round((float)($live['close'] ?? $contract['previous_close']), 2);
            $open = round((float)($live['open'] ?? 0), 2);
            $high = round((float)($live['high'] ?? 0), 2);
            $low = round((float)($live['low'] ?? 0), 2);
            $volume = (int)($live['volume'] ?? 0);
            $changePct = $prevClose > 0 ? round((($ltp - $prevClose) / $prevClose) * 100, 2) : (float)$contract['change_percent'];
            
            try {
                if (!$cacheStmt) {
                    $cacheStmt = $db->prepare("
                        UPDATE fno_contracts SET
                            current_price = ?, previous_close = ?, open_price = ?, high_price = ?, low_price = ?,
                            volume = ?, change_percent = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                }
                $cacheStmt->execute([$ltp, $prevClose, $open, $high, $low, $volume, $changePct, $id]);
            } catch (Exception $e) { /* silent */ }
        } else {
            $ltp = round((float)$contract['current_price'], 2);
            $prevClose = round((float)$contract['previous_close'], 2);
            $high = round((float)$contract['high_price'], 2);
            $low = round((float)$contract['low_price'], 2);
            $volume = (int)$contract['volume'];
            $changePct = round((float)$contract['change_percent'], 2);
            $open = 0;
        }
        
        $result[] = [
            'id'             => $id,
            'symbol'         => $symbol,
            'stock_name'     => $contract['stock_name'],
            'contract_type'  => $contract['contract_type'],
            'strike_price'   => (float)$contract['strike_price'],
            'expiry_date'    => $contract['expiry_date'],
            'lot_size'       => (int)$contract['lot_size'],
            'price'          => $ltp,
            'change_percent' => $changePct,
            'change'         => round($ltp - $prevClose, 2),
            'high'           => $high,
            'low'            => $low,
            'open'           => $open,
            'volume'         => $volume,
            'is_live'        => isset($liveData[$id])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'source'  => $source,
        'count'   => count($result),
        'contracts' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function fetchAngelOneFnoQuotes($settings, $contracts) {
    $jwtToken = angelOneLogin($settings);
    if (!$jwtToken) return [];
    
    $exchangeTokens = [];
    $contractByToken = [];
    
    foreach ($contracts as $contract) {
        $exch = $contract['exchange'] ?: 'NFO';
        $token = $contract['token'];
        if (!$token) continue;
        $exchangeTokens[$exch][] = $token;
        $contractByToken[$token] = $contract['id'];
    }
    
    $quotes = [];
    $url = 'https://apiconnect.angelone.in/rest/secure/angelbroking/market/v1/quote/';
    
    foreach ($exchangeTokens as $exchange => $tokens) {
        if (empty($tokens)) continue;
        
        // Angel One accepts max ~50 tokens per request
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
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            if ($data && !empty($data['data']['fetched'])) {
                foreach ($data['data']['fetched'] as $quote) {
                    $token = $quote['symbolToken'] ?? $quote['token'] ?? '';
                    $contractId = $contractByToken[$token] ?? 0;
                    if ($contractId && isset($quote['ltp'])) {
                        $quotes[$contractId] = [
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

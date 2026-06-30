<?php
/**
 * Get Indian Market Indexes - Angel One (primary) + Database (fallback)
 * Uses exchangeNames/symbols format for index quotes
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

try {
    $db = getDB();

    // Check provider preference
    $pref = $db->prepare("SELECT provider FROM data_provider_preferences WHERE asset_type = 'indices' AND is_enabled = 1 ORDER BY priority ASC LIMIT 1");
    $pref->execute();
    $provider = $pref->fetchColumn() ?: 'angel_one';

    $indexes = [];
    $actualSource = 'none';

    if ($provider === 'angel_one') {
        $indexes = fetchAngelOneIndexes($db);
        if (!empty($indexes)) $actualSource = 'angel_one';
    }

    // Fallback to database
    if (empty($indexes)) {
        $indexes = fetchFromDatabase($db);
        if (!empty($indexes)) $actualSource = 'database';
    }

    if (empty($indexes)) {
        echo json_encode(['success' => true, 'indexes' => [], 'source' => 'empty', 'preferred' => $provider]);
        exit;
    }

    // Update stocks table with live data
    if ($actualSource === 'angel_one') {
        updateIndexStocks($db, $indexes);
    }

    echo json_encode(['success' => true, 'indexes' => $indexes, 'source' => $actualSource, 'preferred' => $provider, 'timestamp' => date('Y-m-d H:i:s')]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// =====================================================
// ANGEL ONE INDEX FETCHER
// =====================================================

function fetchAngelOneIndexes($db) {
    $stmt = $db->query("SELECT api_key, client_id, password, totp_secret, is_active FROM api_settings WHERE provider = 'angel_one' LIMIT 1");
    $creds = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$creds || !$creds['is_active'] || empty($creds['api_key']) || empty($creds['client_id'])) {
        return [];
    }

    $jwt = angelOneLogin($creds['api_key'], $creds['client_id'], $creds['password'], $creds['totp_secret']);
    if (!$jwt) {
        return [];
    }

    // Hardcoded token map (scrip search is blocked on InfinityFree)
    // Tokens discovered via discover-tokens.php on 2026-06-25
    $tokenMap = [
        '99926000' => ['display' => 'Nifty 50',        'exchange' => 'NSE'],
        '99926009' => ['display' => 'Bank Nifty',      'exchange' => 'NSE'],
        '99926008' => ['display' => 'Nifty IT',        'exchange' => 'NSE'],
        '99926037' => ['display' => 'Fin Nifty',       'exchange' => 'NSE'],
        '99926011' => ['display' => 'Midcap 100',      'exchange' => 'NSE'],
        '99926030' => ['display' => 'Nifty Metal',     'exchange' => 'NSE'],
        '99926020' => ['display' => 'Nifty Energy',    'exchange' => 'NSE'],
        '99926025' => ['display' => 'Nifty PSU Bank',  'exchange' => 'NSE'],
        '99926017' => ['display' => 'India VIX',       'exchange' => 'NSE'],
        '99919000' => ['display' => 'Sensex',          'exchange' => 'BSE'],
    ];

    // Build exchangeTokens payload
    $exchangeTokens = ['NSE' => [], 'BSE' => []];
    foreach ($tokenMap as $token => $info) {
        $exchangeTokens[$info['exchange']][] = $token;
    }

    $quotePayload = ['mode' => 'FULL'];
    foreach ($exchangeTokens as $ex => $tokens) {
        if (!empty($tokens)) {
            $quotePayload['exchangeTokens'][$ex] = $tokens;
        }
    }

    $quoteData = angelOneQuote($quotePayload, $jwt, $creds['api_key']);
    if (empty($quoteData)) {
        return [];
    }

    // Match quotes back to indices by symbolToken
    $result = [];
    foreach ($quoteData as $item) {
        $token = $item['symbolToken'] ?? $item['token'] ?? '';
        if (!isset($tokenMap[$token])) continue;

        $idx = $tokenMap[$token];
        $ltp = (float)($item['lastTradedPrice'] ?? $item['ltp'] ?? 0);
        $prevClose = (float)($item['closePrice'] ?? $item['previousClose'] ?? $item['close'] ?? 0);

        if ($prevClose <= 0) {
            $prevClose = (float)($item['openPrice'] ?? $item['open'] ?? $ltp);
        }

        $changePct = ($prevClose > 0 && $ltp > 0) ? round((($ltp - $prevClose) / $prevClose) * 100, 2) : 0;

        $result[] = [
            'id'             => 0,
            'name'           => $idx['display'],
            'symbol'         => $idx['display'],
            'price'          => $ltp,
            'change_percent' => $changePct,
            'change'         => round($ltp - $prevClose, 2),
            'market_cap'     => 0,
            'high'           => (float)($item['dayHigh'] ?? $item['high'] ?? $ltp),
            'low'            => (float)($item['dayLow'] ?? $item['low'] ?? $ltp),
            'volume'         => (int)($item['volume'] ?? $item['tradeVolume'] ?? 0),
        ];
    }

    return $result;
}

// =====================================================
// DATABASE FALLBACK
// =====================================================

function fetchFromDatabase($db) {
    $stmt = $db->prepare("
        SELECT id, symbol, name, ltp, change_percent, previous_close,
               COALESCE(high_price, ltp) as high_price,
               COALESCE(low_price, ltp) as low_price,
               COALESCE(volume, 0) as volume
        FROM stocks WHERE sector = 'Index' AND is_active = 1
        ORDER BY FIELD(symbol, '^NSEI','^NSEBANK','^BSESN','^CNXIT','^CNXFIN','^NSEMDCP100','^NSESMLCP100','^CNXAUTO','^CNXPHARMA','^CNXMETAL')
        LIMIT 10
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $price = round((float)$row['ltp'], 2);
        $changePct = round((float)$row['change_percent'], 2);
        $prevClose = round((float)$row['previous_close'], 2);
        $result[] = [
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'symbol'         => $row['name'],
            'price'          => $price,
            'change_percent' => $changePct,
            'change'         => round($price - $prevClose, 2),
            'market_cap'     => 0,
            'high'           => round((float)$row['high_price'], 2),
            'low'            => round((float)$row['low_price'], 2),
            'volume'         => (int)$row['volume'],
        ];
    }
    return $result;
}

// =====================================================
// UPDATE STOCKS TABLE
// =====================================================

function updateIndexStocks($db, $indexes) {
    $symbolMap = [
        'Nifty 50'      => '^NSEI',
        'Bank Nifty'    => '^NSEBANK',
        'Nifty IT'      => '^CNXIT',
        'Fin Nifty'     => '^CNXFIN',
        'Midcap 100'    => '^NSEMDCP100',
        'Smallcap 100'  => '^NSESMLCP100',
        'Nifty Auto'    => '^CNXAUTO',
        'Nifty Pharma'  => '^CNXPHARMA',
        'Nifty Metal'   => '^CNXMETAL',
        'Sensex'        => '^BSESN',
    ];

    foreach ($indexes as $idx) {
        $symbol   = $symbolMap[$idx['name']] ?? $idx['symbol'];
        $exchange = ($symbol === '^BSESN') ? 'BSE' : 'NSE';

        try {
            $prevClose = $idx['price'] > 0 ? round($idx['price'] / (1 + $idx['change_percent'] / 100), 2) : 0;
            $stmt = $db->prepare("
                INSERT INTO stocks (symbol, name, exchange, sector, ltp, change_percent, previous_close, is_active, created_at, updated_at)
                VALUES (?, ?, ?, 'Index', ?, ?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name), exchange = VALUES(exchange),
                    ltp = VALUES(ltp), change_percent = VALUES(change_percent),
                    previous_close = VALUES(previous_close), updated_at = NOW()
            ");
            $stmt->execute([$symbol, $idx['name'], $exchange, $idx['price'], $idx['change_percent'], $prevClose]);
        } catch (Exception $e) {
            // Continue on error
        }
    }
}

// =====================================================
// ANGEL ONE API FUNCTIONS
// =====================================================

function angelOneLogin($apiKey, $clientId, $password, $totpSecret) {
    $totp = generateTOTP($totpSecret);

    $loginData = json_encode([
        'clientcode' => $clientId,
        'password'   => $password,
        'totp'       => $totp
    ]);

    $ch = curl_init('https://apiconnect.angelone.in/rest/auth/angelbroking/user/v1/loginByPassword');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $loginData,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-UserType: USER',
            'X-SourceID: WEB',
            'X-ClientLocalIP: 127.0.0.1',
            'X-ClientPublicIP: 127.0.0.1',
            'X-MACAddress: 00:00:00:00:00:00',
            'X-PrivateKey: ' . $apiKey
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return false;
    $data = json_decode($response, true);
    if (!$data || !$data['status'] || empty($data['data']['jwtToken'])) return false;

    return $data['data']['jwtToken'];
}

function angelScripSearch($apiKey, $jwt, $symbol, $exchange) {
    $payload = json_encode(['searchsymbol' => $symbol, 'exchange' => $exchange]);
    $ch = curl_init('https://apiconnect.angelone.in/rest/secure/angelbroking/search/scrip/');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-UserType: USER',
            'X-SourceID: WEB',
            'X-ClientLocalIP: 127.0.0.1',
            'X-ClientPublicIP: 127.0.0.1',
            'X-MACAddress: 00:00:00:00:00:00',
            'X-PrivateKey: ' . $apiKey,
            'Authorization: Bearer ' . $jwt
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 8
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) return '';
    $data = json_decode($response, true);
    if ($data && !empty($data['data'])) {
        // Try exact match first
        foreach ($data['data'] as $r) {
            if (strcasecmp($r['tradingsymbol'] ?? '', $symbol) === 0) return $r['token'] ?? '';
        }
        // Fallback to first result
        return $data['data'][0]['token'] ?? '';
    }
    return '';
}

function angelOneQuote($payload, $jwt, $apiKey) {
    $ch = curl_init('https://apiconnect.angelone.in/rest/secure/angelbroking/market/v1/quote/');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-UserType: USER',
            'X-SourceID: WEB',
            'X-ClientLocalIP: 127.0.0.1',
            'X-ClientPublicIP: 127.0.0.1',
            'X-MACAddress: 00:00:00:00:00:00',
            'X-PrivateKey: ' . $apiKey,
            'Authorization: Bearer ' . $jwt
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return [];
    $data = json_decode($response, true);
    if (!$data || !$data['status'] || empty($data['data']['fetched'])) return [];

    return $data['data']['fetched'];
}

function generateTOTP($secret) {
    $secret = str_replace(' ', '', $secret);
    $secret = strtoupper($secret);

    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binaryString = '';
    for ($i = 0; $i < strlen($secret); $i++) {
        $val = strpos($base32chars, $secret[$i]);
        if ($val === false) continue;
        $binaryString .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }

    $key = '';
    for ($i = 0; $i + 8 <= strlen($binaryString); $i += 8) {
        $key .= chr(bindec(substr($binaryString, $i, 8)));
    }

    $timeStep = floor(time() / 30);
    $time = pack('N*', 0) . pack('N*', $timeStep);
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $code = ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF);
    $otp = $code % 1000000;

    return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
}

<?php
/**
 * Test Angel One historical chart API for Nifty 50
 * DELETE after testing
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

function generateTOTP($secret) {
    $secret = str_replace(' ', '', strtoupper($secret));
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
    return str_pad((string)($code % 1000000), 6, '0', STR_PAD_LEFT);
}

try {
    $db = getDB();
    $stmt = $db->query("SELECT api_key, client_id, password, totp_secret FROM api_settings WHERE provider = 'angel_one' LIMIT 1");
    $creds = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$creds) {
        echo json_encode(['success' => false, 'error' => 'No credentials']);
        exit;
    }

    // Login
    $totp = generateTOTP($creds['totp_secret']);
    $loginData = json_encode(['clientcode' => $creds['client_id'], 'password' => $creds['password'], 'totp' => $totp]);
    $ch = curl_init('https://apiconnect.angelone.in/rest/auth/angelbroking/user/v1/loginByPassword');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $loginData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-UserType: USER',
            'X-SourceID: WEB',
            'X-ClientLocalIP: 127.0.0.1',
            'X-ClientPublicIP: 127.0.0.1',
            'X-MACAddress: 00:00:00:00:00:00',
            'X-PrivateKey: ' . $creds['api_key']
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    $jwt = $data['data']['jwtToken'] ?? '';

    if (!$jwt) {
        echo json_encode(['success' => false, 'error' => 'Login failed', 'response' => $data]);
        exit;
    }

    // Test historical API for Nifty 50
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $fromDate = clone $now;
    $fromDate->modify('-1 month');
    
    $payload = json_encode([
        'exchange' => 'NSE',
        'symboltoken' => '99926000',
        'interval' => 'ONE_DAY',
        'fromdate' => $fromDate->format('Y-m-d 09:15'),
        'todate' => $now->format('Y-m-d 15:30')
    ]);

    $ch = curl_init('https://apiconnect.angelone.in/rest/secure/angelbroking/historical/v1/getCandleData');
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
        'X-PrivateKey: ' . $creds['api_key'],
        'Authorization: Bearer ' . $jwt
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo json_encode([
        'success' => true,
        'http_code' => $httpCode,
        'payload' => $payload,
        'response' => json_decode($response, true),
        'raw_response' => substr($response, 0, 500)
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

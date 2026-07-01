<?php
/**
 * Test Angel One actual login and quote fetch
 * DELETE after use
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

    $totp = generateTOTP($creds['totp_secret']);
    $loginData = json_encode([
        'clientcode' => $creds['client_id'],
        'password'   => $creds['password'],
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
            'X-PrivateKey: ' . $creds['api_key']
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($err) {
        echo json_encode(['success' => false, 'error' => 'CURL error: ' . $err, 'http_code' => $info['http_code']]);
        exit;
    }

    $data = json_decode($response, true);

    if (!$data || !$data['status'] || empty($data['data']['jwtToken'])) {
        echo json_encode([
            'success' => false,
            'error' => $data['message'] ?? 'Login failed',
            'http_code' => $info['http_code'],
            'response' => $data
        ]);
        exit;
    }

    $jwt = $data['data']['jwtToken'];

    // Test quote fetch
    $quotePayload = json_encode([
        'mode' => 'FULL',
        'exchangeTokens' => ['NSE' => ['99926000']]
    ]);

    $ch = curl_init('https://apiconnect.angelone.in/rest/secure/angelbroking/market/v1/quote/');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $quotePayload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-UserType: USER',
            'X-SourceID: WEB',
            'X-ClientLocalIP: 127.0.0.1',
            'X-ClientPublicIP: 127.0.0.1',
            'X-MACAddress: 00:00:00:00:00:00',
            'X-PrivateKey: ' . $creds['api_key'],
            'Authorization: Bearer ' . $jwt
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20
    ]);

    $quoteResponse = curl_exec($ch);
    curl_close($ch);
    $quoteData = json_decode($quoteResponse, true);

    echo json_encode([
        'success' => true,
        'login_status' => 'OK',
        'jwt_received' => true,
        'quote_response' => $quoteData
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

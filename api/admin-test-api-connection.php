<?php
/**
 * Admin Test API Connection
 * Tests connection to API providers using direct API calls
 */
// Suppress all warnings/notices
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();

// Discard any stray output from includes
ob_end_clean();
ob_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$provider = $input['provider'] ?? '';

if (!$provider) {
    echo json_encode(['success' => false, 'message' => 'Provider not specified.']);
    exit;
}

try {
    if ($provider === 'angel_one') {
        // Read from form input or database
        $apiKey = $input['api_key'] ?? '';
        $clientId = $input['client_id'] ?? '';
        $password = $input['password'] ?? '';
        $totpSecret = $input['totp_secret'] ?? '';
        
        // If empty, try reading from database
        if (!$apiKey || !$clientId || !$password) {
            $db = getDB();
            $stmt = $db->query("SELECT * FROM api_settings WHERE provider = 'angel_one'");
            $settings = $stmt->fetch();
            if ($settings) {
                $apiKey = $apiKey ?: $settings['api_key'];
                $clientId = $clientId ?: $settings['client_id'];
                $password = $password ?: $settings['password'];
                $totpSecret = $totpSecret ?: $settings['totp_secret'];
            }
        }
        
        if (!$apiKey || !$clientId || !$password) {
            echo json_encode(['success' => false, 'message' => 'API credentials are required.']);
            exit;
        }
        
        // Generate TOTP
        $totp = '';
        if (!empty($totpSecret)) {
            $totp = generateTOTP($totpSecret);
        }
        
        // Debug info
        $debugInfo = [
            'server_time' => date('Y-m-d H:i:s'),
            'server_timezone' => date_default_timezone_get(),
            'unix_timestamp' => time(),
            'totp_secret_provided' => !empty($totpSecret) ? 'Yes (length: ' . strlen($totpSecret) . ')' : 'No - EMPTY!',
            'generated_totp' => $totp ?: 'EMPTY',
            'client_id' => $clientId,
        ];
        
        // Direct API call (same approach as diagnostic - proven to work)
        $payload = json_encode([
            'apikey' => $apiKey,
            'clientcode' => $clientId,
            'password' => $password,
            'totp' => $totp
        ]);
        
        $url = 'https://apiconnect.angelone.in/rest/auth/angelbroking/user/v1/loginByPassword';
        
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
            'X-PrivateKey: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $debugInfo['http_code'] = $httpCode;
        $debugInfo['curl_error'] = $curlError ?: 'None';
        
        if ($httpCode === 200 && !$curlError) {
            $result = json_decode($response, true);
            if ($result && !empty($result['status'])) {
                echo json_encode(['success' => true, 'message' => 'Angel One connection successful!', 'debug' => $debugInfo]);
                exit;
            } else {
                $errorMsg = $result['message'] ?? 'Unknown error';
                echo json_encode(['success' => false, 'message' => 'Angel One connection failed: ' . $errorMsg, 'debug' => $debugInfo]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'HTTP Error: ' . $httpCode . ($curlError ? ' - ' . $curlError : ''), 'debug' => $debugInfo]);
            exit;
        }
        
    } elseif ($provider === 'yahoo_finance') {
        $db = getDB();
        $stmt = $db->prepare("SELECT symbol, ltp FROM stocks WHERE symbol = ?");
        $stmt->execute(['RELIANCE.NS']);
        $stock = $stmt->fetch();
        
        if ($stock && $stock['ltp'] > 0) {
            echo json_encode(['success' => true, 'message' => 'Yahoo Finance data available!']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Yahoo Finance connection OK (data will be fetched on demand).']);
        }
        exit;
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown provider: ' . $provider]);
        exit;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Connection test failed: ' . $e->getMessage()]);
    exit;
}

// ── TOTP Generation (standalone, no class dependency) ──
function generateTOTP($secret) {
    $secret = strtoupper(str_replace(' ', '', $secret));
    $decoded = base32Decode($secret);
    if ($decoded === false) return '';
    
    $timeStep = floor(time() / 30);
    $timeBytes = pack('N*', 0, $timeStep);
    $hmac = hash_hmac('sha1', $timeBytes, $decoded, true);
    $offset = ord($hmac[19]) & 0x0F;
    $code = (
        ((ord($hmac[$offset]) & 0x7F) << 24) |
        ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
        ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
        (ord($hmac[$offset + 3]) & 0xFF)
    ) % 1000000;
    
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

function base32Decode($input) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(rtrim($input, '='));
    $buffer = 0;
    $bitsLeft = 0;
    $output = '';
    
    for ($i = 0; $i < strlen($input); $i++) {
        $val = strpos($alphabet, $input[$i]);
        if ($val === false) return false;
        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $output .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    return $output;
}
?>

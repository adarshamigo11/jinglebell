<?php
/**
 * Test Angel One API connection
 * DELETE after use
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT api_key, client_id, password, totp_secret, is_active FROM api_settings WHERE provider = 'angel_one' LIMIT 1");
    $creds = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$creds) {
        echo json_encode(['success' => false, 'error' => 'No angel_one credentials in database']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'credentials_found' => true,
        'is_active' => (int)$creds['is_active'],
        'api_key' => substr($creds['api_key'], 0, 4) . '...',
        'client_id' => $creds['client_id'],
        'password_set' => !empty($creds['password']),
        'totp_set' => !empty($creds['totp_secret'])
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

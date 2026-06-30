<?php
/**
 * Admin Save API Settings
 * Saves API credentials and provider preferences
 */
// Suppress all warnings/notices from outputting before JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();

// Discard any stray output from includes
ob_end_clean();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

try {
    $db = getDB();
    $db->beginTransaction();
    
    // Save Angel One credentials if provided
    if (isset($input['angel_one'])) {
        $angelOne = $input['angel_one'];
        
        $stmt = $db->prepare("
            INSERT INTO api_settings (provider, api_key, client_id, password, totp_secret, is_active)
            VALUES ('angel_one', ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                api_key = VALUES(api_key),
                client_id = VALUES(client_id),
                password = VALUES(password),
                totp_secret = VALUES(totp_secret),
                is_active = VALUES(is_active)
        ");
        
        $stmt->execute([
            $angelOne['api_key'] ?? null,
            $angelOne['client_id'] ?? null,
            $angelOne['password'] ?? null,
            $angelOne['totp_secret'] ?? null,
            isset($angelOne['is_active']) ? (int)$angelOne['is_active'] : 0
        ]);
    }
    
    // Save provider preferences
    if (isset($input['preferences']) && is_array($input['preferences'])) {
        foreach ($input['preferences'] as $assetType => $pref) {
            $provider = $pref['provider'] ?? 'yahoo_finance';
            $isEnabled = isset($pref['is_enabled']) ? (int)$pref['is_enabled'] : 1;
            
            $stmt = $db->prepare("
                INSERT INTO data_provider_preferences (asset_type, provider, is_enabled)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    provider = VALUES(provider),
                    is_enabled = VALUES(is_enabled)
            ");
            $stmt->execute([$assetType, $provider, $isEnabled]);
        }
    }
    
    $db->commit();
    
    logAdminAction($admin['id'], 'API_SETTINGS_UPDATED', 'api_settings', 0, 'API settings updated');
    
    jsonResponse(true, 'API settings saved successfully.');
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error saving API settings: " . $e->getMessage());
    jsonResponse(false, 'Failed to save settings: ' . $e->getMessage());
}
?>

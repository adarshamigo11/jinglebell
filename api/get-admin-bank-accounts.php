<?php
/**
 * Get Admin Bank Accounts API (Public - for users)
 * Returns active bank accounts for deposit page
 */
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser(); // Any logged-in user can view
$db = getDB();

header('Content-Type: application/json');

try {
    $accounts = $db->query("
        SELECT id, account_name, bank_name, account_number, ifsc_code, upi_id, qr_code_image, is_default
        FROM admin_bank_accounts 
        WHERE is_active = 1
        ORDER BY is_default DESC, display_order ASC, created_at DESC
    ")->fetchAll();
    
    echo json_encode(['success' => true, 'accounts' => $accounts]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch bank accounts']);
}

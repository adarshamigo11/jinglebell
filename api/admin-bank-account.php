<?php
/**
 * Admin Bank Account Management API
 * Supports: GET (list), POST (create), PUT (update), DELETE (delete)
 */
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db = getDB();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // List all admin bank accounts
        $accounts = $db->query("
            SELECT * FROM admin_bank_accounts 
            ORDER BY is_default DESC, display_order ASC, created_at DESC
        ")->fetchAll();
        echo json_encode(['success' => true, 'accounts' => $accounts]);
        break;

    case 'POST':
        // Create new bank account
        $data = json_decode(file_get_contents('php://input'), true);
        
        $accountName = trim($data['account_name'] ?? '');
        $bankName = trim($data['bank_name'] ?? '');
        $accountNumber = trim($data['account_number'] ?? '');
        $ifscCode = trim($data['ifsc_code'] ?? '');
        $upiId = trim($data['upi_id'] ?? '');
        $qrCodeImage = trim($data['qr_code_image'] ?? '');
        $isDefault = !empty($data['is_default']) ? 1 : 0;
        $displayOrder = (int)($data['display_order'] ?? 0);
        
        // Validation
        if (empty($accountName) || empty($bankName) || empty($accountNumber) || empty($ifscCode)) {
            echo json_encode(['success' => false, 'message' => 'Account name, bank name, account number, and IFSC code are required']);
            exit;
        }
        
        // If setting as default, unset other defaults
        if ($isDefault) {
            $db->query("UPDATE admin_bank_accounts SET is_default = 0");
        }
        
        $stmt = $db->prepare("
            INSERT INTO admin_bank_accounts 
            (account_name, bank_name, account_number, ifsc_code, upi_id, qr_code_image, is_default, display_order, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $accountName, $bankName, $accountNumber, $ifscCode, $upiId, 
            $qrCodeImage, $isDefault, $displayOrder, $admin['id']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Bank account added successfully']);
        break;

    case 'PUT':
        // Update bank account
        $data = json_decode(file_get_contents('php://input'), true);
        $accountId = (int)($data['id'] ?? 0);
        
        if (!$accountId) {
            echo json_encode(['success' => false, 'message' => 'Account ID required']);
            exit;
        }
        
        $accountName = trim($data['account_name'] ?? '');
        $bankName = trim($data['bank_name'] ?? '');
        $accountNumber = trim($data['account_number'] ?? '');
        $ifscCode = trim($data['ifsc_code'] ?? '');
        $upiId = trim($data['upi_id'] ?? '');
        $qrCodeImage = trim($data['qr_code_image'] ?? '');
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        $isDefault = !empty($data['is_default']) ? 1 : 0;
        $displayOrder = (int)($data['display_order'] ?? 0);
        
        // Validation
        if (empty($accountName) || empty($bankName) || empty($accountNumber) || empty($ifscCode)) {
            echo json_encode(['success' => false, 'message' => 'Account name, bank name, account number, and IFSC code are required']);
            exit;
        }
        
        // If setting as default, unset other defaults
        if ($isDefault) {
            $db->query("UPDATE admin_bank_accounts SET is_default = 0 WHERE id != $accountId");
        }
        
        $stmt = $db->prepare("
            UPDATE admin_bank_accounts 
            SET account_name = ?, bank_name = ?, account_number = ?, ifsc_code = ?, 
                upi_id = ?, qr_code_image = ?, is_active = ?, is_default = ?, display_order = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $accountName, $bankName, $accountNumber, $ifscCode, $upiId,
            $qrCodeImage, $isActive, $isDefault, $displayOrder, $accountId
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Bank account updated successfully']);
        break;

    case 'DELETE':
        // Delete bank account
        $data = json_decode(file_get_contents('php://input'), true);
        $accountId = (int)($data['id'] ?? 0);
        
        if (!$accountId) {
            echo json_encode(['success' => false, 'message' => 'Account ID required']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM admin_bank_accounts WHERE id = ?");
        $stmt->execute([$accountId]);
        
        echo json_encode(['success' => true, 'message' => 'Bank account deleted successfully']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

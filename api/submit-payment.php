<?php
// =====================================================
// Trade-Zenfy - Submit Deposit API
// POST /api/submit-payment.php
// =====================================================

require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$amount        = (float)($_POST['amount'] ?? 0);
$method        = clean($_POST['payment_method'] ?? '');
$transactionId = clean($_POST['transaction_id'] ?? '');

$allowedMethods = ['upi', 'netbanking', 'neft', 'rtgs', 'imps'];

if ($amount < 100) {
    jsonResponse(false, 'Minimum deposit amount is ₹100.');
}
if (!in_array($method, $allowedMethods, true)) {
    jsonResponse(false, 'Invalid payment method.');
}
if (!$transactionId) {
    jsonResponse(false, 'Transaction ID / UTR is required.');
}

// Handle proof image upload
$proofImage = null;
if (!empty($_FILES['proof_image']['name'])) {
    $file     = $_FILES['proof_image'];
    $allowed  = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowed, true)) {
        jsonResponse(false, 'Proof image must be JPG, PNG, or WebP.');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        jsonResponse(false, 'Proof image must be under 5MB.');
    }

    $ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename   = 'pay_' . $user['id'] . '_' . time() . '.' . $ext;
    $uploadPath = PAYMENT_UPLOAD . $filename;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        jsonResponse(false, 'Failed to upload proof image.');
    }
    $proofImage = 'uploads/payments/' . $filename;
}

$db = getDB();

// Check for duplicate transaction ID
$check = $db->prepare("SELECT id FROM payments WHERE transaction_id = ?");
$check->execute([$transactionId]);
if ($check->fetch()) {
    jsonResponse(false, 'This Transaction ID has already been submitted.');
}

try {
    $stmt = $db->prepare("
        INSERT INTO payments (user_id, amount, payment_method, transaction_id, proof_image, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$user['id'], $amount, $method, $transactionId, $proofImage]);
    
    jsonResponse(true, 'Deposit request submitted successfully. It will be credited after admin verification.', [
        'payment_id' => (int)$db->lastInsertId()
    ]);
} catch (PDOException $e) {
    error_log('Payment submission error: ' . $e->getMessage());
    jsonResponse(false, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log('Payment submission error: ' . $e->getMessage());
    jsonResponse(false, 'Error: ' . $e->getMessage());
}

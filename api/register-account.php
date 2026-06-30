<?php
// =====================================================
// Trade-Zenfy - User Registration API
// POST /api/register-account.php
// =====================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// ── Required fields ──────────────────────────────────
$required = ['first_name', 'last_name', 'email', 'username', 'password'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        jsonResponse(false, ucfirst(str_replace('_', ' ', $field)) . ' is required.');
    }
}

$firstName = clean($input['first_name']);
$lastName  = clean($input['last_name']);
$email     = strtolower(trim($input['email']));
$username  = strtolower(trim($input['username']));
$password  = $input['password'];

// ── Validations ──────────────────────────────────────
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Invalid email address.');
}
if (strlen($username) < 4 || !preg_match('/^[a-z0-9_]+$/', $username)) {
    jsonResponse(false, 'Username must be at least 4 characters and contain only letters, numbers, or underscores.');
}
if (strlen($password) < 8) {
    jsonResponse(false, 'Password must be at least 8 characters.');
}

$db = getDB();

// ── Check duplicates ─────────────────────────────────
$stmt = $db->prepare("SELECT id FROM account_registrations WHERE email = ? OR username = ?");
$stmt->execute([$email, $username]);
if ($stmt->fetch()) {
    jsonResponse(false, 'Email or username already exists.');
}

// ── Insert user ──────────────────────────────────────
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

$stmt = $db->prepare("
    INSERT INTO account_registrations
        (title, first_name, last_name, email, username, password, dob, phone,
         street, postal_code, city, country, state_citizenship, state_tax_residency,
         tax_id, employment_status, trading_currency, annual_income, net_worth,
         source_of_wealth, initial_deposit, status)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'INR', ?, ?, ?, ?, 'pending')
");

$stmt->execute([
    clean($input['title'] ?? ''),
    $firstName,
    $lastName,
    $email,
    $username,
    $hashedPassword,
    $input['dob'] ?? null,
    clean($input['phone'] ?? ''),
    clean($input['street'] ?? ''),
    clean($input['postal_code'] ?? ''),
    clean($input['city'] ?? ''),
    clean($input['country'] ?? ''),
    clean($input['state_citizenship'] ?? ''),
    clean($input['state_tax_residency'] ?? ''),
    clean($input['tax_id'] ?? ''),
    clean($input['employment_status'] ?? ''),
    $input['annual_income'] ?? null,
    $input['net_worth'] ?? null,
    clean($input['source_of_wealth'] ?? ''),
    $input['initial_deposit'] ?? null,
]);

$accountId = $db->lastInsertId();

jsonResponse(true, 'Registration successful. Your account is pending admin approval.', ['account_id' => (int)$accountId]);

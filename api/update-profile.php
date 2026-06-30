<?php
// =====================================================
// Trade-Zenfy - Update Profile API
// POST /api/update-profile.php
// =====================================================
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
header('Content-Type: application/json');

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$firstName = clean($input['first_name'] ?? '');
$lastName  = clean($input['last_name'] ?? '');
$phone     = clean($input['phone'] ?? '');
$city      = clean($input['city'] ?? '');
$country   = clean($input['country'] ?? '');

if (!$firstName || !$lastName) jsonResponse(false, 'First name and last name are required.');

getDB()->prepare("UPDATE account_registrations SET first_name=?, last_name=?, phone=?, city=?, country=?, updated_at=NOW() WHERE id=?")
       ->execute([$firstName, $lastName, $phone, $city, $country, $user['id']]);

jsonResponse(true, 'Profile updated successfully.');

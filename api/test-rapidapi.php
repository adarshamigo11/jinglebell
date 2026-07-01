<?php
/**
 * Test RapidAPI Indian Stock Exchange endpoint
 * DELETE after testing
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$apiKey = '40edf24f48msh1df22089aed2993p1e79adjsnb1b0b2e76bce';
$host = 'indianstockexchange.p.rapidapi.com';

// Test with different scrip IDs
// Common NSE scrip IDs to try:
$testIds = [
    'NSE:RELIANCE',
    'RELIANCE',
    '500209',     // INFY BSE
    'NSE:NIFTY50',
    'NIFTY50',
    'SENSEX',
    'NSE:NIFTY%2050',
    '99926000',   // Angel One Nifty token
];

$results = [];

foreach ($testIds as $scripId) {
    $url = "https://indianstockexchange.p.rapidapi.com/index.php?id=" . urlencode($scripId);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-rapidapi-host: ' . $host,
            'x-rapidapi-key: ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    
    $results[] = [
        'scrip_id' => $scripId,
        'http_code' => $httpCode,
        'curl_error' => $err,
        'response' => $decoded !== null ? $decoded : $response
    ];
}

echo json_encode(['success' => true, 'tests' => $results], JSON_PRETTY_PRINT);
?>

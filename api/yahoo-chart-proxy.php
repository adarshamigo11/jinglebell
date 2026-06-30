<?php
/**
 * Yahoo Finance Chart Data Proxy
 * Fetches historical stock data from Yahoo Finance API
 * Avoids CORS issues by making server-side requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get parameters
$symbol = $_GET['symbol'] ?? '';
$period1 = $_GET['period1'] ?? '';
$period2 = $_GET['period2'] ?? '';
$interval = $_GET['interval'] ?? '1d';

if (empty($symbol) || empty($period1) || empty($period2)) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameters'
    ]);
    exit;
}

// Build Yahoo Finance URL
$yahooUrl = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?period1={$period1}&period2={$period2}&interval={$interval}";

// Create stream context with User-Agent
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n",
        'timeout' => 10,
        'ignore_errors' => true
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

// Fetch data
$response = @file_get_contents($yahooUrl, false, $context);

if ($response === false) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch data from Yahoo Finance'
    ]);
    exit;
}

// Parse response
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON response from Yahoo Finance'
    ]);
    exit;
}

// Check for Yahoo Finance errors
if (isset($data['chart']['error'])) {
    echo json_encode([
        'success' => false,
        'error' => $data['chart']['error']['description'] ?? 'Yahoo Finance API error'
    ]);
    exit;
}

// Check if result exists
if (!isset($data['chart']['result']) || empty($data['chart']['result'])) {
    echo json_encode([
        'success' => false,
        'error' => 'No data available for this stock'
    ]);
    exit;
}

// Return successful response
echo json_encode([
    'success' => true,
    'data' => $data
]);

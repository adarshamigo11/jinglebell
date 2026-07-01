<?php
/**
 * Database Import Script for Railway
 * Run ONCE after deployment by visiting: https://your-domain.railway.app/import-database.php
 * DELETE this file after successful import!
 */

// Show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

echo "<html><head><title>Database Import</title>";
echo "<style>body{font-family:monospace;background:#1a1a2e;color:#0f0;padding:20px;} .ok{color:#0f0;} .err{color:#f44;} .info{color:#ff0;}</style></head><body>";
echo "<h1>TradeZenfy Database Import</h1>";
echo "<pre>";

// Connect using Railway env vars
$host = getenv('MYSQLHOST') ?: 'localhost';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$db   = getenv('MYSQLDATABASE') ?: 'jinglebell';
$port = getenv('MYSQLPORT') ?: '3306';

echo "Connecting to MySQL at {$host}:{$port} as {$user}...\n";

try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<span class='ok'>Connected to MySQL server!</span>\n";
} catch (PDOException $e) {
    echo "<span class='err'>Connection FAILED: " . $e->getMessage() . "</span>\n";
    echo "</pre></body></html>";
    exit;
}

// Create database if not exists
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("USE `{$db}`");
    echo "<span class='ok'>Database '{$db}' ready!</span>\n";
} catch (PDOException $e) {
    echo "<span class='err'>Database creation failed: " . $e->getMessage() . "</span>\n";
    echo "</pre></body></html>";
    exit;
}

// Find SQL files
$sqlDir = __DIR__ . '/database/';
$files = [
    'databaserailway.sql',  // Railway-compatible schema + data
];

foreach ($files as $file) {
    $path = $sqlDir . $file;
    echo "\n--- Processing: {$file} ---\n";
    
    if (!file_exists($path)) {
        echo "<span class='err'>File not found: {$path}</span>\n";
        continue;
    }
    
    $sql = file_get_contents($path);
    echo "File size: " . number_format(strlen($sql)) . " bytes\n";
    
    // Use mysqli_multi_query to execute all statements in the file
    $mysqli = new mysqli($host, $user, $pass, $db, $port);
    if ($mysqli->connect_error) {
        echo "<span class='err'>MySQLi connection failed: " . $mysqli->connect_error . "</span>\n";
        continue;
    }
    
    if ($mysqli->multi_query($sql)) {
        $success = 0;
        $errors = 0;
        do {
            if ($mysqli->errno) {
                $errors++;
                if ($errors <= 5) {
                    echo "<span class='err'>  Error: " . $mysqli->error . "</span>\n";
                }
            } else {
                $success++;
            }
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
    } else {
        echo "<span class='err'>Import failed: " . $mysqli->error . "</span>\n";
        $mysqli->close();
        continue;
    }
    
    echo "<span class='ok'>Done: {$success} succeeded, {$errors} errors</span>\n";
    $mysqli->close();
}

// Reconnect with PDO for verification
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
]);

// Verify tables
echo "\n--- Verification ---\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables found: " . count($tables) . "\n";
foreach ($tables as $t) {
    $count = $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    echo "  {$t}: {$count} rows\n";
}

// Check admin account
try {
    $admin = $pdo->query("SELECT username FROM admins LIMIT 1")->fetchColumn();
    echo "\n<span class='ok'>Admin account: {$admin}</span>\n";
} catch (Exception $e) {
    echo "\n<span class='err'>No admin account found</span>\n";
}

// Check user account
try {
    $user = $pdo->query("SELECT username FROM account_registrations LIMIT 1")->fetchColumn();
    echo "<span class='ok'>User account: {$user}</span>\n";
} catch (Exception $e) {
    echo "<span class='err'>No user account found</span>\n";
}

echo "\n<span class='info'>============================================</span>\n";
echo "<span class='info'>Import complete! You can now login.</span>\n";
echo "<span class='err'>DELETE this file (import-database.php) after import!</span>\n";
echo "</pre></body></html>";
?>

<?php
/**
 * Environment variable diagnostic - DELETE after use
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background:#111;color:#0f0;padding:20px;font-family:monospace;'>";
echo "=== Railway Environment Variables ===\n\n";

// List all env vars starting with MYSQL or DB
$envVars = getenv();
ksort($envVars);

$found = false;
foreach ($envVars as $key => $value) {
    if (stripos($key, 'MYSQL') !== false || stripos($key, 'DB') !== false || stripos($key, 'RAILWAY') !== false) {
        $display = (stripos($key, 'PASS') !== false || stripos($key, 'SECRET') !== false || stripos($key, 'URL') !== false) 
            ? substr($value, 0, 20) . '...' 
            : $value;
        echo "<span style='color:#0f0;'>{$key}</span> = {$display}\n";
        $found = true;
    }
}

if (!$found) {
    echo "<span style='color:#f44;'>NO MySQL/DB/RAILWAY variables found!</span>\n";
}

echo "\n=== Test Connection ===\n";
$host = getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$user = getenv('MYSQLUSER') ?: getenv('MYSQL_USER') ?: getenv('DB_USER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: getenv('DB_PASS') ?: getenv('DB_PASSWORD') ?: '';
$db   = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'jinglebell';
$port = getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: getenv('DB_PORT') ?: '3306';

echo "Using: host={$host}, port={$port}, user={$user}, db={$db}\n";

try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<span style='color:#0f0;'>Connected to MySQL server!</span>\n";
    
    $pdo->exec("USE `{$db}`");
    echo "<span style='color:#0f0;'>Database '{$db}' selected!</span>\n";
    
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . count($tables) . "\n";
    foreach ($tables as $t) {
        echo "  - {$t}\n";
    }
} catch (PDOException $e) {
    echo "<span style='color:#f44;'>Connection FAILED: " . $e->getMessage() . "</span>\n";
}

echo "</pre>";
?>

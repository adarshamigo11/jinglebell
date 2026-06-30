<?php
// Debug file - Delete after testing!
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Information</h2>";

echo "<h3>1. File Structure Check</h3>";
echo "Current directory: " . __DIR__ . "<br>";
echo "config.php exists: " . (file_exists(__DIR__ . '/config.php') ? 'YES' : 'NO') . "<br>";
echo "includes/middleware.php exists: " . (file_exists(__DIR__ . '/includes/middleware.php') ? 'YES' : 'NO') . "<br>";

echo "<h3>2. Config Check</h3>";
require_once __DIR__ . '/config.php';
echo "DB_HOST: " . DB_HOST . "<br>";
echo "DB_USER: " . DB_USER . "<br>";
echo "DB_NAME: " . DB_NAME . "<br>";
echo "SITE_URL: " . SITE_URL . "<br>";

echo "<h3>3. Database Connection Test</h3>";
try {
    $db = getDB();
    echo "✅ Database connected successfully!<br>";
    
    // Test query
    $stmt = $db->query("SELECT COUNT(*) as count FROM stocks");
    $result = $stmt->fetch();
    echo "Stocks in database: " . $result['count'] . "<br>";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<h3>4. PHP Info</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'YES' : 'NO') . "<br>";
echo "OpenSSL: " . (extension_loaded('openssl') ? 'YES' : 'NO') . "<br>";

echo "<h3>5. Server Info</h3>";
echo "HTTPS: " . (isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'NO') . "<br>";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "<br>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "<br>";

echo "<br><strong>⚠️ DELETE THIS FILE AFTER TESTING!</strong>";

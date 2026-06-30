<?php
// Database Connection Test File
// Upload this to your server and visit: https://tradezenfy.xo.je/test-db.php

echo "<h2>Database Connection Test</h2>";

// Test 1: Check if config exists
if (!file_exists('config.php')) {
    echo "<p style='color:red'>❌ config.php not found! Make sure you renamed config-production.php to config.php</p>";
    exit;
}

echo "<p style='color:green'>✅ config.php found</p>";

// Test 2: Load config
require_once 'config.php';

echo "<p style='color:green'>✅ Config loaded successfully</p>";

// Test 3: Check constants
echo "<h3>Database Configuration:</h3>";
echo "<ul>";
echo "<li>DB_HOST: " . DB_HOST . "</li>";
echo "<li>DB_USER: " . DB_USER . "</li>";
echo "<li>DB_NAME: " . DB_NAME . "</li>";
echo "</ul>";

// Test 4: Try to connect
try {
    $db = getDB();
    echo "<p style='color:green; font-size:18px'>✅ <strong>Database connection successful!</strong></p>";
    
    // Test 5: Check if tables exist
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "<p style='color:green'>✅ Found " . count($tables) . " tables in database</p>";
        echo "<h3>Tables:</h3>";
        echo "<ul>";
        foreach (array_slice($tables, 0, 10) as $table) {
            echo "<li>$table</li>";
        }
        if (count($tables) > 10) {
            echo "<li>... and " . (count($tables) - 10) . " more</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:orange'>⚠️ Database connected but no tables found. You need to import your database.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red; font-size:16px'>❌ <strong>Connection Failed!</strong></p>";
    echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    echo "<h3>Common Solutions:</h3>";
    echo "<ul>";
    echo "<li>Check if database <strong>" . DB_NAME . "</strong> exists in your InfinityFree control panel</li>";
    echo "<li>Verify MySQL hostname in InfinityFree (might be different from sql113.infinityfree.com)</li>";
    echo "<li>Check username and password are correct</li>";
    echo "<li>Make sure database user has permissions to access the database</li>";
    echo "</ul>";
}
?>

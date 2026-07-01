<?php
/**
 * Fix stock_price_cache schema to match PHP code expectations
 * Run once after import
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

echo "<pre>";

try {
    $db = getDB();
    
    // Check current columns
    $stmt = $db->query("SHOW COLUMNS FROM stock_price_cache");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current columns: " . implode(', ', $columns) . "\n\n";
    
    // Rename old columns to new names
    $renames = [
        '`open` TO `open_price`',
        '`high` TO `high_price`',
        '`low` TO `low_price`',
        '`close` TO `close_price`',
        '`last_updated` TO `updated_at`'
    ];
    
    foreach ($renames as $rename) {
        try {
            $db->exec("ALTER TABLE stock_price_cache RENAME COLUMN {$rename}");
            echo "Renamed: {$rename} - OK\n";
        } catch (PDOException $e) {
            echo "Rename {$rename}: " . $e->getMessage() . "\n";
        }
    }
    
    // Add missing columns if not exist
    $additions = [
        "source" => "ALTER TABLE stock_price_cache ADD COLUMN IF NOT EXISTS `source` VARCHAR(50) DEFAULT 'database'",
        "is_live" => "ALTER TABLE stock_price_cache ADD COLUMN IF NOT EXISTS `is_live` TINYINT(1) DEFAULT 0"
    ];
    
    foreach ($additions as $col => $sql) {
        if (!in_array($col, $columns)) {
            try {
                $db->exec($sql);
                echo "Added column: {$col} - OK\n";
            } catch (PDOException $e) {
                echo "Add {$col}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Verify
    $stmt = $db->query("SHOW COLUMNS FROM stock_price_cache");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "\nUpdated columns: " . implode(', ', $columns) . "\n";
    echo "\n<span style='color:green'>Schema fix complete!</span>\n";
    
} catch (Exception $e) {
    echo "<span style='color:red'>Error: " . $e->getMessage() . "</span>\n";
}

echo "</pre>";
?>

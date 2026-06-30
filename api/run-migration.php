<?php
// Run this to add soft delete columns to stocks table
require_once __DIR__ . '/../config.php';

try {
    $db = getDB();
    
    // Check if columns exist
    $check = $db->query("SHOW COLUMNS FROM stocks LIKE 'deleted_at'")->fetch();
    
    if (!$check) {
        // Add the columns one by one to handle partial states
        $db->exec("ALTER TABLE stocks ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
        $db->exec("ALTER TABLE stocks ADD COLUMN deleted_by INT NULL");
        
        // Try to add indexes (may already exist)
        try {
            $db->exec("ALTER TABLE stocks ADD INDEX idx_is_active (is_active)");
        } catch (Exception $e) {
            // Index may already exist, ignore
        }
        
        try {
            $db->exec("ALTER TABLE stocks ADD INDEX idx_deleted_at (deleted_at)");
        } catch (Exception $e) {
            // Index may already exist, ignore
        }
        
        echo "Migration successful! Added deleted_at and deleted_by columns to stocks table.";
    } else {
        echo "Columns already exist. No migration needed.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

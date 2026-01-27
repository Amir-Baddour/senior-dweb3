<?php
require_once __DIR__ . '/../../connection/db.php';
$conn = $pdo ?? new PDO('mysql:host=localhost;dbname=digital_wallet', 'root', '');
try {
    $sql = "ALTER TABLE users 
            DROP COLUMN is_validated, 
            DROP COLUMN id_document, 
            DROP COLUMN verification_note";
    
    $conn->exec($sql);
    echo "✅ Users table updated: Verification columns removed.\n";
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}
?>
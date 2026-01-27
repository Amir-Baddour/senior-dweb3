<?php
// update_id_document_nullable.php

require_once __DIR__ . '/../../connection/db.php';
$conn = new PDO('mysql:host=localhost;dbname=your_database', 'root', '');
try {
    // Make 'id_document' column accept NULL with a default of NULL
    $sql = "ALTER TABLE verifications 
            MODIFY COLUMN id_document VARCHAR(255) NULL DEFAULT NULL";

    $conn->exec($sql);
    echo "✅ Column 'id_document' modified to allow NULL in 'verifications' table successfully!\n";
} catch (PDOException $e) {
    echo "❌ Error modifying 'id_document' column: " . $e->getMessage() . "\n";
}

$conn = null;
?>
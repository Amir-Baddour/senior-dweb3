<?php
// alter_transactions_table.php

require_once __DIR__ . '/../../connection/db.php';

// Create PDO connection
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'digital_wallet';

try {
    $conn = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}
try {
    $sql = "
        ALTER TABLE transactions 
        MODIFY transaction_type ENUM('deposit','withdrawal','transfer','qr_payment') NOT NULL
    ";

    $conn->exec($sql);
    echo "✅ Table 'transactions' modified successfully!\n";
} catch (PDOException $e) {
    echo "❌ Error modifying 'transactions' table: " . $e->getMessage() . "\n";
}

$conn = null;
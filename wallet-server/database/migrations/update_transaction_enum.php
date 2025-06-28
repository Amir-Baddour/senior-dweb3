<?php
// alter_transactions_table.php

require_once __DIR__ . '/../../connection/db.php';

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
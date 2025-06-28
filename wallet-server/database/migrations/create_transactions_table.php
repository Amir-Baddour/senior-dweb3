<?php
// create_transactions_table.php

require_once __DIR__ . '/../../connection/db.php';

try {
    $sql = "
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NULL,
            recipient_id INT NULL,
            amount DECIMAL(10,2) NOT NULL,
            transaction_type ENUM('deposit', 'withdrawal', 'transfer') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id),
            FOREIGN KEY (recipient_id) REFERENCES users(id)
        ) ENGINE=InnoDB
    ";

    $conn->exec($sql);
    echo "✅ Table 'transactions' created (or already exists) successfully!\n";
} catch (PDOException $e) {
    echo "❌ Error creating 'transactions' table: " . $e->getMessage() . "\n";
}

$conn = null;
?>
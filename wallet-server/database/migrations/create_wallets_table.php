<?php

require_once __DIR__ . '/../../connection/db.php';

$sql = "CREATE TABLE IF NOT EXISTS wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB";

try {
    $conn->exec($sql);
    echo "✅ Table 'wallets' created successfully!\n";
} catch (PDOException $e) {
    echo "❌ Error creating table: " . $e->getMessage() . "\n";
}

$conn = null;

?>
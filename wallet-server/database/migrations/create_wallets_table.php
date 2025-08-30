<?php

require_once __DIR__ . '/../../connection/db.php';
$conn = getConnection();

$sql = "CREATE TABLE IF NOT EXISTS wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    coin_symbol VARCHAR(10) NOT NULL DEFAULT 'USDT',
    balance DECIMAL(36,18) NOT NULL DEFAULT 0,
    locked_balance DECIMAL(36,18) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_coin (user_id, coin_symbol),
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

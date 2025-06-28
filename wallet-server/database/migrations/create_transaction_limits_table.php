<?php
// create_transaction_limits_table.php
require_once __DIR__ . '/../../connection/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS transaction_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tier VARCHAR(20) NOT NULL UNIQUE,
        daily_limit DECIMAL(10,2) NOT NULL,
        weekly_limit DECIMAL(10,2) NOT NULL,
        monthly_limit DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";
    
    $conn->exec($sql);
    echo "✅ 'transaction_limits' table created successfully!\n";

    // Insert default values for 'regular' and 'vip' tiers
    $insertSql = "INSERT INTO transaction_limits (tier, daily_limit, weekly_limit, monthly_limit)
                  VALUES 
                  ('regular', 500.00, 2000.00, 5000.00),
                  ('vip', 1000.00, 4000.00, 10000.00)
                  ON DUPLICATE KEY UPDATE 
                      daily_limit = VALUES(daily_limit),
                      weekly_limit = VALUES(weekly_limit),
                      monthly_limit = VALUES(monthly_limit)";
    $conn->exec($insertSql);
    echo "✅ Default transaction limits inserted successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Error creating 'transaction_limits' table: " . $e->getMessage() . "\n";
}

$conn = null;
?>
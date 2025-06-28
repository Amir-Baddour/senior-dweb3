<?php

require_once __DIR__ . '/../../connection/db.php';

$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role TINYINT(1) NOT NULL DEFAULT 0, -- 0 = User, 1 = Admin
    is_validated BOOLEAN NOT NULL DEFAULT 0, -- 0 = Not validated, 1 = Validated
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

try {
    $conn->exec($sql);
    echo "✅ Table 'users' created successfully!\n";
} catch (PDOException $e) {
    echo "❌ Error creating table: " . $e->getMessage() . "\n";
}

$conn = null;

?>
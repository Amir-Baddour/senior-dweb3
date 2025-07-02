<?php

require_once __DIR__ . "/../../connection/db.php"; // Include database connection
global $conn;
try {
    $sql = "CREATE TABLE IF NOT EXISTS user_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNIQUE NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        date_of_birth DATE,
        phone_number VARCHAR(20),
        street_address VARCHAR(255),
        city VARCHAR(100),
        country VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    $conn->exec($sql);
    echo "✅ user_profiles table created successfully.\n";
} catch (PDOException $e) {
    echo "❌ Error creating user_profiles table: " . $e->getMessage() . "\n";
}
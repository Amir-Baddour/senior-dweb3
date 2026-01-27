<?php
// create_password_resets_table.php

$dbPath = __DIR__ . '/../../connection/db.php';
if (!file_exists($dbPath)) {
    die("Error: db.php file not found at: " . $dbPath . "\n");
}
require_once $dbPath;

if (!function_exists('getConnection')) {
    die("Error: getConnection() function not found in db.php\n");
}
if (!is_callable('getConnection')) {
    die("Error: getConnection is not callable\n");
}
$conn = getConnection();
try {
    $sql = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB";
    
    $conn->exec($sql);
    echo " Table 'password_resets' created successfully!\n";
} catch (PDOException $e) {
    echo " Error creating table: " . $e->getMessage() . "\n";
}

$conn = null;
?>
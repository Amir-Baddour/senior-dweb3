<?php
// add_tier_to_users.php
require_once __DIR__ . '/../../connection/db.php';
$conn = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
try {
    $sql = "ALTER TABLE users 
            ADD COLUMN tier VARCHAR(20) NOT NULL DEFAULT 'regular' AFTER role";
    $conn->exec($sql);
    echo "'tier' column added to 'users' table successfully!\n";
} catch (PDOException $e) {
    echo " Error adding 'tier' column: " . $e->getMessage() . "\n";
}

$conn = null;
?>
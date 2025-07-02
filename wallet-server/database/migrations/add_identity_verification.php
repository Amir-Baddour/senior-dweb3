<?php
require_once __DIR__ . '/../../connection/db.php';

$conn = getConnection();

function columnExists($conn, $column, $table) {
    $stmt = $conn->prepare("SHOW COLUMNS FROM $table LIKE ?");
    $stmt->execute([$column]);
    return ($stmt->rowCount() > 0);
}

if (!columnExists($conn, 'id_document', 'users')) {
    $sql = "ALTER TABLE users ADD COLUMN id_document VARCHAR(255) NULL AFTER is_validated;";
    $conn->exec($sql);
    echo "✅ Added 'id_document' column to users table.\n";
} else {
    echo "⚠️ 'id_document' column already exists.\n";
}

if (!columnExists($conn, 'verification_note', 'users')) {
    $sql = "ALTER TABLE users ADD COLUMN verification_note TEXT NULL AFTER id_document;";
    $conn->exec($sql);
    echo "✅ Added 'verification_note' column to users table.\n";
} else {
    echo "⚠️ 'verification_note' column already exists.\n";
}

echo "✅ Migration completed successfully.\n";
?>
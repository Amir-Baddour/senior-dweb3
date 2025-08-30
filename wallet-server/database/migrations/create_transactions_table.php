<?php
// wallet-server/user/v1/create-transactions_table.php
// Idempotent creator/upgrader for `transactions` table:
// - Ensures ENUM includes 'exchange'
// - Adds optional meta_json TEXT for rich details
// - Adds useful indexes

header('Content-Type: text/plain');

require_once __DIR__ . '/../../connection/db.php';

// Get PDO (supports either global $conn or getConnection())
if (function_exists('getConnection')) {
    $conn = getConnection();
} else {
    global $conn;
    if (!$conn instanceof PDO) {
        die("❌ No PDO connection available.\n");
    }
}

try {
    // 1) Create table if not exists with desired schema
    // (If the table already exists, we'll patch it below.)
    $sqlCreate = "
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NULL,
            recipient_id INT NULL,
            amount DECIMAL(10,2) NOT NULL,
            transaction_type ENUM('deposit','withdrawal','transfer','exchange') NOT NULL,
            meta_json TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tx_type (transaction_type),
            INDEX idx_tx_sender (sender_id),
            INDEX idx_tx_recipient (recipient_id),
            INDEX idx_tx_created (created_at),
            CONSTRAINT fk_tx_sender FOREIGN KEY (sender_id) REFERENCES users(id),
            CONSTRAINT fk_tx_recipient FOREIGN KEY (recipient_id) REFERENCES users(id)
        ) ENGINE=InnoDB
    ";
    $conn->exec($sqlCreate);
    echo "✅ Table ensured/created.\n";

    // 2) Patch ENUM to include 'exchange' if missing
    $col = $conn->query("SHOW COLUMNS FROM transactions LIKE 'transaction_type'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        throw new Exception("Column transaction_type not found (unexpected).");
    }
    $type = strtolower($col['Type'] ?? '');
    if (strpos($type, "enum(") !== false && strpos($type, "'exchange'") === false) {
        // Replace with full set including 'exchange'
        $sqlAlterEnum = "
            ALTER TABLE transactions
            MODIFY COLUMN transaction_type
            ENUM('deposit','withdrawal','transfer','exchange') NOT NULL
        ";
        $conn->exec($sqlAlterEnum);
        echo "🔧 ENUM updated to include 'exchange'.\n";
    } else {
        echo "✔️ ENUM already includes 'exchange'.\n";
    }

    // 3) Add meta_json if missing (for rich details)
    $metaCol = $conn->query("SHOW COLUMNS FROM transactions LIKE 'meta_json'")->fetch(PDO::FETCH_ASSOC);
    if (!$metaCol) {
        $conn->exec("ALTER TABLE transactions ADD COLUMN meta_json TEXT NULL AFTER transaction_type");
        echo "🔧 Added column meta_json (TEXT).\n";
    } else {
        echo "✔️ meta_json column already present.\n";
    }

    // 4) Ensure helpful indexes exist (ignore errors if already exist)
    $indexes = [
        'idx_tx_type'     => "CREATE INDEX idx_tx_type ON transactions (transaction_type)",
        'idx_tx_sender'   => "CREATE INDEX idx_tx_sender ON transactions (sender_id)",
        'idx_tx_recipient'=> "CREATE INDEX idx_tx_recipient ON transactions (recipient_id)",
        'idx_tx_created'  => "CREATE INDEX idx_tx_created ON transactions (created_at)",
    ];

    // Check existing index names
    $existingIdx = [];
    $stmt = $conn->query("SHOW INDEX FROM transactions");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ix) {
        if (!empty($ix['Key_name'])) $existingIdx[$ix['Key_name']] = true;
    }

    foreach ($indexes as $name => $sql) {
        if (!isset($existingIdx[$name])) {
            try {
                $conn->exec($sql);
                echo "🔧 Created index $name.\n";
            } catch (Throwable $e) {
                echo "ℹ️ Skipped index $name (maybe exists): " . $e->getMessage() . "\n";
            }
        } else {
            echo "✔️ Index $name already exists.\n";
        }
    }

    echo "\nAll done. You can now record and view 'exchange' transactions.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    if (isset($conn)) $conn = null;
}

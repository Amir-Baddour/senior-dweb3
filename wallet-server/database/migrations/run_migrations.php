<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Require your DB connection
require_once __DIR__ . '/../../connection/db.php';

// List of migration files
$migrations = [
    'create_users_table.php',
    'create_wallets_table.php',
    'create_transactions_table.php',
    'create_transaction_limits_table.php',
    'create_password_resets_table.php',
    'create_verifications_table.php',
    'create_user_profiles_table.php',
    'add_identity_verification.php',
    'add_tier_to_users.php',
    'remove_verification_columns.php',
    'update_id_document_nullable.php',
    'update_transaction_enum.php',
];

// Loop through and run each migration
foreach ($migrations as $migration) {
    echo "Running migration: $migration\n";
    require_once __DIR__ . '/' . $migration;
}

echo "✅ All migrations executed.\n";

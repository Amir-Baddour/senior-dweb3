<?php
require_once __DIR__ . '/../connection/db.php';

class WalletsModel
{
    private $conn;

    public function __construct()
    {
        // Use the global PDO instance from db.php for database operations
        global $conn;
        $this->conn = $conn;
    }

    // CREATE: Inserts a new wallet record for a user with an initial balance.
    public function create($user_id, $balance)
    {
        $sql = "INSERT INTO wallets (user_id, balance, created_at, updated_at)
                VALUES (:user_id, :balance, NOW(), NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':balance', $balance);
        $stmt->execute();
        return $this->conn->lastInsertId();
    }

    // READ: Retrieve a wallet record by its wallet ID.
    public function getWalletById($id)
    {
        $sql = "SELECT * FROM wallets WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // READ: Retrieve a wallet record by the associated user ID.
    public function getWalletByUserId($user_id)
    {
        $sql = "SELECT * FROM wallets WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // READ: Retrieve all wallet records.
    public function getAllWallets()
    {
        $sql = "SELECT * FROM wallets";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // UPDATE: Update an existing wallet record with a new balance.
    public function update($id, $user_id, $balance)
    {
        $sql = "UPDATE wallets
                SET user_id = :user_id,
                    balance = :balance,
                    updated_at = NOW()
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':balance', $balance);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // DELETE: Remove a wallet record by its wallet ID.
    public function delete($id)
    {
        $sql = "DELETE FROM wallets WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
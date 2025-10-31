<?php
// wallet-server/models/WalletsModel.php
require_once __DIR__ . '/../connection/db.php';

class WalletsModel
{
    private $conn;

    public function __construct()
    {
        // Use global PDO instance from db.php
        global $conn;
        $this->conn = $conn;
    }

    /**
     * Create a wallet entry (or add to balance if it already exists).
     * Assumes a UNIQUE KEY on (user_id, coin_symbol).
     */
    public function create($user_id, $coin_symbol, $balance)
    {
        $sql = "INSERT INTO wallets (user_id, coin_symbol, balance, created_at, updated_at)
                VALUES (:user_id, :coin_symbol, :balance, NOW(), NOW())
                ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance), updated_at = NOW()";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':coin_symbol', $coin_symbol, PDO::PARAM_STR);
        $stmt->bindParam(':balance', $balance);
        $stmt->execute();
        return $this->conn->lastInsertId();
    }

    /** Get a specific wallet for a user and coin (e.g., USDT). */
    public function getWalletByUserAndCoin($user_id, $coin_symbol)
    {
        $sql = "SELECT * FROM wallets WHERE user_id = :user_id AND coin_symbol = :coin_symbol LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':coin_symbol', $coin_symbol, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Get all wallets for a user. */
    public function getWalletsByUser($user_id)
    {
        $sql = "SELECT * FROM wallets WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Update balance for a given user + coin. */
    public function updateBalance($user_id, $coin_symbol, $new_balance)
    {
        $sql = "UPDATE wallets
                SET balance = :balance, updated_at = NOW()
                WHERE user_id = :user_id AND coin_symbol = :coin_symbol";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':balance', $new_balance);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':coin_symbol', $coin_symbol, PDO::PARAM_STR);
        return $stmt->execute();
    }

    /** Update locked balance (optional feature). */
    public function updateLockedBalance($user_id, $coin_symbol, $locked_balance)
    {
        $sql = "UPDATE wallets
                SET locked_balance = :locked_balance, updated_at = NOW()
                WHERE user_id = :user_id AND coin_symbol = :coin_symbol";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':locked_balance', $locked_balance);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':coin_symbol', $coin_symbol, PDO::PARAM_STR);
        return $stmt->execute();
    }

    /** Delete a wallet row for a given user + coin. */
    public function delete($user_id, $coin_symbol)
    {
        $sql = "DELETE FROM wallets WHERE user_id = :user_id AND coin_symbol = :coin_symbol";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':coin_symbol', $coin_symbol, PDO::PARAM_STR);
        return $stmt->execute();
    }
}

<?php
require_once __DIR__ . '/../connection/db.php';

class WalletsModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    // CREATE: Inserts a new wallet record for a coin.
    public function create($user_id, $coin_symbol, $balance)
    {
        $sql = "INSERT INTO wallets (user_id, coin_symbol, balance, created_at, updated_at)
                VALUES (:user_id, :coin_symbol, :balance, NOW(), NOW())
                ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':coin_symbol', $coin_symbol);
        $stmt->bindParam(':balance', $balance);
        $stmt->execute();
        return $this->conn->lastInsertId();
    }

    // READ: Retrieve wallet for a user & coin
    // READ: Get a specific wallet for a user and coin
  public function getWalletByUserAndCoin($user_id, $coin_symbol)
   {
    $sql = "SELECT * FROM wallets WHERE user_id = :user_id AND coin_symbol = :coin_symbol LIMIT 1";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':coin_symbol', $coin_symbol);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }


    // READ: Get all wallets for a user
    public function getWalletsByUser($user_id)
    {
        $sql = "SELECT * FROM wallets WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // UPDATE: Update wallet balance for user + coin
    public function updateBalance($user_id, $coin_symbol, $new_balance)
    {
        $sql = "UPDATE wallets
                SET balance = :balance, updated_at = NOW()
                WHERE user_id = :user_id AND coin_symbol = :coin_symbol";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':coin_symbol', $coin_symbol);
        $stmt->bindParam(':balance', $new_balance);
        return $stmt->execute();
    }

    // UPDATE: Lock or unlock funds
    public function updateLockedBalance($user_id, $coin_symbol, $locked_balance)
    {
        $sql = "UPDATE wallets
                SET locked_balance = :locked_balance, updated_at = NOW()
                WHERE user_id = :user_id AND coin_symbol = :coin_symbol";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':coin_symbol', $coin_symbol);
        $stmt->bindParam(':locked_balance', $locked_balance);
        return $stmt->execute();
    }

    // DELETE a specific coin wallet
    public function delete($user_id, $coin_symbol)
    {
        $sql = "DELETE FROM wallets WHERE user_id = :user_id AND coin_symbol = :coin_symbol";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':coin_symbol', $coin_symbol);
        return $stmt->execute();
    }
}

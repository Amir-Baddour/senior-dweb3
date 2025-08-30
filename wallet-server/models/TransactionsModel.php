<?php
require_once __DIR__ . '/../connection/db.php';

class TransactionsModel
{
    private $conn;

    public function __construct()
    {
        // Use the global PDO instance from db.php
        global $conn;
        $this->conn = $conn;
    }

    // CREATE a new transaction record.
    public function create($sender_id, $recipient_id, $type, $amount)
    {
        $sql = "INSERT INTO transactions (sender_id, recipient_id, transaction_type, amount, created_at)
                VALUES (:sender_id, :recipient_id, :type, :amount, NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':sender_id', $sender_id);
        $stmt->bindParam(':recipient_id', $recipient_id);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':amount', $amount);
        $stmt->execute();
        return $this->conn->lastInsertId();
    }

    // READ - Retrieve a single transaction by its ID.
    public function getTransactionById($id)
    {
        $sql = "SELECT * FROM transactions WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // READ - Retrieve all transactions.
    public function getAllTransactions()
    {
        $sql = "SELECT * FROM transactions";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // READ - Retrieve transactions filtered by sender ID.
    public function getTransactionsBySenderId($sender_id)
    {
        $sql = "SELECT * FROM transactions WHERE sender_id = :sender_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':sender_id', $sender_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // READ - Retrieve transactions filtered by recipient ID.
    public function getTransactionsByRecipientId($recipient_id)
    {
        $sql = "SELECT * FROM transactions WHERE recipient_id = :recipient_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':recipient_id', $recipient_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // UPDATE - Update an existing transaction record.
    public function update($id, $sender_id, $recipient_id, $type, $amount)
    {
        $sql = "UPDATE transactions
                SET sender_id = :sender_id,
                    recipient_id = :recipient_id,
                    transaction_type = :type,
                    amount = :amount
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':sender_id', $sender_id);
        $stmt->bindParam(':recipient_id', $recipient_id);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // DELETE - Remove a transaction record.
    public function delete($id)
    {
        $sql = "DELETE FROM transactions WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    /**
     * Insert an 'exchange' transaction.
     * If the table has a 'meta_json' column, store details there.
     * Otherwise insert a minimal row (type='exchange', amount={sold amount}).
     */
    public function insertExchange($userId, $amount, array $meta = [])
    {
        $hasMeta = false;
        try {
            $chk = $this->conn->query("SHOW COLUMNS FROM transactions LIKE 'meta_json'");
            $hasMeta = ($chk && $chk->rowCount() > 0);
        } catch (Throwable $e) {
            $hasMeta = false;
        }

        if ($hasMeta) {
            $sql = "INSERT INTO transactions
                        (sender_id, recipient_id, transaction_type, amount, created_at, meta_json)
                    VALUES
                        (:uid, :uid, 'exchange', :amount, NOW(), :meta)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':uid'    => $userId,
                ':amount' => $amount, // how much was sold from the source coin
                ':meta'   => json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ]);
        } else {
            $sql = "INSERT INTO transactions
                        (sender_id, recipient_id, transaction_type, amount, created_at)
                    VALUES
                        (:uid, :uid, 'exchange', :amount, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':uid'    => $userId,
                ':amount' => $amount,
            ]);
        }

        return $this->conn->lastInsertId();
    }
}

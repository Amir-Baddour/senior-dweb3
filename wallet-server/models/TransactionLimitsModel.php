<?php
require_once __DIR__ . '/../connection/db.php';

class TransactionLimitsModel
{
    private $conn;

    public function __construct()
    {
        // Use the global PDO instance from db.php
        global $conn;
        $this->conn = $conn;
    }

    public function getTransactionLimitByTier($tier)
    {
        try {
            $sql = "SELECT daily_limit, weekly_limit, monthly_limit 
                    FROM transaction_limits 
                    WHERE tier = :tier 
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['tier' => $tier]);
            $limits = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$limits) {
                return ["error" => "Transaction limits not defined for your tier"];
            }
            return $limits;
        } catch (PDOException $e) {
            return ["error" => $e->getMessage()];
        }
    }
    
    public function getAllTransactionLimits()
    {
        $sql = "SELECT * FROM transaction_limits";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
<?php
require_once __DIR__ . '/../connection/db.php';

class PasswordResetsModel
{
    private $conn;

    public function __construct()
    {
        // Use the global PDO instance from db.php
        global $conn;
        $this->conn = $conn;
    }

    // Create a new password reset record
    public function create($user_id, $token, $expires_at)
    {
        $sql = "INSERT INTO password_resets (user_id, token, expires_at, created_at, updated_at)
                VALUES (:user_id, :token, :expires_at, NOW(), NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires_at', $expires_at);
        $stmt->execute();
        return $this->conn->lastInsertId();
    }

    // Retrieve a reset record by its ID
    public function getResetById($id)
    {
        $sql = "SELECT * FROM password_resets WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Retrieve a reset record by the token
    public function getResetByToken($token)
    {
        $sql = "SELECT * FROM password_resets WHERE token = :token LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Retrieve a reset record by the user ID
    public function getResetByUserId($user_id)
    {
        $sql = "SELECT * FROM password_resets WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Retrieve all password reset records
    public function getAllResets()
    {
        $sql = "SELECT * FROM password_resets";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update an existing password reset record
    public function update($id, $user_id, $token, $expires_at)
    {
        $sql = "UPDATE password_resets
                SET user_id = :user_id,
                    token = :token,
                    expires_at = :expires_at,
                    updated_at = NOW()
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires_at', $expires_at);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Delete a password reset record
    public function delete($id)
    {
        $sql = "DELETE FROM password_resets WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
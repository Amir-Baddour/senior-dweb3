<?php
require_once __DIR__ . '/../connection/db.php';

class VerificationsModel
{
    private $conn;

    public function __construct()
    {
        // Use the global PDO instance from db.php
        global $conn;
        $this->conn = $conn;
    }

    // CREATE a new verification record.
    public function create($user_id, $id_document, $is_validated, $verification_note)
    {
        $sql = "INSERT INTO verifications (
                    user_id, id_document, is_validated, verification_note, created_at
                ) VALUES (
                    :user_id, :id_document, :is_validated, :verification_note, NOW()
                )";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':id_document', $id_document);
        $stmt->bindParam(':is_validated', $is_validated);
        $stmt->bindParam(':verification_note', $verification_note);
        $stmt->execute();
        return $this->conn->lastInsertId();
    }

    // READ a verification record by its ID.
    public function getVerificationById($id)
    {
        $sql = "SELECT * FROM verifications WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // READ a verification record by the associated user ID.
    public function getVerificationByUserId($user_id)
    {
        $sql = "SELECT * FROM verifications WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // READ all verification records.
    public function getAllVerifications()
    {
        $sql = "SELECT * FROM verifications";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // READ all pending verification records (where is_validated is 0).
    public function getPendingVerifications()
    {
        $sql = "SELECT * FROM verifications WHERE is_validated = 0";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // UPDATE an existing verification record.
    public function update($id, $user_id, $id_document, $is_validated, $verification_note)
    {
        $sql = "UPDATE verifications
                SET user_id = :user_id,
                    id_document = :id_document,
                    is_validated = :is_validated,
                    verification_note = :verification_note
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':id_document', $id_document);
        $stmt->bindParam(':is_validated', $is_validated);
        $stmt->bindParam(':verification_note', $verification_note);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // DELETE a verification record by its ID.
    public function delete($id)
    {
        $sql = "DELETE FROM verifications WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
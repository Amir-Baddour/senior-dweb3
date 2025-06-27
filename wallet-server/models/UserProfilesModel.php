<?php
require_once __DIR__ . '/../connection/db.php';

class UserProfilesModel
{
    private $conn;

    public function __construct()
    {
        // Use the global PDO instance from db.php
        global $conn;
        $this->conn = $conn;
    }

    // CREATE a new user profile record.
    public function create($user_id, $full_name, $date_of_birth, $phone_number, $street_address, $city, $country)
    {
        $sql = "INSERT INTO user_profiles (
                    user_id, full_name, date_of_birth, phone_number, street_address, city, country, created_at, updated_at
                ) VALUES (
                    :user_id, :full_name, :date_of_birth, :phone_number, :street_address, :city, :country, NOW(), NOW()
                )";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':date_of_birth', $date_of_birth);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':street_address', $street_address);
        $stmt->bindParam(':city', $city);
        $stmt->bindParam(':country', $country);
        $stmt->execute();
        return $this->conn->lastInsertId();
    }

    // READ a single profile by its profile ID.
    public function getProfileById($id)
    {
        $sql = "SELECT * FROM user_profiles WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // READ a single profile by the associated user ID.
    public function getProfileByUserId($user_id)
    {
        $sql = "SELECT * FROM user_profiles WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // READ all user profiles.
    public function getAllProfiles()
    {
        $sql = "SELECT * FROM user_profiles";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // UPDATE an existing user profile.
    public function update($user_id, $full_name, $date_of_birth, $phone_number, $street_address, $city, $country)
    {
        $sql = "UPDATE user_profiles
                SET full_name = :full_name,
                    date_of_birth = :date_of_birth,
                    phone_number = :phone_number,
                    street_address = :street_address,
                    city = :city,
                    country = :country,
                    updated_at = NOW()
                WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':date_of_birth', $date_of_birth);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':street_address', $street_address);
        $stmt->bindParam(':city', $city);
        $stmt->bindParam(':country', $country);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // DELETE a user profile by its profile ID.
    public function delete($id)
    {
        $sql = "DELETE FROM user_profiles WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
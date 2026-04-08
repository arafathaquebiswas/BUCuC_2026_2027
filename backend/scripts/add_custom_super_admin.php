<?php
require_once 'Database/db.php';

try {
    $database = new Database();
    $conn = $database->createConnection();

    // Configuration for the new Super Admin
    $username = "Super Admin 2";
    $email = "superadmin@bucuc.com";
    $password = "superadmin123";
    $role = "main_admin";
    $status = "active";

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // SQL to insert
    $sql = "INSERT INTO adminpanel (username, email, password, role, status, created_at) 
            VALUES (:username, :email, :password, :role, :status, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':role', $role);
    $stmt->bindParam(':status', $status);

    if ($stmt->execute()) {
        echo "SUCCESS: Super Admin created successfully.\n";
        echo "Email: $email\n";
        echo "Password: $password\n";
    } else {
        echo "ERROR: Failed to insert user.\n";
    }

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo "ERROR: User with this email or username already exists.\n";
    } else {
        echo "ERROR: Database error: " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>

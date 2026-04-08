<?php
require_once 'Database/db.php';

// This script updates the existing admin password to use proper hashing
// Run this once to update the database

$database = new Database();
$conn = $database->createConnection();

try {
    // Update the existing admin password to use proper hashing
    $newPassword = "admin123"; // The password you want to set
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $sql = "UPDATE adminpanel SET password = :password WHERE email = 'admin@bucuc.com'";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':password', $hashedPassword);
    
    if ($stmt->execute()) {
        echo "Admin password updated successfully!\n";
        echo "New hashed password: " . $hashedPassword . "\n";
        echo "You can now login with: admin@bucuc.com / admin123\n";
    } else {
        echo "Failed to update password.\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?> 
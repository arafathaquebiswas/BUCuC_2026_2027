<?php
require_once '../Database/db.php';

$database = new Database();
$conn = $database->createConnection();

try {
    $newEmail    = "admin@bucuc.org";
    $newPassword = "admin123";
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update the first active admin: set correct email + fresh password hash
    $sql = "UPDATE adminpanel
            SET email    = :email,
                password = :password
            WHERE id = (SELECT id FROM (SELECT id FROM adminpanel WHERE status='active' ORDER BY id ASC LIMIT 1) AS t)";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email',    $newEmail);
    $stmt->bindParam(':password', $hashedPassword);

    if ($stmt->execute() && $stmt->rowCount() > 0) {
        echo "✅ Admin credentials updated successfully!<br>";
        echo "Email: <strong>{$newEmail}</strong><br>";
        echo "Password: <strong>{$newPassword}</strong><br><br>";
        echo "<strong style='color:red'>⚠️ Delete or protect this script now!</strong>";
    } else {
        // No active admin found — insert one
        $insertSql = "INSERT INTO adminpanel (username, email, password, role, status, created_at)
                      VALUES ('Admin', :email, :password, 'main_admin', 'active', NOW())";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bindParam(':email',    $newEmail);
        $insertStmt->bindParam(':password', $hashedPassword);
        $insertStmt->execute();
        echo "✅ No existing admin found — new admin created!<br>";
        echo "Email: <strong>{$newEmail}</strong><br>";
        echo "Password: <strong>{$newPassword}</strong><br><br>";
        echo "<strong style='color:red'>⚠️ Delete or protect this script now!</strong>";
    }

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>

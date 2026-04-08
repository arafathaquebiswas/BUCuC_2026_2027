<?php
session_start();
require_once '../Database/db.php';

// Check if user is logged in and is main admin
if (!isset($_SESSION['admin']) || $_SESSION['admin_role'] !== 'main_admin') {
    header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("Access denied. Main admin privileges required."));
    exit();
}

// Check if required parameters are provided
if (!isset($_GET['admin_id']) || !isset($_GET['username']) || !isset($_GET['email'])) {
    header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("Missing required parameters for admin deletion."));
    exit();
}

$adminId = (int) $_GET['admin_id'];
$username = $_GET['username'];
$email = $_GET['email'];

// Security check: Prevent deleting own account
if ($adminId == $_SESSION['admin_id']) {
    header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("You cannot delete your own account."));
    exit();
}

// Validate admin ID is a positive integer
if ($adminId <= 0) {
    header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("Invalid admin ID provided."));
    exit();
}

try {
    $database = new Database();
    $conn = $database->createConnection();

    // First, verify the admin exists and get their details for logging
    $checkSql = "SELECT id, username, email, role FROM adminpanel WHERE id = :id";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindParam(':id', $adminId, PDO::PARAM_INT);
    $checkStmt->execute();
    $admin = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("Admin account not found in database."));
        exit();
    }

    // Double-check that this is not the current user
    if ($admin['id'] == $_SESSION['admin_id']) {
        header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("Cannot delete your own account."));
        exit();
    }

    // Perform the deletion
    $deleteSql = "DELETE FROM adminpanel WHERE id = :id";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bindParam(':id', $adminId, PDO::PARAM_INT);

    if ($deleteStmt->execute()) {
        // Check if any row was actually deleted
        if ($deleteStmt->rowCount() > 0) {
            // Log the successful deletion
            error_log("Admin account deleted: ID {$adminId}, Username: {$admin['username']}, Email: {$admin['email']}, Role: {$admin['role']} - Deleted by admin ID: {$_SESSION['admin_id']} ({$_SESSION['username']})");

            // Redirect with success message
            header("Location: ../../frontend/super_admin_dashboard.php?success=" . urlencode("Admin account '{$admin['username']}' has been successfully deleted from the database."));
            exit();
        } else {
            // No rows were deleted (admin might have been already deleted)
            header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("Admin account was not found or has already been deleted."));
            exit();
        }
    } else {
        // SQL execution failed
        error_log("Failed to delete admin: ID {$adminId}, Database error occurred");
        header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("Database error: Failed to delete admin account."));
        exit();
    }

} catch (PDOException $e) {
    // Database connection or query error
    error_log("Database error in delete_admin.php: " . $e->getMessage());
    header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("Database error occurred while deleting admin account."));
    exit();
} catch (Exception $e) {
    // General error
    error_log("General error in delete_admin.php: " . $e->getMessage());
    header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("An error occurred while deleting admin account."));
    exit();
}
?>
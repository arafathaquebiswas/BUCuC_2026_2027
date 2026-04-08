<?php
session_start();
require_once '../Database/db.php';

// Check if user is logged in and is main admin
if (!isset($_SESSION['admin']) || $_SESSION['admin_role'] !== 'main_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Main admin privileges required.']);
    exit();
}

$database = new Database();
$conn = $database->createConnection();

// Handle different operations
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_admins':
        getAdmins($conn);
        break;
    case 'create_admin':
        createAdmin($conn);
        break;
    case 'update_password':
        updatePassword($conn);
        break;
    case 'delete_admin':
        deleteAdmin($conn);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getAdmins($conn) {
    try {
        $sql = "SELECT id, username, email, role, status, created_at, updated_at FROM adminpanel ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format dates for better display
        foreach ($admins as &$admin) {
            $admin['created_at'] = date('Y-m-d H:i:s', strtotime($admin['created_at']));
            if ($admin['updated_at']) {
                $admin['updated_at'] = date('Y-m-d H:i:s', strtotime($admin['updated_at']));
            }
        }
        
        echo json_encode(['success' => true, 'data' => $admins, 'count' => count($admins)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function createAdmin($conn) {
    try {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        // Validation
        if (empty($username) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            return;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            return;
        }
        
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
            return;
        }
        
        // Check if email already exists
        $checkSql = "SELECT id FROM adminpanel WHERE email = :email";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();
        
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }
        
        // Check if username already exists
        $checkSql = "SELECT id FROM adminpanel WHERE username = :username";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bindParam(':username', $username);
        $checkStmt->execute();
        
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            return;
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new admin with proper status
        $sql = "INSERT INTO adminpanel (username, email, password, role, status) VALUES (:username, :email, :password, :role, 'active')";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':role', $role);
        
        if ($stmt->execute()) {
            $newAdminId = $conn->lastInsertId();
            
            // Log the creation
            error_log("New admin created: ID {$newAdminId}, Username: {$username}, Email: {$email}, Role: {$role} by admin ID: {$_SESSION['admin_id']}");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Admin account created successfully',
                'admin_id' => $newAdminId
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create admin account']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updatePassword($conn) {
    try {
        $adminId = $_POST['admin_id'];
        $newPassword = $_POST['password'];
        
        // Validation
        if (empty($adminId) || empty($newPassword)) {
            echo json_encode(['success' => false, 'message' => 'Admin ID and password are required']);
            return;
        }
        
        if (strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
            return;
        }
        
        // Check if admin exists
        $checkSql = "SELECT id FROM adminpanel WHERE id = :id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bindParam(':id', $adminId);
        $checkStmt->execute();
        
        if (!$checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Admin not found']);
            return;
        }
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $sql = "UPDATE adminpanel SET password = :password WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':id', $adminId);
        
        if ($stmt->execute()) {
            // Log the password update
            error_log("Admin password updated: ID {$adminId} by admin ID: {$_SESSION['admin_id']}");
            
            echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update password']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function deleteAdmin($conn) {
    try {
        $adminId = $_POST['admin_id'];
        
        // Validation
        if (empty($adminId)) {
            echo json_encode(['success' => false, 'message' => 'Admin ID is required']);
            return;
        }
        
        // Prevent deleting self
        if ($adminId == $_SESSION['admin_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
            return;
        }
        
        // Check if admin exists
        $checkSql = "SELECT id, username, email FROM adminpanel WHERE id = :id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bindParam(':id', $adminId);
        $checkStmt->execute();
        $admin = $checkStmt->fetch();
        
        if (!$admin) {
            echo json_encode(['success' => false, 'message' => 'Admin not found']);
            return;
        }
        
        // Delete admin
        $sql = "DELETE FROM adminpanel WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $adminId);
        
        if ($stmt->execute()) {
            // Log the deletion
            error_log("Admin deleted: {$admin['username']} ({$admin['email']}) by admin ID: {$_SESSION['admin_id']}");
            
            echo json_encode(['success' => true, 'message' => 'Admin account deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete admin account']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?> 
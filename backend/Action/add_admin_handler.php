<?php
session_start();
require_once '../Database/db.php';

// Check if user is logged in and is main admin
if (!isset($_SESSION['admin']) || $_SESSION['admin_role'] !== 'main_admin') {
    header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("Access denied. Main admin privileges required."));
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("Invalid request method."));
    exit();
}

// Get form data
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$role = $_POST['role'] ?? '';

// Validation
$errors = [];

if (empty($username)) {
    $errors[] = "Username is required";
}

if (empty($email)) {
    $errors[] = "Email is required";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format";
}

if (empty($password)) {
    $errors[] = "Password is required";
} elseif (strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters long";
}

// Password confirmation validation
if (!empty($confirmPassword) && $password !== $confirmPassword) {
    $errors[] = "Passwords do not match";
}

if (empty($role) || !in_array($role, ['admin', 'main_admin'])) {
    $errors[] = "Valid role is required";
}

// If there are validation errors, redirect back with error and preserve form data
if (!empty($errors)) {
    $errorMessage = implode(", ", $errors);
    $params = http_build_query([
        'error' => $errorMessage,
        'username' => $username,
        'email' => $email,
        'role' => $role
    ]);
    header("Location: ../../frontend/add_admin.php?" . $params);
    exit();
}

try {
    $database = new Database();
    $conn = $database->createConnection();

    // Check if email already exists
    $checkSql = "SELECT id FROM adminpanel WHERE email = :email";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindParam(':email', $email);
    $checkStmt->execute();

    if ($checkStmt->fetch()) {
        $params = http_build_query([
            'error' => 'Email already exists',
            'username' => $username,
            'email' => $email,
            'role' => $role
        ]);
        header("Location: ../../frontend/add_admin.php?" . $params);
        exit();
    }

    // Check if username already exists
    $checkSql = "SELECT id FROM adminpanel WHERE username = :username";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindParam(':username', $username);
    $checkStmt->execute();

    if ($checkStmt->fetch()) {
        $params = http_build_query([
            'error' => 'Username already exists',
            'username' => $username,
            'email' => $email,
            'role' => $role
        ]);
        header("Location: ../../frontend/add_admin.php?" . $params);
        exit();
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new admin with proper status and timestamps
    $sql = "INSERT INTO adminpanel (username, email, password, role, status, created_at) VALUES (:username, :email, :password, :role, 'active', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->bindParam(':role', $role);

    if ($stmt->execute()) {
        $newAdminId = $conn->lastInsertId();

        // Log the creation
        error_log("New admin created via PHP form: ID {$newAdminId}, Username: {$username}, Email: {$email}, Role: {$role} by admin ID: {$_SESSION['admin_id']}");

        // Redirect back with success message
        header("Location: ../../frontend/super_admin_dashboard.php?success=" . urlencode("Admin account created successfully! Username: {$username}, Role: {$role}"));
        exit();
    } else {
        header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("Failed to create admin account. Please try again."));
        exit();
    }

} catch (PDOException $e) {
    error_log("Database error in add_admin_handler.php: " . $e->getMessage());
    header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("Database error occurred. Please try again."));
    exit();
} catch (Exception $e) {
    error_log("General error in add_admin_handler.php: " . $e->getMessage());
    header("Location: ../../frontend/super_admin_dashboard.php?error=" . urlencode("An error occurred. Please try again."));
    exit();
}
?>
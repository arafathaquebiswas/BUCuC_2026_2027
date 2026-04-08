<?php
session_start();
if (!isset($_SESSION["admin"])) {
    header("Location: admin-login.php");
    exit();
}

require_once '../backend/Database/db.php';

$message = '';
$error = '';
$admin_id = $_SESSION['admin_id'];

$database = new Database();
$conn = $database->createConnection();

// Fetch current admin info
$sql = "SELECT * FROM adminpanel WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die("Admin session invalid or record not found.");
}

// Handle POST request for updating info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Basic validation
    if (empty($username) || empty($email)) {
        $error = "Username and Email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if username/email already exists for OTHER users
        $checkSql = "SELECT id FROM adminpanel WHERE (username = :username OR email = :email) AND id != :id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':username' => $username, ':email' => $email, ':id' => $admin_id]);

        if ($checkStmt->rowCount() > 0) {
            $error = "Username or Email already taken by another admin.";
        } else {
            // Proceed with update
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $updateSql = "UPDATE adminpanel SET username = :username, email = :email, password = :password WHERE id = :id";
                $params = [':username' => $username, ':email' => $email, ':password' => $hashed_password, ':id' => $admin_id];
            } else {
                $updateSql = "UPDATE adminpanel SET username = :username, email = :email WHERE id = :id";
                $params = [':username' => $username, ':email' => $email, ':id' => $admin_id];
            }

            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt->execute($params)) {
                $_SESSION['username'] = $username; // Update session username
                $message = "Profile updated successfully.";
                // Refresh local admin data
                $admin['username'] = $username;
                $admin['email'] = $email;
            } else {
                $error = "Failed to update profile.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - BUCuC</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="AdminCss/Dashboard.css">
    <style>
        .profile-container {
            padding: 4rem 2rem;
            color: white;
            display: flex;
            justify-content: center;
        }

        .profile-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 25px;
            padding: 3rem;
            max-width: 700px;
            width: 100%;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }

        .form-control {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.8rem 1.2rem;
            border-radius: 12px;
        }

        .form-control:focus {
            background: rgba(0, 0, 0, 0.4);
            color: white;
            border-color: #0d6efd;
            box-shadow: 0 0 10px rgba(13, 110, 253, 0.2);
        }

        .btn-update {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 1rem 2rem;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
            color: white;
        }

        .back-btn {
            position: fixed;
            top: 2rem;
            left: 2rem;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            transform: translateX(-5px);
        }

        .avatar-section {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .large-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #ced4da;
        }
    </style>
</head>

<body style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh;">

    <?php
    $dashboardLink = ($admin['role'] === 'main_admin') ? 'super_admin_dashboard.php' : 'admin_dashboard.php';
    ?>
    <a href="<?= $dashboardLink ?>" class="back-btn">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>

    <div class="profile-container">
        <div class="profile-card">
            <div class="avatar-section">
                <div class="large-avatar">
                    <?= strtoupper(substr($admin['username'], 0, 1)) ?>
                </div>
                <h3><?= htmlspecialchars($admin['username']) ?></h3>
                <p class="badge <?= $admin['role'] === 'main_admin' ? 'bg-danger' : 'bg-primary' ?>">
                    <?= $admin['role'] === 'main_admin' ? 'Main Admin' : 'Staff Admin' ?>
                </p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert"
                    style="background: rgba(40, 167, 69, 0.2); border: 1px solid rgba(40, 167, 69, 0.3); color: #51cf66;">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                        style="filter: brightness(0) invert(1);"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert"
                    style="background: rgba(220, 53, 69, 0.2); border: 1px solid rgba(220, 53, 69, 0.3); color: #ff6b6b;">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                        style="filter: brightness(0) invert(1);"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="profile.php">
                <input type="hidden" name="update_profile" value="1">

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label><i class="fas fa-user me-2"></i>Username</label>
                        <input type="text" name="username" class="form-control" required
                            value="<?= htmlspecialchars($admin['username']) ?>">
                    </div>
                    <div class="col-md-6 mb-4">
                        <label><i class="fas fa-envelope me-2"></i>Email Address</label>
                        <input type="email" name="email" class="form-control" required
                            value="<?= htmlspecialchars($admin['email']) ?>">
                    </div>
                </div>

                <hr style="border-color: rgba(255,255,255,0.1); margin: 1rem 0 2rem;">
                <h5 class="mb-4 text-info"><i class="fas fa-key me-2"></i>Change Password</h5>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label>New Password</label>
                        <input type="password" name="password" class="form-control"
                            placeholder="Leave blank to keep current">
                    </div>
                    <div class="col-md-6 mb-4">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control"
                            placeholder="Confirm new password">
                    </div>
                </div>

                <div class="d-grid mt-3">
                    <button type="submit" class="btn btn-update">
                        <i class="fas fa-save me-2"></i>Update Profile Information
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/bootstrap.min.js"></script>
</body>

</html>
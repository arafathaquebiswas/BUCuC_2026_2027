<?php
session_start();
require_once '../backend/Database/db.php';

// Check permissions - Strictly Main Admin
if (!isset($_SESSION["admin"]) || $_SESSION['admin_role'] !== 'main_admin') {
    header("Location: admin_dashboard.php");
    exit();
}

$message = '';
$error = '';
$edit_admin = null;

$database = new Database();
$conn = $database->createConnection();

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_admin'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = $_POST['role'];

        // Only main_admin can add admins
        if ($_SESSION['admin_role'] !== 'main_admin') {
            $error = "Unauthorized to add new admins.";
        } else {
            if ($password !== $confirm_password) {
                $error = "Passwords do not match.";
            } else {
                // Check if username or email exists
                $checkSql = "SELECT id FROM adminpanel WHERE username = :username OR email = :email";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->execute([':username' => $username, ':email' => $email]);

                if ($checkStmt->rowCount() > 0) {
                    $error = "Username or Email already exists.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO adminpanel (username, email, password, role, status) VALUES (:username, :email, :password, :role, 'active')";
                    $stmt = $conn->prepare($sql);
                    if ($stmt->execute([':username' => $username, ':email' => $email, ':password' => $hashed_password, ':role' => $role])) {
                        $message = "Admin added successfully.";
                    } else {
                        $error = "Failed to add admin.";
                    }
                }
            }
        }
    } elseif (isset($_POST['update_admin'])) {
        $id = $_POST['admin_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $password = $_POST['password'];

        // Validation: Check if another admin has same username/email
        $checkSql = "SELECT id FROM adminpanel WHERE (username = :username OR email = :email) AND id != :id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':username' => $username, ':email' => $email, ':id' => $id]);

        if ($checkStmt->rowCount() > 0) {
            $error = "Username or Email already exists.";
        } elseif ($_SESSION['admin_role'] !== 'main_admin' && $id != $_SESSION['admin_id']) {
            $error = "Unauthorized to update this admin.";
        } else {
            // For regular admins, preserve their existing role
            if ($_SESSION['admin_role'] !== 'main_admin') {
                $role = $_SESSION['admin_role'];
            }

            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE adminpanel SET username = :username, email = :email, role = :role, password = :password WHERE id = :id";
                $params = [':username' => $username, ':email' => $email, ':role' => $role, ':password' => $hashed_password, ':id' => $id];
            } else {
                $sql = "UPDATE adminpanel SET username = :username, email = :email, role = :role WHERE id = :id";
                $params = [':username' => $username, ':email' => $email, ':role' => $role, ':id' => $id];
            }
            $stmt = $conn->prepare($sql);
            if ($stmt->execute($params)) {
                $message = "Admin updated successfully.";
                // Clear query params
                header("Location: admin_management.php?success=" . urlencode($message));
                exit();
            } else {
                $error = "Failed to update admin.";
            }
        }

    } elseif (isset($_POST['delete_admin'])) {
        $id = $_POST['admin_id'];

        // Only main_admin can delete admins
        if ($_SESSION['admin_role'] !== 'main_admin') {
            $error = "Unauthorized to delete admins.";
        } elseif ($id == $_SESSION['admin_id']) {
            $error = "You cannot delete yourself.";
        } else {
            $sql = "DELETE FROM adminpanel WHERE id = :id";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([':id' => $id])) {
                $message = "Admin deleted successfully.";
            } else {
                $error = "Failed to delete admin.";
            }
        }
    }
}

// Handle Edit Mode
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];

    // Regular admins can only edit themselves
    if ($_SESSION['admin_role'] !== 'main_admin' && $edit_id != $_SESSION['admin_id']) {
        $edit_id = $_SESSION['admin_id'];
    }

    $sql = "SELECT * FROM adminpanel WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $edit_id]);
    $edit_admin = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($_SESSION['admin_role'] !== 'main_admin') {
    // Regular admins default to editing themselves
    $edit_id = $_SESSION['admin_id'];
    $sql = "SELECT * FROM adminpanel WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $edit_id]);
    $edit_admin = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all admins
$sql = "SELECT * FROM adminpanel ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - BUCuC</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="AdminCss/Dashboard.css">
    <style>
        .management-container {
            padding: 2rem;
            color: white;
        }

        .form-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .table-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 2rem;
        }

        .form-control,
        .form-select {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }

        .form-control:focus,
        .form-select:focus {
            background: rgba(0, 0, 0, 0.3);
            color: white;
            border-color: #0d6efd;
            box-shadow: none;
        }

        .table {
            color: white;
        }

        .table thead th {
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            color: #ddd;
        }

        .table tbody td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-action {
            margin-right: 5px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .back-btn {
            text-decoration: none;
            color: white;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 5px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
    </style>
</head>

<body style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh;">

    <div class="container management-container">
        <div class="page-header">
            <h2><i class="fas fa-users-cog me-2"></i>Admin Management</h2>
            <a href="super_admin_dashboard.php" class="back-btn"><i class="fas fa-arrow-left me-2"></i>Back to
                Dashboard</a>
        </div>

        <?php if ($message || isset($_GET['success'])): ?>
            <div class="alert alert-success"
                style="background: rgba(40, 167, 69, 0.2); border: 1px solid rgba(40, 167, 69, 0.3); color: #51cf66;">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($message ?: $_GET['success']) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Add/Edit Form Section -->
        <div class="form-section">
            <h4 class="mb-4">
                <?= $edit_admin ? '<i class="fas fa-edit me-2"></i>Edit Admin' : '<i class="fas fa-plus me-2"></i>Add New Admin' ?>
            </h4>

            <form method="POST" action="admin_management.php">
                <?php if ($edit_admin): ?>
                    <input type="hidden" name="admin_id" value="<?= $edit_admin['id'] ?>">
                    <input type="hidden" name="update_admin" value="1">
                <?php else: ?>
                    <input type="hidden" name="add_admin" value="1">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required
                            value="<?= $edit_admin ? htmlspecialchars($edit_admin['username']) : '' ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required
                            value="<?= $edit_admin ? htmlspecialchars($edit_admin['email']) : '' ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="admin" <?= ($edit_admin && $edit_admin['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="main_admin" <?= ($edit_admin && $edit_admin['role'] === 'main_admin') ? 'selected' : '' ?>>Main Admin</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password
                            <?= $edit_admin ? '(Leave blank to keep current)' : '' ?></label>
                        <input type="password" name="password" class="form-control" <?= $edit_admin ? '' : 'required' ?>
                            minlength="6">
                    </div>
                </div>

                <?php if (!$edit_admin): ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mt-3">
                    <button type="submit" class="btn btn-success">
                        <?= $edit_admin ? '<i class="fas fa-save me-2"></i>Update Admin' : '<i class="fas fa-plus-circle me-2"></i>Create Admin' ?>
                    </button>
                    <?php if ($edit_admin): ?>
                        <a href="admin_management.php" class="btn btn-secondary ms-2">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Admin Table Section -->
        <div class="table-section">
            <h4 class="mb-4"><i class="fas fa-list me-2"></i>Existing Admins</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?= $admin['id'] ?></td>
                                <td><?= htmlspecialchars($admin['username']) ?></td>
                                <td><?= htmlspecialchars($admin['email']) ?></td>
                                <td>
                                    <span class="badge <?= $admin['role'] === 'main_admin' ? 'bg-danger' : 'bg-primary' ?>">
                                        <?= $admin['role'] === 'main_admin' ? 'Main Admin' : 'Admin' ?>
                                    </span>
                                </td>
                                <td>
                                    <span
                                        class="badge <?= $admin['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= ucfirst($admin['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('Y-m-d', strtotime($admin['created_at'])) ?></td>
                                <td>
                                    <div class="d-flex">
                                        <a href="admin_management.php?edit_id=<?= $admin['id'] ?>"
                                            class="btn btn-sm btn-primary btn-action" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                            <form method="POST" action="admin_management.php"
                                                onsubmit="return confirm('Are you sure you want to delete this admin?');">
                                                <input type="hidden" name="delete_admin" value="1">
                                                <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger btn-action" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-muted">
                <small>Total Admins: <?= count($admins) ?></small>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.min.js"></script>
    <!-- Simple JS just for confirmation dialogs if allowed, otherwise removed as per strict instructions -->
</body>

</html>
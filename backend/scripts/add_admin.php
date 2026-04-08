<?php
session_start();
if (!isset($_SESSION["admin"]) || $_SESSION['admin_role'] !== 'main_admin') {
    header("Location: ../../frontend/admin-login.php");
    exit();
}

require_once 'Database/db.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Admin - BRAC University Cultural Club</title>

    <!-- Bootstrap CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link href="css/templatemo-festava-live.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <link rel="stylesheet" href="AdminCss/Dashboard.css">

    <style>
        .form-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .admin-form-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }

        .form-title {
            color: #fff;
            font-weight: 600;
            margin-bottom: 30px;
            text-align: center;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            color: #fff;
            padding: 12px 15px;
            margin-bottom: 15px;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: #fff;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
            color: #fff;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .form-label {
            color: #fff;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .btn-create {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            border-radius: 25px;
            padding: 15px 40px;
            font-weight: 600;
            font-size: 16px;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }

        .btn-create:hover {
            background: linear-gradient(45deg, #20c997, #28a745);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }

        .btn-back {
            background: linear-gradient(45deg, #6c757d, #495057);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            margin-top: 15px;
            width: 100%;
        }

        .btn-back:hover {
            background: linear-gradient(45deg, #495057, #6c757d);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }

        .alert {
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            color: #51cf66;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #f8d7da;
            border-left: 4px solid #dc3545;
        }

        .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            color: #fff;
            padding: 12px 15px;
            margin-bottom: 15px;
        }

        .form-select:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: #fff;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
            color: #fff;
        }

        .form-select option {
            background: #495057;
            color: #fff;
        }

        .required-indicator {
            color: #ffc107;
            font-weight: bold;
        }

        .info-box {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #17a2b8;
        }

        .info-box h6 {
            color: #fff;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .info-box small {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.5;
        }
    </style>
</head>

<body>
    <div class="form-container">
        <div class="admin-form-card">
            <h2 class="form-title">
                <i class="fas fa-user-plus me-3"></i>
                Add New Admin Account
            </h2>

            <!-- Information Box -->
            <div class="info-box">
                <h6><i class="fas fa-info-circle me-2"></i>Admin Account Information</h6>
                <small>
                    Create a new admin account for the adminpanel table. All fields are required.
                    The password will be securely hashed and stored in the database.
                    <br><strong>Current User:</strong> <?= htmlspecialchars($_SESSION['username']) ?>
                    (<?= $_SESSION['admin_role'] ?>)
                </small>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($_GET['success']) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <!-- Add Admin Form -->
            <form method="POST" action="Action/add_admin_handler.php" novalidate>
                <div class="row">
                    <div class="col-md-6">
                        <label for="username" class="form-label">
                            <i class="fas fa-user me-2"></i>Admin Username <span class="required-indicator">*</span>
                        </label>
                        <input type="text" class="form-control" id="username" name="username"
                            placeholder="Enter unique username" required autocomplete="username"
                            value="<?= htmlspecialchars($_GET['username'] ?? '') ?>">
                        <small class="text-muted">Must be unique, no spaces allowed</small>
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-2"></i>Email Address <span class="required-indicator">*</span>
                        </label>
                        <input type="email" class="form-control" id="email" name="email"
                            placeholder="admin@university.edu" required autocomplete="email"
                            value="<?= htmlspecialchars($_GET['email'] ?? '') ?>">
                        <small class="text-muted">Must be a valid email address</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-2"></i>Password <span class="required-indicator">*</span>
                        </label>
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Min 6 characters" required minlength="6" autocomplete="new-password">
                        <small class="text-muted">Minimum 6 characters, will be securely hashed</small>
                    </div>

                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock me-2"></i>Confirm Password <span class="required-indicator">*</span>
                        </label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                            placeholder="Re-enter password" required minlength="6" autocomplete="new-password">
                        <small class="text-muted">Must match the password above</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <label for="role" class="form-label">
                            <i class="fas fa-user-tag me-2"></i>Admin Role <span class="required-indicator">*</span>
                        </label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select admin role...</option>
                            <option value="admin" <?= (($_GET['role'] ?? '') === 'admin') ? 'selected' : '' ?>>
                                Admin - Standard admin privileges
                            </option>
                            <option value="main_admin" <?= (($_GET['role'] ?? '') === 'main_admin') ? 'selected' : '' ?>>
                                Main Admin - Full admin privileges
                            </option>
                        </select>
                        <small class="text-muted">Main Admin can manage other admins, Admin has standard access</small>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <button type="submit" class="btn btn-create">
                            <i class="fas fa-plus-circle me-2"></i>
                            Create Admin Account
                        </button>

                        <a href="admin_dashboard.php" class="btn btn-back">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            </form>

            <!-- Table Structure Info -->
            <div class="info-box mt-4">
                <h6><i class="fas fa-database me-2"></i>Database Table: adminpanel</h6>
                <small>
                    <strong>Fields:</strong> id (auto), username, email, password (hashed), role, status (auto: active),
                    created_at (auto)
                    <br><strong>Connection:</strong> Database/db.php
                    <br><strong>Processing:</strong> Pure PHP with validation and security
                </small>
            </div>
        </div>
    </div>

    <!-- Client-side validation -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');

            // Password confirmation validation
            function validatePassword() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }

            password.addEventListener('input', validatePassword);
            confirmPassword.addEventListener('input', validatePassword);

            // Form submission validation
            form.addEventListener('submit', function (e) {
                if (password.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    confirmPassword.focus();
                    return false;
                }

                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Admin...';
                submitBtn.disabled = true;
            });

            // Auto-clear success/error messages after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>

</html>
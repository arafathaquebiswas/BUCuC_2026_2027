<?php
session_start();
if (!isset($_SESSION["admin"])) {
    header("Location: admin-login.php");
    exit();
}

$dashboardLink = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'main_admin') ? 'super_admin_dashboard.php' : 'admin_dashboard.php';

require_once '../backend/Database/db.php';

// Handle success and error messages from URL parameters
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    $success_message = urldecode($_GET['success']);
}

if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}

// Fetch members data
try {
    $database = new Database();
    $pdo = $database->createConnection();

    // Get all approved members from dedicated table
    $stmt = $pdo->query("SELECT * FROM members ORDER BY created_at DESC");
    $approvedMembers = $stmt->fetchAll();

    $totalApproved = count($approvedMembers);

} catch (Exception $e) {
    $approvedMembers = [];
    $totalApproved = 0;
    $error_message = "Error fetching data: " . $e->getMessage();
}

/**
 * Function to get initials from full name
 */
function getInitials($name)
{
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2); // Limit to 2 characters
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Members - BRAC University Cultural Club</title>

    <!-- Bootstrap CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link href="css/templatemo-festava-live.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <link rel="stylesheet" href="AdminCss/Dashboard.css">
    <link rel="icon" type="image/png" href="images/logopng.png">

    <style>
        .applications-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 2rem 0;
        }

        .applications-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin: 0 1rem;
        }

        .applications-title {
            color: #fff;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .applications-subtitle {
            color: #ccc;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            text-align: center;
        }

        .back-btn-delicate {
            display: inline-flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .back-btn-delicate:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateX(-5px);
        }

        .applications-table {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .table-dark {
            --bs-table-bg: transparent;
        }

        .table-dark thead th {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            font-weight: 600;
        }

        .table-dark td {
            border-color: rgba(255, 255, 255, 0.1);
            color: #ccc;
            vertical-align: middle;
        }

        .table-dark tbody tr:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .truncate {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .btn-delete {
            background: linear-gradient(45deg, #dc3545, #e74c3c);
            border: none;
            border-radius: 25px;
            padding: 0.5rem 1rem;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(220, 53, 69, 0.3);
            color: white;
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .status-accepted {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: #fff;
        }

        @media (max-width: 768px) {
            .applications-card {
                margin: 0 0.5rem;
                padding: 1.5rem;
            }

            .applications-title {
                font-size: 2rem;
            }

            .table-responsive {
                font-size: 0.85rem;
            }

            .btn-delete {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <!-- Applications Container -->
    <div class="applications-container">
        <div class="applications-card">
            <a href="<?php echo $dashboardLink; ?>" class="back-btn-delicate">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <h1 class="applications-title">
                <i class="fas fa-user-check mb-3"></i><br>
                Approved Members
            </h1>
            <p class="applications-subtitle">
                Finally selected and registered members of BUCuC
            </p>

            <!-- Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert"
                    style="background: rgba(40, 167, 69, 0.2); border: 1px solid rgba(40, 167, 69, 0.3); color: #51cf66;">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                        style="filter: brightness(0) invert(1);"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert"
                    style="background: rgba(220, 53, 69, 0.2); border: 1px solid rgba(220, 53, 69, 0.3); color: #ff6b6b;">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                        style="filter: brightness(0) invert(1);"></button>
                </div>
            <?php endif; ?>

            <!-- Action Section -->
            <div class="action-section text-center mb-4"
                style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 15px; padding: 1.5rem;">
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="../backend/Action/export_members_excel.php?status=Accepted" class="btn btn-export-excel"
                        style="background: linear-gradient(45deg, #28a745, #20c997); border: none; border-radius: 30px; padding: 1rem 2rem; color: white; font-weight: 600; text-decoration: none; transition: all 0.3s ease;">
                        <i class="fas fa-file-excel me-2"></i>Download Member Information (Excel)
                    </a>
                </div>
            </div>

            <!-- Members Table -->
            <div class="applications-table">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0" id="membersTable">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>University ID</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Phone</th>
                                <th>Priorities</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="membersTableBody">
                            <?php if (!empty($approvedMembers)): ?>
                                <?php foreach ($approvedMembers as $row): ?>
                                    <tr>
                                        <td class="truncate"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['university_id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['gsuite_email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($row['firstPriority'] . ', ' . $row['secondPriority']); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-accepted">
                                                <i class="fas fa-check-circle me-1"></i>Approved Member
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'main_admin'): ?>
                                                <form method="POST" action="handle_application.php" style="display:inline;"
                                                    onsubmit="return confirm('Are you sure you want to PERMANENTLY DELETE <?php echo addslashes($row['full_name']); ?> from the members table?\\n\\nWARNING: This action cannot be undone!')">
                                                    <input type="hidden" name="action" value="delete_approved">
                                                    <input type="hidden" name="member_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="redirect_to" value="approved_members.php">
                                                    <button type="submit" class="btn btn-delete btn-sm">
                                                        <i class="fas fa-trash-alt me-1"></i>Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="fas fa-lock me-1"></i>Super Admin Only</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-users-slash fa-3x mb-3"></i><br>
                                        No approved members found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="js/bootstrap.min.js"></script>

</body>

</html>
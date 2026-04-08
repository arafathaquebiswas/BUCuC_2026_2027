<?php
session_start();
if (!isset($_SESSION["admin"])) {
    header("Location: admin-login.php");
    exit();
}

// Strict check for Main Admin
if ($_SESSION['admin_role'] !== 'main_admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// Database connection to fetch dashboard stats
require_once '../backend/Database/db.php';

// Function to get latest dashboard data
function getDashboardStats()
{
    try {
        $database = new Database();
        $conn = $database->createConnection();

        // Get the latest record from dashboardmanagement table for most stats
        $sql = "SELECT totalmembers, completedevents, others, mg_spring, mg_summer, mg_fall, gd_male, gd_female
        FROM dashboardmanagement
        ORDER BY id DESC
        LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get real-time pending applications count from pending_applications table
        $pendingSql = "SELECT COUNT(*) as pending_count FROM pending_applications";
        $pendingStmt = $conn->prepare($pendingSql);
        $pendingStmt->execute();
        $pendingResult = $pendingStmt->fetch(PDO::FETCH_ASSOC);
        $pendingApplications = (int) $pendingResult['pending_count'];

        // Get Dynamic Chart Data
        $chartSql = "SELECT label, value FROM chart_data WHERE category = 'member_growth' ORDER BY id ASC";
        $chartStmt = $conn->prepare($chartSql);
        $chartStmt->execute();
        $memberGrowthData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

        // Format for JS
        $formattedMemberGrowth = [];
        if (!empty($memberGrowthData)) {
            foreach ($memberGrowthData as $row) {
                $formattedMemberGrowth[] = [
                    'label' => $row['label'],
                    'value' => (int) $row['value']
                ];
            }
        } else {
            // Fallback default
            $formattedMemberGrowth = [
                ['label' => 'Fall 2024', 'value' => 1180],
                ['label' => 'Spring 2025', 'value' => 1250],
                ['label' => 'Summer 2025', 'value' => 0]
            ];
        }

        // Get real-time shortlisted applications count from shortlisted_members table
        $shortlistedSql = "SELECT COUNT(*) as shortlisted_count FROM shortlisted_members";
        $shortlistedStmt = $conn->prepare($shortlistedSql);
        $shortlistedStmt->execute();
        $shortlistedResult = $shortlistedStmt->fetch(PDO::FETCH_ASSOC);
        $shortlistedCount = (int) $shortlistedResult['shortlisted_count'];

        // Get real-time approved members count from members table
        $approvedSql = "SELECT COUNT(*) as approved_count FROM members";
        $approvedStmt = $conn->prepare($approvedSql);
        $approvedStmt->execute();
        $approvedResult = $approvedStmt->fetch(PDO::FETCH_ASSOC);
        $approvedCount = (int) $approvedResult['approved_count'];

        if ($result) {
            $totalMembers = (int) $result['totalmembers'] + $approvedCount; // Add real-time approved members
            $completedEvents = (int) $result['completedevents'];
            $others = $shortlistedCount; // Use real-time shortlisted count
            $gd_male = (int) $result['gd_male'];
            $gd_female = (int) $result['gd_female'];
        } else {
            // Return default values if no data found
            $totalMembers = $approvedCount;
            $completedEvents = 0;
            $others = $shortlistedCount;
            $gd_male = 0;
            $gd_female = 0;
        }

        return [
            'total_members' => $totalMembers,
            'pending_applications' => $pendingApplications,
            'completed_events' => $completedEvents,
            'others' => $others,
            'member_growth' => $formattedMemberGrowth, // Array of {label, value}
            'gd_male' => $gd_male,
            'gd_female' => $gd_female,
        ];

    } catch (Exception $e) {
        error_log("Error fetching dashboard stats: " . $e->getMessage());
        // Return default values on error
        return [
            'total_members' => 0,
            'pending_applications' => 0,
            'completed_events' => 0,
            'others' => 0,
            'member_growth' => [],
            'gd_male' => 0,
            'gd_female' => 0,
        ];
    }
}

// Get the dashboard data
$dashboardData = getDashboardStats();

// Inject JS variables
echo "<script>
    window.dashboardStats = {
        memberGrowth: " . json_encode($dashboardData['member_growth']) . ",
        genderDistribution: { male: " . $dashboardData['gd_male'] . ", female: " . $dashboardData['gd_female'] . " }
    };
</script>";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BRAC University Cultural Club</title>

    <!-- Bootstrap CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link href="css/templatemo-festava-live.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" href="AdminCss/Dashboard.css">
    <link rel="icon" type="image/png" href="images/logopng.png">
    <style>
        .signup-toggle-container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            min-width: 250px;
        }

        .signup-toggle-container .form-check-input {
            width: 3rem;
            height: 1.5rem;
            background-color: #dc3545;
            border-color: #dc3545;
            border-radius: 2rem;
        }

        .signup-toggle-container .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }

        .signup-toggle-container .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }

        .signup-toggle-container .form-check-label {
            font-weight: 600;
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .signup-toggle-container {
                min-width: 200px;
                margin-top: 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- Mobile Toggle Button -->
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="images/logo.png" alt="BUCuC Logo">
                <h3>BUCuC</h3>
            </div>
            <p class="sidebar-subtitle">Super Admin Panel</p>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Dashboard</div>
                <a href="#" class="nav-item active" data-section="overview">

                    Overview
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <a href="member_types.php" class="nav-item">
                    Members
                </a>
                <a href="pending_applications.php" class="nav-item">
                    Applications
                    <span class="badge bg-warning ms-2" id="pendingApplicationsBadge" style="display: none;">0</span>
                </a>
                <a href="shortlisted_members.php" class="nav-item">
                    Shortlisted Members
                </a>
                <a href="approved_members.php" class="nav-item">
                    Approved Members
                </a>
                <a href="dashboard_updates.php" class="nav-item">
                    Dashboard Updates
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Settings</div>
                <a href="admin-signup-control.php" class="nav-item">

                    Signup Control
                </a>
                <a href="profile.php" class="nav-item">
                    My Profile
                </a>

                <?php if ($_SESSION['admin_role'] === 'main_admin'): ?>
                    <a href="admin_management.php" class="nav-item">

                        Admin Management
                    </a>
                <?php endif; ?>

                <a href="logout.php" class="nav-item" id="logoutBtn">

                    Logout
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-left">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                        <h1>Super Admin Dashboard</h1>
                        <p>Welcome back, <?php echo $_SESSION['username']; ?>! Here's what's happening with your club.
                        </p>
                    </div>

                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show mt-2" role="alert"
                        style="background: rgba(40, 167, 69, 0.2); border: 1px solid rgba(40, 167, 69, 0.3); color: #51cf66;">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"
                            style="filter: brightness(0) invert(1);"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show mt-2" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="header-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search..." id="searchInput">
                </div>

                <a href="profile.php" class="user-profile" id="userProfile" style="text-decoration: none;">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                    <span>
                        <?php
                        $nameParts = explode(" ", $_SESSION["username"]);
                        echo htmlspecialchars($nameParts[0]);
                        ?>
                    </span>
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card" data-stat="members">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number" id="totalMembersCount"><?php echo $dashboardData['total_members'] ?></div>
                <div class="stat-label">Total Members</div>
            </div>

            <div class="stat-card" data-stat="applications">
                <div class="stat-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-number" id="pendingApplicationsCount"><?= $dashboardData['pending_applications'] ?>
                </div>
                <div class="stat-label">Pending Applications</div>
                <div class="stat-change" id="applicationsChange">
                    <?php if ($dashboardData['pending_applications'] > 0): ?>
                        <i class="fas fa-clock"></i>
                        Needs Attention
                    <?php else: ?>
                        <i class="fas fa-check"></i>
                        All Reviewed
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card" data-stat="completed-events">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-number" id="completedEventsCount">
                    <?= htmlspecialchars($dashboardData['completed_events']) ?>
                </div>
                <div class="stat-label">Completed Events</div>
                <div class="stat-change positive">
                    <i class="fas fa-trophy"></i>
                    Successfully Organized
                </div>
            </div>

            <div class="stat-card" data-stat="others">
                <div class="stat-icon">
                    <i class="fas fa-list-ul"></i>
                </div>
                <div class="stat-number" id="othersCount"><?= htmlspecialchars($dashboardData['others']) ?></div>
                <div class="stat-label">Shortlisted Members</div>
                <div class="stat-change positive">
                    <i class="fas fa-clock"></i>
                    Awaiting Final Approval
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Member Growth</h3>
                    <div class="chart-controls">
                        <button class="active" data-period="1Y">1Y</button>
                        <button data-period="2Y">2Y</button>
                        <button data-period="3Y">3Y</button>
                    </div>
                </div>
                <div style="position: relative; height: 420px; width: 100%;">
                    <canvas id="memberGrowthChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Gender Distribution Chart</h3>
                </div>
                <div style="position: relative; height: 420px; width: 100%;">
                    <canvas id="genderDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <?php if ($_SESSION['admin_role'] === 'main_admin'): ?>
            <!-- Get admin data directly from PHP -->
            <?php
            try {
                $database = new Database();
                $conn = $database->createConnection();
                $sql = "SELECT id, username, email, role, status, created_at FROM adminpanel ORDER BY created_at DESC";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $admins = [];
                error_log("Error fetching admins: " . $e->getMessage());
            }
            ?>

            <div class="admin-management-section" id="admin-management-section" style="display: none; margin-top: 2rem;">
                <div class="chart-header">
                    <h3 class="chart-title">Admin Account Management</h3>
                    <div class="d-flex gap-2">
                        <button class="btn btn-secondary btn-sm" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <a href="add_admin.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Admin
                        </a>
                    </div>
                </div>

                <div class="admin-list-container">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover" id="adminTable">
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
                            <tbody id="adminTableBody">
                                <?php if (empty($admins)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            <i class="fas fa-users-slash me-2"></i>
                                            No admin accounts found in database
                                            <br><small>Use "Add New Admin" to create the first admin account</small>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($admins as $admin): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($admin['id']) ?></td>
                                            <td><?= htmlspecialchars($admin['username']) ?></td>
                                            <td><?= htmlspecialchars($admin['email']) ?></td>
                                            <td>
                                                <span
                                                    class="badge <?= $admin['role'] === 'main_admin' ? 'bg-danger' : 'bg-primary' ?>">
                                                    <?= $admin['role'] === 'main_admin' ? 'Main Admin' : 'Admin' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge <?= $admin['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                                    <?= htmlspecialchars(ucfirst($admin['status'])) ?>
                                                </span>
                                            </td>
                                            <td><?= date('Y-m-d', strtotime($admin['created_at'])) ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-warning"
                                                        onclick="editAdmin(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($admin['email'], ENT_QUOTES) ?>')"
                                                        title="Change Password">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                                        <a href="../backend/Action/delete_admin.php?admin_id=<?= $admin['id'] ?>&username=<?= urlencode($admin['username']) ?>&email=<?= urlencode($admin['email']) ?>"
                                                            class="btn btn-sm btn-danger"
                                                            title="Delete Admin: <?= htmlspecialchars($admin['username']) ?>"
                                                            onclick="return confirm('Are you sure you want to delete admin account:\n\nUsername: <?= htmlspecialchars($admin['username']) ?>\nEmail: <?= htmlspecialchars($admin['email']) ?>\n\nThis action cannot be undone!')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Current User</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (count($admins) > 0): ?>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Total admin accounts: <?= count($admins) ?> |
                                Active accounts: <?= count(array_filter($admins, function ($a) {
                                    return $a['status'] === 'active';
                                })) ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="applications-section" id="applications-section" style="display: none; margin-top: 2rem;">
            <div class="chart-header">
                <h3 class="chart-title">Member Applications</h3>
                <div class="d-flex gap-3 align-items-center">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="applicationSystemToggle">
                        <label class="form-check-label text-white" for="applicationSystemToggle">
                            Application System
                        </label>
                    </div>
                    <button class="btn btn-primary" id="refreshApplicationsBtn">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="applications-container">
                <div class="alert alert-info" id="noApplicationsMessage" style="display: none;">
                    <i class="fas fa-info-circle me-2"></i>
                    No pending applications found.
                </div>

                <div class="row" id="applicationsList">
                    <!-- Applications will be loaded here -->
                </div>
            </div>
        </div>

        <!-- Dashboard Update Section -->
        <div class="dashboard-update-section" id="dashboard-update-section" style="display: none; margin-top: 2rem;">
            <div class="chart-header">
                <h3 class="chart-title">Dashboard Data Management</h3>
                <div class="d-flex gap-3 align-items-center">
                    <p class="text-muted mb-0">Update dashboard statistics and data for testing and management purposes
                    </p>


                </div>
            </div>

            <div class="row mt-4">
                <!-- Total Members Update -->
                <div class="col-lg-12 mb-4">
                    <div class="update-card">

                        <div class="update-card-body">
                            <!-- Simple PHP Form - No JavaScript -->
                            <form method="POST" action="../backend/Action/form_handler.php"
                                style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 8px;">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="simple_totalMembers" class="form-label text-light">Initial/Base
                                            Members</label>
                                        <input type="number" class="form-control bg-dark text-light border-secondary"
                                            name="totalMembers" id="simple_totalMembers" placeholder="0" min="0"
                                            value="0" required>
                                        <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">+ Real-time
                                            approved members</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="simple_completedevents" class="form-label text-light">Completed
                                            Events</label>
                                        <input type="number" class="form-control bg-dark text-light border-secondary"
                                            name="completedevents" id="simple_completedevents" placeholder="0" min="0"
                                            value="0" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="simple_others" class="form-label text-light">Others</label>
                                        <input type="number" class="form-control bg-dark text-light border-secondary"
                                            name="others" id="simple_others" placeholder="0" min="0" value="0" required>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-success btn-lg w-100">
                                            <i class="fas fa-save me-2"></i>Submit to Database
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>






            </div>
        </div>
    </div>

    <!-- Admin Management Modals -->
    <?php if ($_SESSION['admin_role'] === 'main_admin'): ?>
        <!-- Add Admin Form (PHP Only) -->
        <div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bg-dark text-light">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title" id="addAdminModalLabel">
                            <i class="fas fa-user-plus me-2"></i>Add New Admin (PHP Only)
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <form method="POST" action="../backend/Action/add_admin_handler.php">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="newAdminName" class="form-label">Admin Name</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary"
                                    id="newAdminName" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="newAdminEmail" class="form-label">Email Address</label>
                                <input type="email" class="form-control bg-dark text-light border-secondary"
                                    id="newAdminEmail" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="newAdminPassword" class="form-label">Password (min 6 chars)</label>
                                <input type="password" class="form-control bg-dark text-light border-secondary"
                                    id="newAdminPassword" name="password" minlength="6" required>
                            </div>
                            <div class="mb-3">
                                <label for="newAdminRole" class="form-label">Role</label>
                                <select class="form-select bg-dark text-light border-secondary" id="newAdminRole"
                                    name="role" required>
                                    <option value="admin">Admin</option>
                                    <option value="main_admin">Main Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Create Admin (PHP)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Admin Modal -->
        <div class="modal fade" id="editAdminModal" tabindex="-1" aria-labelledby="editAdminModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bg-dark text-light">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title" id="editAdminModalLabel">
                            <i class="fas fa-user-edit me-2"></i>Edit Admin Password
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <form id="editAdminForm">
                        <div class="modal-body">
                            <input type="hidden" id="editAdminId" name="admin_id">
                            <div class="mb-3">
                                <label class="form-label">Admin Name</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary"
                                    id="editAdminName" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control bg-dark text-light border-secondary"
                                    id="editAdminEmail" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="editAdminPassword" class="form-label">New Password</label>
                                <input type="password" class="form-control bg-dark text-light border-secondary"
                                    id="editAdminPassword" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control bg-dark text-light border-secondary"
                                    id="confirmPassword" required>
                            </div>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Admin Modal removed - using PHP-only delete confirmation -->
    <?php endif; ?>

    <!-- Dashboard Update Modal -->
    <div class="modal fade" id="dashboardUpdateModal" tabindex="-1" aria-labelledby="dashboardUpdateModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="dashboardUpdateModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Add New Record
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form id="dashboardUpdateForm" method="POST" action="../backend/Action/form_handler.php">
                    <div class="modal-body">
                        <div class="mb-3">

                            <label for="totalMembers" class="form-label text-light">Total Members</label>

                            <input type="number" class="form-control bg-dark text-light border-secondary"
                                name="totalMembers" id="totalMembers" placeholder="0" min="0" value="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="pending_applications" class="form-label text-light">Pending Applications</label>

                            <input type="number" class="form-control bg-dark text-light border-secondary"
                                name="pending_applications" id="pending_applications" placeholder="0" min="0" value="0"
                                required>

                        </div>
                        <div class="mb-3">

                            <label for="completedevents" class="form-label text-light">Completed Events</label>

                            <input type="number" class="form-control bg-dark text-light border-secondary"
                                name="completedevents" id="completedevents" placeholder="0" min="0" value="0" required>

                        </div>
                        <div class="mb-3">
                            <label for="others" class="form-label text-light">Others</label>

                            <input type="number" class="form-control bg-dark text-light border-secondary" name="others"
                                id="others" placeholder="0" min="0" value="0" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Submit Dashboard Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <div class="notification" id="notification"></div>

    <!-- Bootstrap JS -->
    <script src="js/bootstrap.min.js"></script>

    <!-- Dashboard Fixes - Scrolling and Categories -->
    <script src="js/dashboard-fixes.js"></script>

    <script>
        let memberChart;
        let eventChart;

        // Initialize Charts with dynamic data
        function initCharts() {
            // Check if chart element exists
            const memberCtx = document.getElementById('memberGrowthChart');
            if (!memberCtx) return;

            // Default data
            let labels = ['Spring 2025', 'Summer 2025', 'Fall 2025'];
            let data = [1180, 1250, 0];

            // Use injected data if available
            if (window.dashboardStats && window.dashboardStats.memberGrowth && window.dashboardStats.memberGrowth.length > 0) {
                const growthData = window.dashboardStats.memberGrowth;
                labels = growthData.map(item => item.label);
                data = growthData.map(item => item.value);
            }

            memberChart = new Chart(memberCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Members',
                        data: data,
                        borderColor: '#51cf66',
                        backgroundColor: 'rgba(81, 207, 102, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        pointBackgroundColor: '#51cf66',
                        pointBorderColor: '#51cf66',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 10,
                            bottom: 20,
                            left: 10,
                            right: 10
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#51cf66',
                            borderWidth: 1,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function (context) {
                                    return `Members: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            min: 0,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#fff',
                                font: {
                                    size: 12
                                },
                                stepSize: 100
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#fff',
                                font: {
                                    size: 10
                                },
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    },
                    elements: {
                        point: {
                            hoverBackgroundColor: '#51cf66',
                            hoverBorderColor: '#fff',
                            hoverBorderWidth: 2
                        }
                    }
                }
            });
        }

        // Initialize Gender Distribution Chart
        function initGenderDistributionChart() {
            const chartContainer = document.getElementById('genderDistributionChart');
            if (!chartContainer) return;

            const genderCtx = chartContainer.getContext('2d');

            // Use injected data if available
            if (window.dashboardStats && window.dashboardStats.genderDistribution) {
                const gender = window.dashboardStats.genderDistribution;

                // If we have manual data (non-zero), use it
                if (gender.male > 0 || gender.female > 0) {
                    const manualData = [
                        { gender: 'Male', count: gender.male, color: '#36A2EB' },
                        { gender: 'Female', count: gender.female, color: '#FF6384' }
                    ];
                    createGenderChart(genderCtx, manualData);
                    console.log('Using manual gender distribution data');
                    return;
                }
            }

            // Fallback to API logic if no manual data set


            fetch('../backend/Action/gender_distribution_api.php', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Gender distribution API response:', data);
                    if (data.success && data.data && data.data.length > 0) {
                        createGenderChart(genderCtx, data.data);

                    } else {
                        console.warn('No gender data available, using fallback');
                        createFallbackGenderChart(genderCtx);
                        showNotification('Using sample data - no member data available', 'warning');
                    }
                })
                .catch(error => {
                    console.error('Error loading gender distribution data:', error);
                    createFallbackGenderChart(genderCtx);
                    showNotification('Failed to load data, using sample data', 'warning');
                });
        }

        function createGenderChart(ctx, data) {
            try {
                const labels = data.map(item => item.gender);
                const counts = data.map(item => item.count);
                const colors = data.map(item => item.color);

                if (window.genderChart) {
                    window.genderChart.destroy();
                }

                window.genderChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: counts,
                            backgroundColor: colors,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                top: 10,
                                bottom: 20,
                                left: 10,
                                right: 10
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: '#ccc',
                                    padding: 20,
                                    usePointStyle: true,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: '#51cf66',
                                borderWidth: 1,
                                cornerRadius: 8,
                                displayColors: true,
                                callbacks: {
                                    label: function (context) {
                                        const gender = context.label;
                                        const count = context.parsed;
                                        const genderIcon = gender === 'Male' ? '♂' : gender === 'Female' ? '♀' : '⚧';
                                        return `${gender}: ${count} ${genderIcon}`;
                                    }
                                }
                            }
                        },
                        cutout: '60%'
                    }
                });

                console.log('Gender distribution chart created successfully');
            } catch (error) {
                console.error('Error creating gender chart:', error);
                createFallbackGenderChart(ctx);
            }
        }

        function createFallbackGenderChart(ctx) {
            const fallbackData = [{
                gender: 'Male',
                count: 3,
                color: '#36A2EB'
            },
            {
                gender: 'Female',
                count: 4,
                color: '#FF6384'
            },
            {
                gender: 'Other',
                count: 1,
                color: '#9966FF'
            }
            ];

            createGenderChart(ctx, fallbackData);
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;

            // Set colors based on type
            let backgroundColor, textColor, borderColor;
            switch (type) {
                case 'success':
                    backgroundColor = '#28a745'; // Green background
                    textColor = '#ffffff';
                    borderColor = '#1e7e34';
                    break;
                case 'error':
                case 'danger':
                    backgroundColor = '#dc3545'; // Red background
                    textColor = '#ffffff';
                    borderColor = '#bd2130';
                    break;
                case 'warning':
                    backgroundColor = '#ffc107'; // Yellow background
                    textColor = '#212529';
                    borderColor = '#d39e00';
                    break;
                case 'info':
                    backgroundColor = '#17a2b8'; // Blue background
                    textColor = '#ffffff';
                    borderColor = '#138496';
                    break;
                default:
                    backgroundColor = '#28a745'; // Default to green
                    textColor = '#ffffff';
                    borderColor = '#1e7e34';
            }

            notification.style.backgroundColor = backgroundColor;
            notification.style.color = textColor;
            notification.style.borderLeft = `4px solid ${borderColor}`;
            notification.className = `notification ${type} show`;

            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        document.getElementById('sidebarToggle').addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('open');
        });

        document.querySelectorAll('.chart-controls button').forEach(button => {
            button.addEventListener('click', function () {
                const parent = this.closest('.chart-controls');
                parent.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');

                const period = this.dataset.period;

                if (period) {
                    updateMemberChart(period);
                }
            });
        });

        // Update member chart based on period
        function updateMemberChart(period) {
            let labels, data;

            switch (period) {
                case '1Y':
                    labels = ['Fall 2024', 'Spring 2025', 'Summer 2025'];
                    data = [1180, 1250, 0];
                    break;
                case '2Y':
                    labels = ['Spring 2023', 'Summer 2023', 'Fall 2023', 'Spring 2024', 'Summer 2024', 'Fall 2024', 'Spring 2025', 'Summer 2025'];
                    data = [850, 920, 980, 1050, 1120, 1180, 1250, 0];
                    break;
                case '3Y':
                    labels = ['Spring 2022', 'Summer 2022', 'Fall 2022', 'Spring 2023', 'Summer 2023', 'Fall 2023', 'Spring 2024', 'Summer 2024', 'Fall 2024', 'Spring 2025', 'Summer 2025'];
                    data = [720, 780, 820, 850, 920, 980, 1050, 1120, 1180, 1250, 0];
                    break;
            }

            memberChart.data.labels = labels;
            memberChart.data.datasets[0].data = data;
            memberChart.update();
        }

        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function (e) {

                // Allow normal navigation for external links
                if (this.getAttribute('href') === 'logout.php') return;

                if (this.getAttribute('href') === 'member_types.php') {
                    showNotification('Navigating to member types...', 'success');
                    return;
                }

                if (this.getAttribute('href') === 'pending_applications.php') {
                    showNotification('Navigating to pending applications...', 'success');
                    return;
                }

                if (this.getAttribute('href') === 'admin-signup-control.php') {
                    showNotification('Navigating to signup control...', 'success');
                    return;
                }

                // Only prevent default and handle as section if it has data-section attribute
                const section = this.dataset.section;
                if (section) {
                    e.preventDefault();
                    document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                    this.classList.add('active');

                    showSection(section);
                    showNotification(`Navigating to ${section} section...`, 'success');
                } else if (this.getAttribute('href') && this.getAttribute('href') !== '#') {
                    // Let external links navigate normally
                    return;
                } else {
                    e.preventDefault();
                    showNotification('Navigation section not found', 'warning');
                }
            });
        });

        function showSection(sectionName) {
            document.querySelectorAll('.main-content > div').forEach(div => {
                if (div.id && div.id.includes('section')) {
                    div.style.display = 'none';
                }
            });

            const targetSection = document.getElementById(sectionName + '-section');
            if (targetSection) {
                targetSection.style.display = 'block';
            }
        }

        /* Removed notification to prevent intrusive pop-ups as requested by user */
        /*
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function () {
                const stat = this.dataset.stat;
                showNotification(`Viewing ${stat} statistics...`, 'info');
            });
        });
        */

        document.querySelectorAll('.activity-item').forEach(item => {
            item.addEventListener('click', function () {
                const activity = this.dataset.activity;
                showNotification(`Viewing ${activity} details...`, 'success');
            });
        });

        document.getElementById('searchInput').addEventListener('input', function () {
            const query = this.value.toLowerCase();
            if (query.length > 2) {
                showNotification(`Searching for "${query}"...`, 'success');
            }
        });

        document.getElementById('userProfile').addEventListener('click', function () {
            showNotification('Opening user profile...', 'success');
        });

        document.addEventListener('DOMContentLoaded', function () {
            initCharts();
            initGenderDistributionChart();
            initMemberApplications();

            loadMemberStatistics();



            <?php if ($_SESSION['admin_role'] === 'main_admin'): ?>
                initAdminManagement();
            <?php endif; ?>

            initDashboardUpdates();
            initSignupToggle();

            window.debugDashboard = {
                openUpdateModal: openUpdateModal,
                testModal: function () {
                    console.log('Testing modal functionality...');
                    openUpdateModal('members');
                }
            };

            console.log('Dashboard initialization complete. Stats-grid displays PHP values only.');
        });

        // Admin Management Functions
        <?php if ($_SESSION['admin_role'] === 'main_admin'): ?>

            function initAdminManagement() {

                document.querySelector('[data-section="admin-management"]').addEventListener('click', function () {
                    loadAdminList();
                });

                document.getElementById('addAdminBtn').addEventListener('click', function () {
                    const modal = new bootstrap.Modal(document.getElementById('addAdminModal'));
                    modal.show();
                });

                document.getElementById('addAdminForm').addEventListener('submit', function (e) {
                    e.preventDefault();
                    createAdmin();
                });

                document.getElementById('editAdminForm').addEventListener('submit', function (e) {
                    e.preventDefault();
                    updateAdminPassword();
                });

                document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
                    deleteAdmin();
                });
            }

            function loadAdminList() {
                fetch('../backend/Action/admin_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_admins'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayAdminList(data.data);
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('Failed to load admin list', 'error');
                    });
            }

            function displayAdminList(admins) {
                const tbody = document.getElementById('adminTableBody');
                tbody.innerHTML = '';

                admins.forEach(admin => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                    <td>${admin.id}</td>
                    <td>${admin.username}</td>
                    <td>${admin.email}</td>
                    <td>
                        <span class="badge ${admin.role === 'main_admin' ? 'bg-danger' : 'bg-primary'}">
                            ${admin.role === 'main_admin' ? 'Main Admin' : 'Admin'}
                        </span>
                    </td>
                    <td>
                        <span class="badge ${admin.status === 'active' ? 'bg-success' : 'bg-secondary'}">
                            ${admin.status}
                        </span>
                    </td>
                    <td>${new Date(admin.created_at).toLocaleDateString()}</td>
                    <td>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-warning me-1" onclick="editAdmin(${admin.id}, '${admin.username}', '${admin.email}')">
                                <i class="fas fa-key"></i>
                            </button>
                            ${admin.id != <?php echo $_SESSION['admin_id']; ?> ?
                        `<button class="btn btn-sm btn-danger" onclick="deleteAdminPrompt(${admin.id}, '${admin.username}', '${admin.email}')">
                                    <i class="fas fa-trash"></i>
                                </button>` :
                        '<span class="text-muted">Current User</span>'
                    }
                        </div>
                    </td>
                `;
                    tbody.appendChild(row);
                });
            }

            function createAdmin() {
                const formData = new FormData(document.getElementById('addAdminForm'));
                formData.append('action', 'create_admin');

                fetch('../backend/Action/admin_management.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            document.getElementById('addAdminForm').reset();
                            bootstrap.Modal.getInstance(document.getElementById('addAdminModal')).hide();
                            loadAdminList();
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('Failed to create admin', 'error');
                    });
            }

            function editAdmin(adminId, username, email) {
                document.getElementById('editAdminId').value = adminId;
                document.getElementById('editAdminName').value = username;
                document.getElementById('editAdminEmail').value = email;
                document.getElementById('editAdminPassword').value = '';
                document.getElementById('confirmPassword').value = '';

                const modal = new bootstrap.Modal(document.getElementById('editAdminModal'));
                modal.show();
            }

            function updateAdminPassword() {
                const password = document.getElementById('editAdminPassword').value;
                const confirmPassword = document.getElementById('confirmPassword').value;

                if (password !== confirmPassword) {
                    showNotification('Passwords do not match', 'error');
                    return;
                }

                const formData = new FormData(document.getElementById('editAdminForm'));
                formData.append('action', 'update_password');

                fetch('../backend/Action/admin_management.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            bootstrap.Modal.getInstance(document.getElementById('editAdminModal')).hide();
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('Failed to update password', 'error');
                    });
            }

            // Delete admin functionality now handled by PHP-only ../backend/Action/delete_admin.php
            // No JavaScript functions needed for deletion
        <?php endif; ?>

        // Member Applications Management Functions
        function initMemberApplications() {
            // Load applications when applications section is shown
            document.querySelector('[data-section="applications"]').addEventListener('click', function () {
                loadPendingApplications();
                loadApplicationSystemStatus();
            });

            // Application system toggle
            document.getElementById('applicationSystemToggle').addEventListener('change', function () {
                toggleApplicationSystem(this.checked);
            });

            // Refresh applications button
            document.getElementById('refreshApplicationsBtn').addEventListener('click', function () {
                loadPendingApplications();
            });

            // Load initial status and pending count
            loadApplicationSystemStatus();
            loadPendingApplicationsCount();
        }

        function loadApplicationSystemStatus() {
            fetch('../backend/Action/member_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_application_status'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('applicationSystemToggle').checked = data.enabled;
                    }
                })
                .catch(error => {
                    console.error('Error loading application status:', error);
                });
        }

        function toggleApplicationSystem(enabled) {
            const formData = new FormData();
            formData.append('action', 'toggle_application_system');
            formData.append('enabled', enabled ? 'true' : 'false');

            fetch('../backend/Action/member_management.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message, 'error');
                        // Revert toggle if failed
                        document.getElementById('applicationSystemToggle').checked = !enabled;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to update application system', 'error');
                    // Revert toggle if failed
                    document.getElementById('applicationSystemToggle').checked = !enabled;
                });
        }

        function loadPendingApplications() {
            fetch('../backend/Action/member_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_pending_members'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPendingApplications(data.data);
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to load applications', 'error');
                });
        }

        function loadPendingApplicationsCount() {
            fetch('../backend/Action/member_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_pending_members'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const count = data.data.length;
                        const badge = document.getElementById('pendingApplicationsBadge');
                        if (count > 0) {
                            badge.textContent = count;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading applications count:', error);
                });
        }

        function displayPendingApplications(applications) {
            const applicationsList = document.getElementById('applicationsList');
            const noApplicationsMessage = document.getElementById('noApplicationsMessage');

            applicationsList.innerHTML = '';

            if (applications.length === 0) {
                noApplicationsMessage.style.display = 'block';
                return;
            }

            noApplicationsMessage.style.display = 'none';

            applications.forEach(app => {
                const applicationCard = document.createElement('div');
                applicationCard.className = 'col-lg-6 col-md-8 col-12 mb-4';
                applicationCard.innerHTML = `
                    <div class="card bg-dark text-light border-secondary h-100">
                        <div class="card-header border-secondary">
                            <h5 class="mb-0">
                                <i class="fas fa-user me-2 text-warning"></i>
                                ${app.full_name}
                            </h5>
                            <small class="text-muted">Applied: ${new Date(app.created_at).toLocaleDateString()}</small>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <p class="mb-2"><strong>ID:</strong> ${app.university_id}</p>
                                    <p class="mb-2"><strong>Email:</strong> ${app.email}</p>
                                    <p class="mb-2"><strong>Department:</strong> ${app.department}</p>
                                    <p class="mb-2"><strong>Semester:</strong> ${app.semester}</p>
                                </div>
                                <div class="col-6">
                                    <p class="mb-2"><strong>Phone:</strong> ${app.phone}</p>
                                    <p class="mb-2"><strong>Gender:</strong> ${app.gender}</p>
                                    <p class="mb-2"><strong>Status:</strong> Pending</p>
                                    <p class="mb-2"><strong>Category:</strong> ${app.event_category}</p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <p class="mb-2"><strong>Motivation:</strong></p>
                                <p class="text-muted small">${app.motivation}</p>
                            </div>
                        </div>
                        <div class="card-footer border-secondary">
                            <div class="d-flex gap-2 justify-content-end">
                                <button class="btn btn-success btn-sm" onclick="acceptApplication(${app.id})">
                                    <i class="fas fa-check me-1"></i> Accept
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="rejectApplication(${app.id}, '${app.full_name}')">
                                    <i class="fas fa-times me-1"></i> Reject
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                applicationsList.appendChild(applicationCard);
            });
        }

        function acceptApplication(memberId) {
            if (!confirm('Are you sure you want to accept this application? An email will be sent to the member.')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'accept_member');
            formData.append('member_id', memberId);

            fetch('../backend/Action/member_management.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        loadPendingApplications();
                        loadPendingApplicationsCount();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to accept application', 'error');
                });
        }

        function rejectApplication(memberId, memberName) {
            if (!confirm(`Are you sure you want to reject ${memberName}'s application? This will permanently delete their information.`)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'reject_member');
            formData.append('member_id', memberId);

            fetch('../backend/Action/member_management.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        loadPendingApplications();
                        loadPendingApplicationsCount();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to reject application', 'error');
                });
        }


        // Load member statistics from the member database for additional info
        function loadMemberStatistics() {
            fetch('../backend/Action/member_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_member_statistics'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        console.log('Member statistics loaded:', data.data);
                    } else {
                        console.warn('Failed to load member statistics, using fallback');
                    }
                })
                .catch(error => {
                    console.error('Error loading member statistics:', error);
                });
        }

        // Dashboard Update Management Functions (simplified)
        function initDashboardUpdates() {
            console.log('Dashboard updates initialized (simplified)');
        }

        // Signup Toggle Management Functions
        function initSignupToggle() {
            // Load current signup status on page load
            loadSignupStatus();

            // Add event listener for signup toggle
            document.getElementById('signupToggle').addEventListener('change', function () {
                toggleSignupStatus(this.checked);
            });

            console.log('Signup toggle initialized');
        }

        function loadSignupStatus() {
            console.log('Loading signup status...');
            fetch('../backend/Action/signup_status_handler.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=get_status'
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Signup status response:', data);
                    if (data.success) {
                        updateSignupToggleUI(data.enabled);
                        console.log('Signup status loaded successfully:', data.enabled);
                    } else {
                        console.error('Failed to load signup status:', data.message);
                        showNotification(data.message || 'Failed to load signup status', 'warning');
                    }
                })
                .catch(error => {
                    console.error('Error loading signup status:', error);
                    showNotification('Error loading signup status: ' + error.message, 'error');
                });
        }

        function toggleSignupStatus(enabled) {
            console.log('Toggling signup status to:', enabled);
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('enabled', enabled ? 'true' : 'false');

            // Show loading state
            showNotification('Updating signup status...', 'info');

            fetch('../backend/Action/signup_status_handler.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(response => {
                    console.log('Toggle response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Toggle response data:', data);
                    if (data.success) {
                        updateSignupToggleUI(data.enabled);
                        showNotification(data.message, 'success');
                        console.log('Signup status updated successfully to:', data.enabled);
                    } else {
                        console.error('Failed to toggle signup status:', data.message);
                        showNotification(data.message || 'Failed to update signup status', 'error');
                        // Revert toggle on failure
                        document.getElementById('signupToggle').checked = !enabled;
                    }
                })
                .catch(error => {
                    console.error('Error toggling signup status:', error);
                    showNotification('Error updating signup status: ' + error.message, 'error');
                    // Revert toggle on failure
                    document.getElementById('signupToggle').checked = !enabled;
                });
        }

        function updateSignupToggleUI(enabled) {
            const toggle = document.getElementById('signupToggle');
            const label = document.getElementById('signupToggleLabel');
            const description = document.getElementById('signupToggleDescription');

            if (toggle) {
                toggle.checked = enabled;
            }

            if (label) {
                label.textContent = enabled ? 'Signup Enabled' : 'Signup Disabled';
            }

            if (description) {
                description.textContent = enabled ?
                    'Applications are currently being accepted' :
                    'New applications are temporarily disabled';
            }

            console.log('Signup toggle UI updated:', enabled ? 'enabled' : 'disabled');
        }

        function openUpdateModal(type) {
            console.log('Opening simple form for type:', type);
            openSimpleModal();
        }

        function openSimpleModal() {
            console.log('Opening simple modal');

            const modalElement = document.getElementById('dashboardUpdateModal');
            if (!modalElement) {
                console.error('Modal element not found');
                showNotification('Modal not found. Please refresh the page.', 'error');
                return;
            }

            // Reset form
            const form = document.getElementById('dashboardUpdateForm');
            if (form) {
                form.reset();
            }

            // Show the modal using Bootstrap
            if (typeof bootstrap !== 'undefined') {
                try {
                    const modal = new bootstrap.Modal(modalElement, {
                        backdrop: true,
                        keyboard: true,
                        focus: true
                    });
                    modal.show();
                    showNotification('Form opened for new record', 'info');
                } catch (error) {
                    console.error('Bootstrap modal error:', error);
                    // Fallback
                    modalElement.style.display = 'block';
                    modalElement.classList.add('show');
                    showNotification('Form opened (fallback mode)', 'warning');
                }
            } else {
                // Fallback if Bootstrap is not available
                modalElement.style.display = 'block';
                modalElement.classList.add('show');
                showNotification('Form opened (fallback mode)', 'warning');
            }
        }
    </script>
</body>

</html>
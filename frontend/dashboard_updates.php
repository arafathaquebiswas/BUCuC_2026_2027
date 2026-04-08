<?php
session_start();
require_once '../backend/Database/db.php';

// Check permissions
if (!isset($_SESSION["admin"])) {
    header("Location: admin-login.php");
    exit();
}

$message = '';
$error = '';

$database = new Database();
$conn = $database->createConnection();

// Fetch latest stats for display
try {
    $sql = "SELECT totalmembers, pending_applications, completedevents, others, gd_male, gd_female FROM dashboardmanagement ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $currentStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Default values if no record exists
    if (!$currentStats) {
        $currentStats = [
            'totalmembers' => 0,
            'pending_applications' => 0,
            'completedevents' => 0,
            'others' => 0,
            'gd_male' => 0,
            'gd_female' => 0,
        ];
    }

    // Fetch dynamic Member Growth data
    $chartSql = "SELECT label, value FROM chart_data WHERE category = 'member_growth' ORDER BY id ASC";
    $chartStmt = $conn->prepare($chartSql);
    $chartStmt->execute();
    $memberGrowthData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

    // Default if empty
    if (empty($memberGrowthData)) {
        $memberGrowthData = [
            ['label' => 'Spring 2025', 'value' => 0],
            ['label' => 'Summer 2025', 'value' => 0],
            ['label' => 'Fall 2025', 'value' => 0]
        ];
    }

} catch (Exception $e) {
    $error = "Error fetching current stats: " . $e->getMessage();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $totalMembers = trim($_POST['totalMembers'] ?? '0');
    $pending_applications = trim($_POST['pending_applications'] ?? '0');
    $completedevents = trim($_POST['completedevents'] ?? '0');
    $others = trim($_POST['others'] ?? '0');

    $gd_male = trim($_POST['gd_male'] ?? '0');
    $gd_female = trim($_POST['gd_female'] ?? '0');

    // Dynamic chart data
    $mg_labels = $_POST['mg_label'] ?? [];
    $mg_values = $_POST['mg_value'] ?? [];

    // Validation
    if (
        !is_numeric($totalMembers) || $totalMembers < 0 ||
        !is_numeric($pending_applications) || $pending_applications < 0 ||
        !is_numeric($completedevents) || $completedevents < 0 ||
        !is_numeric($others) || $others < 0 ||
        !is_numeric($gd_male) || $gd_male < 0 ||
        !is_numeric($gd_female) || $gd_female < 0
    ) {
        $error = "General stats fields must be valid non-negative numbers.";
    } else {
        try {
            $conn->beginTransaction();

            // 1. Insert Dashboard Management Stats
            $insert_sql = "INSERT INTO dashboardmanagement (totalmembers, pending_applications, completedevents, others, gd_male, gd_female) 
                           VALUES (:totalmembers, :pending_applications, :completedevents, :others, :gd_male, :gd_female)";

            $stmt = $conn->prepare($insert_sql);
            $stmt->execute([
                ':totalmembers' => $totalMembers,
                ':pending_applications' => $pending_applications,
                ':completedevents' => $completedevents,
                ':others' => $others,
                ':gd_male' => $gd_male,
                ':gd_female' => $gd_female,
            ]);

            // 2. Update Dynamic Chart Data (Member Growth)
            // First delete existing
            $delete_sql = "DELETE FROM chart_data WHERE category = 'member_growth'";
            $conn->exec($delete_sql);

            // Insert new
            $chart_insert_sql = "INSERT INTO chart_data (category, label, value) VALUES ('member_growth', :label, :value)";
            $chart_stmt = $conn->prepare($chart_insert_sql);

            $newMemberGrowthData = [];

            for ($i = 0; $i < count($mg_labels); $i++) {
                $label = trim($mg_labels[$i]);
                $value = trim($mg_values[$i]);

                if (!empty($label) && is_numeric($value)) {
                    $chart_stmt->execute([
                        ':label' => $label,
                        ':value' => $value
                    ]);
                    $newMemberGrowthData[] = ['label' => $label, 'value' => $value];
                }
            }

            $conn->commit();

            $message = "Dashboard data updated successfully!";

            // Refund updated stats
            $currentStats = [
                'totalmembers' => $totalMembers,
                'pending_applications' => $pending_applications,
                'completedevents' => $completedevents,
                'others' => $others,
                'gd_male' => $gd_male,
                'gd_female' => $gd_female
            ];
            $memberGrowthData = $newMemberGrowthData;

        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Failed to update dashboard data: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Updates - BUCuC</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="AdminCss/Dashboard.css">
    <style>
        .updates-container {
            padding: 2rem;
            color: white;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }

        .form-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .form-control {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }

        .form-control:focus {
            background: rgba(0, 0, 0, 0.3);
            color: white;
            border-color: #0d6efd;
            box-shadow: none;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 800px;
            margin: 0 auto 2rem;
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

        .section-title {
            color: #f8f9fa;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 10px;
            margin-top: 30px;
            margin-bottom: 20px;
        }

        .dynamic-row {
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .remove-row-btn {
            color: #ff6b6b;
            cursor: pointer;
            transition: color 0.3s;
        }

        .remove-row-btn:hover {
            color: #fa5252;
        }
    </style>
</head>

<body>
    <div class="updates-container">
        <div class="page-header">
            <h2><i class="fas fa-chart-line me-2"></i>Dashboard Updates</h2>
            <a href="super_admin_dashboard.php" class="back-btn"><i class="fas fa-arrow-left me-2"></i>Back to
                Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success" style="max-width: 800px; margin: 0 auto 2rem; background: rgba(40, 167, 69, 0.2); border: 1px solid rgba(40, 167, 69, 0.3); color: #51cf66;">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="max-width: 800px; margin: 0 auto 2rem;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <h4 class="mb-4">Update Dashboard Statistics</h4>
            <p class="text-muted mb-4">Enter new values below to update the dashboard charts and counters.</p>

            <form method="POST" action="dashboard_updates.php">
                <h5 class="section-title">General Stats</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="totalMembers" class="form-label">Initial/Legacy Member Count</label>
                        <input type="number" class="form-control" id="totalMembers" name="totalMembers"
                            value="<?= htmlspecialchars($currentStats['totalmembers']) ?>" required min="0">
                        <small class="text-muted">Real-time approved members will be added automatically to this base
                            number.</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="pending_applications" class="form-label">Pending Applications</label>
                        <input type="number" class="form-control" id="pending_applications" name="pending_applications"
                            value="<?= htmlspecialchars($currentStats['pending_applications']) ?>" required min="0">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="completedevents" class="form-label">Completed Events</label>
                        <input type="number" class="form-control" id="completedevents" name="completedevents"
                            value="<?= htmlspecialchars($currentStats['completedevents']) ?>" required min="0">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="others" class="form-label">Others</label>
                        <input type="number" class="form-control" id="others" name="others"
                            value="<?= htmlspecialchars($currentStats['others']) ?>" required min="0">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <h5 class="section-title mb-0">Member Growth Chart</h5>
                    <button type="button" class="btn btn-sm btn-outline-light" onclick="addGrowthRow()">
                        <i class="fas fa-plus me-1"></i> Add Point
                    </button>
                </div>
                <div class="text-muted small mb-3">Add data points for the Member Growth Chart.</div>

                <div id="growthRowsContainer">
                    <?php foreach ($memberGrowthData as $index => $data): ?>
                        <div class="row dynamic-row align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small">Label (e.g. Spring 2025)</label>
                                <input type="text" class="form-control" name="mg_label[]"
                                    value="<?= htmlspecialchars($data['label']) ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small">Value</label>
                                <input type="number" class="form-control" name="mg_value[]"
                                    value="<?= htmlspecialchars($data['value']) ?>" required min="0">
                            </div>
                            <div class="col-md-2 text-center">
                                <i class="fas fa-trash remove-row-btn" onclick="removeRow(this)"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h5 class="section-title">Gender Distribution Chart</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="gd_male" class="form-label">Male (Blue)</label>
                        <input type="number" class="form-control" id="gd_male" name="gd_male"
                            value="<?= htmlspecialchars($currentStats['gd_male'] ?? 0) ?>" required min="0">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="gd_female" class="form-label">Female (Pink)</label>
                        <input type="number" class="form-control" id="gd_female" name="gd_female"
                            value="<?= htmlspecialchars($currentStats['gd_female'] ?? 0) ?>" required min="0">
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-2"></i>Update Stats
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/bootstrap.min.js"></script>
    <script>
        function removeRow(btn) {
            const container = document.getElementById('growthRowsContainer');
            // If deleting would leave 0 rows, we might want to prevent or alert, 
            // but for flexiblity allow clearing. Maybe keep at least 1?
            if (container.children.length > 1) {
                btn.closest('.dynamic-row').remove();
            } else {
                alert("You need at least one data point.");
            }
        }

        function addGrowthRow() {
            const container = document.getElementById('growthRowsContainer');
            const newRow = document.createElement('div');
            newRow.className = 'row dynamic-row align-items-end';
            newRow.innerHTML = `
                <div class="col-md-5">
                    <label class="form-label small">Label</label>
                    <input type="text" class="form-control" name="mg_label[]" placeholder="e.g. Next Year" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Value</label>
                    <input type="number" class="form-control" name="mg_value[]" value="0" required min="0">
                </div>
                <div class="col-md-2 text-center">
                    <i class="fas fa-trash remove-row-btn" onclick="removeRow(this)"></i>
                </div>
            `;
            container.appendChild(newRow);
        }
    </script>
</body>

</html>
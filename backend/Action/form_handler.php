<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION["admin"])) {
    header("Location: ../../frontend/admin-login.php");
    exit();
}

// Database connection using the proper Database class
require_once '../Database/db.php';

// Create database connection
$database = new Database();
$conn = $database->createConnection();

// Response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get form data for dashboard management columns
    $totalMembers = trim($_POST['totalMembers'] ?? '0');
    $pending_applications = trim($_POST['pending_applications'] ?? '0');
    $completedevents = trim($_POST['completedevents'] ?? '0');
    $others = trim($_POST['others'] ?? '0');

    // Validate and convert to integers
    if (!is_numeric($totalMembers) || $totalMembers < 0) {
        throw new Exception('Total Members must be a valid non-negative number');
    }
    if (!is_numeric($pending_applications) || $pending_applications < 0) {
        throw new Exception('Pending Applications must be a valid non-negative number');
    }
    if (!is_numeric($completedevents) || $completedevents < 0) {
        throw new Exception('Completed Events must be a valid non-negative number');
    }
    if (!is_numeric($others) || $others < 0) {
        throw new Exception('Others must be a valid non-negative number');
    }

    // Convert to integers
    $totalMembers = (int) $totalMembers;
    $pending_applications = (int) $pending_applications;
    $completedevents = (int) $completedevents;
    $others = (int) $others;


    $insert_sql = "INSERT INTO dashboardmanagement (totalmembers, pending_applications, completedevents, others) 
                   VALUES (:totalmembers, :pending_applications, :completedevents, :others)";

    $stmt = $conn->prepare($insert_sql);
    $stmt->bindParam(':totalmembers', $totalMembers);
    $stmt->bindParam(':pending_applications', $pending_applications);
    $stmt->bindParam(':completedevents', $completedevents);
    $stmt->bindParam(':others', $others);

    if ($stmt->execute()) {
        $new_id = $conn->lastInsertId();

        $response['success'] = true;
        $response['message'] = 'Dashboard management data inserted successfully!';
        $response['data'] = [
            'action' => 'inserted',
            'id' => $new_id,
            'totalmembers' => $totalMembers,
            'pending_applications' => $pending_applications,
            'completedevents' => $completedevents,
            'others' => $others
        ];
    } else {
        throw new Exception('Failed to insert dashboard management data');
    }

    // Log the successful submission
    error_log("Dashboard management data processed - TotalMembers: $totalMembers, PendingApps: $pending_applications, CompletedEvents: $completedevents, Others: $others, Admin: " . ($_SESSION['username'] ?? 'Unknown'));

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();

    // Log the error
    error_log("Dashboard management error: " . $e->getMessage() . " | POST data: " . json_encode($_POST));
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // AJAX request - return JSON
    header('Content-Type: application/json');
    echo json_encode($response);
} else {

    $redirect = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'main_admin') ? '../super_admin_dashboard.php' : '../admin_dashboard.php';
    if ($response['success']) {
        header("Location: $redirect?success=" . urlencode($response['message']));
    } else {
        header("Location: $redirect?error=" . urlencode($response['message']));
    }
}
exit();
?>
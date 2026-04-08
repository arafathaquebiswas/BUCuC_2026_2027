<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION["admin"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once '../Database/db.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$confirmation = $input['confirmation'] ?? '';

// Double confirmation check
if ($action !== 'clear_all_applications' || $confirmation !== 'CLEAR ALL APPLICATIONS') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action or confirmation text. Please type "CLEAR ALL APPLICATIONS" exactly.'
    ]);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->createConnection();



    // Get count of records from all tables (for logging)
    $countPending = $pdo->query("SELECT COUNT(*) as total FROM pending_applications")->fetch()['total'];
    $countShortlisted = $pdo->query("SELECT COUNT(*) as total FROM shortlisted_members")->fetch()['total'];
    $countApproved = $pdo->query("SELECT COUNT(*) as total FROM members")->fetch()['total'];

    $totalRecords = $countPending + $countShortlisted + $countApproved;

    if ($totalRecords == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No applications to clear. All tables are already empty.'
        ]);
        exit();
    }

    // Use a transaction for safety
    $pdo->beginTransaction();

    // 1. Clear pending_applications
    $pdo->exec("DELETE FROM pending_applications");
    $pdo->exec("ALTER TABLE pending_applications AUTO_INCREMENT = 1");

    // 2. Clear shortlisted_members
    $pdo->exec("DELETE FROM shortlisted_members");
    $pdo->exec("ALTER TABLE shortlisted_members AUTO_INCREMENT = 1");

    // 3. Clear members
    $pdo->exec("DELETE FROM members");
    $pdo->exec("ALTER TABLE members AUTO_INCREMENT = 1");

    // Commit transaction
    $result = $pdo->commit();

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully cleared {$totalRecords} record(s) from the database (Pending: {$countPending}, Shortlisted: {$countShortlisted}, Approved: {$countApproved}).",
            'records_deleted' => $totalRecords
        ]);
    } else {
        throw new Exception("Transaction failed. Could not clear applications.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}


?>
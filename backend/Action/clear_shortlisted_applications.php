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
if ($action !== 'clear_shortlisted_applications' || $confirmation !== 'CLEAR ALL SHORTLISTED') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action or confirmation text. Please type "CLEAR ALL SHORTLISTED" exactly.'
    ]);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->createConnection();

    // Get count of records before deletion (for logging)
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM shortlisted_members");
    $totalRecords = $countStmt->fetch()['total'];

    if ($totalRecords == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No shortlisted applications to clear.'
        ]);
        exit();
    }

    $deleteStmt = $pdo->prepare("TRUNCATE TABLE shortlisted_members");
    $result = $deleteStmt->execute();

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully cleared {$totalRecords} shortlisted application(s) from the database.",
            'records_deleted' => $totalRecords
        ]);
    } else {
        throw new Exception("Failed to delete records.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
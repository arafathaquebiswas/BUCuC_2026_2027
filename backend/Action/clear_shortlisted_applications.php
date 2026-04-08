<?php
session_start();

if (!isset($_SESSION["admin"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once '../Database/db.php';

$input        = json_decode(file_get_contents('php://input'), true);
$action       = $input['action']       ?? '';
$confirmation = $input['confirmation'] ?? '';

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
    $pdo      = $database->createConnection();

    // Count records before deletion
    $countStmt   = $pdo->query("SELECT COUNT(*) as total FROM shortlisted_members");
    $totalRecords = $countStmt->fetch()['total'];

    if ($totalRecords == 0) {
        echo json_encode(['success' => false, 'message' => 'No shortlisted applications to clear.']);
        exit();
    }

    // Archive to JSON log before deleting — no data is ever silently lost
    $allRows = $pdo->query("SELECT * FROM shortlisted_members ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $logPath = __DIR__ . '/../logs/cleared_shortlisted.log';
    $logEntry = json_encode([
        'cleared_at'     => date('Y-m-d H:i:s'),
        'cleared_by'     => $_SESSION['username'] ?? 'unknown',
        'records_count'  => $totalRecords,
        'records'        => $allRows,
    ]) . PHP_EOL;
    file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);

    // Use DELETE (not TRUNCATE) so it is transactional and row-counted
    $deleteStmt = $pdo->prepare("DELETE FROM shortlisted_members");
    $deleteStmt->execute();
    $deletedRows = $deleteStmt->rowCount();

    echo json_encode([
        'success'         => true,
        'message'         => "Successfully cleared {$deletedRows} shortlisted application(s). Records archived to server log.",
        'records_deleted' => $deletedRows,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

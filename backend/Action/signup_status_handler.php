<?php
session_start();

// Check if user is admin
if (!isset($_SESSION["admin"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../Database/db.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $conn = $database->createConnection();

    // Create table if it doesn't exist
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS signup_status (
            id INT PRIMARY KEY DEFAULT 1,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by VARCHAR(100) DEFAULT NULL
        )
    ";
    $conn->exec($createTableQuery);
    
    // Insert default record if table is empty
    $checkQuery = "SELECT COUNT(*) FROM signup_status";
    $stmt = $conn->prepare($checkQuery);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $insertQuery = "INSERT INTO signup_status (id, is_enabled, updated_by) VALUES (1, 1, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->execute([$_SESSION['username']]);
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'get_status':
            $query = "SELECT is_enabled FROM signup_status WHERE id = 1";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'enabled' => (bool)$result['is_enabled']
            ]);
            break;
            
        case 'toggle_status':
            $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true' ? 1 : 0;
            
            $query = "UPDATE signup_status SET is_enabled = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1";
            $stmt = $conn->prepare($query);
            $stmt->execute([$enabled, $_SESSION['username']]);
            
            $statusText = $enabled ? 'enabled' : 'disabled';
            echo json_encode([
                'success' => true,
                'message' => "Signup system has been {$statusText} successfully",
                'enabled' => (bool)$enabled
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    error_log("Signup status handler error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

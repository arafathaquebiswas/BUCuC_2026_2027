<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION["admin"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Set content type
header('Content-Type: application/json');

// Include database connection
require_once '../Database/db.php';

try {
    $database = new Database();
    $conn = $database->createConnection();
    
    // Get the latest dashboard statistics from dashboardmanagement table
    $sql = "SELECT 
                totalmembers as total_members,
                pending_applications,
                completedevents as completed_events,
                others,
                created_at as last_updated,
                id
            FROM dashboardmanagement 
            ORDER BY id DESC 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Convert to proper data types
        $dashboardData = [
            'total_members' => (int)$result['total_members'],
            'pending_applications' => (int)$result['pending_applications'],
            'completed_events' => (int)$result['completed_events'],
            'others' => (int)$result['others'],
            'last_updated' => $result['last_updated'],
            'record_id' => (int)$result['id']
        ];
        
        echo json_encode([
            'success' => true,
            'message' => 'Dashboard statistics loaded successfully',
            'data' => $dashboardData,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } else {
        // No data found - return default values
        $defaultData = [
            'total_members' => 0,
            'pending_applications' => 0,
            'completed_events' => 0,
            'others' => 0,
            'last_updated' => null,
            'record_id' => null
        ];
        
        echo json_encode([
            'success' => true,
            'message' => 'No dashboard data found - using default values',
            'data' => $defaultData,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in dashboard_stats_api.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred while fetching dashboard statistics',
        'error' => 'Database connection failed',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("General error in dashboard_stats_api.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching dashboard statistics',
        'error' => 'Server error',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>

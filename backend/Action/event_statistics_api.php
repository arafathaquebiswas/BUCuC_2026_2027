<?php
session_start();
require_once '../Database/db.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$database = new Database();
$conn = $database->createConnection();

try {
    // Get event category statistics using the new view
    $sql = "SELECT * FROM event_gender_summary ORDER BY total_count DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $categoryStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no data from view, fallback to direct query
    if (empty($categoryStats)) {
        $fallbackSql = "SELECT 
            event_category,
            SUM(CASE WHEN gender_tracking = 'Male' THEN 1 ELSE 0 END) as male_count,
            SUM(CASE WHEN gender_tracking = 'Female' THEN 1 ELSE 0 END) as female_count,
            SUM(CASE WHEN gender_tracking = 'Other' THEN 1 ELSE 0 END) as other_count,
            COUNT(*) as total_count
            FROM members 
            WHERE status = 'active'
            GROUP BY event_category
            ORDER BY total_count DESC";
        
        $fallbackStmt = $conn->prepare($fallbackSql);
        $fallbackStmt->execute();
        $categoryStats = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Format data for Chart.js
    $chartData = [];
    $colors = [
        'Music' => '#f3d35c',    // Yellow
        'Dance' => '#e76f2c',    // Orange
        'Drama' => '#28a745',    // Green
        'Art' => '#17a2b8',      // Cyan
        'Poetry' => '#6f42c1'    // Purple
    ];
    
    if (empty($categoryStats)) {
        // Dummy data if no members exist
        $chartData = [
            ['category' => 'Music', 'count' => 35, 'color' => $colors['Music']],
            ['category' => 'Dance', 'count' => 25, 'color' => $colors['Dance']],
            ['category' => 'Drama', 'count' => 20, 'color' => $colors['Drama']],
            ['category' => 'Art', 'count' => 15, 'color' => $colors['Art']],
            ['category' => 'Poetry', 'count' => 5, 'color' => $colors['Poetry']]
        ];
        $message = 'Using dummy data - no active members found';
    } else {
        foreach ($categoryStats as $stat) {
            $category = $stat['event_category'];
            $count = (int)$stat['total_count'];
            
            $chartData[] = [
                'category' => $category,
                'count' => $count,
                'male_count' => (int)$stat['male_count'],
                'female_count' => (int)$stat['female_count'],
                'other_count' => (int)$stat['other_count'],
                'color' => $colors[$category] ?? '#999999'
            ];
        }
        $message = 'Event category statistics loaded successfully';
    }
    
    // Get overall statistics
    $overallSql = "SELECT 
        COUNT(*) as total_active_members,
        COUNT(DISTINCT event_category) as active_categories
        FROM members 
        WHERE status = 'active'";
    
    $overallStmt = $conn->prepare($overallSql);
    $overallStmt->execute();
    $overallStats = $overallStmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'data' => $chartData,
        'overall' => $overallStats ?: ['total_active_members' => 0, 'active_categories' => 0],
        'message' => $message
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error',
        'debug' => $e->getMessage() // Remove in production
    ]);
}
?>
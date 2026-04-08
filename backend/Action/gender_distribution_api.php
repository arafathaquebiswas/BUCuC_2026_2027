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
    // Check if members table exists
    $checkTable = "SHOW TABLES LIKE 'members'";
    $tableCheck = $conn->prepare($checkTable);
    $tableCheck->execute();
    
    if (!$tableCheck->fetch()) {
        // Return dummy data if table doesn't exist
        echo json_encode([
            'success' => true,
            'data' => [
                ['gender' => 'Male', 'count' => 3, 'color' => '#36A2EB'],
                ['gender' => 'Female', 'count' => 4, 'color' => '#FF6384'],
                ['gender' => 'Other', 'count' => 1, 'color' => '#9966FF']
            ],
            'summary' => [
                'total_males' => 3,
                'total_females' => 4,
                'total_others' => 1,
                'total_members' => 8
            ],
            'message' => 'Using dummy data - members table not found'
        ]);
        exit();
    }
    
    // Use the new view for optimized gender distribution data
    $sql = "SELECT 
                gender,
                COUNT(*) as count
            FROM gender_distribution_view
            GROUP BY gender
            ORDER BY count DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data for Chart.js - total gender counts only
    $chartData = [];
    $genderColors = [
        'Male' => '#36A2EB',    // Blue
        'Female' => '#FF6384',   // Pink
        'Other' => '#9966FF'     // Purple
    ];
    
    // If no data found, create dummy data
    if (empty($data)) {
        $chartData = [
            ['gender' => 'Male', 'count' => 3, 'color' => '#36A2EB'],
            ['gender' => 'Female', 'count' => 4, 'color' => '#FF6384'],
            ['gender' => 'Other', 'count' => 1, 'color' => '#9966FF']
        ];
    } else {
        foreach ($data as $row) {
            $gender = $row['gender'];
            $count = (int)$row['count'];
            
            $chartData[] = [
                'gender' => $gender,
                'count' => $count,
                'color' => $genderColors[$gender] ?? '#999999'
            ];
        }
    }
    
    // Get summary statistics using the new member_statistics view
    $summarySql = "SELECT * FROM member_statistics";
    $summaryStmt = $conn->prepare($summarySql);
    $summaryStmt->execute();
    $summaryData = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($summaryData) {
        $summary = [
            'total_males' => (int)$summaryData['total_males'],
            'total_females' => (int)$summaryData['total_females'],
            'total_others' => (int)$summaryData['total_others'],
            'total_members' => (int)$summaryData['total_members'],
            'active_members' => (int)$summaryData['active_members'],
            'pending_applications' => (int)$summaryData['pending_applications'],
            'accepted_members' => (int)$summaryData['accepted_members']
        ];
        $message = 'Data loaded successfully from enhanced database';
    } else {
        // Fallback to dummy data
        $summary = [
            'total_males' => 3,
            'total_females' => 4,
            'total_others' => 1,
            'total_members' => 8,
            'active_members' => 8,
            'pending_applications' => 0,
            'accepted_members' => 8
        ];
        $message = 'Using dummy data - no member statistics available';
    }
    
    $response = [
        'success' => true,
        'data' => $chartData,
        'summary' => $summary,
        'message' => $message
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 
<?php
session_start();
require_once '../Database/db.php';

// Check if user is admin
if (!isset($_SESSION["admin"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$database = new Database();
$conn = $database->createConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_current_stats':
            getCurrentStats($conn);
            break;
        
        case 'update_total_members':
            updateTotalMembers($conn, $_POST);
            break;
        
        case 'update_pending_applications':
            updatePendingApplications($conn, $_POST);
            break;
        
        case 'update_performance_data':
            updatePerformanceData($conn, $_POST);
            break;
        
        case 'update_gender_distribution':
            updateGenderDistribution($conn, $_POST);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function getCurrentStats($conn) {
    try {
        // Get current member statistics - ensure we handle NULL values properly
        $memberSql = "SELECT 
            COALESCE(COUNT(*), 0) as total_members,
            COALESCE(SUM(CASE WHEN application_status = 'pending' THEN 1 ELSE 0 END), 0) as pending_applications,
            COALESCE(SUM(CASE WHEN application_status = 'accepted' THEN 1 ELSE 0 END), 0) as accepted_members,
            COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as active_members,
            COALESCE(SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END), 0) as total_males,
            COALESCE(SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END), 0) as total_females,
            COALESCE(SUM(CASE WHEN gender = 'Other' THEN 1 ELSE 0 END), 0) as total_others
            FROM members";
        
        $memberStmt = $conn->prepare($memberSql);
        $memberStmt->execute();
        $memberStats = $memberStmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure all values are integers
        foreach ($memberStats as $key => $value) {
            $memberStats[$key] = intval($value);
        }
        
        // Get event category statistics
        $categorySql = "SELECT 
            event_category,
            COUNT(*) as count,
            SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_count,
            SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_count,
            SUM(CASE WHEN gender = 'Other' THEN 1 ELSE 0 END) as other_count
            FROM members 
            WHERE status = 'active' AND application_status = 'accepted'
            GROUP BY event_category
            ORDER BY count DESC";
        
        $categoryStmt = $conn->prepare($categorySql);
        $categoryStmt->execute();
        $categoryStats = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get performance metrics - check if performance table exists first
        $performanceStats = [];
        try {
            $performanceCheckSql = "SHOW TABLES LIKE 'performance_metrics'";
            $checkStmt = $conn->prepare($performanceCheckSql);
            $checkStmt->execute();
            $tableExists = $checkStmt->fetch();
            
            if ($tableExists) {
                $performanceSql = "SELECT 
                    metric_name, 
                    SUM(metric_value) as metric_value 
                    FROM performance_metrics 
                    GROUP BY metric_name 
                    ORDER BY metric_value DESC";
                
                $performanceStmt = $conn->prepare($performanceSql);
                $performanceStmt->execute();
                $performanceStats = $performanceStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $pe) {
            // Performance table doesn't exist or error occurred, use default
            $performanceStats = [
                ['metric_name' => 'Active Categories', 'metric_value' => count($categoryStats)],
                ['metric_name' => 'Total Events', 'metric_value' => 5]
            ];
        }
        
        // If no performance data, create default
        if (empty($performanceStats)) {
            $performanceStats = [
                ['metric_name' => 'Active Categories', 'metric_value' => count($categoryStats)],
                ['metric_name' => 'Total Events', 'metric_value' => 5]
            ];
        }
        
        $responseData = [
            'members' => $memberStats,
            'categories' => $categoryStats,
            'performance' => $performanceStats
        ];
        
        // Log the response for debugging
        error_log("getCurrentStats response: " . json_encode($responseData));
        
        echo json_encode([
            'success' => true, 
            'data' => $responseData,
            'message' => 'Statistics loaded successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("getCurrentStats error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage(),
            'error_details' => $e->getTraceAsString()
        ]);
    }
}

function updateTotalMembers($conn, $data) {
    try {
        $operation = $data['operation'] ?? ''; // 'add' or 'remove'
        $amount = intval($data['amount'] ?? 0);
        $category = $data['category'] ?? '';
        $memberName = trim($data['member_name'] ?? '');
        $memberEmail = trim($data['member_email'] ?? '');
        $gender = $data['gender'] ?? 'Other';
        
        if ($operation === 'add' && $amount > 0) {
            // Add new dummy members
            for ($i = 0; $i < $amount; $i++) {
                $dummyName = $memberName ?: "Member " . (time() + $i);
                $dummyEmail = $memberEmail ?: "member" . (time() + $i) . "@example.com";
                
                $sql = "INSERT INTO members (
                    full_name, email, university_id, department, phone, semester, 
                    gender, event_category, membership_status, application_status, 
                    status, created_at
                ) VALUES (
                    :name, :email, :uni_id, 'General', '01700000000', 'Fall 2024',
                    :gender, :category, 'Regular', 'accepted', 'active', NOW()
                )";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':name', $dummyName);
                $stmt->bindParam(':email', $dummyEmail);
                $stmt->bindParam(':uni_id', time() + $i);
                $stmt->bindParam(':gender', $gender);
                $stmt->bindParam(':category', $category);
                $stmt->execute();
            }
            
            echo json_encode(['success' => true, 'message' => "$amount members added successfully"]);
            
        } elseif ($operation === 'remove' && $amount > 0) {
            // Remove members (preferably dummy ones first)
            $sql = "DELETE FROM members 
                    WHERE status = 'active' 
                    AND (email LIKE '%@example.com' OR full_name LIKE 'Member %')
                    " . ($category ? "AND event_category = :category" : "") . "
                    ORDER BY created_at DESC 
                    LIMIT :amount";
            
            $stmt = $conn->prepare($sql);
            if ($category) {
                $stmt->bindParam(':category', $category);
            }
            $stmt->bindParam(':amount', $amount, PDO::PARAM_INT);
            $stmt->execute();
            
            $deleted = $stmt->rowCount();
            echo json_encode(['success' => true, 'message' => "$deleted members removed successfully"]);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid operation or amount']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updatePendingApplications($conn, $data) {
    try {
        $operation = $data['operation'] ?? ''; // 'add' or 'remove'
        $amount = intval($data['amount'] ?? 0);
        $category = $data['category'] ?? 'General';
        
        if ($operation === 'add' && $amount > 0) {
            // Add dummy pending applications
            for ($i = 0; $i < $amount; $i++) {
                $sql = "INSERT INTO members (
                    full_name, email, university_id, department, phone, semester, 
                    gender, event_category, membership_status, application_status, 
                    status, motivation, created_at
                ) VALUES (
                    :name, :email, :uni_id, 'General', '01700000000', 'Fall 2024',
                    'Other', :category, 'Regular', 'pending', 'pending',
                    'Test application for demonstration purposes', NOW()
                )";
                
                $name = "Pending Applicant " . (time() + $i);
                $email = "pending" . (time() + $i) . "@example.com";
                $uniId = "pending" . (time() + $i);
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':uni_id', $uniId);
                $stmt->bindParam(':category', $category);
                $stmt->execute();
            }
            
            echo json_encode(['success' => true, 'message' => "$amount pending applications added successfully"]);
            
        } elseif ($operation === 'remove' && $amount > 0) {
            // Remove pending applications
            $sql = "DELETE FROM members 
                    WHERE application_status = 'pending' 
                    AND (email LIKE '%@example.com' OR full_name LIKE 'Pending Applicant %')
                    ORDER BY created_at DESC 
                    LIMIT :amount";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':amount', $amount, PDO::PARAM_INT);
            $stmt->execute();
            
            $deleted = $stmt->rowCount();
            echo json_encode(['success' => true, 'message' => "$deleted pending applications removed successfully"]);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid operation or amount']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updatePerformanceData($conn, $data) {
    try {
        $metricType = $data['metric_type'] ?? ''; // 'events', 'participation', 'achievements'
        $operation = $data['operation'] ?? ''; // 'add' or 'remove'
        $value = intval($data['value'] ?? 0);
        $description = trim($data['description'] ?? '');
        
        // Create performance tracking table if it doesn't exist
        $createTableSql = "CREATE TABLE IF NOT EXISTS performance_metrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            metric_type VARCHAR(50) NOT NULL,
            metric_name VARCHAR(100) NOT NULL,
            metric_value INT NOT NULL DEFAULT 0,
            description TEXT,
            updated_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (updated_by) REFERENCES adminpanel(id)
        )";
        $conn->exec($createTableSql);
        
        if ($operation === 'add') {
            $sql = "INSERT INTO performance_metrics (metric_type, metric_name, metric_value, description, updated_by) 
                    VALUES (:type, :name, :value, :description, :admin_id)
                    ON DUPLICATE KEY UPDATE 
                    metric_value = metric_value + :value2, 
                    description = :description2, 
                    updated_by = :admin_id2";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':type', $metricType);
            $stmt->bindParam(':name', $description);
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':value2', $value);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':description2', $description);
            $stmt->bindParam(':admin_id', $_SESSION['admin_id']);
            $stmt->bindParam(':admin_id2', $_SESSION['admin_id']);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => "Performance metric added successfully"]);
            
        } elseif ($operation === 'remove') {
            $sql = "UPDATE performance_metrics 
                    SET metric_value = GREATEST(0, metric_value - :value), updated_by = :admin_id
                    WHERE metric_type = :type AND metric_name = :name";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':type', $metricType);
            $stmt->bindParam(':name', $description);
            $stmt->bindParam(':admin_id', $_SESSION['admin_id']);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => "Performance metric updated successfully"]);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid operation']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateGenderDistribution($conn, $data) {
    try {
        $gender = $data['gender'] ?? ''; // 'Male', 'Female', 'Other'
        $operation = $data['operation'] ?? ''; // 'add' or 'remove'
        $amount = intval($data['amount'] ?? 0);
        $category = $data['category'] ?? 'General';
        
        if (!in_array($gender, ['Male', 'Female', 'Other'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid gender value']);
            return;
        }
        
        if ($operation === 'add' && $amount > 0) {
            // Add members with specific gender
            for ($i = 0; $i < $amount; $i++) {
                $sql = "INSERT INTO members (
                    full_name, email, university_id, department, phone, semester, 
                    gender, event_category, membership_status, application_status, 
                    status, created_at
                ) VALUES (
                    :name, :email, :uni_id, 'General', '01700000000', 'Fall 2024',
                    :gender, :category, 'Regular', 'accepted', 'active', NOW()
                )";
                
                $name = $gender . " Member " . (time() + $i);
                $email = strtolower($gender) . "member" . (time() + $i) . "@example.com";
                $uniId = strtolower($gender) . (time() + $i);
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':uni_id', $uniId);
                $stmt->bindParam(':gender', $gender);
                $stmt->bindParam(':category', $category);
                $stmt->execute();
            }
            
            echo json_encode(['success' => true, 'message' => "$amount $gender members added successfully"]);
            
        } elseif ($operation === 'remove' && $amount > 0) {
            // Remove members with specific gender (dummy ones first)
            $sql = "DELETE FROM members 
                    WHERE gender = :gender 
                    AND status = 'active' 
                    AND (email LIKE '%@example.com' OR full_name LIKE :name_pattern)
                    ORDER BY created_at DESC 
                    LIMIT :amount";
            
            $namePattern = $gender . " Member %";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':gender', $gender);
            $stmt->bindParam(':name_pattern', $namePattern);
            $stmt->bindParam(':amount', $amount, PDO::PARAM_INT);
            $stmt->execute();
            
            $deleted = $stmt->rowCount();
            echo json_encode(['success' => true, 'message' => "$deleted $gender members removed successfully"]);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid operation or amount']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
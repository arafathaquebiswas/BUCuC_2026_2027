<?php
header('Content-Type: application/json');
require_once '../Database/db.php';

try {
    $database = new Database();
    $conn = $database->createConnection();
    
    $sql = "SELECT setting_value FROM application_settings WHERE setting_name = 'application_system_enabled'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch();
    
    $enabled = ($result && $result['setting_value'] === 'true') ? true : false;
    
    echo json_encode([
        'success' => true,
        'enabled' => $enabled
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'enabled' => true, // Default to enabled if there's an error
        'message' => 'Database error'
    ]);
}
?>

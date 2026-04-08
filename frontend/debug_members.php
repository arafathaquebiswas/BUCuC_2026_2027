<?php
session_start();
require_once '../backend/Database/db.php';

try {
    $database = new Database();
    $pdo = $database->createConnection();

    // Check all members and their statuses
    $stmt = $pdo->query("SELECT id, full_name, membership_status FROM members ORDER BY updated_at DESC LIMIT 20");
    $members = $stmt->fetchAll();

    echo "<h2>All Members (Last 20):</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Status</th></tr>";

    foreach ($members as $member) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($member['id']) . "</td>";
        echo "<td>" . htmlspecialchars($member['full_name']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($member['membership_status']) . "</strong></td>";
        echo "</tr>";
    }
    echo "</table>";

    // Count by status
    echo "<h2>Count by Status:</h2>";
    $statusStmt = $pdo->query("SELECT membership_status, COUNT(*) as count FROM members GROUP BY membership_status");
    $statuses = $statusStmt->fetchAll();

    echo "<ul>";
    foreach ($statuses as $status) {
        echo "<li><strong>" . htmlspecialchars($status['membership_status']) . ":</strong> " . $status['count'] . "</li>";
    }
    echo "</ul>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
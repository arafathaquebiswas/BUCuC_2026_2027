<?php
require_once 'Database/db.php';

try {
    $database = new Database();
    $pdo = $database->createConnection();
    
    // Create venuInfo table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `venuInfo`(
        `venue_id` INT AUTO_INCREMENT PRIMARY KEY,
        `venue_name` VARCHAR(255) NOT NULL,
        `venue_location` VARCHAR(255) NOT NULL,
        `venue_dateTime` DATETIME NOT NULL,
        `venue_startingTime` VARCHAR(10) NOT NULL,
        `venue_endingTime` VARCHAR(10) NOT NULL,
        `venu_ampm` VARCHAR(2) NOT NULL DEFAULT 'PM'
    )";
    
    $pdo->exec($sql);
    echo "✅ venuInfo table created successfully!<br>";
    
    // Check if table exists and show structure
    $stmt = $pdo->query("DESCRIBE venuInfo");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><p><strong>Table setup complete!</strong> You can now use the venue management functionality.</p>";
    echo "<p><a href='pending_applications.php'>Go to Pending Applications</a> | <a href='set_venue.php'>Go to Set Venue</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error creating table: " . $e->getMessage();
}
?>

<?php
/**
 * Database Migration Script
 * Creates three separate tables and migrates existing data
 * 
 * Run this ONCE to set up the new three-table system
 */

session_start();
if (!isset($_SESSION["admin"]) || $_SESSION['admin_role'] !== 'main_admin') {
    die("Access denied. Super Admin only.");
}

require_once 'Database/db.php';

try {
    $database = new Database();
    $pdo = $database->createConnection();

    echo "<h2>Database Migration Started</h2>";
    echo "<pre>";

    // Step 1: Create pending_applications table
    echo "\n1. Creating pending_applications table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS pending_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        university_id VARCHAR(50) NOT NULL,
        email VARCHAR(255) NOT NULL,
        gsuite_email VARCHAR(255) NOT NULL,
        department VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        semester VARCHAR(50) NOT NULL,
        gender VARCHAR(20) NOT NULL,
        facebook_url TEXT,
        firstPriority VARCHAR(100) NOT NULL,
        secondPriority VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "✓ pending_applications table created\n";

    // Step 2: Create shortlisted_members table
    echo "\n2. Creating shortlisted_members table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS shortlisted_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        university_id VARCHAR(50) NOT NULL,
        email VARCHAR(255) NOT NULL,
        gsuite_email VARCHAR(255) NOT NULL,
        department VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        semester VARCHAR(50) NOT NULL,
        gender VARCHAR(20) NOT NULL,
        facebook_url TEXT,
        firstPriority VARCHAR(100) NOT NULL,
        secondPriority VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "✓ shortlisted_members table created\n";

    // Step 3: Migrate existing data
    echo "\n3. Migrating existing data from members table...\n";

    // Get all members
    $stmt = $pdo->query("SELECT * FROM members");
    $allMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pendingCount = 0;
    $shortlistedCount = 0;
    $acceptedCount = 0;

    foreach ($allMembers as $member) {
        $status = $member['membership_status'];

        // Remove membership_status from the data array
        unset($member['membership_status']);
        unset($member['id']); // Let auto-increment handle this

        if ($status === 'New_member') {
            // Insert into pending_applications
            $columns = implode(', ', array_keys($member));
            $placeholders = ':' . implode(', :', array_keys($member));
            $sql = "INSERT INTO pending_applications ($columns) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($member);
            $pendingCount++;

        } elseif ($status === 'Shortlisted') {
            // Insert into shortlisted_members
            $columns = implode(', ', array_keys($member));
            $placeholders = ':' . implode(', :', array_keys($member));
            $sql = "INSERT INTO shortlisted_members ($columns) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($member);
            $shortlistedCount++;

        } elseif ($status === 'Accepted') {
            // Keep in members table but remove status column
            $acceptedCount++;
        }
    }

    echo "✓ Migrated $pendingCount pending applications\n";
    echo "✓ Migrated $shortlistedCount shortlisted members\n";
    echo "✓ Kept $acceptedCount accepted members in members table\n";

    // Step 4: Remove membership_status column from members table
    echo "\n4. Cleaning up members table...\n";

    // First, delete non-accepted members from members table
    $pdo->exec("DELETE FROM members WHERE membership_status != 'Accepted'");
    echo "✓ Removed pending and shortlisted from members table\n";

    // Remove membership_status column
    $pdo->exec("ALTER TABLE members DROP COLUMN membership_status");
    echo "✓ Removed membership_status column from members table\n";

    echo "\n</pre>";
    echo "<h3 style='color: green;'>✓ Migration Completed Successfully!</h3>";
    echo "<p><strong>Summary:</strong></p>";
    echo "<ul>";
    echo "<li>Pending Applications: $pendingCount</li>";
    echo "<li>Shortlisted Members: $shortlistedCount</li>";
    echo "<li>Accepted Members: $acceptedCount</li>";
    echo "</ul>";
    echo "<p><a href='admin_dashboard.php'>Return to Dashboard</a></p>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Migration Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
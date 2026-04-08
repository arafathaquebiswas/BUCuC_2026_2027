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
        case 'get_pending_members':
            getPendingMembers($conn);
            break;
        
        case 'accept_member':
            acceptMember($conn, $_POST['member_id'] ?? '');
            break;
        
        case 'reject_member':
            rejectMember($conn, $_POST['member_id'] ?? '');
            break;
        
        case 'toggle_application_system':
            toggleApplicationSystem($conn, $_POST['enabled'] ?? '');
            break;
        
        case 'get_application_status':
            getApplicationStatus($conn);
            break;
        
        case 'get_member_statistics':
            getMemberStatistics($conn);
            break;
        
        case 'get_members_by_category':
            getMembersByCategory($conn, $_POST['category'] ?? '');
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function getPendingMembers($conn) {
    try {
        $sql = "SELECT id, full_name, university_id, email, department, phone, semester, 
                gender, date_of_birth, membership_status, event_category, motivation,
                created_at, facebook_url, gsuite_email, gender_tracking
                FROM members 
                WHERE membership_status = 'New_member' 
                ORDER BY created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $members, 'count' => count($members)]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function acceptMember($conn, $memberId) {
    if (empty($memberId)) {
        echo json_encode(['success' => false, 'message' => 'Member ID is required']);
        return;
    }
    
    try {
        // Get member details for email
        $getMemberSql = "SELECT full_name, email, event_category, gsuite_email FROM members WHERE id = :member_id AND membership_status = 'New_member'";
        $getMemberStmt = $conn->prepare($getMemberSql);
        $getMemberStmt->bindParam(':member_id', $memberId);
        $getMemberStmt->execute();
        $member = $getMemberStmt->fetch();
        
        if (!$member) {
            echo json_encode(['success' => false, 'message' => 'Member not found or already processed']);
            return;
        }
        
        // Update membership status to Accepted
        $updateSql = "UPDATE members SET membership_status = 'Accepted', updated_at = CURRENT_TIMESTAMP WHERE id = :member_id";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bindParam(':member_id', $memberId);
        
        if ($updateStmt->execute()) {
            // Send acceptance email
            $emailSent = sendAcceptanceEmail($member['email'], $member['full_name'], $member['event_category']);
            $message = 'Member accepted successfully';
            if ($emailSent) {
                $message .= ' and welcome email sent';
            } else {
                $message .= ' but email notification failed';
            }
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to accept member']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function rejectMember($conn, $memberId) {
    if (empty($memberId)) {
        echo json_encode(['success' => false, 'message' => 'Member ID is required']);
        return;
    }
    
    try {
        // Get member details before deletion (for logging/audit purposes)
        $getMemberSql = "SELECT full_name, email FROM members WHERE id = :member_id AND membership_status = 'New_member'";
        $getMemberStmt = $conn->prepare($getMemberSql);
        $getMemberStmt->bindParam(':member_id', $memberId);
        $getMemberStmt->execute();
        $member = $getMemberStmt->fetch();
        
        if (!$member) {
            echo json_encode(['success' => false, 'message' => 'Member not found or already processed']);
            return;
        }
        
        // Delete the member record
        $deleteSql = "DELETE FROM members WHERE id = :member_id";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bindParam(':member_id', $memberId);
        
        if ($deleteStmt->execute()) {
            // Optional: Log the rejection for audit purposes
            error_log("Member application rejected: {$member['full_name']} ({$member['email']}) by admin ID: {$_SESSION['admin_id']}");
            echo json_encode(['success' => true, 'message' => 'Member application rejected successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reject member']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function toggleApplicationSystem($conn, $enabled) {
    try {
        $value = ($enabled === 'true') ? 'true' : 'false';
        
        $sql = "INSERT INTO application_settings (setting_name, setting_value) 
                VALUES ('application_system_enabled', :value) 
                ON DUPLICATE KEY UPDATE setting_value = :value, updated_at = NOW()";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':value', $value);
        
        if ($stmt->execute()) {
            $status = ($value === 'true') ? 'enabled' : 'disabled';
            echo json_encode(['success' => true, 'message' => "Application system $status successfully"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update application system status']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getApplicationStatus($conn) {
    try {
        $sql = "SELECT setting_value FROM application_settings WHERE setting_name = 'application_system_enabled'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        $enabled = ($result && $result['setting_value'] === 'true') ? true : false;
        echo json_encode(['success' => true, 'enabled' => $enabled]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

require_once '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendAcceptanceEmail($to_email, $full_name, $event_category = 'General') {
    // Check if email notifications are enabled
    global $conn;
    try {
        $settingSql = "SELECT setting_value FROM application_settings WHERE setting_name = 'welcome_email_enabled'";
        $settingStmt = $conn->prepare($settingSql);
        $settingStmt->execute();
        $setting = $settingStmt->fetch();
        
        if (!$setting || $setting['setting_value'] !== 'true') {
            return false; // Email notifications disabled
        }
    } catch (Exception $e) {
        // Continue with email if setting check fails
    }
    
    // Using PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'arafat.haque.biswas@g.bracu.ac.bd';
        $mail->Password   = 'your_app_password'; // Use app password for Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('arafat.haque.biswas@g.bracu.ac.bd', 'BRAC University Cultural Club');
        $mail->addAddress($to_email, $full_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to BRAC University Cultural Club - ' . $event_category . ' Category!';
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #e76f2c, #f3d35c); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 14px; }
                .button { display: inline-block; background: #e76f2c; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Welcome to BUCuC! 🎉</h1>
                </div>
                <div class='content'>
                    <h2>Dear $full_name,</h2>
                    <p>Congratulations! Your application to join the <strong>BRAC University Cultural Club</strong> in the <strong>$event_category category</strong> has been <strong>accepted</strong>!</p>
                    
                    <p>We are thrilled to have you as part of our vibrant community of artists, performers, and cultural enthusiasts. Your journey with us promises to be filled with creativity, friendship, and unforgettable experiences in the $event_category domain.</p>
                    
                    <h3>What's Next?</h3>
                    <ul>
                        <li>🎭 Join our upcoming $event_category events and workshops</li>
                        <li>🤝 Connect with fellow $event_category category members</li>
                        <li>🎨 Showcase your $event_category talents in our performances</li>
                        <li>📚 Access exclusive club resources and $event_category materials</li>
                        <li>🌟 Participate in inter-category collaborations</li>
                    </ul>
                    
                    <p>Keep an eye on your email for updates about our upcoming events, meetings, and opportunities to get involved!</p>
                    
                    <a href='https://www.facebook.com/bucuc' class='button'>Join Our Facebook Group</a>
                </div>
                <div class='footer'>
                    <p>Best regards,<br>
                    <strong>BRAC University Cultural Club</strong><br>
                    Email: bucuc@support.ac.bd<br>
                    Facebook: @bucuc</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Dear $full_name,\n\nCongratulations! Your application to join the BRAC University Cultural Club in the $event_category category has been accepted!\n\nWe look forward to your participation in our upcoming $event_category events and activities.\n\nBest regards,\nBRAC University Cultural Club";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function getMemberStatistics($conn) {
    try {
        // Use the new member_statistics view
        $sql = "SELECT * FROM member_statistics";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stats) {
            echo json_encode(['success' => true, 'data' => $stats]);
        } else {
            // Fallback to manual calculation if view doesn't exist
            $fallbackSql = "SELECT 
                COUNT(*) as total_members,
                SUM(CASE WHEN application_status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
                SUM(CASE WHEN application_status = 'accepted' THEN 1 ELSE 0 END) as accepted_members,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_members,
                SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as total_males,
                SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as total_females,
                SUM(CASE WHEN gender = 'Other' THEN 1 ELSE 0 END) as total_others
                FROM members";
            
            $fallbackStmt = $conn->prepare($fallbackSql);
            $fallbackStmt->execute();
            $fallbackStats = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $fallbackStats]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getMembersByCategory($conn, $category) {
    try {
        if (empty($category)) {
            echo json_encode(['success' => false, 'message' => 'Category is required']);
            return;
        }
        
        $sql = "SELECT id, full_name, email, department, semester, gender, 
                membership_status, event_category, status, created_at
                FROM members 
                WHERE event_category = :category AND status = 'active'
                ORDER BY full_name ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':category', $category);
        $stmt->execute();
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $members, 'category' => $category, 'count' => count($members)]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>

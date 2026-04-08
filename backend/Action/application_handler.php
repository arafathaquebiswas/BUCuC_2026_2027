<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION["admin"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once '../Database/db.php';
require_once '../config/email_config.php';
require_once '../vendor/autoload.php';
require_once 'google_sheets_integration.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$memberId = $input['member_id'] ?? 0;

if (empty($action) || empty($memberId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->createConnection();

    if ($action === 'reject') {
        // Try to delete from both tables (only one will have the record)
        $stmt1 = $pdo->prepare("DELETE FROM pending_applications WHERE id = ?");
        $result1 = $stmt1->execute([$memberId]);

        $stmt2 = $pdo->prepare("DELETE FROM shortlisted_members WHERE id = ?");
        $result2 = $stmt2->execute([$memberId]);

        if ($result1 || $result2) {
            echo json_encode(['success' => true, 'message' => 'Member application rejected and removed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Member not found in pending or shortlisted lists']);
        }

    } elseif ($action === 'shortlist') {
        // Move from pending_applications to shortlisted_members

        // 1. Get member from pending_applications
        $stmt = $pdo->prepare("SELECT * FROM pending_applications WHERE id = ?");
        $stmt->execute([$memberId]);
        $member = $stmt->fetch();

        if (!$member) {
            echo json_encode(['success' => false, 'message' => 'Application not found in pending list']);
            exit();
        }

        // 2. Insert into shortlisted_members
        $insertStmt = $pdo->prepare("
            INSERT INTO shortlisted_members 
            (full_name, university_id, email, gsuite_email, department, phone, semester, gender, facebook_url, firstPriority, secondPriority, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $insertResult = $insertStmt->execute([
            $member['full_name'],
            $member['university_id'],
            $member['email'],
            $member['gsuite_email'],
            $member['department'],
            $member['phone'],
            $member['semester'],
            $member['gender'],
            $member['facebook_url'],
            $member['firstPriority'],
            $member['secondPriority'],
            $member['created_at']
        ]);

        if ($insertResult) {
            // 3. Delete from pending_applications
            $deleteStmt = $pdo->prepare("DELETE FROM pending_applications WHERE id = ?");
            $deleteStmt->execute([$memberId]);

            echo json_encode(['success' => true, 'message' => 'Member shortlisted successfully. Super Admin can now review.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to shortlist member']);
        }

    } elseif ($action === 'accept') {
        // Only Super Admin can accept (Final Approval)
        if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] !== 'main_admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Only Super Admins can give final approval.']);
            exit();
        }

        // First, get member details from shortlisted_members
        $stmt = $pdo->prepare("SELECT * FROM shortlisted_members WHERE id = ?");
        $stmt->execute([$memberId]);
        $member = $stmt->fetch();

        if (!$member) {
            echo json_encode(['success' => false, 'message' => 'Shortlisted member not found']);
            exit();
        }

        // Send congratulations email
        $emailResult = sendCongratulationsEmail($member);

        if ($emailResult['success']) {
            // Insert member into members table (final approved members)
            $insertStmt = $pdo->prepare("
                INSERT INTO members 
                (full_name, university_id, email, gsuite_email, department, phone, semester, gender, facebook_url, firstPriority, secondPriority, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");

            $insertResult = $insertStmt->execute([
                $member['full_name'],
                $member['university_id'],
                $member['email'],
                $member['gsuite_email'],
                $member['department'],
                $member['phone'],
                $member['semester'],
                $member['gender'],
                $member['facebook_url'],
                $member['firstPriority'],
                $member['secondPriority'],
                $member['created_at']
            ]);

            if ($insertResult) {
                // Delete from shortlisted_members
                $delStmt = $pdo->prepare("DELETE FROM shortlisted_members WHERE id = ?");
                $delStmt->execute([$memberId]);

                // Send data to Google Sheets
                $sheetsResult = sendToGoogleSheets($member);

                // Log the Google Sheets operation
                logGoogleSheetsOperation('accept_member', $member, $sheetsResult);

                echo json_encode([
                    'success' => true,
                    'message' => 'Member accepted successfully! Email sent and added to members database.',
                    'email_success' => true,
                    'sheets_success' => $sheetsResult['success']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add member to final members list']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send congratulations email: ' . $emailResult['error']]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function sendCongratulationsEmail($member)
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURITY === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($member['gsuite_email'], $member['full_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Congratulations! Welcome to BRAC University Cultural Club';

        $mail->Body = generateEmailTemplate($member);
        $mail->AltBody = generatePlainTextEmail($member);

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

function generateEmailTemplate($member)
{
    // Get venue information
    $venueInfo = getLatestVenueInfo();

    $template = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Welcome to BUCUC</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f4f4f4;
            }
            .container {
                background-color: #ffffff;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
                color: white;
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 20px;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
            }
            .content {
                padding: 20px 0;
            }
            .highlight {
                background-color: #e8f5e8;
                padding: 15px;
                border-left: 4px solid #28a745;
                margin: 20px 0;
            }
            .footer {
                text-align: center;
                padding: 20px 0;
                color: #666;
                border-top: 1px solid #eee;
                margin-top: 20px;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background-color: #28a745;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 10px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🎉 Congratulations!</h1>
                <p>Welcome to BRAC University Cultural Club</p>
            </div>
            
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($member['full_name']) . '</strong>,</p>
                
                <div class="highlight">
                    <p><strong>Congratulations!</strong> You’ve successfully completed all recruitment steps and are now<strong>an official member of the BRAC University Cultural Club (BUCuC</strong>We’re thrilled to have you on board and can’t wait to see the dedication and creativity you bring to the club!</p>
                </div>
                
                
                <h3>Your Application Details:</h3>
                <ul>
                    <li><strong>Name:</strong> ' . htmlspecialchars($member['full_name']) . '</li>
                    <li><strong>University ID:</strong> ' . htmlspecialchars($member['university_id']) . '</li>
                    <li><strong>Department:</strong> ' . htmlspecialchars($member['department']) . '</li>
                    <li><strong>First Priority:</strong> ' . htmlspecialchars($member['firstPriority']) . '</li>
                    <li><strong>Second Priority:</strong> ' . htmlspecialchars($member['secondPriority']) . '</li>
                </ul>
                <strong>To stay updated on all upcoming activities and announcements, make sure to follow BUCuC on our social media platforms:</strong>
                
                <ul>
                    <li><a href="https://www.facebook.com/bucuc" class="btn">BUCuC Official Page</a></li>
                    <li><a href="https://www.facebook.com/groups/86555568937" class="btn">BUCuC Official Group</a></li>
                    <li><a href="https://www.instagram.com/bucuclive/" class="btn">BUCuC Official Instagram</a></li>
                    <li><a href="https://www.youtube.com/@bracuniversityculturalclub717" class="btn">BUCuC Official Youtube</a></li>
                </ul>

                <p>Now, it’s time for your Orientation — a must-attend event where you’ll meet your fellow members, learn more about BUCuC, and kickstart your journey with us! </p>
                ';

    // Add venue information if available
    if ($venueInfo) {
        $template .= '
                <div class="highlight" style="background-color: #fff3cd; border-left: 4px solid #ffc107; margin: 20px 0;">
                    <h3 style="color: #856404; margin-top: 0;">📍 Upcoming Event/Meeting</h3>
                    <ul style="margin-bottom: 0;">
                        <li><strong>Venue:</strong> ' . htmlspecialchars($venueInfo['venue_name']) . '</li>
                        <li><strong>Location:</strong> ' . htmlspecialchars($venueInfo['venue_location']) . '</li>
                        <li><strong>Date:</strong> ' . date('F j, Y', strtotime($venueInfo['venue_dateTime'])) . '</li>
                        <li><strong>Time:</strong> ' . htmlspecialchars($venueInfo['venue_startingTime'] . ' - ' . $venueInfo['venue_endingTime'] . ' ' . $venueInfo['venu_ampm']) . '</li>
                    </ul>
                </div>
                ';
    }

    $template .= '
                <strong>Your presence is MANDATORY, and we promise it’ll be worth it! Looking forward to seeing you there.!</strong>
                <br>
                <strong>You will be added to a Messenger group after the orientation for smooth communication. If you are not added within the next few days after that , please reach out to the HR team directly.</strong>
                <br>
                <p>Welcome to the BUCuC family!</p>
                <br>

                <b>Work • Bond • Glow</b>

                
                <p>Best regards,<br>
                <strong style="color:#DC143C">Rudian Ahmed </strong>(01601946311),<br><strong style="color:#DC143C">Secretary of Human Resources</strong>,<br> BRAC University Cultural Club</p>
            </div>
            
            <div class="footer">
                <p>This is an automated email. Please do not reply to this message.</p>
                <p>For any questions, contact us at: hr.bucuc@gmail.com</p>
            </div>
        </div>
    </body>
    </html>';

    return $template;
}

function generatePlainTextEmail($member)
{
    $venueInfo = getLatestVenueInfo();

    $text = "Congratulations " . $member['full_name'] . "!\n\n" .
        "Your application to join the BRAC University Cultural Club has been ACCEPTED!\n\n" .
        "Your Details:\n" .
        "Name: " . $member['full_name'] . "\n" .
        "University ID: " . $member['university_id'] . "\n" .
        "Department: " . $member['department'] . "\n" .
        "First Priority: " . $member['firstPriority'] . "\n" .
        "Second Priority: " . $member['secondPriority'] . "\n\n" .
        "What's Next?\n" .
        "As a member of BUCUC, you can now:\n" .
        "- Participate in all cultural events and competitions\n" .
        "- Join various committees based on your interests\n" .
        "- Attend exclusive workshops and training sessions\n" .
        "- Network with fellow cultural enthusiasts\n" .
        "- Contribute to organizing amazing cultural programs\n\n";

    // Add venue information if available
    if ($venueInfo) {
        $text .= "UPCOMING EVENT/MEETING:\n" .
            "Venue: " . $venueInfo['venue_name'] . "\n" .
            "Location: " . $venueInfo['venue_location'] . "\n" .
            "Date: " . date('F j, Y', strtotime($venueInfo['venue_dateTime'])) . "\n" .
            "Time: " . $venueInfo['venue_startingTime'] . ' - ' . $venueInfo['venue_endingTime'] . ' ' . $venueInfo['venu_ampm'] . "\n\n";
    }

    $text .= "Welcome to BUCUC!\n\n" .
        "Best regards,\n" .
        "BRAC University Cultural Club";

    return $text;
}

function getLatestVenueInfo()
{
    try {
        $database = new Database();
        $pdo = $database->createConnection();

        // Get the latest venue information
        $stmt = $pdo->query("SELECT * FROM venuInfo ORDER BY venue_id DESC LIMIT 1");
        $venue = $stmt->fetch();

        return $venue ? $venue : null;
    } catch (Exception $e) {
        // Return null if there's an error or no venue info
        return null;
    }
}

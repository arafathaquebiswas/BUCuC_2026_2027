<?php
session_start();
require '../Database/db.php';

$database = new Database();
$conn = $database->createConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get form data
        $fullName = trim($_POST['signup-name']);
        $universityId = trim($_POST['signup-id']);
        $email = trim($_POST['signup-main-email']);
        $gsuiteEmail = trim($_POST['signup-gsuite-email'] ?? '');
        $department = trim($_POST['signup-department']);
        $phone = trim($_POST['signup-phone']);
        $semester = $_POST['signup-semester'];
        $gender = $_POST['signup-gender'];
        $facebookUrl = trim($_POST['signup-facebook'] ?? '');
        $firstDept = trim($_POST['signup-first-dept'] ?? '');
        $secondDept = trim($_POST['signup-second-dept'] ?? '');

        $dob = $_POST['signup-dob'];

        $checkEmailSql = "SELECT id FROM pending_applications WHERE gsuite_email = :gsuiteEmail";
        $checkEmailStmt = $conn->prepare($checkEmailSql);
        $checkEmailStmt->bindParam(':gsuiteEmail', $gsuiteEmail);
        $checkEmailStmt->execute();

        if ($checkEmailStmt->fetch()) {
            $_SESSION['signup_error'] = 'G-Suite email already registered';
            header('Location: ../../frontend/index.php#signup');
            exit();
        }

        $checkIdSql = "SELECT id FROM pending_applications WHERE university_id = :university_id";
        $checkIdStmt = $conn->prepare($checkIdSql);
        $checkIdStmt->bindParam(':university_id', $universityId);
        $checkIdStmt->execute();

        if ($checkIdStmt->fetch()) {
            $_SESSION['signup_error'] = 'University ID already registered';
            header('Location: ../../frontend/index.php#signup');
            exit();
        }

        $sql = "INSERT INTO pending_applications (
            full_name, university_id, email, gsuite_email, department, 
            phone, semester, gender, date_of_birth, facebook_url, firstPriority, secondPriority
        ) VALUES (
            :full_name, :university_id, :email, :gsuite_email, :department,
            :phone, :semester, :gender, :date_of_birth, :facebook_url, 
            :firstPriority, :secondPriority
        )";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':full_name', $fullName);
        $stmt->bindParam(':university_id', $universityId);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':gsuite_email', $gsuiteEmail);
        $stmt->bindParam(':department', $department);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':semester', $semester);
        $stmt->bindParam(':gender', $gender);
        $stmt->bindParam(':date_of_birth', $dob);
        $stmt->bindParam(':facebook_url', $facebookUrl);
        $stmt->bindParam(":firstPriority", $firstDept);
        $stmt->bindParam(":secondPriority", $secondDept);


        if ($stmt->execute()) {
            $_SESSION['signup_success'] = 'Registration successful! Welcome to BRAC University Cultural Club. Your membership application has been submitted successfully.';
            header('Location: ../../frontend/index.php#signup');
            exit();
        } else {
            $_SESSION['signup_error'] = 'Registration failed. Please try again.';
            header('Location: ../../frontend/index.php#signup');
            exit();
        }

    } catch (PDOException $e) {
        error_log('Database error in signup: ' . $e->getMessage());
        $_SESSION['signup_error'] = 'Database error: ' . $e->getMessage();
        header('Location: ../../frontend/index.php#signup');
        exit();
    } catch (Exception $e) {
        error_log('General error in signup: ' . $e->getMessage());
        $_SESSION['signup_error'] = 'Error: ' . $e->getMessage();
        header('Location: ../../frontend/index.php#signup');
        exit();
    }
} else {
    $_SESSION['signup_error'] = 'Invalid request method';
    header('Location: ../../frontend/index.php#signup');
    exit();
}
?>
<?php
session_start();

// Get success or error messages from session
$signupSuccess = isset($_SESSION['signup_success']) ? $_SESSION['signup_success'] : null;
$signupError = isset($_SESSION['signup_error']) ? $_SESSION['signup_error'] : null;

unset($_SESSION['signup_success'], $_SESSION['signup_error']);

// ── Cache-busting ──────────────────────────────────────────────────────────
// Version = last-modified time of this file.
// Save (touch) index.php after uploading new images to force a new version.
define('IMG_V', filemtime(__FILE__));

// Output-buffer callback: appends ?v=IMG_V to every local <img src="images/…">
// and CSS url('images/…') in the rendered HTML, so browsers never serve stale images.
function _imgCacheBust(string $html): string {
    $v = IMG_V;
    // src="images/..." and src='images/...'
    $html = preg_replace(
        '/\b(src=["\'])(images\/[^"\'?#\s]+)(["\'])/',
        '$1$2?v=' . $v . '$3',
        $html
    );
    // url('images/...'), url("images/..."), url(images/...)
    $html = preg_replace(
        '/\burl\((["\']?)(images\/[^"\'?#\s)]+)\1\)/',
        'url($1$2?v=' . $v . '$1)',
        $html
    );
    return $html;
}
ob_start('_imgCacheBust');
// ──────────────────────────────────────────────────────────────────────────

// Auto-parse Panel_26_27 secretaries from image filenames
function parsePanel26SecretaryFilename($filename) {
    $base = pathinfo($filename, PATHINFO_FILENAME);

    // New format: Name(Department) — parentheses enclose the position
    if (preg_match('/^(.+?)\s*\((.+)\)\s*$/', $base, $m)) {
        $rawName = trim($m[1]);
        $rawDept = trim($m[2]);

        // Format name: split CamelCase, fix dot initials (e.g. "A.B" → "A. B")
        $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $rawName);
        $name = preg_replace('/\.([A-Za-z])/', '. $1', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);

        // SOP shortcuts
        if (preg_match('/^SOP_music$/i', $rawDept)) {
            $dept = 'Secretary of Performance (Music)';
        } elseif (preg_match('/^SOP_dance$/i', $rawDept)) {
            $dept = 'Secretary of Performance (Dance)';
        } else {
            $dept = $rawDept;
            // Fix known typos
            $dept = preg_replace('/^Srecretaryof/i',  'SecretaryOf', $dept);
            $dept = str_replace('Secretay', 'Secretary', $dept);
            // Normalize "SecretaryOf" / "Secretary Of" / "SecretaryOf " → "Secretary of "
            $dept = preg_replace('/Secretary\s*[Oo]f\s*/i', 'Secretary of ', $dept);
            // Expand MIAP acronym
            $dept = str_replace('MIAP', 'Marketing, IT, Archive & Photography', $dept);
            // Split CamelCase
            $dept = preg_replace('/([a-z])([A-Z])/', '$1 $2', $dept);
            // Ensure spaces around &
            $dept = preg_replace('/([A-Za-z])\s*&\s*([A-Za-z])/', '$1 & $2', $dept);
            // Collapse multiple spaces
            $dept = preg_replace('/\s+/', ' ', $dept);
            $dept = trim($dept);
        }

        return ['name' => $name, 'department' => $dept];
    }

    // Fallback: legacy underscore format (e.g. "Name_Department_Sub.jpg")
    $deptKeywords = [
        'Performence', 'Performance', 'Marketing', 'Creative', 'Finance',
        'Administration', 'Administation', 'Human', 'PublicRelation',
        'EventManagement', 'event', 'Research', 'Archive', 'IT'
    ];
    $parts = array_values(array_filter(array_map('trim', explode('_', $base)), fn($p) => $p !== ''));
    $deptStart = count($parts);
    for ($i = 1; $i < count($parts); $i++) {
        foreach ($deptKeywords as $kw) {
            if (stripos($parts[$i], $kw) === 0) { $deptStart = $i; break 2; }
        }
    }
    $nameParts = array_slice($parts, 0, $deptStart);
    $deptParts  = array_slice($parts, $deptStart);
    $nameWords = [];
    foreach ($nameParts as $p) {
        $w = preg_replace('/([a-z])([A-Z])/', '$1 $2', $p);
        $w = preg_replace('/\.([A-Z])/', '. $1', $w);
        $nameWords[] = $w;
    }
    $name = implode(' ', $nameWords);
    $deptResult = '';
    foreach ($deptParts as $idx => $p) {
        $isNewDept = false;
        foreach ($deptKeywords as $kw) {
            if (stripos(trim($p), $kw) === 0) { $isNewDept = true; break; }
        }
        $d = preg_replace('/([a-z])([A-Z])/', '$1 $2', $p);
        $d = preg_replace('/([A-Za-z])&([A-Za-z])/', '$1 & $2', $d);
        // Map short keywords to full department names
        $d = preg_replace('/^event$/i', 'Event Management & Logistics', $d);
        if ($idx === 0) {
            $deptResult = $d;
        } elseif ($isNewDept) {
            $deptResult .= ', ' . $d;
        } else {
            $deptResult .= ' ' . $d;
        }
    }
    $dept = $deptResult;
    $dept = str_replace('Performence', 'Performance', $dept);
    $dept = str_replace('Administation', 'Administration', $dept);
    return ['name' => trim($name), 'department' => trim($dept)];
}

$panel26SecDir = __DIR__ . '/images/Panel_26_27/Secreteries/';
$panel26Secretaries = [];
if (is_dir($panel26SecDir)) {
    $files = array_merge(
        glob($panel26SecDir . '*.jpg')  ?: [],
        glob($panel26SecDir . '*.jpeg') ?: []
    );
    sort($files);
    foreach ($files as $file) {
        $filename = basename($file);
        $parsed = parsePanel26SecretaryFilename($filename);
        $panel26Secretaries[] = [
            'name'     => $parsed['name'],
            'position' => $parsed['department'],
            'image'    => 'images/Panel_26_27/Secreteries/' . rawurlencode($filename) . '?v=' . filemtime($file),
            'facebook' => 'http://www.facebook.com/'
        ];
    }
}

// Pin Arafat first, Zakaria second; sort the rest by department order
$_pinned = [];
$_rest   = [];
foreach ($panel26Secretaries as $_member) {
    if (!isset($_pinned[0]) && stripos($_member['name'], 'Arafat') !== false) {
        $_pinned[0] = $_member;
    } elseif (!isset($_pinned[1]) && stripos($_member['name'], 'Zakaria') !== false) {
        $_pinned[1] = $_member;
    } else {
        $_rest[] = $_member;
    }
}
// Department display order (keyword matched against the parsed position string)
$_deptRank = [
    'Marketing'      => 0,
    'Human'          => 1,
    'Event'          => 2,
    'Finance'        => 3,
    'Administration' => 4,
    'Creative'       => 5,
    'Public'         => 6,
    'Performance'    => 7,
    'Research'       => 8,
];
usort($_rest, function ($a, $b) use ($_deptRank) {
    $rankA = 99;
    $rankB = 99;
    foreach ($_deptRank as $kw => $rank) {
        if (stripos($a['position'], $kw) !== false) { $rankA = $rank; break; }
    }
    foreach ($_deptRank as $kw => $rank) {
        if (stripos($b['position'], $kw) !== false) { $rankB = $rank; break; }
    }
    return $rankA <=> $rankB;
});
ksort($_pinned);
$panel26Secretaries = array_merge(array_values($_pinned), $_rest);
unset($_pinned, $_rest, $_member, $_deptRank);

// Admin login functionality
$adminSuccess = "";
$adminError = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['adminLogin'])) {
    require_once '../backend/Database/db.php';

    $database = new Database();
    $conn = $database->createConnection();

    $adminEmail = htmlspecialchars($_POST["adminEmail"]);
    $adminPassword = $_POST["adminPassword"];

    $sql = "SELECT * FROM adminpanel WHERE email=:adminemail AND status='active'";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":adminemail", $adminEmail);
    $stmt->execute();
    $admin = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($admin && password_verify($adminPassword, $admin[0]['password'])) {
        $adminSuccess = "Login successful! Redirecting to dashboard...";
        $_SESSION['username'] = $admin[0]['username'];
        $_SESSION['admin_id'] = $admin[0]['id'];
        $_SESSION['admin_email'] = $admin[0]['email'];
        $_SESSION['admin_role'] = $admin[0]['role'];
        $_SESSION['admin'] = true;
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_name'] = $admin[0]['username'];
        // Redirect based on role
        if ($admin[0]['role'] === 'main_admin') {
            header("refresh:1;url=super_admin_dashboard.php");
        } else {
            header("refresh:1;url=admin_dashboard.php");
        }
    } else {
        $adminError = "Invalid admin credentials. Contact: hr.bucuc@gmail.com";
    }
}

// Check signup status
function getSignupStatus()
{
    try {
        require_once '../backend/Database/db.php';
        $database = new Database();
        $conn = $database->createConnection();

        // Create table if it doesn't exist
        $createTableQuery = "
            CREATE TABLE IF NOT EXISTS signup_status (
                id INT PRIMARY KEY DEFAULT 1,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by VARCHAR(100) DEFAULT NULL
            )
        ";
        $conn->exec($createTableQuery);

        // Insert default record if table is empty
        $checkQuery = "SELECT COUNT(*) FROM signup_status";
        $stmt = $conn->prepare($checkQuery);
        $stmt->execute();
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            $insertQuery = "INSERT INTO signup_status (id, is_enabled, updated_by) VALUES (1, 1, 'system')";
            $stmt = $conn->prepare($insertQuery);
            $stmt->execute();
        }

        $sql = "SELECT is_enabled FROM signup_status WHERE id = 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return (bool) $result['is_enabled'];
        }

        return true;
    } catch (Exception $e) {
        // Default to enabled on error
        error_log("Error checking signup status: " . $e->getMessage());
        return true;
    }
}

$signupEnabled = getSignupStatus();

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="description" content>
    <meta name="author" content>

    <title>BRAC University Cultural Club</title>

    <!-- CSS FILES -->
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;400;700&display=swap" rel="stylesheet">

    <link href="css/bootstrap.min.css" rel="stylesheet">

    <link href="css/bootstrap-icons.css" rel="stylesheet">

    <link href="css/templatemo-festava-live.css" rel="stylesheet">

    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <!-- Ionicons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="icon" type="image/png" href="images/logopng.png">

    <style>
        /* Custom Scrollbar (match past_events.html) */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #e76f2c, #f3d35c);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #f3d35c, #e76f2c);
        }

        /* SB Members Slideshow Styles */
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600&display=swap");

        /* Enhanced Sign Up Form Styles */
        .signup-title {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #e76f2c, #f3d35c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 2rem;
            animation: titleGlow 3s ease-in-out infinite;
        }

        @keyframes titleGlow {

            0%,
            100% {
                filter: brightness(1);
            }

            50% {
                filter: brightness(1.2);
            }
        }

        .signup-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow:
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            position: relative;
        }

        .signup-container::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #e76f2c, #f3d35c, #e76f2c);
            border-radius: 25px;
            z-index: -1;
            opacity: 0.3;
            animation: borderGlow 4s ease-in-out infinite;
            background-size: 400% 400%;
        }

        @keyframes borderGlow {

            0%,
            100% {
                opacity: 0.3;
                background-position: 0% 50%;
            }

            50% {
                opacity: 0.6;
                background-position: 100% 50%;
            }
        }

        .signup-tab,
        .login-tab,
        .maps-tab {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            border-radius: 15px;
            padding: 12px 24px;
            margin: 0 5px;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .signup-tab::before,
        .login-tab::before,
        .maps-tab::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(231, 111, 44, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .signup-tab:hover::before,
        .login-tab:hover::before,
        .maps-tab:hover::before {
            left: 100%;
        }

        .signup-tab:hover,
        .login-tab:hover,
        .maps-tab:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(231, 111, 44, 0.3);
        }

        .signup-tab.active {
            background: linear-gradient(135deg, #e76f2c, #f3d35c);
            color: white;
            box-shadow: 0 8px 25px rgba(231, 111, 44, 0.4);
        }

        .login-tab.active {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.4);
        }

        .maps-tab.active {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }

        .contact-form {
            padding: 40px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .form-control {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(231, 111, 44, 0.1);
            border-radius: 15px;
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .form-control::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(231, 111, 44, 0.1), transparent);
            transition: left 0.6s ease;
        }

        .form-control:focus::before {
            left: 100%;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 1);
            border-color: #e76f2c;
            box-shadow: 0 0 20px rgba(231, 111, 44, 0.2);
            transform: translateY(-2px);
            outline: none;
        }

        .form-control::placeholder {
            color: #6c757d;
            font-weight: 400;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            display: block;
        }

        /* Date Input Styling - Show "Date of Birth" when empty */
        input[type="date"] {
            color: #333;
        }

        input[type="date"]:invalid::-webkit-datetime-edit {
            color: #6c757d;
        }

        input[type="date"]:invalid::-webkit-datetime-edit-text {
            color: #6c757d;
        }

        input[type="date"]:invalid::-webkit-datetime-edit-month-field {
            color: #6c757d;
        }

        input[type="date"]:invalid::-webkit-datetime-edit-day-field {
            color: #6c757d;
        }

        input[type="date"]:invalid::-webkit-datetime-edit-year-field {
            color: #6c757d;
        }

        /* For Firefox */
        input[type="date"]:invalid {
            color: #6c757d;
        }

        input[type="date"]:valid {
            color: #333;
        }

        /* Custom date input wrapper to show "Date of Birth" placeholder */
        .date-input-wrapper {
            position: relative;
        }

        .date-input-wrapper .date-placeholder {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            pointer-events: none;
            font-size: 1rem;
            z-index: 1;
            transition: opacity 0.3s;
            background: rgba(255, 255, 255, 0.95);
            padding: 0 8px;
        }

        .date-input-wrapper input[type="date"]:not(:invalid)~.date-placeholder,
        .date-input-wrapper input[type="date"]:focus~.date-placeholder {
            opacity: 0;
        }

        .form-check-input {
            border: 2px solid #e76f2c;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .form-check-input:checked {
            background-color: #e76f2c;
            border-color: #e76f2c;
            box-shadow: 0 0 10px rgba(231, 111, 44, 0.3);
        }

        .form-check-label {
            font-weight: 500;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-check-label:hover {
            color: #e76f2c;
        }

        .form-check-inline {
            margin-right: 20px;
        }

        /* Panel Year Selector Styles */
        .panel-year-selector {
            max-width: 400px;
            margin: 0 auto;
        }

        .panel-year-dropdown {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid #e76f2c;
            border-radius: 15px;
            padding: 12px 20px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            transition: all 0.3s ease;
        }

        .panel-year-dropdown:focus {
            background: rgba(255, 255, 255, 1);
            border-color: #f3d35c;
            box-shadow: 0 0 20px rgba(231, 111, 44, 0.3);
            outline: none;
        }

        .panel-year-dropdown:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 111, 44, 0.2);
        }

        /* Panel Content Area */
        .panel-content-area {
            position: relative;
            min-height: 500px;
        }

        .panel-members-container,
        .sb-members-container {
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .panel-members-container.fade-out,
        .sb-members-container.fade-out {
            opacity: 0;
            transform: translateY(20px);
        }

        .panel-members-container.fade-in,
        .sb-members-container.fade-in {
            opacity: 1;
            transform: translateY(0);
        }

        /* Three member layout */
        .three-member .col-lg-5:nth-child(4) {
            margin-top: 0 !important;
        }

        .three-member .panel-members-grid {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .three-member .panel-members-grid .col-lg-5 {
            flex: 0 0 auto;
            max-width: 300px;
        }

        /* ── Panel Member Card ──────────────────────────────────────────────
           Self-contained component used for every panel member card.
           Does NOT touch .artists-thumb (used by the Advisors section).
        ─────────────────────────────────────────────────────────────────── */
        .panel-member-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
        }

        .panel-member-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 16px 48px rgba(243, 211, 92, 0.22),
                        0 6px 20px rgba(0, 0, 0, 0.45);
        }

        /* Image area — fixed 4:5 portrait aspect ratio */
        .panel-member-card .pmc-image-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 5;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.04);
            flex-shrink: 0;
        }

        .panel-member-card .pmc-image-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center;
            display: block;
            transition: transform 0.45s ease;
        }

        .panel-member-card:hover .pmc-image-wrap img {
            transform: scale(1.06);
        }

        /* Facebook overlay — appears on hover */
        .panel-member-card .pmc-overlay {
            position: absolute;
            inset: 0;
            background: rgba(231, 111, 44, 0.50);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
        }

        .panel-member-card:hover .pmc-overlay {
            opacity: 1;
        }

        .panel-member-card .pmc-overlay a {
            color: #ffffff;
            font-size: 2.4rem;
            line-height: 1;
            text-decoration: none;
            transition: transform 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .panel-member-card .pmc-overlay a:hover {
            transform: scale(1.2);
        }

        /* Text body — always visible below the image */
        .panel-member-card .pmc-body {
            padding: 14px 16px 18px;
            text-align: center;
            flex-shrink: 0;
            background: rgba(0, 0, 0, 0.40);
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .panel-member-card .pmc-name {
            font-size: 1rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
            line-height: 1.3;
            word-break: break-word;
        }

        .panel-member-card .pmc-role {
            font-size: 0.78rem;
            font-weight: 600;
            color: #f3d35c;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin: 0;
            display: block;
        }

        /* Column wrapper — makes all cards in a row stretch to equal height */
        .panel-members-grid .pmc-col {
            display: flex;
        }

        /* Loading spinner */
        .panel-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(231, 111, 44, 0.3);
            border-top: 4px solid #e76f2c;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .form-control[type="submit"],
        button[type="submit"] {
            background: linear-gradient(135deg, #e76f2c, #f3d35c);
            border: none;
            border-radius: 15px;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(231, 111, 44, 0.3);
        }

        .form-control[type="submit"]::before,
        button[type="submit"]::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s ease;
        }

        .form-control[type="submit"]:hover::before,
        button[type="submit"]:hover::before {
            left: 100%;
        }

        .form-control[type="submit"]:hover,
        button[type="submit"]:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(231, 111, 44, 0.5);
        }

        /* Floating particles for signup section */
        .signup-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
            z-index: 1;
        }

        .signup-particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: linear-gradient(45deg, #e76f2c, #f3d35c);
            border-radius: 50%;
            animation: signupFloat 8s infinite linear;
            opacity: 0.6;
        }

        @keyframes signupFloat {
            0% {
                transform: translateY(100vh) rotate(0deg) scale(0);
                opacity: 0;
            }

            10% {
                opacity: 0.6;
                transform: scale(1);
            }

            90% {
                opacity: 0.6;
            }

            100% {
                transform: translateY(-100px) rotate(360deg) scale(0);
                opacity: 0;
            }
        }

        /* Enhanced admin check container */
        .admin-check-container {
            padding: 40px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(231, 111, 44, 0.1);
        }

        .admin-check-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #e76f2c, #f3d35c);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 3.5rem;
            color: white;
            box-shadow: 0 15px 40px rgba(231, 111, 44, 0.4);
            animation: iconFloat 3s ease-in-out infinite;
        }

        @keyframes iconFloat {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .admin-check-title {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }

        .admin-check-description {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }

        .admin-options {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .admin-options .btn {
            padding: 12px 24px;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .admin-options .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }

        .admin-options .btn:hover::before {
            left: 100%;
        }

        .admin-options .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .signup-title {
                font-size: 2.5rem;
            }

            .contact-form {
                padding: 30px 20px;
            }

            .admin-options {
                flex-direction: column;
                gap: 15px;
            }

            .admin-options .btn {
                width: 100%;
            }
        }

        .sb-section {
            position: relative;
            padding: 80px 0;
            background: url(https://github.com/ecemgo/mini-samples-great-tricks/assets/13468728/8727c9b1-be21-4932-a221-4257b59a74dd);
            background-repeat: no-repeat;
            backdrop-filter: blur(30%);
            -webkit-backdrop-filter: blur(30%);
            animation: slidein 120s forwards infinite alternate;
        }

        @keyframes slidein {

            0%,
            100% {
                background-position: 20% 0%;
                background-size: 3400px;
            }

            50% {
                background-position: 100% 0%;
                background-size: 2400px;
            }
        }

        .sb-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 1;
        }

        .sb-section .container {
            position: relative;
            z-index: 2;
        }

        .album-cover {
            width: 100%;
            max-width: 100vw;
            box-sizing: border-box;
        }

        .sb-swiper {
            width: 100%;
            max-width: 100vw;
            box-sizing: border-box;
            padding: 40px 0 100px;
        }

        .sb-swiper .swiper-slide {
            position: relative;
            width: 300px;
            height: 300px;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #fff;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
            transition: 0.3s;
            min-width: 120px;
            min-height: 120px;
        }

        .sb-swiper .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }

        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgba(0, 0, 0, 0.5);
            opacity: 0;
            transition: opacity 0.3s;
            border-radius: 10px;
        }

        .sb-swiper .swiper-slide:hover .overlay {
            opacity: 1;
        }

        .sb-swiper .swiper-slide .overlay ion-icon {
            font-size: 3rem;
            color: #fff;
            transition: transform 0.3s;
        }

        .sb-swiper .swiper-slide:hover .overlay ion-icon {
            transform: scale(1.2);
        }

        .member-name {
            position: absolute;
            bottom: -60px;
            left: 0;
            right: 0;
            text-align: center;
            color: white;
            font-size: 1.1rem;
            font-weight: 500;
            padding: 5px;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 0 0 10px 10px;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(20px);
        }

        .member-name .name {
            display: block;
            font-size: 1rem;
            margin-bottom: 1px;
        }

        .member-name .position {
            display: block;
            font-size: 0.8rem;
            color: #ffd700;
            font-weight: 400;
        }

        .sb-swiper .swiper-slide-active .member-name {
            opacity: 1;
            transform: translateY(0);
            bottom: 0;
        }

        .sb-section-title {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            font-size: 2.5rem;
            font-weight: 600;
        }

        .album-img-reflect .main-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px 10px 0 0;
            display: block;
        }

        .album-img-reflect .reflection {
            width: 100%;
            height: 40px;
            overflow: hidden;
            position: relative;
            display: block;
        }

        #mainHeader,
        #mainNavbar {
            transition: all 0.3s ease;
        }

        /* Ensure header is visible from the start */
        #mainNavbar {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
        }

        #mainNavbar.scrolled {
            background-color: #000000 !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        }

        .site-header,
        #mainNavbar {
            padding-top: 4px !important;
            padding-bottom: 4px !important;
            min-height: 48px;
            display: flex;
            align-items: center;
        }

        .site-header .navbar-brand,
        #mainNavbar .navbar-brand {
            display: flex;
            align-items: center;
            font-weight: bold;
            font-size: 1.5em;
            color: #222;
            gap: 0.5em;
        }

        #mainNavbar .navbar-brand {
            color: #fff;
        }

        .site-header .navbar-brand img,
        #mainNavbar .navbar-brand img {
            height: 1.2em;
            margin-right: 0.5em;
        }

        #mainNavbar .navbar-nav {
            gap: 1rem !important;
        }

        #mainNavbar .nav-link {
            font-size: 1em;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        .site-header .custom-btn,
        #mainNavbar .custom-btn {
            border-radius: 2em;
            padding: 8px 24px;
            font-size: 1em;
            font-weight: bold;
            margin-left: 1.5em;
        }

        #mainNavbar .custom-btn {
            background: #e76f2c;
            color: #fff;
        }

        @keyframes glow {
            0% {
                text-shadow: 0 0 10px #e76f2c, 0 2px 4px #000, 0 0 1px #fff;
            }

            100% {
                text-shadow: 0 0 20px #f3d35c, 0 2px 8px #e76f2c, 0 0 4px #fff;
            }
        }

        .hero-section h1 {
            /* Hello */

            font-size: 2.1rem;
            font-weight: 900;
            letter-spacing: 2px;
            color: #fff;
            text-shadow: 0 0 10px #e76f2c, 0 2px 4px #000, 0 0 1px #fff;
            animation: glow 2s ease-in-out infinite alternate;
            position: relative;
            z-index: 3;
            margin-top: 0.5em;
            animation: fadeInHero 1.6s ease-in;
            opacity: 0;
            animation-fill-mode: forwards;
        }

        @keyframes fadeInHero {
            from {
                opacity: 0;
                transform: translateY(24px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-section .btn.custom-btn {
            font-size: 1rem;
            padding: 8px 28px;
        }

        .site-header .container {
            padding-left: 12px !important;
        }

        .site-header .col-lg-3 {
            padding-left: 0 !important;
            margin-left: 0 !important;
        }

        .about-text-info {
            padding-top: 6px;
            padding-bottom: 6px;
            padding-left: 18px;
            padding-right: 18px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.32);
            box-shadow: 0 2px 12px #0001;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .about-text-icon {
            font-size: 2rem;
        }

        .about-text-info h6 {
            margin-bottom: 0;
            font-size: 1.1rem;
        }

        .about-text-info p {
            margin-bottom: 0;
            font-size: 0.95rem;
        }

        .artists-thumb:hover .artists-image {
            filter: brightness(0.92) saturate(1.1);
            transform: scale(1.08);
            box-shadow:
                0 0 0 6px #fff,
                /* white border for separation */
                0 0 32px 12px #f3d35c,
                /* strong yellow glow */
                0 0 48px 24px #e76f2c88;
            /* orange outer glow */
            border-radius: 12px;
        }

        .artists-image {
            transition: filter 0.3s, transform 0.4s, box-shadow 0.4s;
            border-radius: 12px;
        }

        .artists-image-wrap {
            border-radius: 12px;
            overflow: hidden;
        }

        .form-label+.form-check-inline {
            margin-left: 1.2em;
        }

        @media (max-width: 768px) {
            .container {
                width: 100%;
                padding: 0 15px;
            }
        }

        /* Audio Toggle Button Styles */
        .audio-toggle-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .audio-toggle-btn:hover {
            background: rgba(231, 111, 44, 1);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 111, 44, 0.4);
        }

        .audio-toggle-btn:active {
            transform: translateY(0);
        }

        .audio-toggle-btn i {
            margin-right: 8px;
        }

        /* Responsive adjustments for mobile */
        @media (max-width: 768px) {
            .audio-toggle-btn {
                top: 15px;
                right: 15px;
                padding: 8px 16px;
                font-size: 12px;
            }
        }

        .video-audio-btn {
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 20;
            padding: 10px 18px;
            background: rgba(0,0,0,0.7);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: 0.3s;
        }

        .video-audio-btn:hover {
            background: rgba(0,0,0,0.9);
        }

        img {
            max-width: 100%;
            height: auto;
        }

        .event-schedule-container {
            max-width: 1150px;
            margin: 40px auto;
            padding: 32px 16px;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.333) 60%, rgba(255, 200, 2, 0.576) 100%);
            backdrop-filter: blur(8px);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.543);
        }

        .event-schedule-header {
            text-align: center;
            font-size: 2.8rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 32px;
            background: linear-gradient(90deg, #d94c00, #ffc802, #e76f2c);
            background-size: 200% 100%;
            animation: headerGradient 4s linear infinite;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        @keyframes headerGradient {
            0% {
                background-position: 0% 50%;
            }

            100% {
                background-position: 100% 50%;
            }
        }

        .event-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 28px;
        }

        .event-card {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 24px 0 rgb(31, 38, 135);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            min-height: 320px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .event-card:hover {
            transform: scale(1.04) translateY(-8px);
            box-shadow: 0 8px 32px 0 #f3d35c88, 0 0 0 4px #e76f2c55;
        }

        .event-card-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-size: cover;
            background-position: center;
            filter: blur(1px) brightness(0.7);
            z-index: 1;
            transition: filter 0.3s;
        }

        .event-card:hover .event-card-bg {
            filter: blur(0) brightness(0.85);
        }

        .event-card-content {
            position: relative;
            z-index: 2;
            padding: 32px 20px 20px 20px;
            color: #fff;
            text-align: left;
            background: linear-gradient(0deg, rgba(30, 30, 40, 0.85) 70%, rgba(30, 30, 40, 0.1) 100%);
            border-radius: 0 0 18px 18px;
        }

        .event-icon {
            font-size: 2.2rem;
            color: #ffd700;
            margin-bottom: 0.5rem;
            display: inline-block;
        }

        .event-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .event-time {
            font-size: 1.1rem;
            margin-bottom: 0.2rem;
            color: #f3d35c;
        }

        .event-by {
            font-size: 1rem;
            color: #ffd700;
            margin-bottom: 0.2rem;
        }

        /* See More Button - Modern Glassmorphism Style */
        .see-more-button {
            position: relative;
            border-radius: 20px;
            overflow: visible;
            background: rgba(20, 20, 30, 0.7);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
            cursor: pointer;
            min-height: 320px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-decoration: none;
            isolation: isolate;
        }

        /* Animated gradient border on hover */
        .see-more-button::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 20px;
            padding: 2px;
            background: linear-gradient(45deg, #e76f2c, #f3d35c, #e76f2c, #f3d35c);
            background-size: 300% 300%;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            animation: gradientRotate 3s linear infinite;
            transition: opacity 0.5s;
            z-index: -1;
        }

        @keyframes gradientRotate {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .see-more-button:hover::before {
            opacity: 1;
        }

        /* Floating particles effect */
        .see-more-button::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 20px;
            background:
                radial-gradient(circle at 20% 30%, rgba(231, 111, 44, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(243, 211, 92, 0.3) 0%, transparent 50%);
            opacity: 0;
            transition: opacity 0.5s;
            pointer-events: none;
        }

        .see-more-button:hover::after {
            opacity: 1;
            animation: particleFloat 4s ease-in-out infinite;
        }

        @keyframes particleFloat {

            0%,
            100% {
                transform: translateY(0) scale(1);
            }

            50% {
                transform: translateY(-10px) scale(1.05);
            }
        }

        .see-more-button:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow:
                0 20px 60px rgba(231, 111, 44, 0.4),
                0 0 0 1px rgba(243, 211, 92, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            background: rgba(25, 25, 35, 0.85);
        }

        .see-more-button-content {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 30px 20px;
        }

        /* Modern icon with pulse effect */
        .see-more-icon {
            font-size: 2.5rem;
            color: #f3d35c;
            margin-bottom: 1.2rem;
            display: inline-block;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            filter: drop-shadow(0 0 8px rgba(243, 211, 92, 0.5));
        }

        .see-more-button:hover .see-more-icon {
            transform: translateX(8px) scale(1.15);
            color: #ffd700;
            filter: drop-shadow(0 0 15px rgba(255, 215, 0, 0.8));
            animation: iconPulse 1.5s ease-in-out infinite;
        }

        @keyframes iconPulse {

            0%,
            100% {
                transform: translateX(8px) scale(1.15);
            }

            50% {
                transform: translateX(12px) scale(1.2);
            }
        }

        /* Bold modern text */
        .see-more-text {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.8rem;
            color: #ffffff;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.4s;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        .see-more-text::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            transform: translateX(-50%) scaleX(0);
            width: 60%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #f3d35c, transparent);
            transition: transform 0.4s;
        }

        .see-more-button:hover .see-more-text {
            color: #f3d35c;
            text-shadow:
                0 0 20px rgba(243, 211, 92, 0.6),
                0 2px 10px rgba(0, 0, 0, 0.5);
            transform: scale(1.05);
        }

        .see-more-button:hover .see-more-text::after {
            transform: translateX(-50%) scaleX(1);
        }

        /* Subtle subtext */
        .see-more-subtext {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 400;
            letter-spacing: 2px;
            text-transform: uppercase;
            transition: all 0.4s;
            margin-top: 0.5rem;
        }

        .see-more-button:hover .see-more-subtext {
            color: rgba(255, 255, 255, 0.9);
            letter-spacing: 3px;
        }

        @media (max-width: 600px) {
            .event-schedule-header {
                font-size: 2rem;
            }

            .event-card-content {
                padding: 20px 10px 10px 10px;
            }
        }
    </style>
    <style>
        /* --- Responsive Fixes for Panel Section and Google Map --- */
        @media (max-width: 768px) {

            /* Fix panel section overlap */
            .artists-section .row>[class^="col-"] {
                margin-top: 0 !important;
            }

            .artists-thumb {
                margin-bottom: 32px;
            }

            .artists-thumb .artists-image-wrap img {
                height: auto !important;
                max-height: 340px;
                object-fit: cover;
            }

            /* Remove negative margin on mobile */
            .artists-section [style*="margin-top: -"] {
                margin-top: 0 !important;
            }

            /* Google Map responsive */
            .google-map {
                width: 100% !important;
                min-width: 0 !important;
                height: 220px !important;
                border-radius: 12px !important;
                margin-bottom: 16px;
            }
        }
    </style>
    <style>
        /* ============================================================
           GLOBAL RESPONSIVE FOUNDATION
        ============================================================ */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html, body {
            overflow-x: hidden;
            max-width: 100%;
        }

        img {
            max-width: 100%;
            height: auto;
        }

        /* ============================================================
           TYPOGRAPHY — fluid scale across breakpoints
        ============================================================ */

        /* Tablet */
        @media (max-width: 1024px) {
            h1 { font-size: clamp(1.6rem, 4vw, 2.4rem); }
            h2 { font-size: clamp(1.4rem, 3.5vw, 2rem); }
            h3 { font-size: clamp(1.1rem, 3vw, 1.6rem); }
        }

        /* Mobile */
        @media (max-width: 767px) {
            h1 { font-size: clamp(1.3rem, 5vw, 1.8rem); }
            h2 { font-size: clamp(1.2rem, 4.5vw, 1.6rem); }
            h3 { font-size: clamp(1rem, 4vw, 1.3rem); }
            p  { font-size: 0.95rem; line-height: 1.7; }
        }

        /* ============================================================
           HERO SECTION
        ============================================================ */
        @media (max-width: 1024px) {
            .hero-section .col-lg-12[style*="gap: 15rem"] {
                gap: 3rem !important;
            }
        }

        @media (max-width: 767px) {
            .hero-section {
                min-height: 100svh;
            }

            .hero-section h1 {
                font-size: clamp(1.1rem, 5vw, 1.6rem);
                letter-spacing: 1px;
                margin-top: 0.3em;
            }

            /* Stack location + social links vertically, remove huge gap */
            .hero-section .col-lg-12[style*="gap"] {
                flex-direction: column !important;
                gap: 1rem !important;
                align-items: center !important;
            }

            .hero-section .date-wrap h5,
            .hero-section .location-wrap h5 {
                font-size: 0.85rem;
                text-align: center;
            }

            /* Social icons — tighter on mobile */
            .social-icon {
                gap: 8px;
                flex-wrap: wrap;
                justify-content: center;
            }

            .social-icon .social-icon-link {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }

            /* Sound button — smaller on mobile */
            .video-audio-btn {
                bottom: 12px;
                right: 12px;
                padding: 8px 12px;
                font-size: 12px;
                border-radius: 6px;
            }
        }

        /* ============================================================
           PANEL MEMBERS SECTION
        ============================================================ */

        /* Tablet: 2-column, controlled width */
        @media (max-width: 1024px) and (min-width: 768px) {
            .panel-members-grid .col-lg-6 {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .panel-member-card .pmc-body {
                padding: 12px 14px 16px;
            }
        }

        /* Mobile: single column, constrained card width for aesthetics */
        @media (max-width: 767px) {
            .panel-members-grid {
                gap: 1rem !important;
            }

            .panel-members-grid .col-lg-6,
            .panel-members-grid .col-lg-5,
            .panel-members-grid .pmc-col {
                flex: 0 0 100%;
                max-width: 100%;
            }

            /* Center card, limit width so it doesn't look stretched */
            .panel-member-card {
                max-width: 340px;
                margin-left: auto;
                margin-right: auto;
                /* Disable hover lift on touch devices */
                transform: none !important;
            }

            /* Reduce aspect ratio height on small screens */
            .panel-member-card .pmc-image-wrap {
                aspect-ratio: 3 / 4;
            }

            .panel-member-card .pmc-body {
                padding: 10px 12px 14px;
            }

            .panel-member-card .pmc-name {
                font-size: 0.95rem;
            }

            .panel-member-card .pmc-role {
                font-size: 0.72rem;
            }

            /* Three-member layout override */
            .three-member .panel-members-grid {
                flex-direction: column;
                align-items: center;
            }

            .three-member .panel-members-grid .col-lg-5 {
                max-width: 340px;
                width: 100%;
            }
        }

        /* ============================================================
           SECRETARY (SB) SECTION
        ============================================================ */
        @media (max-width: 1024px) {
            .sb-section-title {
                font-size: 2rem;
                margin-bottom: 28px;
            }

            .sb-swiper .swiper-slide {
                width: 260px;
                height: 260px;
            }
        }

        @media (max-width: 767px) {
            .sb-section {
                padding: 60px 0 40px;
            }

            .sb-section-title {
                font-size: 1.6rem;
                margin-bottom: 20px;
                padding: 0 16px;
            }

            .sb-swiper {
                padding: 20px 0 80px;
            }

            .sb-swiper .swiper-slide {
                width: 220px;
                height: 220px;
                border-radius: 8px;
            }

            .member-name {
                font-size: 0.9rem;
            }

            .member-name .name {
                font-size: 0.85rem;
            }

            .member-name .position {
                font-size: 0.7rem;
            }
        }

        @media (max-width: 400px) {
            .sb-swiper .swiper-slide {
                width: 180px;
                height: 180px;
            }
        }

        /* ============================================================
           ABOUT / GENERAL CONTENT SECTIONS
        ============================================================ */
        @media (max-width: 1024px) {
            .section-padding {
                padding-top: 80px;
                padding-bottom: 80px;
            }
        }

        @media (max-width: 767px) {
            .section-padding {
                padding-top: 50px;
                padding-bottom: 50px;
            }

            /* Stack about-section image + text vertically */
            .about-section .row,
            .contact-section .row {
                flex-direction: column;
            }

            .about-section .col-lg-6,
            .contact-section .col-lg-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            /* Images in content sections */
            .about-section img,
            .site-header img:not(.navbar-brand img),
            .artists-section img {
                width: 100%;
                height: auto;
                object-fit: cover;
                border-radius: 12px;
            }

            /* Event schedule header */
            .event-schedule-header {
                font-size: 1.6rem !important;
                padding: 0 12px;
            }

            /* Event cards */
            .event-card-content {
                padding: 16px 10px !important;
            }
        }

        /* ============================================================
           NAVIGATION — fine-tune for tablet
        ============================================================ */
        @media (max-width: 1024px) and (min-width: 768px) {
            .navbar .nav-link {
                font-size: 0.85rem;
                padding: 0.4rem 0.6rem;
            }

            .navbar-brand img {
                max-height: 36px;
            }
        }

        @media (max-width: 767px) {
            /* Ensure navbar brand text doesn't overflow */
            .navbar-brand {
                font-size: 1rem;
                max-width: calc(100% - 60px);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .navbar-brand img {
                max-height: 32px;
                width: auto;
            }
        }

        /* ============================================================
           CARD / MODAL RESPONSIVE POLISH
        ============================================================ */
        @media (max-width: 767px) {
            /* Previous panels modal cards */
            .panel-card {
                min-height: 120px !important;
                padding: 10px !important;
            }

            .panel-card img {
                max-height: 70px !important;
            }

            /* General card spacing */
            .card {
                margin-bottom: 16px;
            }
        }

        /* ============================================================
           FORM SECTIONS
        ============================================================ */
        @media (max-width: 767px) {
            .custom-form {
                padding: 24px 16px !important;
            }

            .form-control,
            .form-select {
                font-size: 16px; /* prevents iOS auto-zoom on focus */
            }
        }

        /* ============================================================
           GOOGLE MAP
        ============================================================ */
        @media (max-width: 767px) {
            iframe[src*="google.com/maps"] {
                width: 100% !important;
                height: 220px !important;
                border-radius: 12px !important;
            }
        }

        /* ============================================================
           UTILITY — prevent content bleed on all screens
        ============================================================ */
        .container,
        .container-fluid {
            padding-left: 16px;
            padding-right: 16px;
        }

        @media (max-width: 767px) {
            /* Remove any inline horizontal margin that causes bleed */
            [style*="margin-left: -"],
            [style*="margin-right: -"] {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }

            /* Prevent any fixed-width element from breaking layout */
            table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>
    <style>
        /* Admin Check Styles */
        .admin-check-container {
            padding: 60px 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Modern Admin Login Interface Styles */
        .admin-login-container {
            background: linear-gradient(135deg, rgba(20, 60, 80, 0.95) 0%, rgba(80, 40, 100, 0.95) 100%);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            position: relative;
            max-width: 850px;

            margin: 0 auto;
            padding: 60px 50px;
        }

        .admin-login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.03)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
            z-index: 1;
            pointer-events: none;
        }

        .admin-login-wrapper {
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .admin-login-icon {
            width: 70px;
            height: 70px;
            background: #e76f2c;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            color: white;
            box-shadow: 0 12px 35px rgba(231, 111, 44, 0.5);
            margin-bottom: 20px;
            animation: adminFloat 3s ease-in-out infinite;
        }

        @keyframes adminFloat {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        .admin-login-title {
            font-size: 1.9rem;
            font-weight: 700;
            color: white;
            margin-bottom: 8px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .admin-login-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-bottom: 28px;
            line-height: 1.4;
        }

        .admin-login-form {
            margin-bottom: 22px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 4px 12px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .input-wrapper:focus-within {
            border-color: #f3d35c;
            box-shadow: 0 0 25px rgba(243, 211, 92, 0.4);
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.12);
        }

        .input-icon {
            color: #f3d35c;
            font-size: 1.1rem;
            margin-right: 12px;
            min-width: 20px;
        }

        .admin-input {
            background: transparent !important;
            border: none;
            color: white !important;
            font-size: 0.95rem;
            padding: 12px 0;
            width: 100%;
            outline: none;
        }

        /* Override browser default styling for filled inputs */
        .admin-input:-webkit-autofill,
        .admin-input:-webkit-autofill:hover,
        .admin-input:-webkit-autofill:focus,
        .admin-input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px transparent inset !important;
            -webkit-text-fill-color: white !important;
            background: transparent !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        .admin-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        /* Make placeholder transparent when input has value */
        .admin-input:not(:placeholder-shown)::placeholder {
            color: transparent;
        }

        .password-toggle {
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            padding: 10px;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #f3d35c;
        }

        .admin-login-btn {
            background: linear-gradient(90deg, #e76f2c, #f3d35c);
            border: none;
            border-radius: 12px;
            padding: 16px 25px;
            font-size: 1rem;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 8px 25px rgba(231, 111, 44, 0.5);
            position: relative;
            overflow: hidden;
        }

        .admin-login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .admin-login-btn:hover::before {
            left: 100%;
        }

        .admin-login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(231, 111, 44, 0.7);
            background: linear-gradient(90deg, #f3d35c, #e76f2c);
        }

        .back-to-site {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            padding: 10px;
            border-radius: 10px;
        }

        .back-to-site:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-5px);
        }

        /* Admin Login Message Styles */
        .admin-error-message {
            background: rgba(220, 53, 69, 0.1) !important;
            border: 1px solid rgba(220, 53, 69, 0.3) !important;
            border-radius: 10px;
            padding: 15px;
            color: #dc3545;
            margin-bottom: 20px;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .admin-success-message {
            background: rgba(40, 167, 69, 0.1) !important;
            border: 1px solid rgba(40, 167, 69, 0.3) !important;
            border-radius: 10px;
            padding: 15px;
            color: #28a745;
            margin-bottom: 20px;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        .admin-check-title {
            color: #333;
            font-weight: 700;
            font-size: 2rem;
        }

        .admin-check-description {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .admin-options {
            margin-top: 30px;
        }

        .admin-options .btn {
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            min-width: 180px;
        }

        .admin-options .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
        }

        /* Notification Styles */
        .custom-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 15px 25px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }

        .custom-notification.show {
            transform: translateX(0);
        }

        .custom-notification.warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
        }

        .custom-notification.success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        @media (max-width: 768px) {

            .contact-form-body .form-check,
            .contact-form-body .form-check-inline {
                display: flex !important;
                align-items: center !important;
                margin-bottom: 16px !important;
                margin-right: 0 !important;
                width: 100%;
                cursor: pointer;
                position: relative;
                padding-left: 0 !important;
                min-height: 36px;
            }

            .form-check-input[type="radio"] {
                margin-right: 10px;
                margin-left: 0 !important;
                width: 28px;
                height: 28px;
                min-width: 28px;
                min-height: 28px;
                display: inline-block;
                vertical-align: middle;
            }

            .form-check-label {
                font-size: 1.12em;
                flex: 1;
                cursor: pointer;
                user-select: none;
                margin-bottom: 0;
                display: flex;
                align-items: center;
                height: 28px;
            }

            /* Target the label for 'Current Member' radio */
            label[for="status-current"] {
                margin-left: 16px;
            }

            .admin-options .btn {
                min-width: 150px;
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }
    </style>
    <!--

TemplateMo 583 BUCuC

https://templatemo.com/tm-583-festava-live

-->
</head>

<body>
    <!-- Mobile Sidebar Navigation (only visible on mobile) -->
    <div id="mobileSidebarOverlay" class="mobile-sidebar-overlay"></div>
    <nav id="mobileSidebar" class="mobile-sidebar">
        <div class="sidebar-profile">
            <img src="images/logopng.png" alt="Club Logo" class="sidebar-profile-img">
            <div class="sidebar-profile-info">
                <strong>BRACU Cultural Club</strong>
                <span>Official</span>
            </div>
        </div>
        <ul class="sidebar-nav">
            <li><a href="#section_1"><i class="fa fa-home"></i>
                    Home</a></li>
            <li><a href="#section_2"><i class="fa fa-info-circle"></i>
                    About</a></li>
            <li><a href="#advisors_section"><i class="fa fa-user-tie"></i>
                    Advisors</a></li>
            <li><a href="#section_3"><i class="fa fa-users"></i>
                    Panel</a></li>
            <li><a href="#section_4"><i class="fa fa-star"></i> Sb
                    Members</a></li>
            <li><a href="#section_5"><i class="fa fa-calendar"></i>
                    Past Events</a></li>
            <li><a href="#footer"><i class="fa fa-user-plus"></i> Sign
                    Up</a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="#friend"><i class="fa fa-share-alt"></i> Tell a
                Friend</a>
            <a href="#help"><i class="fa fa-question-circle"></i> Help &
                Feedback</a>
        </div>
    </nav>
    <button id="sidebarToggle" class="sidebar-toggle-btn" aria-label="Open navigation menu">
        <span class="sidebar-toggle-bar"></span>
        <span class="sidebar-toggle-bar"></span>
        <span class="sidebar-toggle-bar"></span>
    </button>
    <!-- End Mobile Sidebar Navigation -->

    <main>
        <!-- Fresh Header implementation -->
        <nav class="navbar navbar-expand-lg fixed-top shadow-lg" id="mainNavbar"
            style="background: #000000 !important; border-bottom: 2px solid #e76f2c; z-index: 10000 !important; padding: 15px 0;">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="index.php"
                    style="color: #fff; font-weight: bold; font-size: 1.5em;">
                    <img src="images/logo.png" alt="Club Logo" class="me-2" style="height: 1.5em;">
                    BUCuC
                </a>

                <div class="d-flex align-items-center ms-auto d-lg-none">
                    <a href="admin-login.php" class="btn custom-btn me-2"
                        style="background: transparent; color: #e76f2c; font-weight: bold; border: 2px solid #e76f2c; border-radius: 2em; padding: 6px 16px; font-size: 0.9em;">Login</a>

                    <?php if ($signupEnabled): ?>
                        <a href="#signup" class="btn custom-btn me-2"
                            style="background: #e76f2c; color: #fff; font-weight: bold; border-radius: 2em; padding: 6px 16px; font-size: 0.9em;">Apply</a>
                    <?php endif; ?>

                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation"
                        style="border: none; color: white;">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav align-items-lg-center ms-auto me-lg-5">
                        <li class="nav-item"><a class="nav-link click-scroll" href="#section_1"
                                style="color: #fff !important;">Home</a></li>
                        <li class="nav-item"><a class="nav-link click-scroll" href="#section_2"
                                style="color: #fff !important;">About</a></li>
                        <li class="nav-item"><a class="nav-link click-scroll" href="#advisors_section"
                                style="color: #fff !important;">Advisors</a></li>
                        <li class="nav-item"><a class="nav-link click-scroll" href="#section_3"
                                style="color: #fff !important;">Panel</a></li>
                        <li class="nav-item"><a class="nav-link click-scroll" href="#section_4"
                                style="color: #fff !important;">Sb Members</a></li>
                        <li class="nav-item"><a class="nav-link click-scroll" href="#section_5"
                                style="color: #fff !important;">Past Events</a></li>
                        <li class="nav-item"><a class="nav-link click-scroll" href="#very-bottom"
                                style="color: #fff !important;">Contact</a></li>
                    </ul>

                    <div class="d-none d-lg-flex align-items-center">
                        <a href="admin-login.php" class="btn custom-btn me-3"
                            style="background: transparent; color: #e76f2c; font-weight: bold; border: 2px solid #e76f2c; border-radius: 2em; padding: 8px 24px; font-size: 1em;">Login</a>

                        <?php if ($signupEnabled): ?>
                            <a href="#signup" class="btn custom-btn"
                                style="background: #e76f2c; color: #fff; font-weight: bold; border-radius: 2em; padding: 8px 18px; font-size: 1em;">Apply
                                Now</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
        <!-- End of Fresh Header -->

        <section class="hero-section" id="section_1">

            <div class="container d-flex justify-content-center align-items-center">
                <div class="row">

                    <div class="col-12 mt-auto mb-5 text-center">
                        <h1 class="text-white mb-5"></h1>

                    </div>

                    <div class="col-lg-12 col-12 mt-auto d-flex flex-column flex-lg-row text-center"
                        style="gap: 15rem;">
                        <div class="date-wrap">
                            <h5 class="text-white">
                                <i class="custom-icon bi-geo-alt me-2"></i>
                                BRAC University Cultural Club (BUCuC)
                            </h5>
                        </div>

                        <div class="location-wrap mx-auto py-3 py-lg-0">
                            <h5 class="text-white">

                            </h5>
                        </div>

                        <div class="social-share">
                            <ul class="social-icon d-flex align-items-center justify-content-center">
                                <span class="text-white me-3">Follows Us:</span>

                                <li class="social-icon-item">
                                    <a href="https://www.facebook.com/bucuc" class="social-icon-link" target="_blank">
                                        <span class="bi-facebook"></span>
                                    </a>
                                </li>

                                <li class="social-icon-item">
                                    <a href="https://www.facebook.com/bucucarchive" class="social-icon-link"
                                        target="_blank">
                                        <span class="bi-facebook"></span>
                                    </a>
                                </li>

                                <li class="social-icon-item">
                                    <a href="https://www.youtube.com/@bracuniversityculturalclub717"
                                        class="social-icon-link" target="_blank">
                                        <span class="bi-youtube"></span>
                                    </a>
                                </li>

                                <li class="social-icon-item">
                                    <a href="https://www.instagram.com/bucuclive/" class="social-icon-link"
                                        target="_blank">
                                        <span class="bi-instagram"></span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="video-wrap"
                 style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:0;overflow:hidden;">
                <video id="heroVideo" autoplay loop muted playsinline
                       style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;">
                    <source src="video/bucuc1.mp4" type="video/mp4">
                    <source src="video/bucuc1.webm" type="video/webm">
                    Your browser does not support the video tag.
                </video>
                <button id="unmuteBtn" class="video-audio-btn">
                    🔊 Enable Sound
                </button>
            </div>

        </section>

        <section class="about-section section-padding" id="section_2">
            <div class="container">
                <div class="row">

                    <div class="col-lg-6 col-12 mb-4 mb-lg-0 d-flex align-items-center">
                        <div class="services-info">
                            <h2 class="text-white mb-4">BRAC University
                                Cultural Club</h2>

                            <p class="text-white">BRAC University Cultural Club (BUCuC), established in 2002, is the
                                university’s oldest, largest, and most celebrated cultural club with currently 500+
                                active members. Dedicated to preserving Bengali heritage while embracing global artistic
                                expressions, BUCuC provides a vibrant platform for students to showcase talents in
                                music, dance, and cultural storytelling. BUCuC hosts grand events like Rhythm Revive,
                                Jolsha, Symphonic Reminiscence, Boishakh, uniting current students, alumni, and the
                                wider university community. The club uniquely blends tradition with modernity—featuring
                                everything from classical and folk performances to classical, pop, rock, fusion, and
                                Qawali. Beyond performances, BUCuC nurtures confidence, leadership, and creative growth
                                in its members. As the only cultural club at BRAC University, BUCuC is not just a
                                platform—it’s a legacy, a family, and the cultural heartbeat of the university.</p>

                            <h6 class="text-warning mt-4">BUCuC Mentors</h6>

                            <p class="text-white">BUCuC has been shaped by countless mentors whose guidance, creativity,
                                and dedication helped build the club’s vibrant legacy. Among them are figures like Rudan
                                Al Amin, former Secretary of PR and Editorial (2017–2018), celebrated for his humor and
                                commanding stage presence; Yash Rohan, ex-Secretary of Marketing, IT, and Archive
                                (2016–2017), who later rose to national prominence as a Bangladeshi actor; and Sadat
                                Kabir Rudro, senior performer (2014–2015) and co-founder of Dhaka Guys Studios, admired
                                for his musical talent. The club’s artistic spirit was further elevated by Miftah Zaman,
                                former Performance Secretary known for transforming BUCuC’s performing arts scene, and
                                by talented performers like Ankan Kumar, now an acclaimed singer. Leaders such as Nafisa
                                Tasnuva Hossain, ex-Secretary of PR and Editorial and now General Manager at Pathao,
                                exemplify the lasting impact of BUCuC’s mentorship.</p>

                            <p class="text-white">Though these are only a few names among many, each mentor—past and
                                present—continues to inspire, and together they form the legacy that BUCuC and BRAC
                                University proudly celebrate.</p>


                            <h6 class="text-warning mt-4">Future Plans</h6>

                            <p class="text-white">The club aims to organize
                                more interactive and engaging events in the
                                future, including cultural festivals, talent
                                hunts, and workshops to nurture the
                                creativity of its members. Stay tuned for
                                more updates!</p>

                            <div class="mt-4">
                                <a href="#footer" class="btn btn-outline-light click-scroll"
                                    onclick="window.scrollTo({top:document.body.scrollHeight, behavior:'smooth'}); return false;">Contact
                                    Us</a>
                                <a href="#section_5" class="btn btn-warning ms-2">Past Events</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 col-12">
                        <div id="happyMomentCarousel" class="carousel slide" data-bs-ride="carousel"
                            data-bs-interval="2000">
                            <div class="carousel-inner">
                                <div class="carousel-item active">
                                    <div class="about-text-wrap">
                                        <img src="images/2nd.JPG" class="about-image img-fluid">
                                        <div class="about-text-info d-flex">
                                            <div class="d-flex align-items-center justify-content-center"
                                                style="width:56px; height:56px; border-radius:50%; background:#F3D35C; overflow:hidden;">
                                                <img src="images/logo.png" alt="Club Logo"
                                                    style="height:36px; width:36px; object-fit:contain; display:block; margin:0;">
                                            </div>
                                            <div class="ms-4">
                                                <h6>Wild Escape</h6>
                                                <p class="mb-0">Our Amazing Experience with
                                                    BUAC</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="carousel-item">
                                    <div class="about-text-wrap">
                                        <img src="images/slide2.jpg" class="about-image img-fluid">
                                        <div class="about-text-info d-flex">
                                            <div class="d-flex align-items-center justify-content-center"
                                                style="width:56px; height:56px; border-radius:50%; background:#F3D35C; overflow:hidden;">
                                                <img src="images/logo.png" alt="Club Logo"
                                                    style="height:36px; width:36px; object-fit:contain; display:block; margin:0;">
                                            </div>
                                            <div class="ms-4">
                                                <h6>Rhythm Revive 25.0</h6>
                                                <p class="mb-0">Our signature recruitment show.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="carousel-item">
                                    <div class="about-text-wrap">
                                        <img src="images/slide3.jpg" class="about-image img-fluid">
                                        <div class="about-text-info d-flex">
                                            <div class="d-flex align-items-center justify-content-center"
                                                style="width:56px; height:56px; border-radius:50%; background:#F3D35C; overflow:hidden;">
                                                <img src="images/logo.png" alt="Club Logo"
                                                    style="height:36px; width:36px; object-fit:contain; display:block; margin:0;">
                                            </div>
                                            <div class="ms-4">
                                                <h6>Power Panel</h6>
                                                <p class="mb-0">The power house of the club</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="carousel-item">
                                    <div class="about-text-wrap">
                                        <img src="images/slide4.jpg" class="about-image img-fluid">
                                        <div class="about-text-info d-flex">
                                            <div class="d-flex align-items-center justify-content-center"
                                                style="width:56px; height:56px; border-radius:50%; background:#F3D35C; overflow:hidden;">
                                                <img src="images/logo.png" alt="Club Logo"
                                                    style="height:36px; width:36px; object-fit:contain; display:block; margin:0;">
                                            </div>
                                            <div class="ms-4">
                                                <h6>BUCuC Iftar</h6>
                                                <p class="mb-0">Where tradition meets togetherness.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="carousel-item">
                                    <div class="about-text-wrap">
                                        <img src="images/slide5.jpg" class="about-image img-fluid">
                                        <div class="about-text-info d-flex">
                                            <div class="d-flex align-items-center justify-content-center"
                                                style="width:56px; height:56px; border-radius:50%; background:#F3D35C; overflow:hidden;">
                                                <img src="images/logo.png" alt="Club Logo"
                                                    style="height:36px; width:36px; object-fit:contain; display:block; margin:0;">
                                            </div>
                                            <div class="ms-4">
                                                <h6>বৈশাখ উৎসব</h6>
                                                <p class="mb-0">রঙে, গানে, আর ঐতিহ্যে রাঙানো বৈশাখ ১৪৩২!</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#happyMomentCarousel"
                                data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#happyMomentCarousel"
                                data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Advisor Section -->
        <section class="artists-section section-padding" id="advisors_section">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-12 text-center">
                        <h2 class="mb-4">Meet Our Advisor & Co-Advisor</h2>
                    </div>

                    <!-- Advisor (Left Side) -->
                    <div class="col-lg-5 col-12 mb-4 me-lg-4">
                        <div class="artists-thumb">
                            <div class="artists-image-wrap">
                                <img src="images/Advisor/advisor.jpg" class="artists-image img-fluid">
                            </div>

                            <div class="artists-hover">
                                <p style="color: #fff;">
                                    <strong>Name:</strong>
                                    Dr.Sharmind Neelotpol
                                </p>

                                <p style="color: #fff;">
                                    <strong>Position:</strong>
                                    Advisor
                                </p>

                                <hr>

                                <p class="mb-0">
                                    <strong>Contact Info:</strong>
                                    <a href="https://scholar.google.com/citations?user=qC6VODEAAAAJ&hl=en">Dr.Sharmind
                                        Neelotpol</a>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Co-Advisor (Right Side) -->
                    <div class="col-lg-5 col-12 mb-4 ms-lg-4">
                        <div class="artists-thumb">
                            <div class="artists-image-wrap">
                                <img src="images/Advisor/co.JPEG.jpg" class="artists-image img-fluid">
                            </div>

                            <div class="artists-hover">
                                <p style="color: #fff;">
                                    <strong>Name:</strong>
                                    Dr.Upal Aditya Oikya
                                </p>

                                <p style="color: #fff;">
                                    <strong>Position:</strong>
                                    Co-Advisor
                                </p>

                                <hr>

                                <p class="mb-0">
                                    <strong>Contact Info:</strong>
                                    <a href="https://www.linkedin.com/in/oikya/?originalSubdomain=bd">Dr.Upal Aditya
                                        Oikya</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="artists-section section-padding" id="section_3">
            <div class="container">
                <div class="row justify-content-center">

                    <div class="col-12 text-center">
                        <h2 class="mb-4">Meet Presidential Panel</h2>
                    </div>
                    <!-- Panel Year Selector -->
                    <div class="col-12 text-center mb-4">
                        <div class="panel-year-selector">
                            <label for="panelYearSelect" class="form-label text-black fw-bold mb-2">View Panel by
                                Year</label>
                            <select id="panelYearSelect" class="form-select panel-year-dropdown">
                                <option value="panel_26_27">Current Panel (2026–2027)</option>
                                <option value="current">2025–2026</option>
                                <option value="panel_23_24">2023–2025</option>
                                <option value="panel_22_23">2022–2023</option>
                                <option value="panel_21_22">2021–2022</option>
                                <option value="panel_20_21">2020–2021</option>
                                <option value="panel_19_20">2019–2020</option>
                            </select>
                        </div>
                    </div>

                    <!-- Previous Panels Modal -->
                    <div class="modal fade" id="previousPanelsModal" tabindex="-1"
                        aria-labelledby="previousPanelsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content"
                                style="background: rgba(30,30,40,0.97); color: #fff; border-radius: 18px;">
                                <div class="modal-header" style="border-bottom: 1px solid #f3d35c;">
                                    <h4 class="modal-title" id="previousPanelsModalLabel">Previous Panel Members &
                                        Secretaries (2021-2024)</h4>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <!-- Tabs for years -->
                                    <ul class="nav nav-tabs mb-4" id="panelYearTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="panel2024-tab" data-bs-toggle="tab"
                                                data-bs-target="#panel2024" type="button" role="tab"
                                                aria-controls="panel2024" aria-selected="true">2024</button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="panel2023-tab" data-bs-toggle="tab"
                                                data-bs-target="#panel2023" type="button" role="tab"
                                                aria-controls="panel2023" aria-selected="false">2023</button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="panel2022-tab" data-bs-toggle="tab"
                                                data-bs-target="#panel2022" type="button" role="tab"
                                                aria-controls="panel2022" aria-selected="false">2022</button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="panel2021-tab" data-bs-toggle="tab"
                                                data-bs-target="#panel2021" type="button" role="tab"
                                                aria-controls="panel2021" aria-selected="false">2021</button>
                                        </li>
                                    </ul>
                                    <div class="tab-content" id="panelYearTabsContent">
                                        <!-- 2024 Panel -->
                                        <div class="tab-pane fade show active" id="panel2024" role="tabpanel"
                                            aria-labelledby="panel2024-tab">
                                            <h5 class="mb-3">Panel Members 2024</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Panel/aparup.jpg" class="img-fluid"
                                                            alt="aparup.jpg">
                                                        <div class="name">Aparup</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Panel/mamun.jpg" class="img-fluid"
                                                            alt="mamun.jpg">
                                                        <div class="name">Mamun</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Panel/nafisa.jpg" class="img-fluid"
                                                            alt="nafisa.jpg">
                                                        <div class="name">Nafisa</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img src="images/Panel_24_25/Panel/zia.jpg"
                                                            class="img-fluid" alt="zia.jpg">
                                                        <div class="name">zia</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <h5 class="mb-3">Secretaries 2024</h5>
                                            <div class="row g-3">
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Kazi_Tawsiat_Binte_Ehsan.jpg"
                                                            class="img-fluid" alt="Kazi_Tawsiat_Binte_Ehsan.jpg">
                                                        <div class="name">Kazi Tawsiat Binte Ehsan</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Avibadhan_Das.jpg"
                                                            class="img-fluid" alt="Avibadhan_Das.jpg">
                                                        <div class="name">Avibadhan Das</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Diana_Halder_Momo.jpg"
                                                            class="img-fluid" alt="Diana_Halder_Momo.jpg">
                                                        <div class="name">Diana Halder Momo</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Fabiha_Bushra_Ali.jpg"
                                                            class="img-fluid" alt="Fabiha_Bushra_Ali.jpg">
                                                        <div class="name">Fabiha Bushra Ali</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Habib_Hasan.jpg"
                                                            class="img-fluid" alt="Habib_Hasan.jpg">
                                                        <div class="name">Habib Hasan</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Jareen_Tasnim_Bushra.jpg"
                                                            class="img-fluid" alt="Jareen_Tasnim_Bushra.jpg">
                                                        <div class="name">Jareen Tasnim Bushra</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Jubair_Rahman.jpg"
                                                            class="img-fluid" alt="Jubair_Rahman.jpg">
                                                        <div class="name">Jubair Rahman</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Khaled_Bin_Taher.jpg"
                                                            class="img-fluid" alt="Khaled_Bin_Taher.jpg">
                                                        <div class="name">Khaled Bin Taher</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Lalon.jpg"
                                                            class="img-fluid" alt="Lalon.jpg">
                                                        <div class="name">Lalon</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Lawrence_Clifford_Gomes.jpg"
                                                            class="img-fluid" alt="Lawrence_Clifford_Gomes.jpg">
                                                        <div class="name">Lawrence Clifford Gomes</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/MD_Sadman_Safin_Oasif.jpg"
                                                            class="img-fluid" alt="MD_Sadman_Safin_Oasif.jpg">
                                                        <div class="name">MD Sadman Safin Oasif</div>
                                                    </div>
                                                </div>

                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Katha.jpg"
                                                            class="img-fluid" alt="Maria_Kamal_Katha.jpg">
                                                        <div class="name">Maria Kamal Katha</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Md_Ahnaf_Farhan.jpg"
                                                            class="img-fluid" alt="Md_Ahnaf_Farhan.jpg">
                                                        <div class="name">Md Ahnaf Farhan</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Nafisa_Islam.jpg"
                                                            class="img-fluid" alt="Nafisa_Islam.jpg">
                                                        <div class="name">Nafisa Islam</div>
                                                    </div>
                                                </div>

                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Rubu.jpg"
                                                            class="img-fluid" alt="Rubaba_Khijir_Nusheen.jpg">
                                                        <div class="name">Rubaba Khijir Nusheen</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Rudra_Mathew_Gomes.jpg"
                                                            class="img-fluid" alt="Rudra_Mathew_Gomes.jpg">
                                                        <div class="name">Rudra Mathew Gomes</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Showmik_Safi.jpg"
                                                            class="img-fluid" alt="Showmik_Safi.jpg">
                                                        <div class="name">Showmik Safi</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Shreya_Sangbriti.jpg"
                                                            class="img-fluid" alt="Shreya_Sangbriti.jpg">
                                                        <div class="name">Shreya Sangbriti</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Syed_Ariful_Islam_Aowan.jpg"
                                                            class="img-fluid" alt="Syed_Ariful_Islam_Aowan.jpg">
                                                        <div class="name">Syed Ariful Islam Aowan</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Tamejul_habib.jpg"
                                                            class="img-fluid" alt="Tamejul_habib.jpg">
                                                        <div class="name">Tamejul habib</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/Tasnimul_Hasan.jpg"
                                                            class="img-fluid" alt="Tasnimul_Hasan.jpg">
                                                        <div class="name">Tasnimul Hasan</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/reshad.jpg"
                                                            class="img-fluid" alt="reshad.jpg">
                                                        <div class="name">Reshad</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_24_25/Secreteries/rudian.jpg"
                                                            class="img-fluid" alt="rudian.jpg">
                                                        <div class="name">Rudian Ahmed</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- 2023 Panel -->
                                        <div class="tab-pane fade" id="panel2023" role="tabpanel"
                                            aria-labelledby="panel2023-tab">
                                            <h5 class="mb-3">Panel Members 2023</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Panel/Tamima Diba (Financial Secretary).jpg"
                                                            class="img-fluid"
                                                            alt="Tamima Diba (Financial Secretary).jpg">
                                                        <div class="name">Tamima Diba (Financial Secretary)</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Panel/Musharrat Quazi (GS).jpg"
                                                            class="img-fluid" alt="Musharrat Quazi (GS).jpg">
                                                        <div class="name">Musharrat Quazi (GS)</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Panel/Nawshaba Maniza Riddhi (VP).jpg"
                                                            class="img-fluid" alt="Nawshaba Maniza Riddhi (VP).jpg">
                                                        <div class="name">Nawshaba Maniza Riddhi (VP)</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Panel/Nashib Siam (President).jpg"
                                                            class="img-fluid" alt="Nashib Siam (President).jpg">
                                                        <div class="name">Nashib Siam (President)</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <h5 class="mb-3">Secretaries 2023</h5>
                                            <div class="row g-3">
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Admin/Affan_Adid.jpg"
                                                            class="img-fluid" alt="Affan_Adid.jpg">
                                                        <div class="name">Affan Adid</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Admin/Shadman Sakib (Admin).jpg"
                                                            class="img-fluid" alt="Shadman Sakib (Admin).jpg">
                                                        <div class="name">Shadman Sakib</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Creative/Khandoker_Nabila_Humayra.jpg"
                                                            class="img-fluid" alt="Khandoker_Nabila_Humayra.jpg">
                                                        <div class="name">Khandoker Nabila Humayra</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Creative/Musfura_Rahman.jpg"
                                                            class="img-fluid" alt="Musfura_Rahman.jpg">
                                                        <div class="name">Musfura Rahman</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Events/Tahreen_Nubaha_Rabbi.jpg"
                                                            class="img-fluid" alt="Tahreen_Nubaha_Rabbi.jpg">
                                                        <div class="name">Tahreen Nubaha Rabbi</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Events/Mamun_Abdullah.jpg"
                                                            class="img-fluid" alt="Mamun_Abdullah.jpg">
                                                        <div class="name">Mamun Abdullah</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Events/Towkeer_Mohammad_Zia.jpg"
                                                            class="img-fluid" alt="Towkeer_Mohammad_Zia.jpg">
                                                        <div class="name">Towkeer Mohammad Zia</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Finance/Kaushik_Datta.jpg"
                                                            class="img-fluid" alt="Kaushik_Datta.jpg">
                                                        <div class="name">Kaushik Datta</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Marketing/Ayam_Dhar.jpg"
                                                            class="img-fluid" alt="Ayam_Dhar.jpg">
                                                        <div class="name">Ayam Dhar</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Marketing/Imam_Ur_Rashid.jpg"
                                                            class="img-fluid" alt="Imam_Ur_Rashid.jpg">
                                                        <div class="name">Imam Ur Rashid</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Marketing/Qurratul_Ayen_Elma.jpg"
                                                            class="img-fluid" alt="Qurratul_Ayen_Elma.jpg">
                                                        <div class="name">Qurratul Ayen Elma</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Performance/6a2c9948-7284-4455-87ea-e78640578bc1.jpg"
                                                            class="img-fluid"
                                                            alt="6a2c9948-7284-4455-87ea-e78640578bc1.jpg">
                                                        <div class="name">Performance Secretary</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Performance/Performance.jpg"
                                                            class="img-fluid" alt="Performance.jpg">
                                                        <div class="name">Performance Secretary</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Performance/Mitisha_Tasnim_Rahman.jpeg"
                                                            class="img-fluid" alt="Mitisha_Tasnim_Rahman.jpeg">
                                                        <div class="name">Mitisha Tasnim Rahman</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Performance/e46329e0-5f2e-4921-8f9b-94326e0a79e3.jpeg"
                                                            class="img-fluid"
                                                            alt="e46329e0-5f2e-4921-8f9b-94326e0a79e3.jpeg">
                                                        <div class="name">Performance Secretary</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Performance/Taufiq (Performance).jpg"
                                                            class="img-fluid" alt="Taufiq (Performance).jpg">
                                                        <div class="name">Taufiq</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Performance/Priyata_Mondal.jpg"
                                                            class="img-fluid" alt="Priyata_Mondal.jpg">
                                                        <div class="name">Priyata Mondal</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Performance/Aparup Chy (Performance).jpg"
                                                            class="img-fluid" alt="Aparup Chy (Performance).jpg">
                                                        <div class="name">Aparup Chowdhury</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Performance/Nafisa Noor (Performance).jpg"
                                                            class="img-fluid" alt="Nafisa Noor (Performance).jpg">
                                                        <div class="name">Nafisa Noor</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/Performance/Yeashfa_Haque.jpeg"
                                                            class="img-fluid" alt="Yeashfa_Haque.jpeg">
                                                        <div class="name">Yeashfa Haque</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/PR/e335550a-5587-4fce-98ec-e8a348a349ed.jpg"
                                                            class="img-fluid"
                                                            alt="e335550a-5587-4fce-98ec-e8a348a349ed.jpg">
                                                        <div class="name">PR Secretary</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/PR/Jisa (PR).jpg"
                                                            class="img-fluid" alt="Jisa (PR).jpg">
                                                        <div class="name">Jisa</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/RD/Sadid ( Rd).jpg"
                                                            class="img-fluid" alt="Sadid ( Rd).jpg">
                                                        <div class="name">Sadid</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_23_24/Secretary/RD/Zaarin RD.jpg"
                                                            class="img-fluid" alt="Zaarin RD.jpg">
                                                        <div class="name">Zaarin</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- 2022 Panel -->
                                        <div class="tab-pane fade" id="panel2022" role="tabpanel"
                                            aria-labelledby="panel2022-tab">
                                            <h5 class="mb-3">Panel Members 2022</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Panel/Merazul_Dihan.jpg"
                                                            class="img-fluid" alt="Merazul_Dihan.jpg">
                                                        <div class="name">Merazul Dihan</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Panel/Towsif_Tazwar_Zia.jpg"
                                                            class="img-fluid" alt="Towsif_Tazwar_Zia.jpg">
                                                        <div class="name">Towsif Tazwar Zia</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Panel/Tawhid_Mollah_Orpon.jpg"
                                                            class="img-fluid" alt="Tawhid_Mollah_Orpon.jpg">
                                                        <div class="name">Tawhid Mollah Orpon</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Panel/Shakira_Mustahid.jpg"
                                                            class="img-fluid" alt="Shakira_Mustahid.jpg">
                                                        <div class="name">Shakira Mustahid</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <h5 class="mb-3">Secretaries 2022</h5>
                                            <div class="row g-3">
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Creative/Muhaiminul_Shuvo.jpg"
                                                            class="img-fluid" alt="Muhaiminul_Shuvo.jpg">
                                                        <div class="name">Muhaiminul Shuvo</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Creative/Nisa_Hai.jpg"
                                                            class="img-fluid" alt="Nisa_Hai.jpg">
                                                        <div class="name">Nisa Hai</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Events/489028088_4153227901564307_6545631968149006210_n.jpg"
                                                            class="img-fluid"
                                                            alt="489028088_4153227901564307_6545631968149006210_n.jpg">
                                                        <div class="name">Events Secretary</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Events/Mohammad_Nashib .jpg"
                                                            class="img-fluid" alt="Mohammad_Nashib .jpg">
                                                        <div class="name">Mohammad Nashib</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Hr/Musharrat_Quazi.jpg"
                                                            class="img-fluid" alt="Musharrat_Quazi.jpg">
                                                        <div class="name">Musharrat Quazi</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Hr/Naziat_Podmo.jpg"
                                                            class="img-fluid" alt="Naziat_Podmo.jpg">
                                                        <div class="name">Naziat Podmo</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Miap/Mehjabeen_Meem.jpg"
                                                            class="img-fluid" alt="Mehjabeen_Meem.jpg">
                                                        <div class="name">Mehjabeen Meem</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Miap/Omar_Ibtesum.jpg"
                                                            class="img-fluid" alt="Omar_Ibtesum.jpg">
                                                        <div class="name">Omar Ibtesum</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Miap/Saadman_Alvi.jpg"
                                                            class="img-fluid" alt="Saadman_Alvi.jpg">
                                                        <div class="name">Saadman Alvi</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Performance/Kabery_Moniza_Riddhi .jpg"
                                                            class="img-fluid" alt="Kabery_Moniza_Riddhi .jpg">
                                                        <div class="name">Kabery Moniza Riddhi</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Performance/693523ae-d6a7-4ecf-9019-7dbeffe40d5b.jpg"
                                                            class="img-fluid"
                                                            alt="693523ae-d6a7-4ecf-9019-7dbeffe40d5b.jpg">
                                                        <div class="name">Performance Secretary</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Performance/Rifah_Tasnia.jpg"
                                                            class="img-fluid" alt="Rifah_Tasnia.jpg">
                                                        <div class="name">Rifah Tasnia</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Performance/Tahiatun_Nazi.jpg"
                                                            class="img-fluid" alt="Tahiatun_Nazi.jpg">
                                                        <div class="name">Tahiatun Nazi</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Performance/486450617_1919606155485104_6809497294325923308_n.jpg"
                                                            class="img-fluid"
                                                            alt="486450617_1919606155485104_6809497294325923308_n.jpg">
                                                        <div class="name">Performance Secretary</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Performance/Tamima_Hossain_Diba.jpg"
                                                            class="img-fluid" alt="Tamima_Hossain_Diba.jpg">
                                                        <div class="name">Tamima Hossain Diba</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/pr/Upama_Adhikary.jpg"
                                                            class="img-fluid" alt="Upama_Adhikary.jpg">
                                                        <div class="name">Upama Adhikary</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/pr/Swagata_oishi.jpg"
                                                            class="img-fluid" alt="Swagata_oishi.jpg">
                                                        <div class="name">Swagata Oishi</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/pr/Minoor_karim.jpg"
                                                            class="img-fluid" alt="Minoor_karim.jpg">
                                                        <div class="name">Minoor Karim</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_22_23/Secreteries/Rd/GM_Apurbo.jpg"
                                                            class="img-fluid" alt="GM_Apurbo.jpg">
                                                        <div class="name">GM Apurbo</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- 2021 Panel -->
                                        <div class="tab-pane fade" id="panel2021" role="tabpanel"
                                            aria-labelledby="panel2021-tab">
                                            <h5 class="mb-3">Panel Members 2021</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Panel/487048639_3105546046276958_9148728155394135784_n.jpg"
                                                            class="img-fluid"
                                                            alt="487048639_3105546046276958_9148728155394135784_n.jpg">
                                                        <div class="name">
                                                            487048639_3105546046276958_9148728155394135784_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Panel/183667601_2775574012753527_371707836928886195_n.jpg"
                                                            class="img-fluid"
                                                            alt="183667601_2775574012753527_371707836928886195_n.jpg">
                                                        <div class="name">
                                                            183667601_2775574012753527_371707836928886195_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Panel/476383228_3256767717796347_285371354205090761_n.jpg"
                                                            class="img-fluid"
                                                            alt="476383228_3256767717796347_285371354205090761_n.jpg">
                                                        <div class="name">
                                                            476383228_3256767717796347_285371354205090761_n</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <h5 class="mb-3">Secretaries 2021</h5>
                                            <div class="row g-3">
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /admin/476856981_4123432817892541_1624224823209103418_n.jpg"
                                                            class="img-fluid"
                                                            alt="476856981_4123432817892541_1624224823209103418_n.jpg">
                                                        <div class="name">
                                                            476856981_4123432817892541_1624224823209103418_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /admin/480205108_1673069036614324_5079654607934815247_n.jpg"
                                                            class="img-fluid"
                                                            alt="480205108_1673069036614324_5079654607934815247_n.jpg">
                                                        <div class="name">
                                                            480205108_1673069036614324_5079654607934815247_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /creative/69861786_510323629778636_5845785793657831424_n.jpg"
                                                            class="img-fluid"
                                                            alt="69861786_510323629778636_5845785793657831424_n.jpg">
                                                        <div class="name">69861786_510323629778636_5845785793657831424_n
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /creative/471658768_1664068401128077_7406622409763109517_n.jpg"
                                                            class="img-fluid"
                                                            alt="471658768_1664068401128077_7406622409763109517_n.jpg">
                                                        <div class="name">
                                                            471658768_1664068401128077_7406622409763109517_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /events/504868796_4146322538979491_918509307535544619_n.jpg"
                                                            class="img-fluid"
                                                            alt="504868796_4146322538979491_918509307535544619_n.jpg">
                                                        <div class="name">
                                                            504868796_4146322538979491_918509307535544619_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /events/454317040_10225148019343878_7594567972609169834_n.jpg"
                                                            class="img-fluid"
                                                            alt="454317040_10225148019343878_7594567972609169834_n.jpg">
                                                        <div class="name">
                                                            454317040_10225148019343878_7594567972609169834_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /fin/500367999_3581840292112516_2140517489434280250_n.jpg"
                                                            class="img-fluid"
                                                            alt="500367999_3581840292112516_2140517489434280250_n.jpg">
                                                        <div class="name">
                                                            500367999_3581840292112516_2140517489434280250_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /fin/Copy of 487208029_4090148864590979_892055582219564636_n.jpg"
                                                            class="img-fluid"
                                                            alt="Copy of 487208029_4090148864590979_892055582219564636_n.jpg">
                                                        <div class="name">Copy of
                                                            487208029_4090148864590979_892055582219564636_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /hr/465844472_2640701509435187_7978154684435128783_n.jpg"
                                                            class="img-fluid"
                                                            alt="465844472_2640701509435187_7978154684435128783_n.jpg">
                                                        <div class="name">
                                                            465844472_2640701509435187_7978154684435128783_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /hr/515437567_1952489408489387_1315940866281760937_n.jpg"
                                                            class="img-fluid"
                                                            alt="515437567_1952489408489387_1315940866281760937_n.jpg">
                                                        <div class="name">
                                                            515437567_1952489408489387_1315940866281760937_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /miap/354961206_6671265479584724_7746041063655378665_n.jpg"
                                                            class="img-fluid"
                                                            alt="354961206_6671265479584724_7746041063655378665_n.jpg">
                                                        <div class="name">
                                                            354961206_6671265479584724_7746041063655378665_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /miap/124971238_3388976321209438_2585177128912475862_n.jpg"
                                                            class="img-fluid"
                                                            alt="124971238_3388976321209438_2585177128912475862_n.jpg">
                                                        <div class="name">
                                                            124971238_3388976321209438_2585177128912475862_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /pr/469337595_3924168784509776_3814304461961048715_n.jpg"
                                                            class="img-fluid"
                                                            alt="469337595_3924168784509776_3814304461961048715_n.jpg">
                                                        <div class="name">
                                                            469337595_3924168784509776_3814304461961048715_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /pr/483106530_4026994964252647_8895841218075580060_n.jpg"
                                                            class="img-fluid"
                                                            alt="483106530_4026994964252647_8895841218075580060_n.jpg">
                                                        <div class="name">
                                                            483106530_4026994964252647_8895841218075580060_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /rd/470235244_2667326150106056_3872053460634156449_n.jpg"
                                                            class="img-fluid"
                                                            alt="470235244_2667326150106056_3872053460634156449_n.jpg">
                                                        <div class="name">
                                                            470235244_2667326150106056_3872053460634156449_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /rd/128351021_228325362052613_4561984735401676055_n.jpg"
                                                            class="img-fluid"
                                                            alt="128351021_228325362052613_4561984735401676055_n.jpg">
                                                        <div class="name">
                                                            128351021_228325362052613_4561984735401676055_n</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 text-center">
                                                    <div class="panel-card"><img
                                                            src="images/Panel_21_22/Secreteries /rd/475195409_1168331491303047_2401447768072024913_n.jpg"
                                                            class="img-fluid"
                                                            alt="475195409_1168331491303047_2401447768072024913_n.jpg">
                                                        <div class="name">
                                                            475195409_1168331491303047_2401447768072024913_n</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer" style="border-top: 1px solid #f3d35c;">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End Previous Panels Modal -->

                    <!-- Panel Content Area -->
                    <div class="panel-content-area col-12">
                        <!-- Dynamic subtitle showing the currently selected panel year -->
                        <div class="text-center mb-3">
                            <h5 id="panelYearTitle" class="fw-bold" style="color: #f3d35c;">Current Panel (2026–2027)</h5>
                        </div>
                        <!-- Loading Spinner -->
                        <div class="panel-loading" id="panelLoading" style="display: none;">
                            <div class="spinner"></div>
                        </div>

                        <!-- Panel Members Container -->
                        <div class="panel-members-container" id="panelMembersContainer">
                            <div class="row g-4 justify-content-center panel-members-grid">
                                <!-- Default view: Panel_24_25 (4 members → col-lg-6 each) -->

                                <div class="col-lg-6 col-12 pmc-col">
                                    <div class="panel-member-card">
                                        <div class="pmc-image-wrap">
                                            <img src="images/Panel_24_25/Panel/aparup.jpg" alt="Aparup Chowdhury">
                                            <div class="pmc-overlay">
                                                <a href="https://www.facebook.com/aparup.chy.77" target="_blank" aria-label="Facebook">
                                                    <ion-icon name="logo-facebook"></ion-icon>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="pmc-body">
                                            <p class="pmc-name">Aparup Chowdhury</p>
                                            <span class="pmc-role">President</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6 col-12 pmc-col">
                                    <div class="panel-member-card">
                                        <div class="pmc-image-wrap">
                                            <img src="images/Panel_24_25/Panel/nafisa.jpg" alt="Nafisa Noor">
                                            <div class="pmc-overlay">
                                                <a href="https://www.facebook.com/nafisa.noor.57685" target="_blank" aria-label="Facebook">
                                                    <ion-icon name="logo-facebook"></ion-icon>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="pmc-body">
                                            <p class="pmc-name">Nafisa Noor</p>
                                            <span class="pmc-role">General Secretary</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6 col-12 pmc-col">
                                    <div class="panel-member-card">
                                        <div class="pmc-image-wrap">
                                            <img src="images/Panel_24_25/Panel/zia.jpg" alt="Towkeer Mohammad Zia">
                                            <div class="pmc-overlay">
                                                <a href="https://www.facebook.com/towkeer.mohammad.zia.2024" target="_blank" aria-label="Facebook">
                                                    <ion-icon name="logo-facebook"></ion-icon>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="pmc-body">
                                            <p class="pmc-name">Towkeer Mohammad Zia</p>
                                            <span class="pmc-role">Joint Secretary</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6 col-12 pmc-col">
                                    <div class="panel-member-card">
                                        <div class="pmc-image-wrap">
                                            <img src="images/Panel_24_25/Panel/mamun.jpg" alt="Mamun Abdullah">
                                            <div class="pmc-overlay">
                                                <a href="https://www.facebook.com/aam099" target="_blank" aria-label="Facebook">
                                                    <ion-icon name="logo-facebook"></ion-icon>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="pmc-body">
                                            <p class="pmc-name">Mamun Abdullah</p>
                                            <span class="pmc-role">Vice President</span>
                                        </div>
                                    </div>
                                </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
        <!-- done -->

        <!-- SB Members Section -->
        <section class="sb-section" id="section_4">
            <div class="container">
                <h2 class="sb-section-title" id="sbSectionTitle">Meet Our Secretaries</h2>

                <!-- SB Members Container -->
                <div class="sb-members-container" id="sbMembersContainer">
                    <div class="album-cover">
                        <div class="swiper sb-swiper" id="sbSwiper">
                            <div class="swiper-wrapper" id="sbSwiperWrapper">
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/rudian.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/rudian.borneel" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Rudian Ahmed</span>
                                        <span class="position">Secretary of
                                            Human Resource</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/MD_Sadman_Safin_Oasif.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/profile.php?id=100008597416622"
                                            target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">MD Sadman Safin
                                            Oasif</span>
                                        <span class="position">Secretary of
                                            Human Resource</span>
                                    </div>
                                </div>

                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Showmik_Safi.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/profile.php?id=100067106982577"
                                            target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Showmik Safi</span>
                                        <span class="position">Secretary of
                                            Event Management & Logistics</span>
                                    </div>
                                </div>

                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Tamejul_habib.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/INCcharlie19" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Tamejul Habib</span>
                                        <span class="position">Secretary of
                                            Admin</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Nafisa_Islam.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/nafisaislamahona" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Nafisa Islam</span>
                                        <span class="position">Secretary of
                                            Creative</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Shreya_Sangbriti.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://web.facebook.com/shreyasangbriti#" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Shreya
                                            Sangbriti</span>
                                        <span class="position">Secretary of
                                            Creative</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Avibadhan_Das.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/avibadhan.dasarno" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Avibadhan Das</span>
                                        <span class="position">Secretary of
                                            Performance (Music)</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Lalon.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/andalib.mostafa.1" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Lalon Mostafa</span>
                                        <span class="position">Secretary of
                                            Performance (Music)</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Rudra_Mathew_Gomes.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/henry.ribeiro.33" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Rudra Mathew
                                            Gomes</span>
                                        <span class="position">Secretary of
                                            Performance (Music)</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Syed_Ariful_Islam_Aowan.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/syedariful.aowan" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Syed Ariful Islam
                                            Aowan</span>
                                        <span class="position">Secretary of
                                            Performance (Music)</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Rubaba_Khijir_Nusheen.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/rubaba.nusheen" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Rubaba Khijir
                                            Nusheen</span>
                                        <span class="position">Secretary of
                                            Performance (Dance)</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Maria_Kamal_Katha.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/maria.kamal.katha" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Maria Kamal
                                            Katha</span>
                                        <span class="position">Secretary of
                                            Performance (Dance)</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Dino.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/diana.momo.334" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Diana Halder
                                            Momo</span>
                                        <span class="position">Secretary of
                                            Performance (Dance)</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Jubair_Rahman.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/jubair.rahman.765511" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Jubair Rahman</span>
                                        <span class="position">Secretary of
                                            Performance (Dance)</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Fabiha_Bushra_Ali.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/fabooshu" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Fabiha Bushra
                                            Ali</span>
                                        <span class="position">Secretary of
                                            Public Relation</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Md_Ahnaf_Farhan.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/ahnaf.farhan.1" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Md Ahnaf
                                            Farhan</span>
                                        <span class="position">Secretary of
                                            Public Relation</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Khaled_Bin_Taher.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/Khaled.tahsin18" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Khaled Bin
                                            Taher</span>
                                        <span class="position">Secretary of
                                            Public Relation</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Kazi_Tawsiat_Binte_Ehsan.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/kazitawsiat.binteehsan" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Kazi Tawsiat Binte Ehsan</span>
                                        <span class="position">Secretary of
                                            Admin</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Jareen_Tasnim_Bushra.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/buushraaaaaa21" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Jareen Tasnim
                                            Bushra</span>
                                        <span class="position">Secretary of
                                            Research & Development</span>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <img src="images/Panel_24_25/Secreteries/Tasnimul_Hasan.jpg"
                                        onerror="this.src='images/placeholder.png'" />
                                    <div class="overlay">
                                        <a href="https://www.facebook.com/buushraaaaaa21" target="_blank">
                                            <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
                                        </a>
                                    </div>
                                    <div class="member-name">
                                        <span class="name">Tasnimul Hasan</span>
                                        <span class="position">Secretary of
                                            Research & Development</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Add Swiper Pagination -->
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                </div>
        </section>

        <section class="schedule-section section-padding" id="section_5">
            <div class="container">
                <div class="row">

                    <div class="col-12 text-center">

                        <div class="event-schedule-container">
                            <div class="event-schedule-header">Past Events</div>
                            <div class="event-cards-grid">
                                <!-- Card 1 -->
                                <div class="event-card" onclick="window.location.href='past_events.html#event-6'"
                                    style="cursor: pointer;">
                                    <div class="event-card-bg" style="background-image: url('images/pls.jpg');"></div>
                                    <div class="event-card-content">
                                        <div class="event-title">Club Leadership Bootcamp 2025</div>
                                        <div class="event-time">10 October, 2025</div>
                                        <div class="event-by">Friday</div>
                                    </div>
                                </div>
                                <!-- Card 2 -->
                                <div class="event-card" onclick="window.location.href='past_events.html#event-7'"
                                    style="cursor: pointer;">
                                    <div class="event-card-bg" style="background-image: url('images/slide8.jpg');">
                                    </div>
                                    <div class="event-card-content">
                                        <div class="event-title">Rhythm Revive 25.1</div>
                                        <div class="event-time">7 September, 2025</div>
                                        <div class="event-by">Sunday</div>
                                    </div>
                                </div>
                                <!-- Card 3 -->
                                <div class="event-card" onclick="window.location.href='past_events.html#event-8'"
                                    style="cursor: pointer;">
                                    <div class="event-card-bg" style="background-image: url('images/slide9.jpg');">
                                    </div>
                                    <div class="event-card-content">
                                        <div class="event-title">Participation at 16th Convocation Volunteers
                                            Appreciation Ceremony</div>
                                        <div class="event-time">15 August, 2025</div>
                                        <div class="event-by">Friday</div>
                                    </div>
                                </div>
                                <!-- Card 4 -->
                                <div class="event-card" onclick="window.location.href='past_events.html#country-music'"
                                    style="cursor: pointer;">
                                    <div class="event-card-bg" style="background-image: url('images/slide5.jpg');">
                                    </div>
                                    <div class="event-card-content">
                                        <div class="event-title">বৈশাখী উৎসব ১৪৩২</div>
                                        <div class="event-time">28th April, 2025</div>
                                        <div class="event-by">Tuesday</div>
                                    </div>
                                </div>
                                <!-- Card 5 -->
                                <div class="event-card" onclick="window.location.href='past_events.html#event-9'"
                                    style="cursor: pointer;">
                                    <div class="event-card-bg" style="background-image: url('images/Wild.jpg');"></div>
                                    <div class="event-card-content">
                                        <div class="event-title">Participation at Wild Escape by BUAC</div>
                                        <div class="event-time">22th April, 2025</div>
                                        <div class="event-by">Tuesday</div>
                                    </div>
                                </div>
                                <!-- See More Button -->
                                <a href="past_events.html" class="see-more-button">
                                    <div class="see-more-button-content">
                                        <i class="fas fa-chevron-right see-more-icon"></i>
                                        <div class="see-more-text">View All</div>
                                        <div class="see-more-subtext">Past Events</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="contact-section section-padding" id="signup">
            <div class="container">
                <div class="row">

                    <div class="col-lg-8 col-12 mx-auto">
                        <h2 class="text-center mb-4 signup-title">Apply Now</h2>

                        <nav class="d-flex justify-content-center">
                            <div class="nav nav-tabs align-items-baseline justify-content-center" id="nav-tab"
                                role="tablist">
                                <button class="nav-link active signup-tab" id="nav-ContactForm-tab" data-bs-toggle="tab"
                                    data-bs-target="#nav-ContactForm" type="button" role="tab"
                                    aria-controls="nav-ContactForm" aria-selected="true">
                                    <h5>Application Form</h5>
                                </button>

                                <button class="nav-link maps-tab" id="nav-ContactMap-tab" data-bs-toggle="tab"
                                    data-bs-target="#nav-ContactMap" type="button" role="tab"
                                    aria-controls="nav-ContactMap" aria-selected="false">
                                    <h5>Google Maps</h5>
                                </button>
                            </div>
                        </nav>

                        <div class="tab-content shadow-lg mt-5 signup-container" id="nav-tabContent">
                            <!-- Floating particles for signup section -->
                            <div class="signup-particles" id="signupParticles"></div>

                            <div class="tab-pane fade show active" id="nav-ContactForm" role="tabpanel"
                                aria-labelledby="nav-ContactForm-tab">

                                <?php if ($signupSuccess): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert"
                                        style="background: rgba(40, 167, 69, 0.2); border: 1px solid rgba(40, 167, 69, 0.3); color: #51cf66;">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <?php echo htmlspecialchars($signupSuccess); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"
                                            style="filter: brightness(0) invert(1);"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if ($signupError): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <?php echo htmlspecialchars($signupError); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"
                                            aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if ($signupEnabled): ?>
                                    <form id="signup-form" name="signupForm" class="custom-form contact-form mb-5 mb-lg-0"
                                        action="../backend/Action/signup_handler.php" method="POST" role="form">
                                        <div class="contact-form-body">
                                            <div class="row">
                                                <div class="col-lg-6 col-md-6 col-12 mb-3">
                                                    <input type="text" name="signup-name" id="signup-name"
                                                        class="form-control" placeholder="Full Name" required>
                                                </div>
                                                <div class="col-lg-6 col-md-6 col-12 mb-3">
                                                    <input type="text" name="signup-id" id="signup-id" class="form-control"
                                                        placeholder="University ID" required>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-lg-6 col-md-6 col-12 mb-3">
                                                    <input type="email" name="signup-main-email" id="signup-main-email"
                                                        class="form-control" placeholder="Main Email Address" required>
                                                </div>
                                                <div class="col-lg-6 col-md-6 col-12 mb-3">
                                                    <input type="email" name="signup-gsuite-email" id="signup-gsuite-email"
                                                        class="form-control" placeholder="GSuite Email" required>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6 col-md-6 col-12 mb-3">
                                                    <input type="text" name="signup-department" id="signup-department"
                                                        class="form-control" placeholder="Department" required>
                                                </div>
                                                <div class="col-lg-6 col-md-6 col-12 mb-3">
                                                    <input type="tel" name="signup-phone" id="signup-phone"
                                                        class="form-control" placeholder="Phone Number" required>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-lg-6 col-md-6 col-12 mb-3">
                                                    <select name="signup-semester" id="signup-semester" class="form-control"
                                                        required>
                                                        <option value>Current
                                                            Semester</option>
                                                        <option value="1st">1st</option>
                                                        <option value="2nd">2nd</option>
                                                        <option value="3rd">3rd</option>
                                                        <option value="4th">4th</option>
                                                        <option value="5th">5th</option>
                                                        <option value="6th">6th</option>
                                                        <option value="7th">7th</option>
                                                        <option value="8th">8th</option>
                                                        <option value="9th">9th</option>
                                                        <option value="10th+">10th
                                                            or above</option>
                                                    </select>
                                                </div>
                                                <div class="col-lg-6 col-md-6 col-12 mb-3">
                                                    <select name="signup-gender" id="signup-gender" class="form-control"
                                                        required>
                                                        <option value>Gender</option>
                                                        <option value="Male">Male</option>
                                                        <option value="Female">Female</option>
                                                        <option value="Other">Other</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-lg-6 col-md-6 col-12 mb-3">
                                                    <div class="date-input-wrapper">
                                                        <input type="date" name="signup-dob" id="signup-dob"
                                                            class="form-control" required
                                                            oninvalid="this.setCustomValidity('Please select your date of birth')"
                                                            oninput="this.setCustomValidity('')">
                                                        <span class="date-placeholder">Date of Birth</span>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6 col-md-6 col-12 mb-3">
                                                    <input type="url" name="signup-facebook" id="signup-facebook"
                                                        class="form-control" placeholder="Facebook Profile URL" required>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-12 mb-3">
                                                    <label class="form-label">First Department Preference in BUCUC:</label>
                                                    <select name="signup-first-dept" id="signup-first-dept"
                                                        class="form-control" required>
                                                        <option value="">Select Department</option>
                                                        <!-- Options will be populated dynamically by JavaScript -->
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-12 mb-3">
                                                    <label class="form-label">Second Department Preference in BUCUC:</label>
                                                    <select name="signup-second-dept" id="signup-second-dept"
                                                        class="form-control" required>
                                                        <option value="">Select Department</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-4 col-md-10 col-8 mx-auto">
                                                <button type="submit"
                                                    class="form-control"><?php echo $signupEnabled ? 'Apply Now' : 'Login'; ?></button>
                                            </div>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div class="contact-form mb-5 mb-lg-0" style="padding: 60px 40px; text-align: center;">
                                        <div class="signup-disabled-message">
                                            <i class="fas fa-user-slash"
                                                style="font-size: 4rem; color: #dc3545; margin-bottom: 2rem; opacity: 0.8;"></i>
                                            <h3 style="color: #dc3545; font-weight: 700; margin-bottom: 1rem;">Application
                                                Form expired for current Semester</h3>
                                            <p
                                                style="color: #666; font-size: 1.1rem; line-height: 1.6; margin-bottom: 2rem;">
                                                The membership registration system is temporarily disabled.
                                                Please check back Next Semester or contact the admin team for more
                                                information.
                                            </p>
                                            <div
                                                style="background: rgba(220, 53, 69, 0.1); border: 2px solid rgba(220, 53, 69, 0.3); border-radius: 15px; padding: 20px; margin-top: 2rem;">
                                                <p style="color: #721c24; margin: 0; font-weight: 600;">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    This is a temporary measure to manage the application process
                                                    effectively.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>



                            <div class="tab-pane fade" id="nav-ContactMap" role="tabpanel"
                                aria-labelledby="nav-ContactMap-tab">
                                <iframe class="google-map"
                                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1100.1434944730236!2d90.42450743390042!3d23.77246450986446!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3755c7715a40c603%3A0xec01cd75f33139f5!2sBRAC%20University!5e1!3m2!1sen!2sbd!4v1745998853929!5m2!1sen!2sbd"
                                    width="100%" height="450" style="border:0;" allowfullscreen loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"></iframe>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer" id="footer">
        <div class="site-footer-top">
            <div class="container">
                <div class="row">

                    <div class="col-lg-6 col-12">
                        <h2 class="text-white mb-lg-0 d-flex align-items-center">
                            <img src="images/logo.png" alt="Club Logo"
                                style="height: 1.5em; margin-right: 0.5em; vertical-align: middle;">
                            BUCuC
                        </h2>
                    </div>

                    <div class="col-lg-6 col-12 d-flex justify-content-lg-end align-items-center">
                        <ul class="social-icon d-flex justify-content-lg-end ms-lg-auto">
                            <li class="social-icon-item">
                                <a href="https://www.facebook.com/bucuc" class="social-icon-link" target="_blank">
                                    <span class="bi-facebook"></span>
                                </a>
                            </li>
                            <li class="social-icon-item">
                                <a href="https://www.facebook.com/bucucarchive" class="social-icon-link"
                                    target="_blank">
                                    <span class="bi-facebook"></span>
                                </a>
                            </li>
                            <li class="social-icon-item">
                                <a href="https://www.youtube.com/@bracuniversityculturalclub717"
                                    class="social-icon-link" target="_blank">
                                    <span class="bi-youtube"></span>
                                </a>
                            </li>
                            <li class="social-icon-item">
                                <a href="https://www.instagram.com/bucuclive/" class="social-icon-link" target="_blank">
                                    <span class="bi-instagram"></span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="row">

                <div class="col-lg-6 col-12 mb-4 pb-2">
                    <h5 class="site-footer-title mb-3">Links</h5>

                    <ul class="site-footer-links">
                        <li class="site-footer-link-item">
                            <a href="#section_1" class="site-footer-link click-scroll">Home</a>
                        </li>

                        <li class="site-footer-link-item">
                            <a href="#section_2" class="site-footer-link click-scroll">About</a>
                        </li>

                        <li class="site-footer-link-item">
                            <a href="#section_3" class="site-footer-link click-scroll">BUCuC
                                Panel</a>
                        </li>

                        <li class="site-footer-link-item">
                            <a href="#section_5" class="site-footer-link click-scroll">Past Events</a>
                        </li>

                        <li class="site-footer-link-item">
                            <a href="#section_4" class="site-footer-link click-scroll">Sb
                                Members</a>
                        </li>

                        <li class="site-footer-link-item">
                            <a href="#very-bottom" class="site-footer-link click-scroll">Contact</a>
                        </li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6 col-12 mb-4 mb-lg-0">
                    <h5 class="site-footer-title mb-3">Have a question?</h5>

                    <p class="text-white d-flex mb-1">
                        <a href="mailto:club.bucuc@g.bracu.ac.bd" class="site-footer-link">
                            club.bucuc@g.bracu.ac.bd
                        </a>
                    </p>

                    <p class="text-white d-flex">
                        <a href="mailto:hr.bucuc@gmail.com" class="site-footer-link">
                            hr.bucuc@gmail.com
                        </a>
                    </p>
                </div>

                <div class="col-lg-3 col-md-6 col-11 mb-4 mb-lg-0 mb-md-0">
                    <h5 class="site-footer-title mb-3">Location</h5>

                    <p class="text-white d-flex mt-3 mb-2">
                        Kha 224 Pragati Sarani, Merul Badda , Dhaka 1212</p>

                    <a class="link-fx-1 color-contrast-higher mt-3"
                        href="https://www.google.com/maps/place/BRAC+University/@23.7724645,90.4245074,17z"
                        target="_blank">
                        <span>Our Maps</span>
                        <svg class="icon" viewBox="0 0 32 32" aria-hidden="true">
                            <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="16" cy="16" r="15.5"></circle>
                                <line x1="10" y1="18" x2="16" y2="12"></line>
                                <line x1="16" y1="12" x2="22" y2="18"></line>
                            </g>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <div class="site-footer-bottom">
            <div class="container">
                <div class="row">

                    <div class="col-lg-3 col-12 mt-5">
                        <p class="copyright-text">Copyright © 2025 BUCuC</p>
                    </div>

                    <div class="col-lg-8 col-12 mt-lg-5">
                        <ul class="site-footer-links">
                            <li class="site-footer-link-item">
                                <a href="#" class="site-footer-link">Terms
                                    &amp; Conditions</a>
                            </li>

                            <li class="site-footer-link-item">
                                <a href="#" class="site-footer-link">Privacy
                                    Policy</a>
                            </li>

                            <li class="site-footer-link-item">
                                <a href="#" class="site-footer-link">Your
                                    Feedback</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div id="very-bottom"></div>
    </footer>

    <!-- JAVASCRIPT FILES -->
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.sticky.js"></script>
    <script src="js/click-scroll.js"></script>
    <script src="js/custom.js"></script>
    <script src="js/apps-script.js"></script>

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- Initialize Swiper -->
    <script>
        // Initialize Swiper after DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function () {
            // Function to determine if loop should be enabled
            function shouldEnableLoop() {
                const slides = document.querySelectorAll('.sb-swiper .swiper-slide');
                console.log('SB Slides found:', slides.length);
                return slides.length > 5; // Enable loop only if more than 5 slides
            }

            // Count slides before initializing
            const totalSlides = document.querySelectorAll('.sb-swiper .swiper-slide').length;
            console.log('Total SB slides detected:', totalSlides);

            window.sbSwiper = new Swiper('.sb-swiper', {
                effect: 'coverflow',
                grabCursor: true,
                centeredSlides: true,
                loop: totalSlides > 5, // Enable loop if more than 5 slides
                loopAdditionalSlides: 2,
                initialSlide: 0,
                coverflowEffect: {
                    rotate: 30,
                    stretch: 0,
                    depth: 120,
                    modifier: 1,
                    slideShadows: true,
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                autoplay: false,
                // Responsive breakpoints:
                breakpoints: {
                    320: {
                        slidesPerView: 1,
                        spaceBetween: 10,
                    },
                    480: {
                        slidesPerView: 2,
                        spaceBetween: 20,
                    },
                    768: {
                        slidesPerView: 3,
                        spaceBetween: 30,
                    },
                    1024: {
                        slidesPerView: 5,
                        spaceBetween: 56,
                    },
                },
                on: {
                    init: function () {
                        console.log('SB Swiper initialized with', this.slides.length, 'slides');
                    },
                    slideChange: function () {
                        console.log('Slide changed to:', this.activeIndex);
                    }
                }
            });

            // Setup intersection observer for autoplay
            let autoplayStarted = false;
            const sbSection = document.getElementById('section_4');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !autoplayStarted && window.sbSwiper) {
                        try {
                            window.sbSwiper.slideToLoop(0, 0); // Go to first slide instantly
                            if (window.sbSwiper.autoplay) {
                                window.sbSwiper.autoplay.start();
                            }
                            autoplayStarted = true;
                            console.log('SB Swiper autoplay started');
                        } catch (error) {
                            console.error('Error starting autoplay:', error);
                        }
                    }
                });
            }, {
                threshold: 0.7
            });

            if (sbSection) {
                observer.observe(sbSection);
            }
        });
    </script>

    <!-- Password Toggle Function -->
    <script>
        function togglePassword() {
            const passwordInput = document.querySelector('.admin-input[name="adminPassword"]');
            const toggleIcon = document.querySelector('.password-toggle');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>

    <script>
        window.addEventListener('scroll', function () {
            const navbar = document.getElementById('mainNavbar');
            if (navbar) {
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            }
        });
    </script>

    <script>
        window.addEventListener('DOMContentLoaded', function () {
            window.scrollTo(0,0);
            // Also clear any hash in the URL
            if (window.location.hash) {
                history.replaceState(null, null, window.location.pathname + window.location.search);
            }
        });
    </script>


    <script>
        // Admin Check Function
        function checkAdminStatus(isAdmin) {
            if (isAdmin) {
                // Redirect to admin login page
                window.location.href = 'admin-login.php';
            } else {
                // Show notification and redirect to header
                showCustomNotification('Sorry, you are not an admin', 'warning');
                setTimeout(() => {
                    window.location.href = 'index.php#section_1';
                }, 2000);
            }
        }

        // Custom notification function
        function showCustomNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `custom-notification ${type}`;
            notification.textContent = message;

            document.body.appendChild(notification);

            // Show notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);

            // Hide and remove notification
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        function showInlineMessage(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.style.position = 'fixed';
            alertDiv.style.top = '20px';
            alertDiv.style.left = '50%';
            alertDiv.style.transform = 'translateX(-50%)';
            alertDiv.style.zIndex = '9999';
            alertDiv.style.minWidth = '300px';
            alertDiv.style.textAlign = 'center';

            alertDiv.innerHTML = `
${message}
<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
`;

            document.body.appendChild(alertDiv);

            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Only add login form listener if the form exists
        const loginForm = document.getElementById('login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(document.getElementById('login-form'));
                formData.append('action', 'login');

                fetch('https://script.google.com/macros/s/AKfycbz-FKOQ8Uu32cr4q6DUv2--KtAqJHMdGWUcEknJAV9mJh6TlB-JYrw1mmG2myiTar6C/exec', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(response => {
                        if (response.success) {
                            showInlineMessage('Logged in as ' + response.name, 'success');
                        } else {
                            showInlineMessage('Invalid email or password', 'danger');
                        }
                    })
                    .catch(err => {
                        showInlineMessage('There was an error. Please try again.', 'danger');
                    });
            });
        }

        // Department preference dropdown functionality - Enhanced Debug Version (Production)
        (function () {
            // Department options array
            const departmentOptions = [{
                value: 'Admin',
                text: 'Admin'
            },
            {
                value: 'PR',
                text: 'PR - Public Relations and Editorial'
            },
            {
                value: 'HR',
                text: 'HR - Human Resources'
            },
            {
                value: 'EM',
                text: 'EM - Event Management and Logistics'
            },
            {
                value: 'Creative',
                text: 'Creative'
            },
            {
                value: 'Finance',
                text: 'Finance'
            },
            {
                value: 'Performance',
                text: 'Performance'
            },
            {
                value: 'RD',
                text: 'R&D - Research and Development'
            },
            {
                value: 'MIAP',
                text: 'MIAP - Marketing IT Archive & Photography'
            }
            ];

            // Function to populate dropdown options
            function populateDropdown(dropdown, excludeValue = null) {
                if (!dropdown) {
                    console.error('Dropdown element not found!');
                    return;
                }

                // Clear existing options except the first one (placeholder)
                dropdown.innerHTML = '<option value="">Select Department</option>';

                // Populate with department options
                let addedCount = 0;
                departmentOptions.forEach(dept => {
                    if (dept.value !== excludeValue) {
                        const option = document.createElement('option');
                        option.value = dept.value;
                        option.textContent = dept.text;
                        dropdown.appendChild(option);
                        addedCount++;
                    }
                });

                console.log(`Populated ${dropdown.id} with ${addedCount} options`);
            }

            // Function to initialize dropdowns
            function initializeDepartmentDropdowns() {
                const firstDeptSelect = document.getElementById('signup-first-dept');
                const secondDeptSelect = document.getElementById('signup-second-dept');

                console.log('First dropdown found:', firstDeptSelect !== null);
                console.log('Second dropdown found:', secondDeptSelect !== null);

                if (!firstDeptSelect || !secondDeptSelect) {
                    console.error('One or both dropdown elements not found! Retrying in 500ms...');
                    setTimeout(initializeDepartmentDropdowns, 500);
                    return;
                }

                // Initial population of both dropdowns
                populateDropdown(firstDeptSelect);
                populateDropdown(secondDeptSelect);

                // Handle first dropdown changes
                firstDeptSelect.addEventListener('change', function () {
                    const selectedValue = this.value;
                    console.log('First dropdown changed to:', selectedValue);

                    // Store the current second dropdown value before any changes
                    const currentSecondValue = secondDeptSelect.value;

                    // If first dropdown has a value and it matches second dropdown, clear second dropdown
                    if (selectedValue && currentSecondValue === selectedValue) {
                        secondDeptSelect.value = '';
                        console.log('Cleared second dropdown due to duplicate selection');
                        // Re-populate second dropdown excluding the selected value from first
                        populateDropdown(secondDeptSelect, selectedValue);
                    } else {
                        // Re-populate second dropdown excluding the selected value from first
                        populateDropdown(secondDeptSelect, selectedValue);

                        // Restore the second dropdown's value if it's still valid (not the same as first's selection)
                        if (currentSecondValue && currentSecondValue !== selectedValue) {
                            secondDeptSelect.value = currentSecondValue;
                            console.log('Preserved second dropdown value:', currentSecondValue);
                        }
                    }

                    // Store the current value for future reference
                    secondDeptSelect.dataset.previousValue = secondDeptSelect.value;
                });

                // Handle second dropdown changes
                secondDeptSelect.addEventListener('change', function () {
                    const selectedValue = this.value;
                    console.log('Second dropdown changed to:', selectedValue);

                    // Store the current first dropdown value before any changes
                    const currentFirstValue = firstDeptSelect.value;

                    // If second dropdown has a value and it matches first dropdown, clear first dropdown
                    if (selectedValue && currentFirstValue === selectedValue) {
                        firstDeptSelect.value = '';
                        console.log('Cleared first dropdown due to duplicate selection');
                        // Re-populate first dropdown excluding the selected value from second
                        populateDropdown(firstDeptSelect, selectedValue);
                    } else if (selectedValue) {
                        // Re-populate first dropdown excluding the selected value from second
                        populateDropdown(firstDeptSelect, selectedValue);

                        // Restore the first dropdown's value if it's still valid (not the same as second's selection)
                        if (currentFirstValue && currentFirstValue !== selectedValue) {
                            firstDeptSelect.value = currentFirstValue;
                            console.log('Preserved first dropdown value:', currentFirstValue);
                        }
                    } else {
                        // Second dropdown was cleared, restore all options to first dropdown
                        populateDropdown(firstDeptSelect, null);
                        if (currentFirstValue) {
                            firstDeptSelect.value = currentFirstValue;
                            console.log('Restored first dropdown value after second cleared:', currentFirstValue);
                        }
                    }

                    // Store the current value for future reference
                    firstDeptSelect.dataset.previousValue = firstDeptSelect.value;
                });

                // Store initial values
                firstDeptSelect.dataset.previousValue = '';
                secondDeptSelect.dataset.previousValue = '';

                console.log('Department dropdowns initialized successfully!');
            }

            // Initialize when DOM is loaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeDepartmentDropdowns);
            } else {
                initializeDepartmentDropdowns();
            }

            // Also try to initialize after a short delay as backup
            setTimeout(() => {
                initializeDepartmentDropdowns();
            }, 1000);
        })();
    </script>
    <script>
        // Mobile Sidebar Toggle Script (right sidebar, close button)
        (function () {
            var sidebar = document.getElementById('mobileSidebar');
            var overlay = document.getElementById('mobileSidebarOverlay');
            var toggleBtn = document.getElementById('sidebarToggle');

            function toggleSidebar() {
                var isOpen = sidebar.classList.toggle('open');
                overlay.classList.toggle('open', isOpen);
                toggleBtn.classList.toggle('open', isOpen);
            }
            if (toggleBtn) toggleBtn.addEventListener('click', toggleSidebar);
            if (overlay) overlay.addEventListener('click', function () {
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
                toggleBtn.classList.remove('open');
            });
        })();
    </script>
    <style>
        @media (max-width: 768px) {
            .sidebar-toggle-btn {
                width: 44px;
                background: #f8f8fc;
                border: none;
                border-radius: 50%;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
                z-index: 1200;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .sidebar-toggle-bar {
                position: absolute;
                left: 50%;
                width: 24px;
                height: 3.5px;
                background: #555;
                border-radius: 2.5px;
                transition: all 0.3s cubic-bezier(.4, 2, .6, 1);
                transform: translateX(-50%) translateY(0);
            }

            .sidebar-toggle-bar:nth-child(1) {
                top: 14px;
            }

            .sidebar-toggle-bar:nth-child(2) {
                top: 20px;
            }

            .sidebar-toggle-bar:nth-child(3) {
                top: 26px;
            }

            .sidebar-toggle-btn.open .sidebar-toggle-bar:nth-child(1) {
                top: 20px;
                transform: translateX(-50%) rotate(45deg);
            }

            .sidebar-toggle-btn.open .sidebar-toggle-bar:nth-child(2) {
                opacity: 0;
            }

            .sidebar-toggle-btn.open .sidebar-toggle-bar:nth-child(3) {
                top: 20px;
                transform: translateX(-50%) rotate(-45deg);
            }
        }
    </style>
    <style>
        /* Modern Premium Modal Styles for Previous Panels */
        #previousPanelsModal .modal-content {
            background: linear-gradient(135deg, #0a1931 0%, #1a2639 100%);
            color: #222;
            border-radius: 22px;
            box-shadow: 0 8px 40px 0 #000a, 0 0 0 4px #ffd70033;
            border: none;
        }

        #previousPanelsModal .modal-header,
        #previousPanelsModal .modal-footer {
            border: none;
            background: transparent;
        }

        #previousPanelsModal .modal-title {
            font-weight: 700;
            letter-spacing: 1px;
            color: #ffd700;
        }

        #previousPanelsModal .nav-tabs {
            border-bottom: 2.5px solid #ffd700;
            justify-content: center;
        }

        #previousPanelsModal .nav-tabs .nav-link {
            color: #0a1931;
            background: none;
            border: none;
            font-weight: 600;
            font-size: 1.1em;
            margin: 0 8px;
            border-radius: 8px 8px 0 0;
            transition: background 0.2s, color 0.2s;
        }

        #previousPanelsModal .nav-tabs .nav-link.active {
            background: #ffd700;
            color: #0a1931;
            box-shadow: 0 2px 8px #ffd70044;
        }

        #previousPanelsModal .tab-pane {
            padding: 8px 0 0 0;
        }

        #previousPanelsModal .row.g-3 {
            margin-bottom: 0.5rem;
        }

        #previousPanelsModal .panel-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px #0002, 0 0 0 2px #ffd70033;
            padding: 18px 8px 10px 8px;
            margin-bottom: 12px;
            transition: transform 0.18s, box-shadow 0.18s;
            border: 2px solid #ffd700;
            position: relative;
            min-height: 210px;
        }

        #previousPanelsModal .panel-card:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 8px 32px #ffd70033, 0 0 0 4px #ffd70055;
            z-index: 2;
        }

        #previousPanelsModal .panel-card img {
            border-radius: 12px;
            border: 2.5px solid #ffd700;
            box-shadow: 0 2px 12px #0004;
            margin-bottom: 8px;
            max-height: 120px;
            object-fit: cover;
            background: #fff;
        }

        #previousPanelsModal .panel-card .name {
            font-weight: 600;
            font-size: 1.08em;
            color: #0a1931;
        }

        #previousPanelsModal .panel-card .position {
            font-size: 0.98em;
            color: #1a2639;
            opacity: 0.85;
        }

        #previousPanelsModal .tab-pane h5 {
            color: #ffd700;
            font-weight: 600;
            margin-bottom: 1.1rem;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            #previousPanelsModal .panel-card img {
                max-height: 80px;
            }

            #previousPanelsModal .panel-card {
                min-height: 140px;
                padding: 10px 2px 6px 2px;
            }
        }
    </style>

    <script>
        // Other functionality
        document.addEventListener('DOMContentLoaded', function () {







        });
    </script>

    <!-- Panel Year Dropdown JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Correct panel-year-dropdown identifier
            const dropdown = document.getElementById('panelYearSelect');
            if (!dropdown) {
                console.error('Panel year dropdown not found!');
                return;
            }
            // Map dropdown values to panel data keys
            const panelDataKeys = {
                'current': '2024',
                'panel_26_27': '2026',
                'panel_23_24': '2023',
                'panel_22_23': '2022',
                'panel_21_22': '2021',
                'panel_20_21': '2020',
                'panel_19_20': '2019'
            };

            // Cache-busting: mirrors PHP IMG_V; appends ?v= to any local image URL
            const IMG_V = '<?= IMG_V ?>';
            const imgUrl = url => url + (url.includes('?') ? '&' : '?') + 'v=' + IMG_V;

            // Panel data mapping
            const panelData = {
                '2024': {
                    panelMembers: [{
                        name: 'Aparup Chowdhury',
                        image: 'images/Panel_24_25/Panel/aparup.jpg',
                        panel: "President",
                        facebook: "https://www.facebook.com/aparup.chy.77"
                    },

                    {
                        name: 'Mamun Abdullah',
                        image: 'images/Panel_24_25/Panel/mamun.jpg',
                        panel: "Vice President",
                        facebook: "https://www.facebook.com/aam099"
                    },

                    {
                        name: 'Nafisa Noor',
                        image: 'images/Panel_24_25/Panel/nafisa.jpg',
                        panel: "General Secretary",
                        facebook: "https://www.facebook.com/nafisa.noor.57685"
                    },

                    {
                        name: 'Towkeer Mohammad Zia',
                        image: 'images/Panel_24_25/Panel/zia.jpg',
                        panel: "Joint Secretary",
                        facebook: "https://www.facebook.com/towkeer.mohammad.zia.2024"
                    },
                    ],
                    sbMembers: [{
                        name: 'Kazi Tawsiat Binte Ehsan',
                        image: 'images/Panel_24_25/Secreteries/Kazi_Tawsiat_Binte_Ehsan.jpg'
                    },
                    {
                        name: 'Avibadhan Das',
                        image: 'images/Panel_24_25/Secreteries/Avibadhan_Das.jpg'
                    },
                    {
                        name: 'Diana Halder Momo',
                        image: 'images/Panel_24_25/Secreteries/Diana_Halder_Momo.jpg'
                    },
                    {
                        name: 'Fabiha Bushra Ali',
                        image: 'images/Panel_24_25/Secreteries/Fabiha_Bushra_Ali.jpg'
                    },
                    {
                        name: 'Habib Hasan',
                        image: 'images/Panel_24_25/Secreteries/Habib_Hasan.jpg'
                    },
                    {
                        name: 'Jareen Tasnim Bushra',
                        image: 'images/Panel_24_25/Secreteries/Jareen_Tasnim_Bushra.jpg'
                    },
                    {
                        name: 'Jubair Rahman',
                        image: 'images/Panel_24_25/Secreteries/Jubair_Rahman.jpg'
                    },
                    {
                        name: 'Rudian Ahmed',
                        image: 'images/Panel_24_25/Secreteries/rudian.jpg'
                    }
                    ]
                },
                '2023': {
                    panelMembers: [{
                        name: 'Nashib Siam',
                        image: 'images/Panel_23_24/Panels/Nashib Siam (President).jpg',
                        panel: 'President',
                        facebook: "https://www.facebook.com/mohmed.nashib.5"
                    },
                    {
                        name: 'Nawshaba Maniza Riddhi',
                        image: 'images/Panel_23_24/Panels/Nawshaba Maniza Riddhi (VP).jpg',
                        panel: 'Vice President',
                        facebook: "https://www.facebook.com/nawshaba.maniza.riddhi"
                    },
                    {
                        name: 'Musharrat Quazi',
                        image: 'images/Panel_23_24/Panels/Musharrat Quazi (GS).jpg',
                        panel: 'General Secretary',
                        facebook: "https://www.facebook.com/musharrat.quazi"
                    },
                    {
                        name: 'Tamima Diba',
                        image: 'images/Panel_23_24/Panels/Tamima Diba (Financial Secretary).jpg',
                        panel: 'Joint Secretary',
                        facebook: "https://www.facebook.com/tamima.diba.2024"
                    }
                    ],
                    sbMembers: [{
                        name: 'Affan Adid',
                        image: 'images/Panel_23_24/Secretary/Admin/Affan_Adid.jpg'
                    },
                    {
                        name: 'Shadman Sakib',
                        image: 'images/Panel_23_24/Secretary/Admin/Shadman Sakib (Admin).jpg'
                    },
                    {
                        name: 'Khandoker Nabila Humayra',
                        image: 'images/Panel_23_24/Secretary/Creative/Khandoker_Nabila_Humayra.jpg'
                    },
                    {
                        name: 'Musfura Rahman',
                        image: 'images/Panel_23_24/Secretary/Creative/Musfura_Rahman.jpg'
                    },
                    {
                        name: 'Tahreen Nubaha Rabbi',
                        image: 'images/Panel_23_24/Secretary/Events/Tahreen_Nubaha_Rabbi.jpg'
                    },
                    {
                        name: 'Mamun Abdullah',
                        image: 'images/Panel_23_24/Secretary/Events/Mamun_Abdullah.jpg',
                        facebook: "www.youtube.com"
                    },
                    {
                        name: 'Towkeer Mohammad Zia',
                        image: 'images/Panel_23_24/Secretary/Events/Towkeer_Mohammad_Zia.jpg'
                    },
                    {
                        name: 'Kaushik Datta',
                        image: 'images/Panel_23_24/Secretary/Finance/Kaushik_Datta.jpg'
                    },
                    {
                        name: 'Ayam Dhar',
                        image: 'images/Panel_23_24/Secretary/Marketing/Ayam_Dhar.jpg'
                    },
                    {
                        name: 'Imam Ur Rashid',
                        image: 'images/Panel_23_24/Secretary/Marketing/Imam_Ur_Rashid.jpg'
                    },
                    {
                        name: 'Qurratul Ayen Elma',
                        image: 'images/Panel_23_24/Secretary/Marketing/Qurratul_Ayen_Elma.jpg'
                    },
                    {
                        name: 'Performance Secretary',
                        image: 'images/Panel_23_24/Secretary/Performance/6a2c9948-7284-4455-87ea-e78640578bc1.jpg'
                    },
                    {
                        name: 'Performance Secretary',
                        image: 'images/Panel_23_24/Secretary/Performance/Performance.jpg'
                    },
                    {
                        name: 'Mitisha Tasnim Rahman',
                        image: 'images/Panel_23_24/Secretary/Performance/Mitisha_Tasnim_Rahman.jpeg'
                    },
                    {
                        name: 'Performance Secretary',
                        image: 'images/Panel_23_24/Secretary/Performance/e46329e0-5f2e-4921-8f9b-94326e0a79e3.jpeg'
                    },
                    {
                        name: 'Taufiq',
                        image: 'images/Panel_23_24/Secretary/Performance/Taufiq (Performance).jpg'
                    },
                    {
                        name: 'Priyata Mondal',
                        image: 'images/Panel_23_24/Secretary/Performance/Priyata_Mondal.jpg'
                    },
                    {
                        name: 'Aparup Chy',
                        image: 'images/Panel_23_24/Secretary/Performance/Aparup Chy (Performance).jpg'
                    },
                    {
                        name: 'Nafisa Noor',
                        image: 'images/Panel_23_24/Secretary/Performance/Nafisa Noor (Performance).jpg'
                    },
                    {
                        name: 'Yeashfa Haque',
                        image: 'images/Panel_23_24/Secretary/Performance/Yeashfa_Haque.jpeg'
                    },
                    {
                        name: 'PR Secretary',
                        image: 'images/Panel_23_24/Secretary/PR/e335550a-5587-4fce-98ec-e8a348a349ed.jpg'
                    },
                    {
                        name: 'Jisa',
                        image: 'images/Panel_23_24/Secretary/PR/Jisa (PR).jpg'
                    },
                    {
                        name: 'Sadid',
                        image: 'images/Panel_23_24/Secretary/RD/Sadid ( Rd).jpg'
                    },
                    {
                        name: 'Zaarin',
                        image: 'images/Panel_23_24/Secretary/RD/Zaarin RD.jpg'
                    }
                    ]
                },
                '2022': {
                    panelMembers: [{
                        name: 'Merazul Dihan',
                        image: 'images/Panel_22_23/Panel/Merazul_Dihan.jpg',
                        panel: 'President',
                        facebook: "https://www.facebook.com/merazul.dihan.7"
                    },
                    {
                        name: 'Towsif Tazwar Zia',
                        image: 'images/Panel_22_23/Panel/Towsif_Tazwar_Zia.jpg',
                        panel: 'Vice President',
                        facebook: "https://www.facebook.com/towsif"
                    },
                    {
                        name: 'Tawhid Mollah Orpon',
                        image: 'images/Panel_22_23/Panel/Tawhid_Mollah_Orpon.jpg',
                        panel: 'General Secretary',
                        facebook: "https://www.facebook.com/tawhid.m.orpon"
                    },
                    {
                        name: 'Shakira Mustahid',
                        image: 'images/Panel_22_23/Panel/Shakira_Mustahid.jpg',
                        panel: 'Joint Secretary',
                        facebook: "https://www.facebook.com/groups/86555568937/user/100011850409007"
                    }
                    ],
                    sbMembers: [{
                        name: 'Nisa Hai',
                        image: 'images/Panel_22_23/Secreteries/Creative/Nisa_Hai.jpg'
                    },
                    {
                        name: 'Muhaiminul Shuvo',
                        image: 'images/Panel_22_23/Secreteries/Creative/Muhaiminul_Shuvo.jpg'
                    },
                    {
                        name: 'Mohammad Nashib',
                        image: 'images/Panel_22_23/Secreteries/Events/Mohammad_Nashib .jpg'
                    },
                    {
                        name: 'Events Secretary',
                        image: 'images/Panel_22_23/Secreteries/Events/489028088_4153227901564307_6545631968149006210_n.jpg'
                    },
                    {
                        name: 'Musharrat Quazi',
                        image: 'images/Panel_22_23/Secreteries/Hr/Musharrat_Quazi.jpg'
                    },
                    {
                        name: 'Tahiatun Nazi',
                        image: 'images/Panel_22_23/Secreteries/Performance/Tahiatun_Nazi.jpg'
                    },
                    {
                        name: 'Performance Secretary',
                        image: 'images/Panel_22_23/Secreteries/Performance/486450617_1919606155485104_6809497294325923308_n.jpg'
                    },
                    {
                        name: 'Upama Adhikary',
                        image: 'images/Panel_22_23/Secreteries/pr/Upama_Adhikary.jpg'
                    },
                    {
                        name: 'Minoor Karim',
                        image: 'images/Panel_22_23/Secreteries/pr/Minoor_karim.jpg'
                    },
                    {
                        name: 'Swagata Oishi',
                        image: 'images/Panel_22_23/Secreteries/pr/Swagata_oishi.jpg'
                    },
                    {
                        name: 'Naziat Podmo',
                        image: 'images/Panel_22_23/Secreteries/Hr/Naziat_Podmo.jpg'
                    },
                    {
                        name: 'Rifah Tasnia',
                        image: 'images/Panel_22_23/Secreteries/Performance/Rifah_Tasnia.jpg'
                    },
                    {
                        name: 'Kabery Moniza Riddhi',
                        image: 'images/Panel_22_23/Secreteries/Performance/Kabery_Moniza_Riddhi .jpg'
                    },
                    {
                        name: 'Tamima Hossain Diba',
                        image: 'images/Panel_22_23/Secreteries/Performance/Tamima_Hossain_Diba.jpg'
                    },
                    {
                        name: 'Saadman Alvi',
                        image: 'images/Panel_22_23/Secreteries/Miap/Saadman_Alvi.jpg'
                    },
                    {
                        name: 'Mehjabeen Meem',
                        image: 'images/Panel_22_23/Secreteries/Miap/Mehjabeen_Meem.jpg'
                    },
                    {
                        name: 'GM Apurbo',
                        image: 'images/Panel_22_23/Secreteries/Rd/GM_Apurbo.jpg'
                    }
                    ]
                },
                '2021': {
                    panelMembers: [{
                        name: 'Jabir Al Ahad',
                        image: 'images/Panel_21_22/Panel/Jabir_Al_Ahad.jpg',
                        panel: 'President',
                        facebook: "https://www.facebook.com/jabir.rafi.33"
                    }, {
                        name: 'ABD Quaiyum',
                        image: 'images/Panel_21_22/Panel/ABD_Quaiyum.jpg',
                        panel: 'Vice President',
                        facebook: "https://www.facebook.com/abdul.quaiyum.732"
                    }, {
                        name: 'Fahim Anjum Efty',
                        image: 'images/Panel_21_22/Panel/Fahim_Anjum_Efty.jpg',
                        panel: 'General Secretary',
                        facebook: "https://www.facebook.com/fahim.efty.5"
                    }],
                    sbMembers: [{
                        name: 'Jawad Zarif',
                        image: 'images/Panel_21_22/Secreteries/admin/476856981_4123432817892541_1624224823209103418_n.jpg'
                    },
                    {
                        name: 'Muhib Hasan',
                        image: 'images/Panel_21_22/Secreteries/admin/480205108_1673069036614324_5079654607934815247_n.jpg'
                    },
                    {
                        name: 'Cameron Phillips (Creative)',
                        image: 'images/Panel_21_22/Secreteries/creative/471658768_1664068401128077_7406622409763109517_n.jpg'
                    },
                    {
                        name: 'Derek Reese (Creative)',
                        image: 'images/Panel_21_22/Secreteries/creative/69861786_510323629778636_5845785793657831424_n.jpg'
                    },
                    {
                        name: 'Jesse Flores (Events)',
                        image: 'images/Panel_21_22/Secreteries/events/454317040_10225148019343878_7594567972609169834_n.jpg'
                    },
                    {
                        name: 'Riley Dawson (Events)',
                        image: 'images/Panel_21_22/Secreteries/events/504868796_4146322538979491_918509307535544619_n.jpg'
                    },
                    {
                        name: 'Allison Young (Finance)',
                        image: 'images/Panel_21_22/Secreteries/fin/500367999_3581840292112516_2140517489434280250_n.jpg'
                    },
                    {
                        name: 'Charley Dixon (Finance)',
                        image: 'images/Panel_21_22/Secreteries/fin/Copy of 487208029_4090148864590979_892055582219564636_n.jpg'
                    },
                    {
                        name: 'Ellison (HR)',
                        image: 'images/Panel_21_22/Secreteries/hr/465844472_2640701509435187_7978154684435128783_n.jpg'
                    },
                    {
                        name: 'Weaver (HR)',
                        image: 'images/Panel_21_22/Secreteries/hr/515437567_1952489408489387_1315940866281760937_n.jpg'
                    },
                    {
                        name: 'Cromartie (Performance)',
                        image: 'images/Panel_21_22/Secreteries/perform/471993795_1815017182236611_7554013114998935137_n.jpg'
                    },
                    {
                        name: 'Bloodhound (Performance)',
                        image: 'images/Panel_21_22/Secreteries/perform/482211523_1686167981971096_5335183090621960727_n.jpg'
                    },
                    {
                        name: 'Shirley (Performance)',
                        image: 'images/Panel_21_22/Secreteries/perform/489790186_122124644834718672_952791625051701298_n.jpg'
                    },
                    {
                        name: 'Vick (PR)',
                        image: 'images/Panel_21_22/Secreteries/pr/469337595_3924168784509776_3814304461961048715_n.jpg'
                    },
                    {
                        name: 'Fischer (PR)',
                        image: 'images/Panel_21_22/Secreteries/pr/483106530_4026994964252647_8895841218075580060_n.jpg'
                    },
                    {
                        name: 'Tarissa (RD)',
                        image: 'images/Panel_21_22/Secreteries/rd/128351021_228325362052613_4561984735401676055_n.jpg'
                    },
                    {
                        name: 'Sarkissian (RD)',
                        image: 'images/Panel_21_22/Secreteries/rd/470235244_2667326150106056_3872053460634156449_n.jpg'
                    },
                    {
                        name: 'Goode (RD)',
                        image: 'images/Panel_21_22/Secreteries/rd/475195409_1168331491303047_2401447768072024913_n.jpg'
                    }
                    ]
                },
                '2020': {
                    panelMembers: [{
                        name: 'Toriqul Islam Dipro',
                        image: 'images/Panel_20_21/Panel/Toriqul_Islam_Dipro.jpg',
                        panel: 'President',
                        facebook: "https://www.facebook.com/toriqul.islam.dipro"
                    }, {
                        name: 'Amal Chowdhury',
                        image: 'images/Panel_20_21/Panel/Amal_Chowdhury.jpg',
                        panel: 'Vice President',
                        facebook: "https://www.facebook.com/amal.chowdhury.79"
                    },
                    {
                        name: 'Injamul Islam Fahim',
                        image: 'images/Panel_20_21/Panel/Injamul_Islam_Fahim.jpg',
                        panel: 'General Secretary',
                        facebook: "https://www.facebook.com/injamul.fahim"
                    }

                    ],
                    sbMembers: [{
                        name: 'Emily Davis (Admin)',
                        image: 'images/Panel_20_21/ Secreteries/admin/476231235_1669345223653372_3407867370287020034_n.jpg'
                    },
                    {
                        name: 'James Brown (Creative)',
                        image: 'images/Panel_20_21/ Secreteries/creative/123926406_3095940650510775_7926925358628684138_n.jpg'
                    },
                    {
                        name: 'Mia Taylor (Creative)',
                        image: 'images/Panel_20_21/ Secreteries/creative/170745495_1691963967642284_8994484908450075508_n.jpg'
                    },
                    {
                        name: 'Noah Wilson (Creative)',
                        image: 'images/Panel_20_21/ Secreteries/creative/471644167_1812589182479411_6622247673044813939_n.jpg'
                    },
                    {
                        name: 'Ava Clark (Creative)',
                        image: 'images/Panel_20_21/ Secreteries/creative/474080428_2696073080564696_2146795879080295010_n.jpg'
                    },
                    {
                        name: 'Mason Lewis (Event)',
                        image: 'images/Panel_20_21/ Secreteries/event/464713040_2792921144222449_472237782771168408_n.jpg'
                    },
                    {
                        name: 'Isabella King (Event)',
                        image: 'images/Panel_20_21/ Secreteries/event/487048639_3105546046276958_9148728155394135784_n.jpg'
                    },
                    {
                        name: 'Lucas Anderson (Fin)',
                        image: 'images/Panel_20_21/ Secreteries/fin/454267796_3980565755491577_2874358743371603141_n.jpg'
                    },
                    {
                        name: 'Sophia Johnson (Fin)',
                        image: 'images/Panel_20_21/ Secreteries/fin/478539252_1178973010238895_1597122680872953961_n.jpg'
                    },
                    {
                        name: 'Ethan Martinez (HR)',
                        image: 'images/Panel_20_21/ Secreteries/hr/471583557_1813851939019802_6471843107952171020_n.jpg'
                    },
                    {
                        name: 'Amelia Harris (HR)',
                        image: 'images/Panel_20_21/ Secreteries/hr/516591792_3616860595277152_2104663676730743326_n.jpg'
                    },
                    {
                        name: 'Jacob Thompson (Miap)',
                        image: 'images/Panel_20_21/ Secreteries/miap/122523984_3062594330512074_6233342128374299200_n.jpg'
                    },
                    {
                        name: 'Charlotte White (Miap)',
                        image: 'images/Panel_20_21/ Secreteries/miap/66852429_2590974680915041_3878385481519464448_n.jpg'
                    },
                    {
                        name: 'Aiden Hall (Performance)',
                        image: 'images/Panel_20_21/ Secreteries/performance/469825113_2665417436963594_4201553788795341419_n (1).jpg'
                    },
                    {
                        name: 'Scarlett Allen (Performance)',
                        image: 'images/Panel_20_21/ Secreteries/performance/470238528_8671941482933412_630060569786467991_n.jpg'
                    },
                    {
                        name: 'Oliver Walker (Performance)',
                        image: 'images/Panel_20_21/ Secreteries/performance/471089076_2674572022714802_9175108119770714368_n.jpg'
                    },
                    {
                        name: 'Aubrey Scott (Performance)',
                        image: 'images/Panel_20_21/ Secreteries/performance/481067682_1684472232140671_6947975985174216726_n.jpg'
                    },
                    {
                        name: 'Sebastian Wright (Performance)',
                        image: 'images/Panel_20_21/ Secreteries/performance/494080037_3561101540853058_349832023715608278_n.jpg'
                    },
                    {
                        name: 'Zoey Baker (PR)',
                        image: 'images/Panel_20_21/ Secreteries/pr/146167213_3320171584754346_6243508639537889689_n.jpg'
                    },
                    {
                        name: 'Logan Phillips (PR)',
                        image: 'images/Panel_20_21/ Secreteries/pr/472235864_1815019072236422_1600336458892749079_n.jpg'
                    },
                    {
                        name: 'Lily Campbell (RD)',
                        image: 'images/Panel_20_21/ Secreteries/rd/404806443_7232254810171158_6587854861112096768_n.jpg'
                    },
                    {
                        name: 'Ben Mitchell (RD)',
                        image: 'images/Panel_20_21/ Secreteries/rd/468920931_8708487025893905_8271555839924075526_n.jpg'
                    },
                    {
                        name: 'Amelia Perez (RD)',
                        image: 'images/Panel_20_21/ Secreteries/rd/471327187_2679088635596474_4983230428569764592_n.jpg'
                    },
                    {
                        name: 'Jackson Garcia (RD)',
                        image: 'images/Panel_20_21/ Secreteries/rd/475698755_3192983197535274_4204510840426245112_n.jpg'
                    }
                    ]
                },
                '2019': {
                    panelMembers: [{
                        name: 'Shoaib Kamal',
                        image: 'images/Panel_19_20/Panel/Shoaib_Kamal.jpg',
                        panel: 'President',
                        facebook: "https://www.facebook.com/shoaib.sculpture"
                    },
                    {
                        name: 'Samara Mehruz',
                        image: 'images/Panel_19_20/Panel/Samara_Mehruz.jpg',
                        panel: 'Vice President',
                        facebook: "https://www.facebook.com/meheruz.samara/"
                    },
                    {
                        name: 'Murtafa Mridha',
                        image: 'images/Panel_19_20/Panel/Murtafa_Mridha.jpg',
                        panel: 'General Secretary',
                        facebook: "https://www.facebook.com/murtafa.mridha"
                    }


                    ],
                    sbMembers: [{
                        name: 'Shahriar Nasif',
                        image: 'images/Panel_19_20/Secretaries/admin/Shahriar_Nasif.jpg'
                    },
                    {
                        name: 'Sadia Ishtiaque',
                        image: 'images/Panel_19_20/Secretaries/creative/Sadia_Ishtiaque.jpg'
                    },
                    {
                        name: 'Sayeda Lamia Tabassum',
                        image: 'images/Panel_19_20/Secretaries/creative/Sayeda_Lamia_Tabassum.jpg'
                    },
                    {
                        name: 'Iffat Binte Hakim',
                        image: 'images/Panel_19_20/Secretaries/creative/Iffat_Binte_Hakim.jpg'
                    },
                    {
                        name: 'Nafiz Noor',
                        image: 'images/Panel_19_20/Secretaries/event/Nafiz_Noor.jpg'
                    },
                    {
                        name: 'Ishraq Avi',
                        image: 'images/Panel_19_20/Secretaries/fin/Ishraq_Avi.jpg'
                    },
                    {
                        name: 'Zuairya Ashger Khan Nuha',
                        image: 'images/Panel_19_20/Secretaries/hr/Zuairya_Ashger_Khan_Nuha.jpg'
                    },
                    {
                        name: 'Sadman Sakib Ayon',
                        image: 'images/Panel_19_20/Secretaries/miap/Sadman_Sakib_Ayon.jpg'
                    },
                    {
                        name: 'Aninda Nahiyan',
                        image: 'images/Panel_19_20/Secretaries/perfromance/Aninda_Nahiyan.jpg'
                    },
                    {
                        name: 'Munirul Azam Zayed',
                        image: 'images/Panel_19_20/Secretaries/perfromance/Munirul_Azam_Zayed.jpg'
                    },
                    {
                        name: 'Saib Sizan',
                        image: 'images/Panel_19_20/Secretaries/perfromance/Saib_Sizan.jpg'
                    },
                    {
                        name: 'George Gourab',
                        image: 'images/Panel_19_20/Secretaries/perfromance/George_Gourab.jpg'
                    },
                    {
                        name: 'Sumit Dutta',
                        image: 'images/Panel_19_20/Secretaries/perfromance/Sumit_Dutta.jpg'
                    },
                    {
                        name: 'Deepita Chakrabortty',
                        image: 'images/Panel_19_20/Secretaries/perfromance/Deepita_Chakrabortty.jpg'
                    },
                    {
                        name: 'Pritam Chakraborty',
                        image: 'images/Panel_19_20/Secretaries/pr/Pritam_Chakraborty.jpg'
                    },
                    {
                        name: 'Modhumonty Das',
                        image: 'images/Panel_19_20/Secretaries/pr/Modhumonty_Das.jpg'
                    },
                    {
                        name: 'Afeed Nur',
                        image: 'images/Panel_19_20/Secretaries/rd/Afeed_Nur.jpg'
                    },
                    {
                        name: 'Tanzim Ahmed Ornob',
                        image: 'images/Panel_19_20/Secretaries/rd/Tanzim_Ahmed_Ornob.jpg'
                    },
                    {
                        name: 'Kanika Saha',
                        image: 'images/Panel_19_20/Secretaries/rd/Kanika_Saha.jpg'
                    },
                    {
                        name: 'Anika Anjum Sadia',
                        image: 'images/Panel_19_20/Secretaries/rd/Anika_Anjum_Sadia.jpg'
                    }
                    ]
                },
                '2026': {
                    panelMembers: [
                        {
                            name: 'Avibadhan Das',
                            image: 'images/Panel_26_27/Panel/Avibadhan_Das1.jpg',
                            panel: 'President',
                            facebook: 'http://www.facebook.com/'
                        },
                        {
                            name: 'Rudian Ahmed',
                            image: 'images/Panel_26_27/Panel/rudian1.jpg',
                            panel: 'Vice President',
                            facebook: 'http://www.facebook.com/'
                        },
                        {
                            name: 'Rubaba Nusheen',
                            image: 'images/Panel_26_27/Panel/Rubu.jpg',
                            panel: 'General Secretary',
                            facebook: 'http://www.facebook.com/'
                        },
                        {
                            name: 'Khaled Bin Taher',
                            image: 'images/Panel_26_27/Panel/Khaled_Bin_Taher1.jpg',
                            panel: 'Treasurer',
                            facebook: 'http://www.facebook.com/'
                        },
                        {
                            name: 'Sadman Safin Oasif',
                            image: 'images/Panel_26_27/Panel/SadmanSafinOasif.jpg',
                            panel: 'Joint Secretary',
                            facebook: 'http://www.facebook.com/'
                        }
                    ],
                    sbMembers: <?php echo json_encode($panel26Secretaries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                }
            };

            // Display labels for each dropdown option (used to update panelYearTitle)
            const panelTitleLabels = {
                'panel_26_27': 'Current Panel (2026–2027)',
                'current':     '2025–2026',
                'panel_23_24': '2023–2025',
                'panel_22_23': '2022–2023',
                'panel_21_22': '2021–2022',
                'panel_20_21': '2020–2021',
                'panel_19_20': '2019–2020',
            };

            // Handle dropdown change event
            dropdown.addEventListener('change', function () {
                const selectedYearKey = panelDataKeys[this.value];
                console.log('Selected year key:', selectedYearKey);
                if (!selectedYearKey) {
                    console.error('No data found for year:', this.value);
                    return;
                }

                // Update the panel year subtitle heading
                const panelYearTitle = document.getElementById('panelYearTitle');
                if (panelYearTitle) {
                    panelYearTitle.textContent = panelTitleLabels[this.value] || this.options[this.selectedIndex].text;
                }

                const data = panelData[selectedYearKey];
                updateMembers(data, selectedYearKey);
            });

            console.log('JavaScript loaded correctly!');

            async function updateMembers(data, yearKey = null) {
                const panelContainer = document.getElementById('panelMembersContainer');

                if (!panelContainer) {
                    console.error('Panel container not found!');
                    return;
                }

                console.log('Panel container found:', panelContainer);

                // Find the existing grid within the panel container
                const existingGrid = panelContainer.querySelector('.row.justify-content-center.panel-members-grid');
                console.log('Existing grid:', existingGrid);

                if (!existingGrid) {
                    console.error('Panel grid not found! Container HTML:', panelContainer.innerHTML);
                    return;
                }

                // Clear existing member cards
                existingGrid.innerHTML = '';

                // Add new panel members with full structure
                data.panelMembers.forEach((member, index) => {
                    console.log(member, "Panel Members JSON")
                    const memberDiv = document.createElement('div');

                    // Column classes — row has justify-content-center so
                    // any partial last row is automatically centred.
                    const memberCount = data.panelMembers.length;
                    if (memberCount === 5) {
                        // Desktop  (≥992 px): 3 + 2 centred
                        // Tablet   (768–991): 2 + 2 + 1 centred
                        // Mobile   (<768 px): 1 per row
                        memberDiv.className = 'col-lg-4 col-md-6 col-12 pmc-col';
                    } else if (memberCount === 4) {
                        // 2 + 2
                        memberDiv.className = 'col-lg-6 col-12 pmc-col';
                    } else if (memberCount === 3) {
                        // 2 on top, 1 centred below (mx-auto centres the lone last item)
                        memberDiv.className = index < 2
                            ? 'col-lg-6 col-12 pmc-col'
                            : 'col-lg-6 col-12 pmc-col mx-auto';
                    } else {
                        memberDiv.className = 'col-lg-5 col-12 pmc-col';
                    }

                    memberDiv.innerHTML = `
<div class="panel-member-card">
  <div class="pmc-image-wrap">
    <img src="${encodeURI(imgUrl(member.image))}" alt="${member.name}"
         onerror="this.src='images/placeholder.svg';" />
    <div class="pmc-overlay">
      <a href="${member.facebook}" target="_blank" aria-label="Facebook – ${member.name}">
        <ion-icon name="logo-facebook"></ion-icon>
      </a>
    </div>
  </div>
  <div class="pmc-body">
    <p class="pmc-name">${member.name}</p>
    <span class="pmc-role">${member.panel}</span>
  </div>
</div>
`;

                    existingGrid.appendChild(memberDiv);
                });

                // Update SB section title based on year
                const sbTitle = document.getElementById('sbSectionTitle');
                if (sbTitle) {
                    if (yearKey === '2020' || yearKey === '2019') {
                        sbTitle.textContent = `Meet Our Secretaries`;
                        console.log(`Updated SB title for years ${yearKey}:`, sbTitle.textContent);
                    } else {
                        sbTitle.textContent = 'Meet Our Secretaries';
                    }
                }

                // Update SB members - try JSON data first, fallback to hardcoded data
                let sbMembersData = data.sbMembers; // Default to hardcoded data
                console.log('Default hardcoded SB members count:', sbMembersData?.length || 0);

                if (yearKey) {
                    const jsonSBMembers = await loadSBMembersForYear(yearKey);
                    if (jsonSBMembers) {
                        console.log('Using JSON SB members:', jsonSBMembers.length, 'members');
                        sbMembersData = jsonSBMembers;
                    } else {
                        console.log('JSON SB members not found, using hardcoded data');
                    }
                } else {
                    console.log('No yearKey provided, using default hardcoded data');
                }

                console.log('Final SB members data:', sbMembersData?.length || 0, 'members');

                const swiperWrapper = document.getElementById('sbSwiperWrapper');
                console.log('SB Swiper wrapper found:', swiperWrapper !== null);
                if (swiperWrapper && sbMembersData) {
                    // Clear existing slides
                    swiperWrapper.innerHTML = '';

                    // Add new SB member slides
                    sbMembersData.forEach(member => {
                        const slide = document.createElement('div');
                        slide.className = 'swiper-slide';
                        const facebookLink = member.facebook || '#';
                        const memberName = member.name || 'Unknown';
                        const memberPosition = member.position || 'Secretary';

                        slide.innerHTML = `
<img src="${imgUrl(member.image)}" alt="${memberName}" onerror="this.src='images/placeholder.png'; console.error('Failed to load SB image:', '${member.image}');" />
<div class="overlay">
    <a href="${facebookLink}" target="_blank">
        <ion-icon name="logo-facebook" style="color: #1877f2"></ion-icon>
    </a>
</div>
<div class="member-name">
    <span class="name">${memberName}</span>
    <span class="position">${memberPosition}</span>
</div>
`;
                        swiperWrapper.appendChild(slide);
                    });

                    // Reinitialize Swiper if it exists
                    if (window.Swiper && window.sbSwiper) {
                        // Destroy existing swiper
                        window.sbSwiper.destroy(true, true);

                        // Reinitialize with new slides
                        const slideCount = sbMembersData.length;
                        console.log('Reinitializing SB Swiper with', slideCount, 'slides for year:', yearKey);

                        window.sbSwiper = new Swiper('.sb-swiper', {
                            effect: 'coverflow',
                            grabCursor: true,
                            centeredSlides: true,
                            loop: slideCount > 5,
                            loopAdditionalSlides: 2,
                            initialSlide: 0,
                            coverflowEffect: {
                                rotate: 30,
                                stretch: 0,
                                depth: 120,
                                modifier: 1,
                                slideShadows: true,
                            },
                            pagination: {
                                el: '.swiper-pagination',
                                clickable: true,
                            },
                            autoplay: {
                                delay: 3000,
                                disableOnInteraction: false,
                            },
                            breakpoints: {
                                320: {
                                    slidesPerView: 1,
                                    spaceBetween: 10
                                },
                                480: {
                                    slidesPerView: 2,
                                    spaceBetween: 20
                                },
                                768: {
                                    slidesPerView: 3,
                                    spaceBetween: 30
                                },
                                1024: {
                                    slidesPerView: 5,
                                    spaceBetween: 56
                                },
                            },
                            on: {
                                init: function () {
                                    console.log('SB Swiper reinitialized with', this.slides.length, 'slides');
                                }
                            }
                        });
                    }
                }

                console.log('Updated members for selected year:', data);
            }

            // Enhanced updateMembers function to work with both hardcoded and JSON data
            async function loadSBMembersForYear(yearKey) {
                try {
                    console.log('Loading SB members for yearKey:', yearKey);
                    // Add cache-busting parameter to force fresh data load
                    const cacheBuster = Date.now();
                    const response = await fetch(`../backend/Api/members.json?cb=${cacheBuster}`);
                    const jsonData = await response.json();
                    console.log('JSON data loaded successfully');

                    // Map yearKey to actual year number
                    const yearMapping = {
                        '2024': 2025, // Current year data
                        '2026': 2027,
                        '2023': 2024,
                        '2022': 2023,
                        '2021': 2022,
                        '2020': 2021,
                        '2019': 2020
                    };

                    const targetYear = yearMapping[yearKey];
                    console.log('Target year mapping:', yearKey, '->', targetYear);
                    const yearData = jsonData.years.find(y => y.year === targetYear);
                    console.log('Year data found:', yearData ? 'YES' : 'NO');

                    if (yearData && yearData.secretaries !== undefined) {
                        console.log(`Found ${yearData.secretaries.length} secretaries for ${targetYear}`);
                        if (yearData.secretaries.length === 0) {
                            console.warn('Secretaries array is empty for year', targetYear);
                        }
                        return yearData.secretaries;
                    } else {
                        console.warn(`No JSON data found for year ${targetYear}, using hardcoded data`);
                        return null;
                    }
                } catch (error) {
                    console.error('Error loading JSON data:', error);
                    return null;
                }
            }

            // Initialize with current panel data (2024-2025)
            const initializeSBMembers = async () => {
                const currentData = panelData['2026'];
                if (currentData) {
                    await updateMembers(currentData, '2026');
                }
            };

            // Call the initialization function
            initializeSBMembers();
        });

        // Date Input Placeholder Handler
        function hideDatePlaceholder(input) {
            const placeholder = input.nextElementSibling;
            if (placeholder && placeholder.classList.contains('date-placeholder')) {
                if (input.value) {
                    placeholder.style.opacity = '0';
                } else {
                    placeholder.style.opacity = '1';
                }
            }
        }

        // Initialize date placeholder visibility
        document.addEventListener('DOMContentLoaded', function () {
            const dateInput = document.getElementById('signup-dob');
            if (dateInput) {
                // Check on load
                hideDatePlaceholder(dateInput);

                // Check on change
                dateInput.addEventListener('change', function () {
                    hideDatePlaceholder(this);
                });

                // Check on input
                dateInput.addEventListener('input', function () {
                    hideDatePlaceholder(this);
                });
            }
        });

        // Hero banner video
        document.addEventListener("DOMContentLoaded", function () {
            const video      = document.getElementById("heroVideo");
            const unmuteBtn  = document.getElementById("unmuteBtn");
            const heroSection = document.getElementById("section_1");

            if (!video || !unmuteBtn || !heroSection) return;

            // Tracks whether the user has explicitly enabled sound
            let userSoundEnabled = false;

            // Start muted — required for autoplay to work in all browsers
            video.muted  = true;
            video.volume = 1;
            video.play().catch(function () { /* autoplay fully blocked */ });

            // User clicks Enable Sound
            unmuteBtn.addEventListener("click", function () {
                userSoundEnabled = true;
                video.muted  = false;
                video.volume = 1;
                video.play();

                unmuteBtn.innerHTML = "🔈 Sound Enabled";
                setTimeout(function () {
                    unmuteBtn.style.display = "none";
                }, 1500);
            });

            // IntersectionObserver — watches hero section visibility
            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        // Hero is visible — restore sound only if user enabled it
                        if (userSoundEnabled) {
                            video.muted = false;
                        }
                    } else {
                        // Hero left viewport — always mute
                        video.muted = true;
                    }
                });
            }, {
                threshold: 0.2  // trigger when 20% of hero is visible/hidden
            });

            observer.observe(heroSection);
        });

        // QR Code Hash Navigation Handler
        // This ensures that when someone scans a QR code with a hash (#section_5),
        // the page automatically scrolls to that section
        document.addEventListener('DOMContentLoaded', function () {
            function scrollToHash() {
                if (window.location.hash) {
                    const hash = window.location.hash;
                    const targetElement = document.querySelector(hash);

                    if (targetElement) {
                        // Wait for page to fully load
                        setTimeout(function () {
                            const offsetTop = targetElement.offsetTop - 83; // Account for navbar height
                            window.scrollTo({
                                top: offsetTop,
                                behavior: 'smooth'
                            });
                        }, 500); // Small delay to ensure everything is loaded
                    }
                }
            }

            // Handle initial hash on page load
            scrollToHash();

            // Handle hash changes (if user navigates while on page)
            window.addEventListener('hashchange', function () {
                scrollToHash();
            });
        });
    </script>
</body>

</html>
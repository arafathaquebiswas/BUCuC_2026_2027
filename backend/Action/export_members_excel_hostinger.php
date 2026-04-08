<?php
session_start();
if (!isset($_SESSION["admin"])) {
    header("Location: ../../frontend/admin-login.php");
    exit();
}

// Check PHP version compatibility
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    die(json_encode([
        'success' => false,
        'message' => 'PHP 8.1 or higher is required. Current version: ' . PHP_VERSION
    ]));
}

// Check required extensions
$required_extensions = ['dom', 'fileinfo', 'gd', 'iconv', 'libxml', 'mbstring', 'simplexml', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    die(json_encode([
        'success' => false,
        'message' => 'Missing required PHP extensions: ' . implode(', ', $missing_extensions)
    ]));
}

// Check if vendor directory exists
if (!file_exists('../vendor/autoload.php')) {
    die(json_encode([
        'success' => false,
        'message' => 'Composer dependencies not found. Please run "composer install" on your server.'
    ]));
}

require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    require_once '../Database/db.php';

    $database = new Database();
    $pdo = $database->createConnection();

    // Get all members data
    $stmt = $pdo->query("SELECT * FROM members ORDER BY created_at DESC");
    $members = $stmt->fetchAll();

    if (empty($members)) {
        throw new Exception("No members found in the database.");
    }

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('BRAC University Cultural Club')
        ->setLastModifiedBy('Admin')
        ->setTitle('Member Applications Export')
        ->setSubject('Member Applications Data')
        ->setDescription('Export of all member applications from the database')
        ->setKeywords('members, applications, brac, cultural club')
        ->setCategory('Member Data');

    // Set headers
    $headers = [
        'A1' => 'Full Name',
        'B1' => 'University ID',
        'C1' => 'Email',
        'D1' => 'G-Suite Email',
        'E1' => 'Department',
        'F1' => 'Phone',
        'G1' => 'Semester',
        'H1' => 'Gender',
        'I1' => 'Facebook URL',
        'J1' => 'First Priority',
        'K1' => 'Second Priority',
        'L1' => 'Membership Status',
        'M1' => 'Application Date',
        'N1' => 'Last Updated'
    ];

    // Set header values
    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }

    // Style the header row
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '2E86AB']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];

    $sheet->getStyle('A1:N1')->applyFromArray($headerStyle);

    // Set row height for header
    $sheet->getRowDimension(1)->setRowHeight(25);

    // Fill data rows
    $row = 2;
    foreach ($members as $member) {
        $sheet->setCellValue('A' . $row, $member['full_name']);
        $sheet->setCellValue('B' . $row, $member['university_id']);
        $sheet->setCellValue('C' . $row, $member['email']);
        $sheet->setCellValue('D' . $row, $member['gsuite_email']);
        $sheet->setCellValue('E' . $row, $member['department']);
        $sheet->setCellValue('F' . $row, $member['phone']);
        $sheet->setCellValue('G' . $row, $member['semester']);
        $sheet->setCellValue('H' . $row, $member['gender']);
        $sheet->setCellValue('I' . $row, $member['facebook_url']);
        $sheet->setCellValue('J' . $row, $member['firstPriority']);
        $sheet->setCellValue('K' . $row, $member['secondPriority']);

        // Format membership status
        $status = $member['membership_status'];
        $statusText = ($status == 'New_member') ? 'Pending' : 'Accepted';
        $sheet->setCellValue('L' . $row, $statusText);

        // Format dates
        $createdDate = date('Y-m-d H:i:s', strtotime($member['created_at']));
        $updatedDate = isset($member['updated_at']) ? date('Y-m-d H:i:s', strtotime($member['updated_at'])) : $createdDate;

        $sheet->setCellValue('M' . $row, $createdDate);
        $sheet->setCellValue('N' . $row, $updatedDate);

        $row++;
    }

    // Style data rows
    $dataStyle = [
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ]
    ];

    $lastRow = $row - 1;
    $sheet->getStyle('A2:N' . $lastRow)->applyFromArray($dataStyle);

    // Auto-size columns (with limits for Hostinger)
    foreach (range('A', 'N') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
        // Set maximum width to prevent memory issues
        $sheet->getColumnDimension($column)->setWidth(min(50, $sheet->getColumnDimension($column)->getWidth()));
    }

    // Add summary information
    $summaryRow = $lastRow + 3;
    $sheet->setCellValue('A' . $summaryRow, 'Export Summary:');
    $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true);

    $sheet->setCellValue('A' . ($summaryRow + 1), 'Total Applications: ' . count($members));
    $sheet->setCellValue('A' . ($summaryRow + 2), 'Export Date: ' . date('Y-m-d H:i:s'));
    $sheet->setCellValue('A' . ($summaryRow + 3), 'Exported By: ' . ($_SESSION['admin_name'] ?? 'Admin'));

    // Count pending vs accepted
    $pendingCount = count(array_filter($members, function ($member) {
        return $member['membership_status'] == 'New_member';
    }));
    $acceptedCount = count($members) - $pendingCount;

    $sheet->setCellValue('A' . ($summaryRow + 4), 'Pending Applications: ' . $pendingCount);
    $sheet->setCellValue('A' . ($summaryRow + 5), 'Accepted Applications: ' . $acceptedCount);

    // Set filename with timestamp
    $filename = 'Member_Applications_' . date('Y-m-d_H-i-s') . '.xlsx';

    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    // Create writer and save
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    // Log the export action
    error_log("Excel export completed: " . count($members) . " members exported by " . ($_SESSION['admin_name'] ?? 'Admin') . " at " . date('Y-m-d H:i:s'));
} catch (Exception $e) {
    // Log error
    error_log("Excel export error: " . $e->getMessage());

    // Clear any output
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Return error response
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to export data: ' . $e->getMessage()
    ]);
    exit();
}

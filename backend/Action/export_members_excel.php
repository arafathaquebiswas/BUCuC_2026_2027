<?php
session_start();
if (!isset($_SESSION["admin"])) {
    header("Location: ../../frontend/admin-login.php");
    exit();
}

require_once '../Database/db.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    $database = new Database();
    $pdo = $database->createConnection();

    // Check for status filter
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : null;

    // Determine which table to query based on status
    if ($statusFilter === 'Shortlisted') {
        $query = "SELECT * FROM shortlisted_members";
    } elseif ($statusFilter === 'Pending' || $statusFilter === 'New_member') {
        $query = "SELECT * FROM pending_applications";
    } else {
        // Default: export accepted members from members table
        $query = "SELECT * FROM members";
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $members = $stmt->fetchAll();

    if (empty($members)) {
        throw new Exception("No " . ($statusFilter ? $statusFilter . " " : "") . "members found in the database.");
    }

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('BRAC University Cultural Club')
        ->setLastModifiedBy('Admin')
        ->setTitle(($statusFilter ? $statusFilter . ' ' : '') . 'Member Applications Export')
        ->setSubject('Member Applications Data')
        ->setDescription('Export of ' . ($statusFilter ? $statusFilter . ' ' : '') . 'member applications from the database')
        ->setKeywords('members, applications, brac, cultural club')
        ->setCategory('Member Data');

    // ... (headers code same as before) ...

    // Set filename with timestamp
    $prefix = $statusFilter ? str_replace(' ', '_', $statusFilter) . '_Members_' : 'Member_Applications_';
    $filename = $prefix . date('Y-m-d_H-i-s') . '.xlsx';

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
        $status = $member['membership_status'] ?? ($statusFilter === 'Accepted' || !($statusFilter === 'Shortlisted' || $statusFilter === 'Pending' || $statusFilter === 'New_member') ? 'Accepted' : 'Unknown');
        $statusText = ($status == 'New_member') ? 'Pending' : (($status == 'Shortlisted') ? 'Shortlisted' : 'Accepted');
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

    // Auto-size columns
    foreach (range('A', 'N') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    // Add summary information
    $summaryRow = $lastRow + 3;
    $sheet->setCellValue('A' . $summaryRow, 'Export Summary:');
    $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true);

    $sheet->setCellValue('A' . ($summaryRow + 1), 'Total Applications: ' . count($members));
    $sheet->setCellValue('A' . ($summaryRow + 2), 'Export Date: ' . date('Y-m-d H:i:s'));
    $sheet->setCellValue('A' . ($summaryRow + 3), 'Exported By: ' . ($_SESSION['admin_name'] ?? 'Admin'));

    // Count pending vs accepted vs shortlisted
    $pendingCount = 0;
    $shortlistedCount = 0;
    $acceptedCount = 0;

    if ($statusFilter === 'Shortlisted') {
        $shortlistedCount = count($members);
    } elseif ($statusFilter === 'Pending' || $statusFilter === 'New_member') {
        $pendingCount = count($members);
    } elseif ($statusFilter === 'Accepted') {
        $acceptedCount = count($members);
    } else {
        // mixed or default
        foreach ($members as $m) {
            $s = $m['membership_status'] ?? 'Accepted';
            if ($s === 'New_member')
                $pendingCount++;
            elseif ($s === 'Shortlisted')
                $shortlistedCount++;
            else
                $acceptedCount++;
        }
    }

    $sheet->setCellValue('A' . ($summaryRow + 4), 'Pending Applications: ' . $pendingCount);
    $sheet->setCellValue('A' . ($summaryRow + 5), 'Shortlisted Applications: ' . $shortlistedCount);
    $sheet->setCellValue('A' . ($summaryRow + 6), 'Accepted Members: ' . $acceptedCount);

    // Set filename with timestamp
    // Note: $filename is already set above

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // Create writer and save
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    // Log the export action
    error_log("Excel export completed: " . count($members) . " members exported by " . ($_SESSION['admin_name'] ?? 'Admin') . " at " . date('Y-m-d H:i:s'));
} catch (Exception $e) {
    // Log error
    error_log("Excel export error: " . $e->getMessage());

    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to export data: ' . $e->getMessage()
    ]);
    exit();
}

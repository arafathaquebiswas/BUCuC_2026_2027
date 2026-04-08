<?php

/**
 * Google Sheets Integration
 * This file handles sending accepted application data to Google Sheets
 */

// Try to include configuration from different possible paths
if (file_exists('../config/google_sheets_config.php')) {
    require_once '../config/google_sheets_config.php';
} elseif (file_exists('config/google_sheets_config.php')) {
    require_once 'config/google_sheets_config.php';
} else {
    // Fallback constants if config file is not found
    define('GOOGLE_SHEETS_WEBAPP_URL', 'https://script.google.com/macros/s/AKfycbyktCtz9RUsKrpwZHmrgZcpEN_kDbS9CRwCfW5-7pXjO0eCE9RsLGvYbSNQKlJBEI98/exec');
    define('GOOGLE_SHEETS_TIMEOUT', 30);
    define('GOOGLE_SHEETS_LOGGING', true);
}

/**
 * Send member data to Google Sheets
 * @param array $member - Member data from database
 * @return array - Result with success status and message
 */
function sendToGoogleSheets($member) {
    // Use configured Web App URL
    $webAppUrl = GOOGLE_SHEETS_WEBAPP_URL;
    
    // Prepare data according to Google Sheet columns
    // Required columns: "Name", "ID", "G-Suite", "Position", "Facebook", "Phone", "FirstP", "SecondP"
    $sheetData = array(
        'Name' => $member['full_name'],
        'ID' => $member['university_id'],
        'G-Suite' => $member['gsuite_email'] ?: $member['email'], // Use G-Suite email if available, otherwise regular email
        'Position' => $member['department'], // Using department as position for now
        'Facebook' => $member['facebook_url'] ?: '', // Facebook URL
        'Phone' => $member['phone'],
        'FirstP' => $member['firstPriority'],
        'SecondP' => $member['secondPriority']
    );
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt_array($ch, array(
        CURLOPT_URL => $webAppUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($sheetData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // For development, in production you should verify SSL
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => GOOGLE_SHEETS_TIMEOUT,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'User-Agent: BUCUC-Application-System/1.0'
        )
    ));
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    // Handle the response
    if ($error) {
        return array(
            'success' => false,
            'message' => 'Failed to connect to Google Sheets: ' . $error
        );
    }
    
    if ($httpCode !== 200) {
        return array(
            'success' => false,
            'message' => 'Google Sheets API returned HTTP code: ' . $httpCode . '. Response: ' . $response
        );
    }
    
    // Try to decode JSON response (if your Google Apps Script returns JSON)
    $responseData = json_decode($response, true);
    
    if ($responseData && isset($responseData['success'])) {
        if ($responseData['success']) {
            return array(
                'success' => true,
                'message' => 'Successfully added to Google Sheets'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Google Sheets error: ' . ($responseData['message'] ?? 'Unknown error')
            );
        }
    } else {
        // If the response is not JSON, treat it as success if we get a 200 status
        // Some Google Apps Script configurations return plain text
        return array(
            'success' => true,
            'message' => 'Successfully sent to Google Sheets (Response: ' . substr($response, 0, 100) . ')'
        );
    }
}

/**
 * Log Google Sheets operations (for debugging)
 * @param string $action - The action being performed
 * @param array $data - Data being sent
 * @param array $result - Result from the operation
 */
function logGoogleSheetsOperation($action, $data, $result) {
    // Only log if logging is enabled
    if (!GOOGLE_SHEETS_LOGGING) {
        return;
    }
    
    $logEntry = array(
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'data' => $data,
        'result' => $result
    );
    
    // Determine the correct path for the log file
    if (file_exists('../logs/')) {
        $logFile = '../logs/google_sheets.log';
    } else {
        $logFile = 'logs/google_sheets.log';
    }
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Write to log file
    file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

?>

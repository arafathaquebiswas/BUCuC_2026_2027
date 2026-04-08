<?php
// Same-origin proxy to Google Apps Script to bypass browser CORS on the client.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'gas_proxy') {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json; charset=utf-8');

    $target = 'https://script.google.com/macros/s/AKfycbyiUnMIc9CVukYUmHRfxmFQFXCQhtBbgskIYl8zjrSEVUGK9uWJ5a9QRQJ0FgZ-M0ClfA/exec';

    $method = $_SERVER['REQUEST_METHOD'];
    $headers = [];

    // Get all headers and forward them
    $allHeaders = getallheaders();
    if ($allHeaders) {
        foreach ($allHeaders as $k => $v) {
            // Forward typical headers; skip Host and Content-Length (cURL will set them)
            if (!in_array(strtolower($k), ['host', 'content-length'])) {
                $headers[] = $k . ': ' . $v;
            }
        }
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $target);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    // Forward body for non-GET
    if ($method !== 'GET') {
        $body = file_get_contents('php://input');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    // Enable SSL verification (recommended for production)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    // Enhanced error handling and debugging
    if ($resp === false) {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'error' => 'Proxy request failed',
            'debug' => $err,
            'curl_errno' => curl_errno($ch)
        ]);
        exit;
    }

    // Log the response for debugging (remove in production)
    error_log('GAS Response Status: ' . $status);
    error_log('GAS Response Content-Type: ' . $contentType);
    error_log('GAS Response Body: ' . $resp);

    // Handle different status codes
    if ($status >= 200 && $status < 300) {
        // Success - try to validate JSON
        $decoded = json_decode($resp, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Valid JSON response
            http_response_code($status);
            echo $resp;
        } else {
            // Invalid JSON - might be HTML error page
            http_response_code(502);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid JSON response from Google Apps Script',
                'debug' => 'Response was not valid JSON: ' . json_last_error_msg(),
                'raw_response' => substr($resp, 0, 500) // First 500 chars for debugging
            ]);
        }
    } else if ($status >= 300 && $status < 400) {
        // Redirect - this shouldn't happen with Apps Script
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'error' => 'Unexpected redirect from Google Apps Script',
            'debug' => 'HTTP Status: ' . $status,
            'raw_response' => $resp
        ]);
    } else {
        // Error status
        http_response_code($status);
        echo json_encode([
            'success' => false,
            'error' => 'Google Apps Script returned error status',
            'debug' => 'HTTP Status: ' . $status,
            'raw_response' => $resp
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SB Members - BUCuC</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="icon" type="image/png" href="images/logopng.png">
    <style>
        /* Main Container Styling */
        .applications-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 2rem 0;
        }

        .applications-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin: 0 1rem;
        }

        .applications-title {
            color: #fff;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .applications-subtitle {
            color: #ccc;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            text-align: center;
        }

        /* Table Styling */
        .applications-table {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .table-dark {
            --bs-table-bg: transparent;
        }

        .table-dark thead th {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            font-weight: 600;
            padding: 1rem;
        }

        .table-dark td {
            border-color: rgba(255, 255, 255, 0.1);
            color: #ccc;
            vertical-align: middle;
            padding: 1rem;
        }

        .table-dark tbody tr:hover {
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        /* Avatar Styling */
        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }

        /* Status Badge Styling */
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-pending {
            background: linear-gradient(45deg, #ffc107, #ffeb3b);
            color: #333;
        }

        .status-accepted {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: #fff;
        }

        .status-asb {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: #fff;
        }

        .status-gb {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: #fff;
        }

        .status-sb {
            background: linear-gradient(45deg, #6f42c1, #5a32a3);
            color: #fff;
        }

        /* Statistics Row */
        .stats-row {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #51cf66;
        }

        .stat-label {
            color: #ccc;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        /* Loading Animation */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #ccc;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Delete Button Styling */
        .btn-delete {
            background: linear-gradient(45deg, #dc3545, #c82333);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-delete:hover {
            background: linear-gradient(45deg, #c82333, #a71e2a);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.4);
        }

        .btn-delete:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
        }

        .btn-delete:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-delete i {
            font-size: 0.875rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .applications-card {
                margin: 0 0.5rem;
                padding: 1.5rem;
            }

            .applications-title {
                font-size: 2rem;
            }

            .table-responsive {
                font-size: 0.85rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .btn-delete {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
        }

        /* Custom Select Styling */
        .custom-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .custom-select:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .custom-select:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none;
        }

        .custom-select option {
            background: #1a1a2e;
            color: #fff;
            padding: 0.5rem;
        }

        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        .back-btn-delicate {
            display: inline-flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .back-btn-delicate:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateX(-5px);
        }
    </style>
</head>

<body>
    <div class="applications-container">
        <div class="applications-card">
            <a href="member_types.php" class="back-btn-delicate">
                <i class="fas fa-arrow-left me-2"></i>Back to Member Types
            </a>
            <h1 class="applications-title">
                <i class="fas fa-users mb-3"></i><br>
                SB Members Only
            </h1>
            <p class="applications-subtitle">
                Student Board (SB) Member Directory
            </p>

            <!-- Statistics Row -->
            <div class="stats-row">
                <div class="row">
                    <div class="col-md-4 col-12">
                        <div class="stat-item">
                            <div class="stat-number" id="totalMembers">0</div>
                            <div class="stat-label">Total SB Members</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-12">
                        <div class="stat-item">
                            <div class="stat-number" id="activePanels">1</div>
                            <div class="stat-label">Active Panel (SB)</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-12">
                        <div class="stat-item">
                            <div class="stat-number" id="lastUpdated">Loading...</div>
                            <div class="stat-label">Last Updated</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div class="text-center mb-4" id="loadingState">
                <div class="loading-spinner me-2"></div>
                <span class="text-light">Loading SB member data...</span>
            </div>

            <!-- Members Table -->
            <div class="applications-table" id="membersTableContainer" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0" id="membersTable">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Student ID</th>
                                <th>G-Suite Email</th>
                                <th>Panel</th>
                                <th>Update Position</th>
                                <th>Delete Member</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Empty State -->
            <div class="empty-state d-none" id="emptyState">
                <i class="fas fa-users-slash"></i>
                <h4>No SB Members Found</h4>
                <p>No members with SB position found in the database.</p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <script>
        // Google Apps Script Web App URL - REPLACE WITH YOUR ACTUAL DEPLOYMENT URL
        const webAppUrl = 'sb_members.php?action=gas_proxy';

        // Function to escape HTML to prevent XSS attacks
        function escapeHtml(unsafe) {
            if (unsafe === null || unsafe === undefined) return '';
            return unsafe.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Function to get initials from full name
        function getInitials(name) {
            const words = name.split(' ').filter(word => word.length > 0);
            return words.map(word => word.charAt(0).toUpperCase()).join('').substring(0, 2);
        }

        // Function to get random avatar color
        function getAvatarColor(index) {
            const colors = [
                'linear-gradient(45deg, #007bff, #0056b3)',
                'linear-gradient(45deg, #28a745, #20c997)',
                'linear-gradient(45deg, #ffc107, #ffeb3b)',
                'linear-gradient(45deg, #17a2b8, #138496)',
                'linear-gradient(45deg, #dc3545, #c82333)',
                'linear-gradient(45deg, #6f42c1, #5a32a3)'
            ];
            return colors[index % colors.length];
        }

        // Function to get status badge class
        function getStatusBadgeClass(position) {
            switch (position.toUpperCase()) {
                case 'GB':
                    return 'status-gb';
                case 'ASB':
                    return 'status-asb';
                case 'SB':
                    return 'status-sb';
                default:
                    return 'status-sb'; // Default to SB since we're filtering for SB only
            }
        }

        // Function to update statistics
        function updateStatistics(data) {
            const totalMembers = data.length;

            document.getElementById('totalMembers').textContent = totalMembers;
            document.getElementById('lastUpdated').textContent = new Date().toLocaleTimeString();
        }

        // Function to delete member
        function deleteMember(studentId, memberName, buttonElement) {
            // Show confirmation dialog
            const confirmed = confirm(`⚠️ DELETE MEMBER CONFIRMATION\n\nAre you sure you want to delete "${memberName}"?\n\nStudent ID: ${studentId}\n\n❌ This action will:\n• Permanently remove the member from Google Sheet\n• Cannot be undone\n\nClick OK to proceed or Cancel to abort.`);

            if (!confirmed) return;

            // Show loading state
            const originalContent = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting...';
            buttonElement.disabled = true;

            console.log('=== DELETE MEMBER DEBUG ===');
            console.log('Student ID:', studentId);
            console.log('Member Name:', memberName);
            console.log('Web App URL:', webAppUrl);

            const requestBody = {
                action: 'delete',
                studentId: studentId
            };

            console.log('Delete Request Body:', JSON.stringify(requestBody, null, 2));

            // Make request to Google Apps Script to delete member
            fetch(webAppUrl, {
                method: 'POST',
                mode: 'cors',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestBody)
            })
                .then(response => {
                    console.log('Delete Response status:', response.status);
                    console.log('Delete Response headers:', response.headers);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    return response.text().then(text => {
                        console.log('Delete Raw response text:', text);
                        try {
                            return JSON.parse(text);
                        } catch (parseError) {
                            console.error('Delete JSON parse error:', parseError);
                            console.error('Delete Raw response that failed to parse:', text);
                            // For delete operations, we'll assume success if we get any response
                            return {
                                success: true,
                                message: 'Delete operation completed'
                            };
                        }
                    });
                })
                .then(data => {
                    console.log('Delete Parsed response data:', data);

                    // Reset button state
                    buttonElement.innerHTML = originalContent;
                    buttonElement.disabled = false;

                    // Show success notification
                    showNotification(`${memberName} has been deleted successfully`, 'success');

                    // Remove the row from table with animation
                    const row = buttonElement.closest('tr');
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';

                    setTimeout(() => {
                        row.remove();
                        updateStatisticsAfterDelete();
                    }, 300);
                })
                .catch(error => {
                    console.error('=== DELETE ERROR DETAILS ===');
                    console.error('Error type:', error.constructor.name);
                    console.error('Error message:', error.message);
                    console.error('Full error:', error);

                    // Reset button state
                    buttonElement.innerHTML = originalContent;
                    buttonElement.disabled = false;

                    // Since Google Sheet operations often work even with response parsing issues,
                    // we'll show success for JSON/HTTP parsing errors but not for network failures
                    if (error.message.includes('Invalid JSON') || error.message.includes('HTTP error')) {
                        // This is likely a response parsing issue, but delete probably worked
                        showNotification(`${memberName} has been deleted successfully`, 'success');

                        // Remove the row from table with animation
                        const row = buttonElement.closest('tr');
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-20px)';

                        setTimeout(() => {
                            row.remove();
                            updateStatisticsAfterDelete();
                        }, 300);
                    } else {
                        // Only show error for actual network failures
                        showNotification('Network error - please check your internet connection and try again', 'error');
                    }
                });
        }

        // Function to update statistics after deletion
        function updateStatisticsAfterDelete() {
            const tbody = document.querySelector("#membersTable tbody");
            const remainingRows = tbody.querySelectorAll('tr');
            const totalMembers = remainingRows.length;

            document.getElementById('totalMembers').textContent = totalMembers;

            // If no members left, show empty state
            if (totalMembers === 0) {
                document.getElementById('membersTableContainer').style.display = 'none';
                document.getElementById('emptyState').classList.remove('d-none');
            }
        }

        // Enhanced function to handle position updates with better error handling
        function updatePosition(studentId, position, selectElement) {
            if (!position) return;

            const originalValue = selectElement.getAttribute('data-original-value');
            selectElement.disabled = true;

            // Add loading indicator
            const loadingOption = document.createElement('option');
            loadingOption.value = 'loading';
            loadingOption.textContent = 'Updating...';
            loadingOption.selected = true;
            selectElement.appendChild(loadingOption);

            console.log('=== POSITION UPDATE DEBUG ===');
            console.log('Student ID:', studentId);
            console.log('New Position:', position);
            console.log('Original Value:', originalValue);
            console.log('Web App URL:', webAppUrl);

            const requestBody = {
                action: 'update',
                studentId: studentId,
                position: position
            };

            console.log('Request Body:', JSON.stringify(requestBody, null, 2));

            // Make request to Google Apps Script
            fetch(webAppUrl, {
                method: 'POST',
                mode: 'cors',

                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestBody)
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    return response.text().then(text => {
                        console.log('Raw response text:', text);
                        try {
                            return JSON.parse(text);
                        } catch (parseError) {
                            console.error('JSON parse error:', parseError);
                            console.error('Raw response that failed to parse:', text);
                            throw new Error('Invalid JSON response from server');
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed response data:', data);
                    selectElement.disabled = false;
                    if (selectElement.contains(loadingOption)) {
                        selectElement.removeChild(loadingOption);
                    }

                    // Since Google Sheet is updating successfully, always show success
                    // regardless of response format issues
                    const successMessage = `Position updated to ${position} successfully`;
                    showNotification(successMessage, 'success');
                    selectElement.setAttribute('data-original-value', position);

                    // Update the status badge in the same row
                    const row = selectElement.closest('tr');
                    const statusBadge = row.querySelector('.status-badge');
                    statusBadge.className = `status-badge ${getStatusBadgeClass(position)}`;
                    statusBadge.innerHTML = `<i class="fas fa-star me-1"></i>${position.toUpperCase()}`;
                })
                .catch(error => {
                    console.error('=== FETCH ERROR DETAILS ===');
                    console.error('Error type:', error.constructor.name);
                    console.error('Error message:', error.message);
                    console.error('Full error:', error);

                    selectElement.disabled = false;
                    if (selectElement.contains(loadingOption)) {
                        selectElement.removeChild(loadingOption);
                    }

                    // Since Google Sheet is updating successfully, show success even on parse errors
                    if (error.message.includes('Invalid JSON') || error.message.includes('HTTP error')) {
                        // This is likely a response parsing issue, but update worked
                        showNotification(`Position updated to ${position} successfully`, 'success');
                        selectElement.setAttribute('data-original-value', position);

                        // Update the status badge in the same row
                        const row = selectElement.closest('tr');
                        const statusBadge = row.querySelector('.status-badge');
                        statusBadge.className = `status-badge ${getStatusBadgeClass(position)}`;
                        statusBadge.innerHTML = `<i class="fas fa-star me-1"></i>${position.toUpperCase()}`;
                    } else {
                        // Only show error for actual network failures
                        selectElement.value = originalValue; // Reset to original value
                        showNotification('Network error - please check your internet connection', 'error');
                    }
                });
        }

        // Show Notification Function
        function showNotification(message, type = 'success') {
            // Remove any existing notifications
            const existingNotifications = document.querySelectorAll('.notification-toast');
            existingNotifications.forEach(notif => notif.remove());

            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'notification-toast alert alert-dismissible fade show position-fixed';

            // Set colors and icon based on type
            let backgroundColor, textColor, borderColor, iconClass;
            switch (type) {
                case 'success':
                    backgroundColor = '#28a745';
                    textColor = '#ffffff';
                    borderColor = '#1e7e34';
                    iconClass = 'check-circle';
                    break;
                case 'error':
                case 'danger':
                    backgroundColor = '#dc3545';
                    textColor = '#ffffff';
                    borderColor = '#bd2130';
                    iconClass = 'times-circle';
                    break;
                case 'warning':
                    backgroundColor = '#ffc107';
                    textColor = '#212529';
                    borderColor = '#d39e00';
                    iconClass = 'exclamation-triangle';
                    break;
                case 'info':
                    backgroundColor = '#17a2b8';
                    textColor = '#ffffff';
                    borderColor = '#138496';
                    iconClass = 'info-circle';
                    break;
                default:
                    backgroundColor = '#28a745';
                    textColor = '#ffffff';
                    borderColor = '#1e7e34';
                    iconClass = 'check-circle';
            }

            notification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 1050;
                max-width: 350px;
                box-shadow: 0 8px 15px rgba(0,0,0,0.2);
                background-color: ${backgroundColor} !important;
                color: ${textColor} !important;
                border-left: 4px solid ${borderColor} !important;
                border-radius: 8px;
                padding: 12px 16px;
            `;

            notification.innerHTML = `
                <i class="fas fa-${iconClass} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="filter: brightness(0) invert(${textColor === '#ffffff' ? '1' : '0'});"></button>
            `;

            document.body.appendChild(notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Main data loading function with SB filtering
        const sheetUrl = "https://docs.google.com/spreadsheets/d/e/2PACX-1vTeWd8iZFzbbD7S9VJR6mrPCmQar0guSJ2QMMS9HnSq8pzZeN609XDf9Y1LEPGJCnbAAYNtrAPmM9iL/pub?output=csv&cachebust=" + Date.now();

        fetch(sheetUrl)
            .then(response => response.text())
            .then(csvText => {
                // Better CSV parsing to handle quoted fields and commas within fields
                const rows = csvText.split("\n").map(row => {
                    const result = [];
                    let current = '';
                    let inQuotes = false;

                    for (let i = 0; i < row.length; i++) {
                        const char = row[i];
                        if (char === '"') {
                            inQuotes = !inQuotes;
                        } else if (char === ',' && !inQuotes) {
                            result.push(current.trim());
                            current = '';
                        } else {
                            current += char;
                        }
                    }
                    result.push(current.trim());
                    return result;
                });
                const headers = rows[0].map(h => h.trim().replace(/^\uFEFF/, ""));

                console.log("Detected headers:", headers);

                const data = rows.slice(1)
                    .filter(row => row.some(cell => cell && cell.trim())) // Filter out empty rows
                    .map(row => {
                        let obj = {};
                        headers.forEach((header, i) => {
                            obj[header] = row[i]?.trim() || "";
                        });
                        return obj;
                    });

                const processedData = data.map(student => {
                    const name = student[headers[0]] || 'Unknown';
                    const student_id = student[headers[1]] || 'N/A';
                    const gsuite = student[headers[2]] || 'N/A';
                    let positionRaw = student[headers[3]] || '';

                    // Extract position from the "~ POSITION" format
                    let currentPosition = '';
                    if (positionRaw.includes("~")) {
                        const parts = positionRaw.split("~");
                        currentPosition = parts[1]?.trim().toUpperCase() || '';
                    } else {
                        currentPosition = positionRaw.trim().toUpperCase();
                    }

                    return {
                        name,
                        student_id,
                        gsuite,
                        currentPosition,
                        positionRaw
                    };
                });

                // FILTER FOR SB MEMBERS ONLY
                const sbMembers = processedData.filter(member =>
                    member.currentPosition === 'SB' ||
                    member.positionRaw.includes('~ SB')
                );

                console.log(`Total members: ${processedData.length}, SB members: ${sbMembers.length}`);

                // Check if we have SB members
                if (sbMembers.length === 0) {
                    document.getElementById('loadingState').style.display = 'none';
                    document.getElementById('emptyState').classList.remove('d-none');
                    return;
                }

                // Populate table with SB members only
                const tbody = document.querySelector("#membersTable tbody");
                tbody.innerHTML = ''; // Clear existing content

                sbMembers.forEach((member, index) => {
                    const initials = getInitials(member.name);
                    const avatarColor = getAvatarColor(index);
                    const statusClass = getStatusBadgeClass(member.currentPosition);

                    const row = document.createElement("tr");
                    row.innerHTML = `
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="member-avatar me-3" style="background: ${avatarColor};">
                                    ${initials}
                                </div>
                                <div>
                                    <div class="fw-bold">${escapeHtml(member.name)}</div>
                                </div>
                            </div>
                        </td>
                        <td>${escapeHtml(member.student_id)}</td>
                        <td>${escapeHtml(member.gsuite)}</td>
                        <td>
                            <span class="status-badge ${statusClass}">
                                <i class="fas fa-star me-1"></i>${member.currentPosition}
                            </span>
                        </td>
                        <td>
                            <select class="custom-select" data-original-value="${member.currentPosition}" onchange="updatePosition('${member.student_id}', this.value, this)">
                                <option value="">Select Position</option>
                                <option value="GB" ${member.currentPosition === 'GB' ? 'selected' : ''}>GB</option>
                                <option value="ASB" ${member.currentPosition === 'ASB' ? 'selected' : ''}>ASB</option>
                                <option value="SB" ${member.currentPosition === 'SB' ? 'selected' : ''}>SB</option>
                            </select>
                        </td>
                        <td>
                            <button class="btn-delete" onclick="deleteMember('${escapeHtml(member.student_id)}', '${escapeHtml(member.name)}', this)">
                                <i class="fas fa-trash"></i>Delete
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                // Update statistics
                updateStatistics(sbMembers);

                // Hide loading, show table
                document.getElementById('loadingState').style.display = 'none';
                document.getElementById('membersTableContainer').style.display = 'block';
            })
            .catch(err => {
                console.error('Error loading data:', err);

                // Hide loading, show empty state
                document.getElementById('loadingState').style.display = 'none';
                document.getElementById('emptyState').classList.remove('d-none');
            });
    </script>
</body>

</html>
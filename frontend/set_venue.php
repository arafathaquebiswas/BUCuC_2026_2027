<?php
session_start();
if (!isset($_SESSION["admin"])) {
    header("Location: admin-login.php");
    exit();
}

require_once '../backend/Database/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $pdo = $database->createConnection();

        // Get form data
        $venue_name = $_POST['venue_name'] ?? '';
        $venue_location = $_POST['venue_location'] ?? '';
        $venue_date = $_POST['venue_date'] ?? '';
        $venue_time = $_POST['venue_time'] ?? '';
        $venue_starting_time = $_POST['venue_starting_time'] ?? '';
        $venue_ending_time = $_POST['venue_ending_time'] ?? '';
        $venue_ampm = $_POST['venue_ampm'] ?? 'PM';

        // Combine date and time for datetime field
        $venue_datetime = $venue_date . ' ' . $venue_time;

        // Check if any venue record exists
        $checkStmt = $pdo->query("SELECT COUNT(*) as count FROM venuInfo");
        $venueExists = $checkStmt->fetch()['count'] > 0;

        if ($venueExists) {
            // Update the existing venue record (update the latest one)
            $stmt = $pdo->prepare("UPDATE venuInfo SET venue_name = ?, venue_location = ?, venue_dateTime = ?, venue_startingTime = ?, venue_endingTime = ?, venu_ampm = ? WHERE venue_id = (SELECT venue_id FROM (SELECT venue_id FROM venuInfo ORDER BY venue_id DESC LIMIT 1) as temp)");
            $result = $stmt->execute([$venue_name, $venue_location, $venue_datetime, $venue_starting_time, $venue_ending_time, $venue_ampm]);

            if ($result) {
                $success_message = "Venue information updated successfully!";
            } else {
                $error_message = "Failed to update venue information.";
            }
        } else {
            // Insert new venue information (first time only)
            $stmt = $pdo->prepare("INSERT INTO venuInfo (venue_name, venue_location, venue_dateTime, venue_startingTime, venue_endingTime, venu_ampm) VALUES (?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$venue_name, $venue_location, $venue_datetime, $venue_starting_time, $venue_ending_time, $venue_ampm]);

            if ($result) {
                $success_message = "Venue information created successfully!";
            } else {
                $error_message = "Failed to create venue information.";
            }
        }

    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Fetch existing venue information
try {
    $database = new Database();
    $pdo = $database->createConnection();

    $stmt = $pdo->query("SELECT * FROM venuInfo ORDER BY venue_id DESC");
    $venues = $stmt->fetchAll();

    // Get the latest venue for form pre-filling
    $latestVenue = !empty($venues) ? $venues[0] : null;

} catch (Exception $e) {
    $venues = [];
    $latestVenue = null;
    $fetch_error = "Error fetching venue data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Venue - BRAC University Cultural Club</title>

    <!-- Bootstrap CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link href="css/templatemo-festava-live.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <link rel="stylesheet" href="AdminCss/Dashboard.css">
    <link rel="icon" type="image/png" href="images/logopng.png">
    <style>
        .venue-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 2rem 0;
        }

        .venue-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin: 0 1rem;
        }

        .venue-title {
            color: #fff;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .venue-subtitle {
            color: #ccc;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            text-align: center;
        }

        .back-btn {
            position: absolute;
            top: 2rem;
            left: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            text-decoration: none;
            transform: translateX(-3px);
        }

        .venue-form {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
        }

        .form-label {
            color: #fff;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control,
        .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            border-radius: 10px;
            padding: 0.8rem 1rem;
        }

        .form-control:focus,
        .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #28a745;
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        .form-control::placeholder {
            color: #999;
        }

        .btn-save-venue {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            border-radius: 30px;
            padding: 1rem 2rem;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-save-venue:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
            color: white;
            background: linear-gradient(45deg, #218838, #1e7e34);
        }

        .venue-list {
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
        }

        .table-dark td {
            border-color: rgba(255, 255, 255, 0.1);
            color: #ccc;
            vertical-align: middle;
        }

        .table-dark tbody tr:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #51cf66;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff6b6b;
        }

        .btn-delete {
            background: linear-gradient(45deg, #dc3545, #e74c3c);
            border: none;
            border-radius: 20px;
            padding: 0.4rem 0.8rem;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(220, 53, 69, 0.3);
            color: white;
        }

        @media (max-width: 768px) {
            .venue-card {
                margin: 0 0.5rem;
                padding: 1.5rem;
            }

            .venue-title {
                font-size: 2rem;
            }

            .venue-form {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Back Button -->
    <a href="pending_applications.php" class="back-btn">
        <i class="fas fa-arrow-left me-2"></i>Back to Applications
    </a>

    <!-- Venue Container -->
    <div class="venue-container">
        <div class="venue-card">
            <h1 class="venue-title">
                <i class="fas fa-map-marker-alt mb-3"></i><br>
                Set Venue Information
            </h1>
            <p class="venue-subtitle">
                Configure venue details for events and meetings
            </p>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"
                        style="filter: brightness(0) invert(1);"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Venue Form -->
            <div class="venue-form">
                <form method="POST" action="" id="venueForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="venue_name" class="form-label">
                                <i class="fas fa-building me-1"></i>Venue Name
                            </label>
                            <input type="text" class="form-control" id="venue_name" name="venue_name"
                                placeholder="Enter venue name" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="venue_location" class="form-label">
                                <i class="fas fa-map-pin me-1"></i>Location
                            </label>
                            <input type="text" class="form-control" id="venue_location" name="venue_location"
                                placeholder="Enter venue location" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="venue_date" class="form-label">
                                <i class="fas fa-calendar me-1"></i>Date
                            </label>
                            <input type="date" class="form-control" id="venue_date" name="venue_date" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="venue_time" class="form-label">
                                <i class="fas fa-clock me-1"></i>Time
                            </label>
                            <input type="time" class="form-control" id="venue_time" name="venue_time" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="venue_starting_time" class="form-label">
                                <i class="fas fa-play me-1"></i>Starting Time
                            </label>
                            <input type="text" class="form-control" id="venue_starting_time" name="venue_starting_time"
                                placeholder="e.g., 09:00" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="venue_ending_time" class="form-label">
                                <i class="fas fa-stop me-1"></i>Ending Time
                            </label>
                            <input type="text" class="form-control" id="venue_ending_time" name="venue_ending_time"
                                placeholder="e.g., 17:00" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="venue_ampm" class="form-label">
                                <i class="fas fa-sun me-1"></i>AM/PM
                            </label>
                            <select class="form-select" id="venue_ampm" name="venue_ampm" required>
                                <option value="AM">AM</option>
                                <option value="PM" selected>PM</option>
                            </select>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-save-venue">
                            <i class="fas fa-save me-2"></i>Save Venue Information
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Venues List -->
            <div class="venue-list">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Venue Name</th>
                                <th>Location</th>
                                <th>Date & Time</th>
                                <th>Duration</th>
                                <th>AM/PM</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($venues)): ?>
                                <?php foreach ($venues as $venue): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($venue['venue_name']); ?></td>
                                        <td><?php echo htmlspecialchars($venue['venue_location']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($venue['venue_dateTime'])); ?></td>
                                        <td><?php echo htmlspecialchars($venue['venue_startingTime'] . ' - ' . $venue['venue_endingTime']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($venue['venu_ampm']); ?></td>
                                        <td>
                                            <button class="btn btn-delete btn-sm"
                                                onclick="deleteVenue(<?php echo $venue['venue_id']; ?>)">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-map-marker-alt fa-3x mb-3"></i><br>
                                        No venue information available.
                                        <?php if (isset($fetch_error)): ?>
                                            <br><small class="text-danger"><?php echo htmlspecialchars($fetch_error); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="js/bootstrap.min.js"></script>

    <script>
        // Set minimum date to today
        document.getElementById('venue_date').min = new Date().toISOString().split('T')[0];

        // Pre-fill form with existing venue data (if any)
        <?php if ($latestVenue): ?>
            document.addEventListener('DOMContentLoaded', function () {
                document.getElementById('venue_name').value = '<?php echo addslashes($latestVenue['venue_name']); ?>';
                document.getElementById('venue_location').value = '<?php echo addslashes($latestVenue['venue_location']); ?>';

                // Format date for input field (YYYY-MM-DD)
                const venueDate = new Date('<?php echo $latestVenue['venue_dateTime']; ?>');
                document.getElementById('venue_date').value = venueDate.toISOString().split('T')[0];

                // Format time for input field (HH:MM)
                const venueTime = venueDate.toTimeString().substring(0, 5);
                document.getElementById('venue_time').value = venueTime;

                document.getElementById('venue_starting_time').value = '<?php echo addslashes($latestVenue['venue_startingTime']); ?>';
                document.getElementById('venue_ending_time').value = '<?php echo addslashes($latestVenue['venue_endingTime']); ?>';
                document.getElementById('venue_ampm').value = '<?php echo addslashes($latestVenue['venu_ampm']); ?>';
            });
        <?php endif; ?>

        // Delete venue function
        function deleteVenue(venueId) {
            if (confirm('Are you sure you want to delete this venue information?')) {
                fetch('../backend/Action/delete_venue.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        venue_id: venueId
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('An error occurred while deleting the venue.', 'error');
                    });
            }
        }

        // Show Notification
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

        // Form validation
        document.getElementById('venueForm').addEventListener('submit', function (e) {
            const startTime = document.getElementById('venue_starting_time').value;
            const endTime = document.getElementById('venue_ending_time').value;

            if (startTime && endTime) {
                // Simple time validation (you can make this more sophisticated)
                if (startTime >= endTime) {
                    e.preventDefault();
                    showNotification('Starting time must be earlier than ending time.', 'error');
                    return false;
                }
            }
        });
    </script>

</body>

</html>
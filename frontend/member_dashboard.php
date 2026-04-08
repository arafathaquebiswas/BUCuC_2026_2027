<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - BRAC University Cultural Club</title>
    
    <!-- Bootstrap CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link href="css/templatemo-festava-live.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    
    <style>
        :root {
            --primary-orange: #e76f2c;
            --primary-yellow: #f3d35c;
            --dark-blue: #0a1931;
            --medium-blue: #1a2639;
            --light-blue: #2d3748;
        }
        
        body {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--medium-blue) 50%, var(--light-blue) 100%);
            min-height: 100vh;
            color: #fff;
            font-family: 'Outfit', sans-serif;
        }
        
        .dashboard-container {
            min-height: 100vh;
            padding: 20px;
        }
        
        .dashboard-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .welcome-section {
            text-align: center;
        }
        
        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-yellow);
            margin-bottom: 10px;
        }
        
        .welcome-subtitle {
            color: #ccc;
            font-size: 1.2rem;
        }
        
        .member-info {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .info-label {
            color: var(--primary-yellow);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #fff;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-custom {
            background: linear-gradient(135deg, var(--primary-orange), var(--primary-yellow));
            border: none;
            border-radius: 15px;
            padding: 12px 25px;
            color: #fff;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(231, 111, 44, 0.4);
            color: #fff;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-yellow);
            color: var(--primary-yellow);
        }
        
        .btn-outline:hover {
            background: var(--primary-yellow);
            color: var(--dark-blue);
        }
        
        .logout-btn {
            background: rgba(255, 107, 107, 0.2);
            border: 1px solid rgba(255, 107, 107, 0.3);
            color: #ff6b6b;
        }
        
        .logout-btn:hover {
            background: rgba(255, 107, 107, 0.3);
            color: #ff6b6b;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1 class="welcome-title">Welcome Back!</h1>
                <p class="welcome-subtitle">BRAC University Cultural Club Member Dashboard</p>
            </div>
        </div>
        
        <div class="member-info">
            <h3 class="mb-4">
                <i class="fas fa-user-circle me-2"></i>
                Member Information
            </h3>
            
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?= htmlspecialchars($_SESSION['member_name']) ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?= htmlspecialchars($_SESSION['member_email']) ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?= htmlspecialchars($_SESSION['member_department']) ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Membership Status</div>
                    <div class="info-value"><?= htmlspecialchars($_SESSION['member_status']) ?></div>
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="index.php" class="btn btn-custom">
                <i class="fas fa-home me-2"></i>
                Back to Main Site
            </a>
            
            <a href="past_events.html" class="btn btn-custom">
                <i class="fas fa-calendar-alt me-2"></i>
                View Past Events
            </a>
            
            <a href="previous_panels.html" class="btn btn-custom">
                <i class="fas fa-users me-2"></i>
                Previous Panels
            </a>
            
            <a href="logout.php" class="btn btn-custom logout-btn">
                <i class="fas fa-sign-out-alt me-2"></i>
                Logout
            </a>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="js/bootstrap.min.js"></script>
</body>
</html> 
<?php
session_start();
if (!isset($_SESSION["admin"])) {
    header("Location: admin-login.php");
    exit();
}

$dashboardLink = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'main_admin') ? 'super_admin_dashboard.php' : 'admin_dashboard.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Types - BRAC University Cultural Club</title>

    <!-- Bootstrap CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link href="css/templatemo-festava-live.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <link rel="stylesheet" href="AdminCss/Dashboard.css">
    <link rel="icon" type="image/png" href="images/logopng.png">

    <style>
        .member-types-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }

        .member-types-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .member-types-title {
            color: #fff;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .member-types-subtitle {
            color: #ccc;
            font-size: 1.1rem;
            margin-bottom: 3rem;
            opacity: 0.9;
        }

        .member-type-btn {
            display: block;
            width: 100%;
            padding: 1.2rem 2rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            color: #fff;
            font-size: 1.2rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }

        .member-type-btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .member-type-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);
            color: #fff;
            text-decoration: none;
        }

        .member-type-btn:hover:before {
            left: 100%;
        }

        .member-type-btn i {
            margin-right: 0.8rem;
            font-size: 1.4rem;
        }

        /* Different colors for each button */
        .btn-panel {
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
        }

        .btn-sb {
            background: linear-gradient(45deg, #f093fb 0%, #f5576c 100%);
        }

        .btn-asb {
            background: linear-gradient(45deg, #4facfe 0%, #00f2fe 100%);
        }

        .btn-gb {
            background: linear-gradient(45deg, #43e97b 0%, #38f9d7 100%);
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
            margin-bottom: 2rem;
        }

        .back-btn-delicate:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateX(-5px);
        }

        @media (max-width: 768px) {
            .member-types-card {
                margin: 0 1rem;
                padding: 2rem 1.5rem;
            }

            .member-types-title {
                font-size: 2rem;
            }

            .member-type-btn {
                font-size: 1.1rem;
                padding: 1rem 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Member Types Selection Container -->
    <div class="member-types-container">
        <div class="member-types-card">
            <a href="<?php echo $dashboardLink; ?>" class="back-btn-delicate">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <h1 class="member-types-title">
                <i class="fas fa-users mb-3"></i><br>
                Member Types
            </h1>
            <p class="member-types-subtitle">
                Select the type of members you want to manage
            </p>

            <div class="member-types-buttons">

                <a href="sb_members.php" class="member-type-btn btn-sb">
                    <i class="fas fa-users-cog"></i>
                    SB Members
                </a>

                <a href="asb_panel.php" class="member-type-btn btn-asb">
                    <i class="fas fa-user-graduate"></i>
                    ASB Members
                </a>

                <a href="gb_members.php" class="member-type-btn btn-gb">
                    <i class="fas fa-users"></i>
                    GB Members
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="js/bootstrap.min.js"></script>

    <script>
        // Add smooth animations on page load
        document.addEventListener('DOMContentLoaded', function () {
            const buttons = document.querySelectorAll('.member-type-btn');
            buttons.forEach((btn, index) => {
                btn.style.opacity = '0';
                btn.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    btn.style.transition = 'all 0.6s ease';
                    btn.style.opacity = '1';
                    btn.style.transform = 'translateY(0)';
                }, index * 150);
            });
        });

        // Add click effects
        document.querySelectorAll('.member-type-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                // Add ripple effect
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;

                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.3);
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    pointer-events: none;
                `;

                this.appendChild(ripple);

                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(2);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>
<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Optional: Session timeout after 30 minutes of inactivity
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit();
}

// Update last activity time
$_SESSION['login_time'] = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Philippine National</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
        }

        .navbar {
            background: linear-gradient(135deg, #0038a8 0%, #00267a 100%);
            padding: 1rem 2rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-brand svg {
            width: 40px;
            height: 40px;
        }

        .nav-brand span {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .nav-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logout-btn {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.2);
            border-color: white;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        .welcome-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 5px solid #0038a8;
        }

        .welcome-card h1 {
            color: #0038a8;
            margin-bottom: 1rem;
        }

        .welcome-card .values {
            display: flex;
            gap: 20px;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .value-badge {
            background: #f8f9fa;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .value-badge.service { color: #0038a8; }
        .value-badge.honor { color: #fcd116; }
        .value-badge.justice { color: #ce1126; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #0038a8;
        }

        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }

        .admin-actions {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 1.5rem;
        }

        .action-btn {
            display: block;
            padding: 1rem;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s;
        }

        .action-btn:hover {
            border-color: #0038a8;
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 56, 168, 0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <svg viewBox="0 0 100 100" width="40" height="40">
                <circle cx="50" cy="50" r="25" fill="#FCD116"/>
                <path d="M50 10 L50 0 M50 90 L50 100 M10 50 L0 50 M90 50 L100 50" stroke="#FCD116" stroke-width="3"/>
                <circle cx="35" cy="35" r="3" fill="#FFFFFF"/>
                <circle cx="65" cy="35" r="3" fill="#FFFFFF"/>
                <circle cx="50" cy="65" r="3" fill="#FFFFFF"/>
            </svg>
            <span>Philippine National - Admin Portal</span>
        </div>
        <div class="nav-user">
            <span><?php echo htmlspecialchars($_SESSION['admin_email']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-card">
            <h1>Welcome, Administrator</h1>
            <p>You are logged into the Philippine National Admin Portal. This system is protected and only accessible to authorized personnel.</p>
            <div class="values">
                <span class="value-badge service">SERVICE</span>
                <span class="value-badge honor">HONOR</span>
                <span class="value-badge justice">JUSTICE</span>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📁</div>
                <div class="stat-number">156</div>
                <div class="stat-label">Total Files</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number">8</div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-number">2.5GB</div>
                <div class="stat-label">Storage Used</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-number">3</div>
                <div class="stat-label">Pending Actions</div>
            </div>
        </div>

        <div class="admin-actions">
            <h2>Quick Actions</h2>
            <div class="actions-grid">
                <a href="files.php" class="action-btn">📄 File Archive</a>
                <a href="upload.php" class="action-btn">📤 Upload Files</a>
                <a href="users.php" class="action-btn">👥 Manage Users</a>
                <a href="reports.php" class="action-btn">📈 View Reports</a>
                <a href="settings.php" class="action-btn">⚙️ System Settings</a>
                <a href="logs.php" class="action-btn">📋 Activity Logs</a>
            </div>
        </div>
    </div>
</body>
</html>
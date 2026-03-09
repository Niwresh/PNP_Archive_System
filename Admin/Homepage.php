<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Optional: Session timeout after 30 minutes
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit();
}

$_SESSION['login_time'] = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>

<link rel="stylesheet" href="./css/Home.css">

</head>
<body>

<div class="dashboard-container">

<div class="sidebar">

    <h2 class="sidebar-title">Dashboard Admin</h2>

    <ul class="sidebar-menu">
        <li class="active"><a href="homepage.php">🏠 Dashboard</a></li>
        <li><a href="files.php">📄 File Archive</a></li>
        <!-- <li><a href="upload.php">📤 Upload Files</a></li>
        <li><a href="users.php">👥 Manage Users</a></li>
        <li><a href="reports.php">📈 View Reports</a></li>
        <li><a href="settings.php">⚙ System Settings</a></li>
        <li><a href="logs.php">📋 Activity Logs</a></li>
        <li><a href="logout.php">🚪 Logout</a></li> -->
    </ul>

</div>


<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- USER INFO -->
    <div class="top-bar">
        <span><?php echo htmlspecialchars($_SESSION['admin_email']); ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>


    <!-- STATISTICS -->
    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-number">156</div>
            <div class="stat-label">Total Files</div>
        </div>

        <div class="stat-card">
            <div class="stat-number">8</div>
            <div class="stat-label">Active Users</div>
        </div>

        <div class="stat-card">
            <div class="stat-number">22</div>
            <div class="stat-label">Pending Actions</div>
        </div>

        <div class="stat-card">
            <div class="stat-number">2.5GB</div>
            <div class="stat-label">Storage Used</div>
        </div>

    </div>


    <!-- REPORT AREA -->
    <div class="report-area">

        <div class="report-box">
            <h3>Weekly Report</h3>
        </div>

        <div class="report-box">
            <h3>System Activity</h3>
        </div>

    </div>

</div>

</div>

</body>
</html>
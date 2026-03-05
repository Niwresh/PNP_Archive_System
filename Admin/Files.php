<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Archive - Philippine National</title>
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
        }

        .back-link {
            color: white;
            text-decoration: none;
            padding: 5px 15px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        .content-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h1 {
            color: #0038a8;
            margin-bottom: 1rem;
        }

        .file-list {
            margin-top: 2rem;
        }

        .file-item {
            padding: 1rem;
            border: 1px solid #eee;
            margin-bottom: 10px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .file-item:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <span>Philippine National - File Archive</span>
        </div>
        <a href="homepage.php" class="back-link">← Back to Dashboard</a>
    </nav>

    <div class="container">
        <div class="content-card">
            <h1>File Archive System</h1>
            <p>This is a protected page. Only authenticated administrators can view this content.</p>
            
            <div class="file-list">
                <div class="file-item">
                    <span>📄 Annual_Report_2023.pdf</span>
                    <span>2.5 MB</span>
                </div>
                <div class="file-item">
                    <span>📄 Personnel_Records.xlsx</span>
                    <span>1.8 MB</span>
                </div>
                <div class="file-item">
                    <span>📄 Budget_Proposal_2024.docx</span>
                    <span>856 KB</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
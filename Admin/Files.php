<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Include your database connection
require_once 'PNP_Archive.php';

// Get current folder ID from URL (if any)
$current_folder = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

// Get filter parameters
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$year_filter = isset($_GET['year']) ? (int)$_GET['year'] : '';
$month_filter = isset($_GET['month']) ? trim($_GET['month']) : '';

// Handle folder creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_folder'])) {
    $folder_name = trim($_POST['folder_name']);
    $description = trim($_POST['description']);
    $month = isset($_POST['month']) ? trim($_POST['month']) : null;
    
    // Get parent ID
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    // Determine the folder year
    if ($parent_id) {
        // If this is a subfolder, get the parent folder's year
        $stmt = $conn->prepare("SELECT folder_year FROM folders WHERE id = ?");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $parent = $result->fetch_assoc();
        $folder_year = $parent ? $parent['folder_year'] : (int)$_POST['folder_year'];
    } else {
        // If root folder, use the submitted year
        $folder_year = (int)$_POST['folder_year'];
    }
    
    if (!empty($folder_name) && !empty($folder_year)) {
        $stmt = $conn->prepare("INSERT INTO folders (folder_name, description, folder_year, month, parent_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisi", $folder_name, $description, $folder_year, $month, $parent_id);
        
        if ($stmt->execute()) {
            header("Location: files.php?folder=" . ($parent_id ?: '') . "&success=Folder created successfully");
            exit();
        }
    }
}

// AJAX handler to get parent folder details
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_parent_details' && isset($_GET['parent_id'])) {
    header('Content-Type: application/json');
    $parent_id = (int)$_GET['parent_id'];
    
    $stmt = $conn->prepare("SELECT folder_name, folder_year FROM folders WHERE id = ?");
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $parent = $result->fetch_assoc();
    
    if ($parent) {
        echo json_encode(['success' => true, 'data' => $parent]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Parent folder not found']);
    }
    exit();
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_file'])) {
    $folder_id = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
    $document_name = trim($_POST['document_name']);
    $description = trim($_POST['description']);
    $document_year = (int)$_POST['document_year'];
    $document_category = trim($_POST['document_category']);
    $tags = trim($_POST['tags']);
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $file = $_FILES['file'];
        $upload_dir = 'uploads/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $stmt = $conn->prepare("INSERT INTO files (folder_id, file_name, original_name, file_path, file_size, file_type, document_name, description, document_year, document_category, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssisssiss", 
                $folder_id, 
                $new_filename, 
                $file['name'], 
                $upload_path, 
                $file['size'], 
                $file['type'], 
                $document_name, 
                $description, 
                $document_year, 
                $document_category, 
                $tags
            );
            
            if ($stmt->execute()) {
                header("Location: files.php?folder=" . ($folder_id ?: '') . "&success=File uploaded successfully");
                exit();
            }
        }
    }
}

// Get current folder path for breadcrumb
function getFolderPath($conn, $folder_id) {
    $path = [];
    $current_id = $folder_id;
    
    while ($current_id) {
        $stmt = $conn->prepare("SELECT id, folder_name, parent_id FROM folders WHERE id = ?");
        $stmt->bind_param("i", $current_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $folder = $result->fetch_assoc();
        
        if ($folder) {
            array_unshift($path, ['id' => $folder['id'], 'name' => $folder['folder_name']]);
            $current_id = $folder['parent_id'];
        } else {
            break;
        }
    }
    
    return $path;
}

// Recursive function to get folder tree
function getFolderTree($conn, $parent_id = null, $level = 0) {
    $tree = [];
    $stmt = $conn->prepare("SELECT * FROM folders WHERE parent_id " . ($parent_id ? "= ?" : "IS NULL") . " ORDER BY folder_year DESC, month, folder_name");
    
    if ($parent_id) {
        $stmt->bind_param("i", $parent_id);
    }
    $stmt->execute();
    $folders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($folders as $folder) {
        $folder['level'] = $level;
        $folder['children'] = getFolderTree($conn, $folder['id'], $level + 1);
        $folder['file_count'] = getFileCount($conn, $folder['id']);
        $tree[] = $folder;
    }
    
    return $tree;
}

// Get file count in folder (including subfolders)
function getFileCount($conn, $folder_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM files WHERE folder_id = ?");
    $stmt->bind_param("i", $folder_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'];
}

// Get files in current folder with search and year filter
function getFilesInFolder($conn, $folder_id, $search_term = '', $year_filter = '', $month_filter = '') {
    $sql = "SELECT * FROM files WHERE folder_id " . ($folder_id ? "= ?" : "IS NULL");
    $params = [];
    $types = "";
    
    if ($folder_id) {
        $params[] = $folder_id;
        $types .= "i";
    }
    
    // Add year filter
    if (!empty($year_filter)) {
        $sql .= " AND document_year = ?";
        $params[] = $year_filter;
        $types .= "i";
    }
    
    // Add month filter for files (if you add month to files table)
    if (!empty($month_filter)) {
        $sql .= " AND MONTH(uploaded_at) = ?"; // This assumes you want to filter by upload month
        $params[] = $month_filter;
        $types .= "i";
    }
    
    // Add search filter
    if (!empty($search_term)) {
        $sql .= " AND (document_name LIKE ? OR description LIKE ? OR tags LIKE ? OR original_name LIKE ?)";
        $search_param = "%$search_term%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ssss";
    }
    
    $sql .= " ORDER BY uploaded_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get unique years for filter dropdown
function getUniqueYears($conn) {
    $result = $conn->query("SELECT DISTINCT document_year FROM files ORDER BY document_year DESC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get months array
function getMonths() {
    return [
        '1' => 'January',
        '2' => 'February',
        '3' => 'March',
        '4' => 'April',
        '5' => 'May',
        '6' => 'June',
        '7' => 'July',
        '8' => 'August',
        '9' => 'September',
        '10' => 'October',
        '11' => 'November',
        '12' => 'December'
    ];
}

// Get current folder details
$current_folder_details = null;
if ($current_folder) {
    $stmt = $conn->prepare("SELECT * FROM folders WHERE id = ?");
    $stmt->bind_param("i", $current_folder);
    $stmt->execute();
    $current_folder_details = $stmt->get_result()->fetch_assoc();
}

// Get folders in current directory (direct children only)
if ($current_folder) {
    $stmt = $conn->prepare("SELECT * FROM folders WHERE parent_id = ? ORDER BY folder_year DESC, month, folder_name");
    $stmt->bind_param("i", $current_folder);
} else {
    $stmt = $conn->prepare("SELECT * FROM folders WHERE parent_id IS NULL ORDER BY folder_year DESC, folder_name");
}
$stmt->execute();
$direct_folders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get files in current directory with filters
$files = getFilesInFolder($conn, $current_folder, $search_term, $year_filter, $month_filter);

// Get unique years for filter dropdown
$available_years = getUniqueYears($conn);

// Get complete folder tree for sidebar
$folder_tree = getFolderTree($conn);

// Get folder path for breadcrumb
$folder_path = $current_folder ? getFolderPath($conn, $current_folder) : [];

// Get stats
$stats = [];
$stats['total_folders'] = $conn->query("SELECT COUNT(*) as count FROM folders")->fetch_assoc()['count'];
$stats['total_files'] = $conn->query("SELECT COUNT(*) as count FROM files")->fetch_assoc()['count'];
$size_result = $conn->query("SELECT SUM(file_size) as total FROM files")->fetch_assoc();
$stats['total_size'] = $size_result['total'] ?? 0;

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes === null || $bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Recursive function to display folder tree in sidebar
function displayFolderTree($folders, $current_folder, $months) {
    foreach ($folders as $folder) {
        $is_active = ($current_folder == $folder['id']);
        $has_children = !empty($folder['children']);
        $month_display = isset($folder['month']) && !empty($folder['month']) ? ' - ' . $months[$folder['month']] : '';
        ?>
        <li class="folder-tree-item" data-level="<?php echo $folder['level']; ?>">
            <div class="folder-item <?php echo $is_active ? 'active' : ''; ?>" 
                 style="margin-left: <?php echo $folder['level'] * 20; ?>px;">
                <?php if ($has_children): ?>
                    <span class="folder-expander" onclick="toggleSubfolders(<?php echo $folder['id']; ?>)">
                        <i class="fas fa-chevron-right" id="expander-<?php echo $folder['id']; ?>"></i>
                    </span>
                <?php else: ?>
                    <span class="folder-expander-placeholder"></span>
                <?php endif; ?>
                
                <a href="files.php?folder=<?php echo $folder['id']; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?><?php echo !empty($year_filter) ? '&year='.$year_filter : ''; ?>" class="folder-link">
                    <i class="fas fa-folder folder-icon"></i>
                    <span class="folder-name-text"><?php echo htmlspecialchars($folder['folder_name'] . $month_display); ?></span>
                    <?php if ($folder['file_count'] > 0): ?>
                        <span class="file-count-badge"><?php echo $folder['file_count']; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <?php if ($has_children): ?>
                <ul class="subfolder-list" id="subfolder-<?php echo $folder['id']; ?>" style="display: none;">
                    <?php displayFolderTree($folder['children'], $current_folder, $months); ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
    }
}

$months = getMonths();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Archive - Philippine National</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/files.css">
    <style>
        /* Additional styles for hierarchical display and collapsible sections */
        
        /* ===== FOLDER TREE STYLES ===== */
        .folder-tree {
            list-style: none;
            padding: 0;
        }

        .folder-tree-item {
            list-style: none;
            margin: 2px 0;
        }

        .folder-item {
            display: flex;
            align-items: center;
            padding: 8px;
            border-radius: 5px;
            transition: all 0.3s;
            background: transparent;
        }

        .folder-item:hover {
            background: #e3f2fd;
        }

        .folder-item.active {
            background: #bbdefb;
            border-left: 3px solid #0038a8;
        }

        .folder-expander {
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
            transition: all 0.3s;
            margin-right: 5px;
        }

        .folder-expander:hover {
            color: #0038a8;
            transform: scale(1.1);
        }

        .folder-expander i {
            font-size: 12px;
            transition: transform 0.3s;
        }

        .folder-expander.expanded i {
            transform: rotate(90deg);
        }

        .folder-expander-placeholder {
            width: 24px;
            margin-right: 5px;
        }

        .folder-link {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #333;
            flex: 1;
        }

        .folder-icon {
            color: #ffc107;
            font-size: 1.1rem;
        }

        .folder-name-text {
            font-size: 14px;
            font-weight: 500;
        }

        .file-count-badge {
            background: #0038a8;
            color: white;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
        }

        .subfolder-list {
            list-style: none;
            padding-left: 24px;
            margin: 2px 0;
        }

        /* ===== COLLAPSIBLE SECTIONS STYLES ===== */
        .collapsible-section {
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }

        .section-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            border-bottom: 2px solid #0038a8;
        }

        .section-header:hover {
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
        }

        .section-header h2 {
            color: #0038a8;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2rem;
            margin: 0;
        }

        .section-header .toggle-icon {
            font-size: 1.2rem;
            color: #0038a8;
            transition: transform 0.3s ease;
        }

        .section-header.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }

        .section-content {
            transition: max-height 0.3s ease-out, padding 0.3s ease;
            overflow: hidden;
            background: white;
        }

        .section-content.collapsed {
            max-height: 0;
            padding: 0 20px;
        }

        .section-content.expanded {
            max-height: 2000px;
            padding: 20px;
        }

        /* ===== BREADCRUMB STYLES ===== */
        .breadcrumb {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .breadcrumb-item:hover {
            background: #f0f2f5;
            color: #0038a8;
        }

        .breadcrumb-item.active {
            color: #0038a8;
            font-weight: 600;
            background: #e3f2fd;
        }

        .breadcrumb-separator {
            color: #ccc;
        }

        /* ===== CURRENT FOLDER INFO ===== */
        .current-folder-info {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .folder-icon-large {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #ffc107, #ffb300);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
        }

        .folder-details h2 {
            color: #0038a8;
            margin-bottom: 5px;
        }

        .folder-details p {
            color: #666;
            font-size: 14px;
        }

        .folder-stats {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .folder-stat {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
            font-size: 13px;
        }

        /* ===== FILTER BAR STYLES ===== */
        .filter-bar {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 2;
            min-width: 200px;
        }

        .search-box input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #0038a8;
            box-shadow: 0 0 0 3px rgba(0,56,168,0.1);
        }

        .year-filter {
            flex: 1;
            min-width: 150px;
        }

        .year-filter select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        .year-filter select:focus {
            outline: none;
            border-color: #0038a8;
        }

        .filter-btn {
            padding: 10px 20px;
            background: #0038a8;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-btn:hover {
            background: #00267a;
            transform: translateY(-2px);
        }

        .clear-btn {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .clear-btn:hover {
            background: #5a6268;
        }

        .active-filters {
            margin-top: 10px;
            padding: 10px;
            background: #e3f2fd;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-tag {
            background: #0038a8;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .filter-tag a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            margin-left: 5px;
        }

        .filter-tag a:hover {
            color: #ffc107;
        }

        /* ===== PARENT FOLDER INFO IN MODAL ===== */
        .parent-folder-info {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 3px solid #0038a8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .parent-folder-info i {
            color: #0038a8;
        }

        .parent-folder-info strong {
            color: #0038a8;
        }

        /* ===== MONTH SELECTION STYLES ===== */
        .month-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        .month-select:focus {
            outline: none;
            border-color: #0038a8;
        }

        .month-badge {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 5px;
        }

        /* ===== EMPTY STATE ===== */
        .empty-folder {
            text-align: center;
            padding: 3rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 0;
        }

        .empty-folder i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        .empty-folder h3 {
            color: #666;
            margin-bottom: 0.5rem;
        }

        .empty-folder p {
            color: #999;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-content.expanded .folders-grid,
        .section-content.expanded .files-table {
            animation: slideDown 0.3s ease;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .folder-item {
                padding: 6px;
            }
            
            .folder-name-text {
                font-size: 13px;
            }
            
            .subfolder-list {
                padding-left: 15px;
            }

            .folder-stats {
                flex-direction: column;
                gap: 10px;
            }

            .section-header h2 {
                font-size: 1rem;
            }

            .filter-bar {
                flex-direction: column;
                width: 100%;
            }

            .search-box,
            .year-filter {
                width: 100%;
            }

            .filter-btn,
            .clear-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-brand">
            <i class="fas fa-archive"></i>
            <span>Philippine National - File Archive</span>
        </div>
        <div class="nav-actions">
            <a href="homepage.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <div class="container">
        <!-- Success Message -->
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Folders</h3>
                    <p><?php echo $stats['total_folders']; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Files</h3>
                    <p><?php echo $stats['total_files']; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Size</h3>
                    <p><?php echo formatFileSize($stats['total_size']); ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-sitemap"></i>
                </div>
                <div class="stat-info">
                    <h3>Current Path</h3>
                    <p><?php echo count($folder_path); ?> levels</p>
                </div>
            </div>
        </div>

        <!-- Toolbar with Search and Year Filter -->
        <div class="toolbar">
            <div class="toolbar-actions">
                <button class="toolbar-btn btn-primary" onclick="openCreateFolderModal()">
                    <i class="fas fa-folder-plus"></i> Create Folder
                </button>
                <button class="toolbar-btn btn-success" onclick="openUploadFileModal()">
                    <i class="fas fa-cloud-upload-alt"></i> Upload Files
                </button>
            </div>
            
            <form method="GET" action="" class="filter-bar" id="filterForm">
                <input type="hidden" name="folder" value="<?php echo $current_folder; ?>">
                
                <div class="search-box">
                    <input 
                        type="text" 
                        name="search" 
                        id="searchInput" 
                        placeholder="Search by name, description, or tags..." 
                        value="<?php echo htmlspecialchars($search_term); ?>"
                        autocomplete="off"
                    >
                </div>
                
                <div class="year-filter">
                    <select name="year" id="yearSelect">
                        <option value="">All Years</option>
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo $year['document_year']; ?>" 
                                <?php echo $year_filter == $year['document_year'] ? 'selected' : ''; ?>>
                                <?php echo $year['document_year']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                
                <?php if (!empty($search_term) || !empty($year_filter)): ?>
                    <a href="files.php?folder=<?php echo $current_folder; ?>" class="clear-btn">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Active Filters Display -->
        <?php if (!empty($search_term) || !empty($year_filter)): ?>
            <div class="active-filters">
                <i class="fas fa-filter" style="color: #0038a8;"></i>
                <span style="color: #666;">Active Filters:</span>
                
                <?php if (!empty($search_term)): ?>
                    <span class="filter-tag">
                        <i class="fas fa-search"></i> "<?php echo htmlspecialchars($search_term); ?>"
                        <a href="?folder=<?php echo $current_folder; ?><?php echo !empty($year_filter) ? '&year='.$year_filter : ''; ?>">×</a>
                    </span>
                <?php endif; ?>
                
                <?php if (!empty($year_filter)): ?>
                    <span class="filter-tag">
                        <i class="fas fa-calendar"></i> Year: <?php echo $year_filter; ?>
                        <a href="?folder=<?php echo $current_folder; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>">×</a>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb">
            <a href="files.php?<?php echo !empty($search_term) ? 'search='.urlencode($search_term) : ''; ?><?php echo !empty($year_filter) ? '&year='.$year_filter : ''; ?>" class="breadcrumb-item <?php echo !$current_folder ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> My Drive
            </a>
            <?php foreach ($folder_path as $index => $folder): ?>
                <span class="breadcrumb-separator">/</span>
                <a href="files.php?folder=<?php echo $folder['id']; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?><?php echo !empty($year_filter) ? '&year='.$year_filter : ''; ?>" 
                   class="breadcrumb-item <?php echo ($index == count($folder_path) - 1) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($folder['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Current Folder Info -->
        <?php if ($current_folder_details): ?>
        <div class="current-folder-info">
            <div class="folder-icon-large">
                <i class="fas fa-folder"></i>
            </div>
            <div class="folder-details">
                <h2><?php echo htmlspecialchars($current_folder_details['folder_name']); ?></h2>
                <?php if (!empty($current_folder_details['description'])): ?>
                    <p><?php echo htmlspecialchars($current_folder_details['description']); ?></p>
                <?php endif; ?>
                <div class="folder-stats">
                    <span class="folder-stat">
                        <i class="fas fa-calendar"></i> Year: <?php echo $current_folder_details['folder_year']; ?>
                    </span>
                    <?php if (!empty($current_folder_details['month'])): ?>
                    <span class="folder-stat">
                        <i class="fas fa-calendar-alt"></i> Month: <?php echo $months[$current_folder_details['month']]; ?>
                    </span>
                    <?php endif; ?>
                    <span class="folder-stat">
                        <i class="fas fa-folder"></i> Subfolders: <?php echo count($direct_folders); ?>
                    </span>
                    <span class="folder-stat">
                        <i class="fas fa-file"></i> Files: <?php echo count($files); ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Sidebar with Hierarchical Folder Tree -->
            <div class="sidebar">
                <h3 class="sidebar-title">
                    <i class="fas fa-folder-tree"></i> Folder Structure
                </h3>
                <ul class="folder-tree">
                    <?php displayFolderTree($folder_tree, $current_folder, $months); ?>
                </ul>
            </div>

            <!-- Main Content with Collapsible Sections -->
            <div class="main-content">
                <!-- Subfolders Section (Collapsible) -->
                <div class="collapsible-section">
                    <div class="section-header" onclick="toggleMainSection('foldersSection')">
                        <h2>
                            <i class="fas fa-folder-open"></i> 
                            Subfolders (<?php echo count($direct_folders); ?>)
                        </h2>
                        <i class="fas fa-chevron-down toggle-icon" id="foldersToggle"></i>
                    </div>
                    <div class="section-content expanded" id="foldersSection">
                        <?php if (empty($direct_folders)): ?>
                            <div class="empty-folder">
                                <i class="fas fa-folder-open"></i>
                                <h3>No subfolders in this location</h3>
                                <p>Click "Create Folder" to create a new folder</p>
                            </div>
                        <?php else: ?>
                            <div class="folders-grid">
                                <?php foreach ($direct_folders as $folder): ?>
                                    <div class="folder-card" onclick="window.location.href='files.php?folder=<?php echo $folder['id']; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?><?php echo !empty($year_filter) ? '&year='.$year_filter : ''; ?>'">
                                        <div class="folder-actions">
                                            <button class="folder-action-btn" onclick="event.stopPropagation(); openRenameFolderModal(<?php echo $folder['id']; ?>, '<?php echo htmlspecialchars($folder['folder_name']); ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="folder-action-btn" onclick="event.stopPropagation(); deleteFolder(<?php echo $folder['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        <i class="fas fa-folder folder-icon"></i>
                                        <div class="folder-name"><?php echo htmlspecialchars($folder['folder_name']); ?></div>
                                        <div class="folder-meta">
                                            <i class="fas fa-calendar"></i> <?php echo $folder['folder_year']; ?>
                                            <?php if (!empty($folder['month'])): ?>
                                                <span class="month-badge"><?php echo $months[$folder['month']]; ?></span>
                                            <?php endif; ?>
                                            <?php 
                                            $file_count = getFileCount($conn, $folder['id']);
                                            if ($file_count > 0): 
                                            ?>
                                                <span class="badge badge-year" style="margin-left: 8px;"><?php echo $file_count; ?> files</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Files Section (Collapsible) -->
                <div class="collapsible-section">
                    <div class="section-header" onclick="toggleMainSection('filesSection')">
                        <h2>
                            <i class="fas fa-file"></i> 
                            Files (<?php echo count($files); ?>)
                        </h2>
                        <i class="fas fa-chevron-down toggle-icon" id="filesToggle"></i>
                    </div>
                    <div class="section-content expanded" id="filesSection">
                        <?php if (empty($files)): ?>
                            <div class="empty-folder">
                                <i class="fas fa-file"></i>
                                <h3>No files in this location</h3>
                                <?php if (!empty($search_term) || !empty($year_filter)): ?>
                                    <p>No files match your search criteria. <a href="?folder=<?php echo $current_folder; ?>" style="color: #0038a8;">Clear filters</a></p>
                                <?php else: ?>
                                    <p>Click "Upload Files" to upload your first file</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <table class="files-table" id="filesTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Year</th>
                                        <th>Category</th>
                                        <th>Size</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($files as $file): ?>
                                        <tr class="file-row">
                                            <td>
                                                <div class="file-info">
                                                    <i class="fas fa-file file-icon"></i>
                                                    <div class="file-details">
                                                        <span class="file-name"><?php echo htmlspecialchars($file['document_name']); ?></span>
                                                        <span class="file-meta">
                                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($file['original_name']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge badge-year"><?php echo $file['document_year']; ?></span></td>
                                            <td>
                                                <?php if (!empty($file['document_category'])): ?>
                                                    <span class="badge badge-category"><?php echo htmlspecialchars($file['document_category']); ?></span>
                                                <?php else: ?>
                                                    <span>-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatFileSize($file['file_size']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($file['uploaded_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="download.php?id=<?php echo $file['id']; ?>" class="action-btn btn-download" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <button class="action-btn btn-view" title="View Details" onclick="viewFileDetails(<?php echo $file['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="action-btn btn-delete" title="Delete" onclick="deleteFile(<?php echo $file['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Folder Modal -->
    <div class="modal" id="createFolderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-folder-plus"></i> Create New Folder</h3>
                <button class="modal-close" onclick="closeCreateFolderModal()">&times;</button>
            </div>
            <form method="POST" action="" id="createFolderForm">
                <div class="modal-body">
                    <input type="hidden" name="parent_id" id="parent_id" value="<?php echo $current_folder; ?>">
                    
                    <!-- Parent Folder Info (will be shown via JavaScript) -->
                    <div id="parentFolderInfo" class="parent-folder-info" style="display: none;">
                        <i class="fas fa-info-circle"></i>
                        <span id="parentFolderMessage"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="folder_name">Folder Name *</label>
                        <input type="text" id="folder_name" name="folder_name" required placeholder="Enter folder name">
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Enter folder description"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="folder_year">Year *</label>
                        <select id="folder_year" name="folder_year" required>
                            <option value="">Select Year</option>
                            <?php for ($year = date('Y'); $year >= 1900; $year--): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                        <small id="yearHelpText" style="color: #666; display: block; margin-top: 5px;"></small>
                    </div>

                    <div class="form-group" id="monthGroup">
                        <label for="month">Month (Optional for subfolders)</label>
                        <select id="month" name="month" class="month-select">
                            <option value="">Select Month (Optional)</option>
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small id="monthHelpText" style="color: #666; display: block; margin-top: 5px;">
                            Select a month for this subfolder (optional)
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="toolbar-btn btn-outline" onclick="closeCreateFolderModal()">Cancel</button>
                    <button type="submit" name="create_folder" class="toolbar-btn btn-primary">Create Folder</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload File Modal -->
    <div class="modal" id="uploadFileModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-cloud-upload-alt"></i> Upload Files</h3>
                <button class="modal-close" onclick="closeUploadFileModal()">&times;</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="folder_id" value="<?php echo $current_folder; ?>">

                    <div class="form-group">
                        <label for="file">Select File *</label>
                        <input type="file" id="file" name="file" required>
                    </div>

                    <div class="form-group">
                        <label for="document_name">Document Name *</label>
                        <input type="text" id="document_name" name="document_name" required placeholder="Enter document name">
                    </div>

                    <div class="form-group">
                        <label for="file_description">Description</label>
                        <textarea id="file_description" name="description" placeholder="Enter file description"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="document_year">Year *</label>
                        <select id="document_year" name="document_year" required>
                            <option value="">Select Year</option>
                            <?php for ($year = date('Y'); $year >= 1900; $year--): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="document_category">Category</label>
                        <input type="text" id="document_category" name="document_category" placeholder="e.g., Financial, Legal, Personal">
                    </div>

                    <div class="form-group">
                        <label for="tags">Tags</label>
                        <input type="text" id="tags" name="tags" placeholder="Enter tags separated by commas">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="toolbar-btn btn-outline" onclick="closeUploadFileModal()">Cancel</button>
                    <button type="submit" name="upload_file" class="toolbar-btn btn-success">Upload File</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions for Create Folder
        function openCreateFolderModal() {
            const modal = document.getElementById('createFolderModal');
            modal.classList.add('active');
            
            // Get parent folder ID
            const parentId = document.getElementById('parent_id').value;
            
            // If we're in a subfolder, fetch parent details and auto-set year
            if (parentId) {
                fetchParentFolderDetails(parentId);
                // Show month selection for subfolders
                document.getElementById('monthGroup').style.display = 'block';
            } else {
                // If at root, show normal year selection and hide month
                document.getElementById('parentFolderInfo').style.display = 'none';
                document.getElementById('folder_year').disabled = false;
                document.getElementById('yearHelpText').textContent = '';
                document.getElementById('monthGroup').style.display = 'none';
                document.getElementById('month').value = '';
            }
        }

        function closeCreateFolderModal() {
            document.getElementById('createFolderModal').classList.remove('active');
            // Reset form
            document.getElementById('createFolderForm').reset();
            document.getElementById('parentFolderInfo').style.display = 'none';
            document.getElementById('folder_year').disabled = false;
            document.getElementById('monthGroup').style.display = 'block';
        }

        // Fetch parent folder details via AJAX
        function fetchParentFolderDetails(parentId) {
            fetch(`files.php?ajax=get_parent_details&parent_id=${parentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const parent = data.data;
                        const parentInfo = document.getElementById('parentFolderInfo');
                        const parentMessage = document.getElementById('parentFolderMessage');
                        const yearSelect = document.getElementById('folder_year');
                        
                        // Show parent folder info
                        parentMessage.innerHTML = `Creating subfolder inside <strong>${parent.folder_name}</strong> (Year: ${parent.folder_year})`;
                        parentInfo.style.display = 'flex';
                        
                        // Auto-select and disable year selection to match parent
                        yearSelect.value = parent.folder_year;
                        yearSelect.disabled = true;
                        
                        // Add helpful message
                        document.getElementById('yearHelpText').textContent = 
                            `Year is automatically set to match parent folder (${parent.folder_year})`;
                        
                        // Show month selection for subfolders
                        document.getElementById('monthGroup').style.display = 'block';
                        document.getElementById('monthHelpText').textContent = 
                            'Select a month for this subfolder (optional)';
                    }
                })
                .catch(error => {
                    console.error('Error fetching parent details:', error);
                });
        }

        // Modal functions for Upload File
        function openUploadFileModal() {
            document.getElementById('uploadFileModal').classList.add('active');
        }

        function closeUploadFileModal() {
            document.getElementById('uploadFileModal').classList.remove('active');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('createFolderModal');
            const uploadModal = document.getElementById('uploadFileModal');
            
            if (event.target == createModal) {
                createModal.classList.remove('active');
                // Reset form when closing
                document.getElementById('createFolderForm').reset();
                document.getElementById('parentFolderInfo').style.display = 'none';
                document.getElementById('folder_year').disabled = false;
                document.getElementById('monthGroup').style.display = 'block';
            }
            if (event.target == uploadModal) {
                uploadModal.classList.remove('active');
            }
        }

        // Delete folder function
        function deleteFolder(folderId) {
            if (confirm('Are you sure you want to delete this folder? All files inside will be moved to root.')) {
                window.location.href = 'delete_folder.php?id=' + folderId;
            }
        }

        // Delete file function
        function deleteFile(fileId) {
            if (confirm('Are you sure you want to delete this file?')) {
                window.location.href = 'delete_file.php?id=' + fileId;
            }
        }

        // View file details
        function viewFileDetails(fileId) {
            window.location.href = 'view_file.php?id=' + fileId;
        }

        // Rename folder
        function openRenameFolderModal(folderId, currentName) {
            const newName = prompt('Enter new folder name:', currentName);
            if (newName && newName !== currentName) {
                window.location.href = 'rename_folder.php?id=' + folderId + '&name=' + encodeURIComponent(newName);
            }
        }

        // Auto-submit form when year changes
        document.getElementById('yearSelect').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        // Debounced search
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });

        // Toggle subfolders in sidebar
        function toggleSubfolders(folderId) {
            const subfolderList = document.getElementById('subfolder-' + folderId);
            const expander = document.getElementById('expander-' + folderId);
            
            if (subfolderList.style.display === 'none') {
                subfolderList.style.display = 'block';
                expander.style.transform = 'rotate(90deg)';
                expander.parentElement.classList.add('expanded');
                
                // Save state to localStorage
                localStorage.setItem('subfolder_' + folderId, 'expanded');
            } else {
                subfolderList.style.display = 'none';
                expander.style.transform = 'rotate(0deg)';
                expander.parentElement.classList.remove('expanded');
                
                // Save state to localStorage
                localStorage.setItem('subfolder_' + folderId, 'collapsed');
            }
        }

        // Toggle main sections (Folders/Files)
        function toggleMainSection(sectionId) {
            const section = document.getElementById(sectionId);
            const toggleIcon = document.getElementById(sectionId === 'foldersSection' ? 'foldersToggle' : 'filesToggle');
            const header = section.previousElementSibling;
            
            if (section.classList.contains('expanded')) {
                section.classList.remove('expanded');
                section.classList.add('collapsed');
                header.classList.add('collapsed');
                toggleIcon.style.transform = 'rotate(-90deg)';
                
                // Save state to localStorage
                localStorage.setItem(sectionId, 'collapsed');
            } else {
                section.classList.remove('collapsed');
                section.classList.add('expanded');
                header.classList.remove('collapsed');
                toggleIcon.style.transform = 'rotate(0deg)';
                
                // Save state to localStorage
                localStorage.setItem(sectionId, 'expanded');
            }
        }

        // Load saved states from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            // Load main sections states
            const sections = ['foldersSection', 'filesSection'];
            
            sections.forEach(sectionId => {
                const savedState = localStorage.getItem(sectionId);
                const section = document.getElementById(sectionId);
                const toggleIcon = document.getElementById(sectionId === 'foldersSection' ? 'foldersToggle' : 'filesToggle');
                const header = section.previousElementSibling;
                
                if (savedState === 'collapsed') {
                    section.classList.remove('expanded');
                    section.classList.add('collapsed');
                    header.classList.add('collapsed');
                    toggleIcon.style.transform = 'rotate(-90deg)';
                } else {
                    section.classList.remove('collapsed');
                    section.classList.add('expanded');
                    header.classList.remove('collapsed');
                    toggleIcon.style.transform = 'rotate(0deg)';
                }
            });

            // Auto-expand folder tree to show current folder
            <?php if ($current_folder): ?>
            const folderPath = <?php echo json_encode(array_column($folder_path, 'id')); ?>;
            folderPath.forEach(function(folderId) {
                const subfolderList = document.getElementById('subfolder-' + folderId);
                const expander = document.getElementById('expander-' + folderId);
                if (subfolderList && expander) {
                    subfolderList.style.display = 'block';
                    expander.style.transform = 'rotate(90deg)';
                    expander.parentElement.classList.add('expanded');
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>
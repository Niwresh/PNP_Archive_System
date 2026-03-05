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

// Handle folder creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_folder'])) {
    $folder_name = trim($_POST['folder_name']);
    $description = trim($_POST['description']);
    $folder_year = (int)$_POST['folder_year'];
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    if (!empty($folder_name) && !empty($folder_year)) {
        $stmt = $conn->prepare("INSERT INTO folders (folder_name, description, folder_year, parent_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $folder_name, $description, $folder_year, $parent_id);
        
        if ($stmt->execute()) {
            header("Location: files.php?folder=" . ($parent_id ?: '') . "&success=Folder created successfully");
            exit();
        }
    }
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

// Get folders in current directory
if ($current_folder) {
    $stmt = $conn->prepare("SELECT * FROM folders WHERE parent_id = ? ORDER BY folder_name");
    $stmt->bind_param("i", $current_folder);
} else {
    $stmt = $conn->prepare("SELECT * FROM folders WHERE parent_id IS NULL ORDER BY folder_name");
}
$stmt->execute();
$folders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get files in current directory
if ($current_folder) {
    $stmt = $conn->prepare("SELECT * FROM files WHERE folder_id = ? ORDER BY uploaded_at DESC");
    $stmt->bind_param("i", $current_folder);
} else {
    $stmt = $conn->prepare("SELECT * FROM files WHERE folder_id IS NULL ORDER BY uploaded_at DESC");
}
$stmt->execute();
$files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get folder path for breadcrumb
$folder_path = $current_folder ? getFolderPath($conn, $current_folder) : [];

// Get all folders for move/copy operations
$all_folders = $conn->query("SELECT id, folder_name, parent_id FROM folders ORDER BY folder_name")->fetch_all(MYSQLI_ASSOC);

// Get stats
$stats = [];
$stats['total_folders'] = $conn->query("SELECT COUNT(*) as count FROM folders")->fetch_assoc()['count'];
$stats['total_files'] = $conn->query("SELECT COUNT(*) as count FROM files")->fetch_assoc()['count'];
$size_result = $conn->query("SELECT SUM(file_size) as total FROM files")->fetch_assoc();
$stats['total_size'] = $size_result['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Archive - Philippine National</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        /* Navbar */
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
            font-size: 1.2rem;
            font-weight: 600;
        }

        .nav-brand i {
            font-size: 1.5rem;
        }

        .nav-actions {
            display: flex;
            gap: 15px;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            border-color: white;
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        /* Success Message */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Toolbar */
        .toolbar {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .toolbar-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .toolbar-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-primary {
            background: #0038a8;
            color: white;
        }

        .btn-primary:hover {
            background: #00267a;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: white;
            border: 1px solid #ddd;
            color: #333;
        }

        .btn-outline:hover {
            background: #f8f9fa;
            border-color: #0038a8;
        }

        /* Breadcrumb */
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
        }

        .breadcrumb-item:hover {
            color: #0038a8;
        }

        .breadcrumb-item.active {
            color: #0038a8;
            font-weight: 600;
        }

        .breadcrumb-separator {
            color: #ccc;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #0038a8, #00267a);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .stat-info h3 {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0038a8;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
        }

        /* Sidebar */
        .sidebar {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
        }

        .sidebar-title {
            color: #0038a8;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #0038a8;
        }

        .folder-tree {
            list-style: none;
        }

        .folder-tree li {
            margin-bottom: 5px;
        }

        .folder-tree a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .folder-tree a:hover,
        .folder-tree a.active {
            background: #f0f2f5;
            color: #0038a8;
        }

        .folder-tree .folder-icon {
            color: #ffc107;
        }

        /* Main Content */
        .main-content {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #0038a8;
        }

        .section-header h2 {
            color: #0038a8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Folders Grid */
        .folders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 2rem;
        }

        .folder-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .folder-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,56,168,0.1);
            border-color: #0038a8;
        }

        .folder-card .folder-icon {
            font-size: 2rem;
            color: #ffc107;
            margin-bottom: 0.5rem;
        }

        .folder-card .folder-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .folder-card .folder-meta {
            font-size: 0.8rem;
            color: #666;
        }

        .folder-card .folder-actions {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            display: none;
            gap: 5px;
        }

        .folder-card:hover .folder-actions {
            display: flex;
        }

        .folder-action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: white;
            border: 1px solid #ddd;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
        }

        .folder-action-btn:hover {
            background: #0038a8;
            color: white;
            border-color: #0038a8;
        }

        /* Files Table */
        .files-table {
            width: 100%;
            border-collapse: collapse;
        }

        .files-table th {
            text-align: left;
            padding: 1rem;
            background: #f8f9fa;
            color: #0038a8;
            font-weight: 600;
        }

        .files-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .files-table tr:hover {
            background: #f8f9fa;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-icon {
            font-size: 1.5rem;
            color: #0038a8;
        }

        .file-details {
            display: flex;
            flex-direction: column;
        }

        .file-name {
            font-weight: 600;
            color: #333;
        }

        .file-meta {
            font-size: 0.8rem;
            color: #666;
        }

        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-year {
            background: #0038a8;
            color: white;
        }

        .badge-category {
            background: #e9ecef;
            color: #495057;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .btn-download { background: #28a745; }
        .btn-view { background: #17a2b8; }
        .btn-move { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; }

        .action-btn:hover {
            transform: translateY(-2px);
            filter: brightness(110%);
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: #0038a8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .modal-close:hover {
            color: #dc3545;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0038a8;
            box-shadow: 0 0 0 3px rgba(0,56,168,0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .navbar {
                flex-direction: column;
                gap: 10px;
            }

            .nav-actions {
                width: 100%;
                justify-content: center;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .toolbar-actions {
                justify-content: center;
            }

            .folders-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
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
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="stat-info">
                    <h3>Current Folder</h3>
                    <p><?php echo $current_folder ? 'Subfolder' : 'Root'; ?></p>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="toolbar-actions">
                <button class="toolbar-btn btn-primary" onclick="openCreateFolderModal()">
                    <i class="fas fa-folder-plus"></i> Create Folder
                </button>
                <button class="toolbar-btn btn-success" onclick="openUploadFileModal()">
                    <i class="fas fa-cloud-upload-alt"></i> Upload Files
                </button>
            </div>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search files..." style="padding: 10px; border: 1px solid #ddd; border-radius: 5px; width: 250px;" onkeyup="searchFiles()">
            </div>
        </div>

        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="files.php" class="breadcrumb-item">
                <i class="fas fa-home"></i> My Drive
            </a>
            <?php foreach ($folder_path as $folder): ?>
                <span class="breadcrumb-separator">/</span>
                <a href="files.php?folder=<?php echo $folder['id']; ?>" class="breadcrumb-item">
                    <?php echo htmlspecialchars($folder['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Sidebar -->
            <div class="sidebar">
                <h3 class="sidebar-title">
                    <i class="fas fa-folder-tree"></i> Folders
                </h3>
                <ul class="folder-tree">
                    <li>
                        <a href="files.php" class="<?php echo !$current_folder ? 'active' : ''; ?>">
                            <i class="fas fa-folder folder-icon"></i> My Drive
                        </a>
                    </li>
                    <?php foreach ($all_folders as $folder): ?>
                        <li style="margin-left: 20px;">
                            <a href="files.php?folder=<?php echo $folder['id']; ?>" 
                               class="<?php echo $current_folder == $folder['id'] ? 'active' : ''; ?>">
                                <i class="fas fa-folder folder-icon"></i> 
                                <?php echo htmlspecialchars($folder['folder_name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Folders Section -->
                <div class="section-header">
                    <h2><i class="fas fa-folder-open"></i> Folders</h2>
                    <span><?php echo count($folders); ?> folders</span>
                </div>

                <?php if (empty($folders)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>No folders yet</h3>
                        <p>Click "Create Folder" to create your first folder</p>
                    </div>
                <?php else: ?>
                    <div class="folders-grid">
                        <?php foreach ($folders as $folder): ?>
                            <div class="folder-card" onclick="window.location.href='files.php?folder=<?php echo $folder['id']; ?>'">
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
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Files Section -->
                <div class="section-header" style="margin-top: 2rem;">
                    <h2><i class="fas fa-file"></i> Files</h2>
                    <span><?php echo count($files); ?> files</span>
                </div>

                <?php if (empty($files)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file"></i>
                        <h3>No files yet</h3>
                        <p>Click "Upload Files" to upload your first file</p>
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

    <!-- Create Folder Modal -->
    <div class="modal" id="createFolderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-folder-plus"></i> Create New Folder</h3>
                <button class="modal-close" onclick="closeCreateFolderModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="parent_id" value="<?php echo $current_folder; ?>">
                    
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
            document.getElementById('createFolderModal').classList.add('active');
        }

        function closeCreateFolderModal() {
            document.getElementById('createFolderModal').classList.remove('active');
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

        // Search files function
        function searchFiles() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('filesTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const fileName = row.getElementsByClassName('file-name')[0];
                
                if (fileName) {
                    const textValue = fileName.textContent || fileName.innerText;
                    if (textValue.toUpperCase().indexOf(filter) > -1) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            }
        }
    </script>
</body>
</html>

<?php
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
?>
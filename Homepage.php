<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once "PNP_Archive.php";

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Session timeout after 30 minutes
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit();
}
$_SESSION['login_time'] = time();

// Get parameters safely
$current_folder_id = isset($_GET['folder_id']) && is_numeric($_GET['folder_id']) ? intval($_GET['folder_id']) : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$year_filter = isset($_GET['year']) && is_numeric($_GET['year']) ? intval($_GET['year']) : '';
$semester_filter = isset($_GET['semester']) ? $_GET['semester'] : '';
$view = isset($_GET['view']) && in_array($_GET['view'], ['dashboard', 'drive', 'trash']) ? $_GET['view'] : 'drive';
$trash_filter = isset($_GET['trash_filter']) && in_array($_GET['trash_filter'], ['all', 'folders', 'files']) ? $_GET['trash_filter'] : 'all';

// Initialize variables
$current_folder = null;
$folder_path = [];
$folders = null;
$files = null;
$folders_list = [];

// Get current folder info
if ($current_folder_id && $view != 'trash') {
    $folder_query = $conn->query("SELECT * FROM folders WHERE id = $current_folder_id AND deleted_at IS NULL");
    if ($folder_query && $folder_query->num_rows > 0) {
        $current_folder = $folder_query->fetch_assoc();
        
        // Build folder path (breadcrumb)
        $path_id = $current_folder_id;
        while ($path_id) {
            $path_query = $conn->query("SELECT id, folder_name, parent_id FROM folders WHERE id = $path_id AND deleted_at IS NULL");
            if ($path_query && $path_row = $path_query->fetch_assoc()) {
                array_unshift($folder_path, $path_row);
                $path_id = $path_row['parent_id'];
            } else {
                break;
            }
        }
    }
}

// Get statistics for dashboard
$total_files_query = $conn->query("SELECT COUNT(*) as count FROM files WHERE deleted_at IS NULL");
$total_files = $total_files_query ? $total_files_query->fetch_assoc()['count'] : 0;

$total_folders_query = $conn->query("SELECT COUNT(*) as count FROM folders WHERE deleted_at IS NULL");
$total_folders = $total_folders_query ? $total_folders_query->fetch_assoc()['count'] : 0;

// Calculate total subfolders
$subfolders_query = $conn->query("SELECT COUNT(*) as count FROM folders WHERE parent_id IS NOT NULL AND deleted_at IS NULL");
$total_subfolders = $subfolders_query ? $subfolders_query->fetch_assoc()['count'] : 0;

// Calculate root folders
$root_folders_query = $conn->query("SELECT COUNT(*) as count FROM folders WHERE parent_id IS NULL AND deleted_at IS NULL");
$total_root_folders = $root_folders_query ? $root_folders_query->fetch_assoc()['count'] : 0;

// Get trash statistics
$trash_files_query = $conn->query("SELECT COUNT(*) as count FROM files WHERE deleted_at IS NOT NULL");
$trash_files = $trash_files_query ? $trash_files_query->fetch_assoc()['count'] : 0;

$trash_folders_query = $conn->query("SELECT COUNT(*) as count FROM folders WHERE deleted_at IS NOT NULL");
$trash_folders = $trash_folders_query ? $trash_folders_query->fetch_assoc()['count'] : 0;

// Calculate total storage used
$storage_used = calculateTotalStorage($conn);

// Define total storage limit (5GB)
$total_storage_limit = 5 * 1024 * 1024 * 1024; // 5GB in bytes

// Calculate storage percentage
$storage_percent = ($storage_used / $total_storage_limit) * 100;

// Ensure very small storage still shows on bar
if ($storage_percent > 0 && $storage_percent < 1) {
    $storage_percent = 1;
}

$storage_percent = min(100, round($storage_percent, 2));

// Determine storage bar color
$storage_bar_color = '#4caf50';
if ($storage_percent > 80) {
    $storage_bar_color = '#ff9800';
}
if ($storage_percent > 95) {
    $storage_bar_color = '#f44336';
}

// Format storage used
$storage_display = formatFileSize($storage_used);
$total_storage_display = '5 GB';
$remaining_display = formatFileSize($total_storage_limit - $storage_used);

// Get folder hierarchy statistics
$folder_hierarchy_query = $conn->query("
    SELECT 
        f1.id as folder_id,
        f1.folder_name,
        COUNT(f2.id) as subfolder_count
    FROM folders f1
    LEFT JOIN folders f2 ON f2.parent_id = f1.id AND f2.deleted_at IS NULL
    WHERE f1.deleted_at IS NULL
    GROUP BY f1.id
    ORDER BY subfolder_count DESC
    LIMIT 5
");

// Get distinct years for filter dropdown
$years_query = $conn->query("
    SELECT DISTINCT year FROM (
        SELECT year FROM folders WHERE year IS NOT NULL AND deleted_at IS NULL
        UNION
        SELECT year FROM files WHERE year IS NOT NULL AND deleted_at IS NULL
    ) AS all_years ORDER BY year DESC
");
$available_years = [];
if ($years_query && $years_query->num_rows > 0) {
    while ($year_row = $years_query->fetch_assoc()) {
        $available_years[] = $year_row['year'];
    }
}

// Build folder query for drive view
if ($view == 'drive') {

    // GLOBAL SEARCH
    if (!empty($search)) {
        $search_escaped = $conn->real_escape_string($search);
        
        $folder_query = "
            SELECT * FROM folders
            WHERE deleted_at IS NULL
            AND folder_name LIKE '%$search_escaped%'
            ORDER BY folder_name ASC
        ";
        $folders = $conn->query($folder_query);

        $file_query = "
            SELECT files.*, folders.folder_name
            FROM files
            LEFT JOIN folders ON files.folder_id = folders.id
            WHERE files.deleted_at IS NULL
            AND files.file_name LIKE '%$search_escaped%'
            ORDER BY files.uploaded_at DESC
        ";
        $files = $conn->query($file_query);
    }
    // NORMAL DRIVE VIEW
    else {
        $folder_query = "SELECT * FROM folders WHERE deleted_at IS NULL";

        if ($current_folder_id) {
            $folder_query .= " AND parent_id = $current_folder_id";
        } else {
            $folder_query .= " AND parent_id IS NULL";
        }

        if (!empty($year_filter)) {
            $folder_query .= " AND year = " . intval($year_filter);
            
            if (!empty($semester_filter)) {
                $months = getSemesterMonths($semester_filter);
                $folder_query .= " AND month BETWEEN " . $months[0] . " AND " . $months[1];
            }
        }

        $folder_query .= " ORDER BY folder_name ASC";
        $folders = $conn->query($folder_query);

        $file_query = "
            SELECT files.*, folders.folder_name
            FROM files
            LEFT JOIN folders ON files.folder_id = folders.id
            WHERE files.deleted_at IS NULL
        ";

        if ($current_folder_id) {
            $file_query .= " AND files.folder_id = $current_folder_id";
        } else {
            $file_query .= " AND files.folder_id IS NULL";
        }

        if (!empty($year_filter)) {
            $file_query .= " AND files.year = " . intval($year_filter);
            
            if (!empty($semester_filter)) {
                $months = getSemesterMonths($semester_filter);
                $file_query .= " AND files.month BETWEEN " . $months[0] . " AND " . $months[1];
            }
        }

        $file_query .= " ORDER BY files.uploaded_at DESC";
        $files = $conn->query($file_query);
    }
}
// Build query for trash view
elseif ($view == 'trash') {
    if ($trash_filter == 'folders') {
        $folders = $conn->query("SELECT * FROM folders WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
        $files = null;
    } elseif ($trash_filter == 'files') {
        $files = $conn->query("SELECT files.*, folders.folder_name FROM files LEFT JOIN folders ON files.folder_id = folders.id WHERE files.deleted_at IS NOT NULL ORDER BY files.deleted_at DESC");
        $folders = null;
    } else {
        $folders = $conn->query("SELECT * FROM folders WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
        $files = $conn->query("SELECT files.*, folders.folder_name FROM files LEFT JOIN folders ON files.folder_id = folders.id WHERE files.deleted_at IS NOT NULL ORDER BY files.deleted_at DESC");
    }
}

// Check if queries were successful
if (isset($folders) && !$folders) {
    die("Error in folder query: " . $conn->error);
}
if (isset($files) && !$files) {
    die("Error in files query: " . $conn->error);
}

// Get all folders for dropdown
$folders_list_query = $conn->query("SELECT * FROM folders WHERE deleted_at IS NULL ORDER BY folder_name ASC");
$folders_list = [];
if ($folders_list_query) {
    $folders_list = $folders_list_query->fetch_all(MYSQLI_ASSOC);
}

// Function to build folder tree for dropdown
function buildFolderTree($folders, $parent_id = null, $level = 0) {
    $tree = [];
    foreach ($folders as $folder) {
        if ($folder['parent_id'] == $parent_id) {
            $folder['level'] = $level;
            $tree[] = $folder;
            $tree = array_merge($tree, buildFolderTree($folders, $folder['id'], $level + 1));
        }
    }
    return $tree;
}

$folder_tree = buildFolderTree($folders_list);

// Get parent folder year if we're in a subfolder
$parent_year = null;
if ($current_folder_id) {
    $parent_year_query = $conn->query("SELECT year FROM folders WHERE id = $current_folder_id");
    if ($parent_year_query && $parent_year_query->num_rows > 0) {
        $parent_year = $parent_year_query->fetch_assoc()['year'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>PNP Archive - Google Drive Style</title>
    <link rel="stylesheet" href="css/Home.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Additional mobile optimizations */
        @media (max-width: 768px) {
            .file-modified::before, .file-size::before {
                content: attr(data-label) ': ';
                opacity: 0.7;
                margin-right: 5px;
                font-weight: normal;
            }
            
            .file-item {
                position: relative;
            }
            
            .file-actions {
                position: relative;
                z-index: 2;
            }
            
            .file-name a {
                max-width: calc(100% - 30px);
            }
        }
        
        /* Inheritance info styles */
        .inheritance-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
            color: #0d47a1;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .inheritance-info i {
            font-size: 18px;
            color: #2196f3;
        }
        
        .inheritance-info strong {
            color: #0d47a1;
        }
        
        .year-badge {
            background: #4caf50;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 5px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-archive"></i> PNP Archive</h2>
            </div>
            
            <ul class="sidebar-menu">
                <li class="<?php echo $view == 'dashboard' ? 'active' : ''; ?>">
                    <a href="Homepage.php?view=dashboard">
                        <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
                    </a>
                </li>
                <li class="<?php echo $view == 'drive' ? 'active' : ''; ?>">
                    <a href="Homepage.php?view=drive">
                        <i class="fas fa-folder"></i> <span>My Drive</span>
                    </a>
                </li>
                <li class="<?php echo $view == 'trash' ? 'active' : ''; ?>">
                    <a href="Homepage.php?view=trash">
                        <i class="fas fa-trash"></i> <span>Trash</span>
                        <?php if ($trash_files + $trash_folders > 0): ?>
                            <span class="trash-badge"><?php echo $trash_files + $trash_folders; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>

            <!-- Storage Info -->
            <div class="storage-info">
                <div class="storage-header">
                    <i class="fas fa-database"></i>
                    <span>Storage Overview</span>
                </div>
                
                <div class="storage-stats">
                    <span class="storage-used-text">Used: <?php echo $storage_display; ?></span>
                    <span class="storage-percent"><?php echo $storage_percent; ?>%</span>
                </div>
                
                <div class="storage-bar-container">
                    <div class="storage-bar">
                        <div class="storage-used-bar" style="width: <?php echo $storage_percent; ?>%; background-color: <?php echo $storage_bar_color; ?>;"></div>
                    </div>
                </div>
                
                <div class="storage-details">
                    <span>Total: <?php echo $total_storage_display; ?></span>
                    <span>Free: <?php echo $remaining_display; ?></span>
                </div>
                
                <?php if ($storage_percent > 80): ?>
                    <div class="storage-warning <?php echo $storage_percent > 95 ? 'storage-critical' : ''; ?>">
                        <i class="fas <?php echo $storage_percent > 95 ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'; ?>"></i>
                        <span>
                            <?php 
                            if ($storage_percent > 95) {
                                echo "Critical: Storage almost full!";
                            } elseif ($storage_percent > 90) {
                                echo "Warning: Very low storage space!";
                            } else {
                                echo "Notice: Storage running low";
                            }
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="breadcrumb">
                    <?php if ($view == 'trash'): ?>
                        <a href="Homepage.php?view=trash"><i class="fas fa-trash"></i> Trash</a>
                    <?php else: ?>
                        <a href="Homepage.php?view=drive"><i class="fas fa-home"></i> My Drive</a>
                        <?php foreach ($folder_path as $folder): ?>
                            <i class="fas fa-chevron-right"></i>
                            <a href="Homepage.php?view=drive&folder_id=<?php echo $folder['id']; ?>">
                                <?php echo htmlspecialchars($folder['folder_name']); ?>
                                <?php if (!empty($folder['year'])): ?>
                                    <span class="year-badge"><?php echo $folder['year']; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="user-info">
                    <span><?php echo htmlspecialchars($_SESSION['admin_email']); ?></span>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>

            <?php if ($view == 'dashboard'): ?>
                <!-- DASHBOARD VIEW -->
                <div class="dashboard-view">
                    <!-- STATISTICS -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $total_files; ?></div>
                            <div class="stat-label">Total Files</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $total_folders; ?></div>
                            <div class="stat-label">Total Folders</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $total_root_folders; ?></div>
                            <div class="stat-label">Root Folders</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $total_subfolders; ?></div>
                            <div class="stat-label">Sub Folders</div>
                        </div>
                    </div>

                    <!-- QUICK ACTIONS -->
                    <div class="report-area">
                        <div class="report-box">
                            <h3><i class="fas fa-chart-line"></i> Quick Actions</h3>
                            <div class="quick-actions">
                                <button onclick="openUploadModal()" class="quick-action-btn">
                                    <i class="fas fa-upload"></i>
                                    <span>Upload File</span>
                                </button>
                                <button onclick="openFolderModal()" class="quick-action-btn">
                                    <i class="fas fa-folder-plus"></i>
                                    <span>Create Folder</span>
                                </button>
                                <a href="Homepage.php?view=drive" class="quick-action-btn">
                                    <i class="fas fa-folder-open"></i>
                                    <span>Go to Drive</span>
                                </a>
                            </div>
                        </div>

                        <div class="report-box">
                            <h3><i class="fas fa-sitemap"></i> Folder Hierarchy</h3>
                            <div class="recent-files-list">
                                <?php if ($folder_hierarchy_query && $folder_hierarchy_query->num_rows > 0): ?>
                                    <?php while ($folder_stat = $folder_hierarchy_query->fetch_assoc()): ?>
                                        <div class="recent-file-item">
                                            <i class="fas fa-folder folder-icon"></i>
                                            <div class="recent-file-info">
                                                <span class="recent-file-name"><?php echo htmlspecialchars($folder_stat['folder_name']); ?></span>
                                                <span class="recent-file-location">
                                                    Subfolders: <?php echo $folder_stat['subfolder_count']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="no-recent-files">
                                        <p>No folder hierarchy data available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- SYSTEM INFO -->
                    <div class="system-info">
                        <div class="info-card">
                            <h4><i class="fas fa-info-circle"></i> System Status</h4>
                            <div class="info-item">
                                <span>Status:</span>
                                <span class="status-badge online"><i class="fas fa-circle"></i> Online</span>
                            </div>
                            <div class="info-item">
                                <span>Last Login:</span>
                                <span><?php echo date('M d, Y H:i', $_SESSION['login_time']); ?></span>
                            </div>
                        </div>

                        <div class="info-card">
                            <h4><i class="fas fa-trash"></i> Trash Summary</h4>
                            <div class="info-item">
                                <span>Files in Trash:</span>
                                <span><?php echo $trash_files; ?></span>
                            </div>
                            <div class="info-item">
                                <span>Folders in Trash:</span>
                                <span><?php echo $trash_folders; ?></span>
                            </div>
                            <div class="info-item">
                                <a href="Homepage.php?view=trash" class="quick-action-btn" style="width: 100%; justify-content: center; margin-top: 10px;">
                                    <i class="fas fa-trash"></i> View Trash
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($view == 'trash'): ?>
                <!-- TRASH VIEW -->
                <div class="drive-view">
                    <!-- Trash Header -->
                    <div class="filters-section">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                            <h3 style="color: white; margin: 0;">
                                <i class="fas fa-trash"></i> Trash Bin
                                <small style="font-size: 14px; opacity: 0.7; margin-left: 10px;">Items are automatically deleted after 30 days</small>
                            </h3>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <a href="Homepage.php?view=trash&trash_filter=all" class="filter-btn <?php echo $trash_filter == 'all' ? 'active' : ''; ?>">All</a>
                                <a href="Homepage.php?view=trash&trash_filter=folders" class="filter-btn <?php echo $trash_filter == 'folders' ? 'active' : ''; ?>">Folders</a>
                                <a href="Homepage.php?view=trash&trash_filter=files" class="filter-btn <?php echo $trash_filter == 'files' ? 'active' : ''; ?>">Files</a>
                                <button onclick="emptyTrash()" class="filter-btn" style="background: #ff4757; color: white;">
                                    <i class="fas fa-trash-alt"></i> Empty Trash
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Files Area -->
                    <div class="files-area">
                        <div class="files-header">
                            <div class="header-name">Name</div>
                            <div class="header-modified">Deleted on</div>
                            <div class="header-size">Size / Type</div>
                            <div class="header-actions">Actions</div>
                        </div>

                        <div class="files-list">
                            <!-- Display Folders in Trash -->
                            <?php if (isset($folders) && $folders && $folders->num_rows > 0): ?>
                                <?php while ($folder = $folders->fetch_assoc()): ?>
                                    <?php $days_until_deletion = 30 - floor((time() - strtotime($folder['deleted_at'])) / (60 * 60 * 24)); ?>
                                    <div class="file-item folder-item trash-item" data-id="<?php echo $folder['id']; ?>">
                                        <div class="file-name">
                                            <i class="fas fa-folder folder-icon"></i>
                                            <span>
                                                <?php echo htmlspecialchars($folder['folder_name']); ?>
                                                <?php if (!empty($folder['year'])): ?>
                                                    <span class="year-badge"><?php echo $folder['year']; ?></span>
                                                <?php endif; ?>
                                                <?php if ($days_until_deletion > 0): ?>
                                                    <span class="badge">Deletes in <?php echo $days_until_deletion; ?> days</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background: #ff4757;">Will be deleted soon</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="file-modified" data-label="Deleted on">
                                            <?php echo date('M d, Y', strtotime($folder['deleted_at'])); ?>
                                        </div>
                                        <div class="file-size" data-label="Type">Folder</div>
                                        <div class="file-actions">
                                            <button class="action-btn" onclick="restoreFolder(<?php echo $folder['id']; ?>)" title="Restore">
                                                <i class="fas fa-undo-alt"></i>
                                            </button>
                                            <button class="action-btn" onclick="permanentlyDeleteFolder(<?php echo $folder['id']; ?>)" title="Delete Permanently">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>

                            <!-- Display Files in Trash -->
                            <?php if (isset($files) && $files && $files->num_rows > 0): ?>
                                <?php while ($file = $files->fetch_assoc()): ?>
                                    <?php 
                                    $file_path = $file['file_path'];
                                    $file_size = file_exists($file_path) ? filesize($file_path) : 0;
                                    $days_until_deletion = 30 - floor((time() - strtotime($file['deleted_at'])) / (60 * 60 * 24));
                                    ?>
                                    <div class="file-item trash-item" data-id="<?php echo $file['id']; ?>">
                                        <div class="file-name">
                                            <i class="fas <?php echo getFileIcon($file['file_name']); ?> file-icon"></i>
                                            <span>
                                                <?php echo htmlspecialchars($file['file_name']); ?>
                                                <?php if (!empty($file['year'])): ?>
                                                    <span class="year-badge"><?php echo $file['year']; ?></span>
                                                <?php endif; ?>
                                                <?php if ($days_until_deletion > 0): ?>
                                                    <span class="badge">Deletes in <?php echo $days_until_deletion; ?> days</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background: #ff4757;">Will be deleted soon</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="file-modified" data-label="Deleted on">
                                            <?php echo date('M d, Y', strtotime($file['deleted_at'])); ?>
                                        </div>
                                        <div class="file-size" data-label="Size"><?php echo formatFileSize($file_size); ?></div>
                                        <div class="file-actions">
                                            <button class="action-btn" onclick="restoreFile(<?php echo $file['id']; ?>)" title="Restore">
                                                <i class="fas fa-undo-alt"></i>
                                            </button>
                                            <button class="action-btn" onclick="permanentlyDeleteFile(<?php echo $file['id']; ?>)" title="Delete Permanently">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>

                            <!-- Empty State -->
                            <?php if ((!isset($folders) || $folders->num_rows == 0) && (!isset($files) || $files->num_rows == 0)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-trash"></i>
                                    <h3>Trash is empty</h3>
                                    <p>Items you delete will appear here</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- DRIVE VIEW -->
                <div class="drive-view">
                    <!-- Search and Filter Section -->
                    <div class="filters-section">
                        <form method="GET" class="filters-form">
                            <input type="hidden" name="view" value="drive">
                            <?php if ($current_folder_id): ?>
                                <input type="hidden" name="folder_id" value="<?php echo $current_folder_id; ?>">
                            <?php endif; ?>
                            
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Search files and folders..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <!-- Year Filter -->
                            <div class="filter-dropdown year-filter">
                                <i class="fas fa-calendar-alt"></i>
                                <select name="year" onchange="this.form.submit()">
                                    <option value="">All Years</option>
                                    <?php foreach ($available_years as $year): ?>
                                        <option value="<?php echo $year; ?>" <?php echo $year_filter == $year ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Semester Filter -->
                            <div class="filter-dropdown semester-filter">
                                <i class="fas fa-layer-group"></i>
                                <select name="semester" onchange="this.form.submit()" <?php echo empty($year_filter) ? 'disabled' : ''; ?>>
                                    <option value="">All Semesters</option>
                                    <option value="first" <?php echo $semester_filter == 'first' ? 'selected' : ''; ?>>First Semester (Jan - Jun)</option>
                                    <option value="second" <?php echo $semester_filter == 'second' ? 'selected' : ''; ?>>Second Semester (Jul - Dec)</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="filter-btn">Search</button>
                            <?php if ($current_folder_id || $search || $year_filter || $semester_filter): ?>
                                <a href="Homepage.php?view=drive" class="clear-filters">Clear All</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Files Area -->
                    <div class="files-area">
                        <div class="files-header">
                            <div class="header-name">Name</div>
                            <div class="header-modified">Last modified</div>
                            <div class="header-size">Size</div>
                            <div class="header-actions">Actions</div>
                        </div>

                        <div class="files-list">
                            <!-- Display Folders -->
                            <?php 
                            $has_folders = false;
                            if (isset($folders) && $folders && $folders->num_rows > 0): 
                                $has_folders = true;
                                while ($folder = $folders->fetch_assoc()): 
                                    $subfolder_count_query = $conn->query("SELECT COUNT(*) as count FROM folders WHERE parent_id = " . $folder['id'] . " AND deleted_at IS NULL");
                                    $subfolder_count = $subfolder_count_query ? $subfolder_count_query->fetch_assoc()['count'] : 0;
                                    $month_name = !empty($folder['month']) ? date('F', mktime(0, 0, 0, $folder['month'], 1)) : '';
                                    $semester = !empty($folder['month']) ? getSemesterFromMonth($folder['month']) : '';
                                    $semester_display = $semester == 'first' ? '1st Sem' : ($semester == 'second' ? '2nd Sem' : '');
                            ?>
                                <div class="file-item folder-item" data-id="<?php echo $folder['id']; ?>">
                                    <div class="file-name">
                                        <i class="fas fa-folder folder-icon"></i>
                                        <a href="Homepage.php?view=drive&folder_id=<?php echo $folder['id']; ?><?php echo $year_filter ? '&year='.$year_filter : ''; ?><?php echo $semester_filter ? '&semester='.$semester_filter : ''; ?>">
                                            <?php echo htmlspecialchars($folder['folder_name']); ?>
                                            <?php if ($subfolder_count > 0): ?>
                                                <span class="badge"><?php echo $subfolder_count; ?> subfolders</span>
                                            <?php endif; ?>
                                            <?php if (!empty($folder['year'])): ?>
                                                <span class="year-badge"><?php echo $folder['year']; ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($month_name)): ?>
                                                <span class="month-badge"><?php echo $month_name; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <div class="file-modified" data-label="Modified">
                                        <?php echo date('M d, Y', strtotime($folder['created_at'])); ?>
                                    </div>
                                    <div class="file-size" data-label="Size">--</div>
                                    <div class="file-actions">
                                        <button class="action-btn" onclick="renameFolder(<?php echo $folder['id']; ?>, '<?php echo htmlspecialchars(addslashes($folder['folder_name'])); ?>')" title="Rename">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn" onclick="deleteFolder(<?php echo $folder['id']; ?>)" title="Move to Trash">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php 
                                endwhile; 
                            endif; 
                            ?>

                            <!-- Display Files -->
                            <?php 
                            $has_files = false;
                            if (isset($files) && $files && $files->num_rows > 0): 
                                $has_files = true;
                                while ($file = $files->fetch_assoc()): 
                                    $file_path = $file['file_path'];
                                    $file_size = 0;
                                    
                                    if (file_exists($file_path)) {
                                        $file_size = filesize($file_path);
                                    } elseif (file_exists('uploads/' . basename($file_path))) {
                                        $file_size = filesize('uploads/' . basename($file_path));
                                    }
                                    
                                    $semester = !empty($file['month']) ? getSemesterFromMonth($file['month']) : '';
                                    $semester_display = $semester == 'first' ? '1st Sem' : ($semester == 'second' ? '2nd Sem' : '');
                                    $month_name = !empty($file['month']) ? date('F', mktime(0, 0, 0, $file['month'], 1)) : '';
                            ?>
                                    <div class="file-item" data-id="<?php echo $file['id']; ?>">
                                        <div class="file-name">
                                            <i class="fas <?php echo getFileIcon($file['file_name']); ?> file-icon"></i>
                                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank">
                                                <?php echo htmlspecialchars($file['file_name']); ?>
                                                <?php if (!empty($file['year'])): ?>
                                                    <span class="year-badge"><?php echo $file['year']; ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($month_name)): ?>
                                                    <span class="month-badge"><?php echo $month_name; ?></span>
                                                <?php endif; ?>
                                            </a>
                                        </div>
                                        <div class="file-modified" data-label="Modified">
                                            <?php echo date('M d, Y', strtotime($file['uploaded_at'])); ?>
                                        </div>
                                        <div class="file-size" data-label="Size"><?php echo formatFileSize($file_size); ?></div>
                                        <div class="file-actions">
                                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>" download class="action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button class="action-btn" onclick="renameFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars(addslashes($file['file_name'])); ?>')" title="Rename">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-btn" onclick="deleteFile(<?php echo $file['id']; ?>)" title="Move to Trash">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                            <?php 
                                endwhile; 
                            endif; 
                            ?>

                            <!-- Empty State -->
                            <?php if (!$has_folders && !$has_files): ?>
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <h3>This folder is empty</h3>
                                    <p>Click the + button to create a folder or upload a file</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Floating Action Button -->
        <?php if ($view == 'drive'): ?>
        <div class="fab-container">
            <div class="fab" onclick="toggleFabMenu()">
                <i class="fas fa-plus"></i>
            </div>
            <div class="fab-menu" id="fabMenu">
                <button class="fab-menu-item" onclick="openUploadModal()">
                    <i class="fas fa-upload"></i>
                    <span>Upload File</span>
                </button>
                <button class="fab-menu-item" onclick="openFolderModal()">
                    <i class="fas fa-folder-plus"></i>
                    <span>Create Folder</span>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Create Folder Modal -->
    <div class="modal" id="folderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Folder</h3>
                <button class="close-btn" onclick="closeModal('folderModal')">&times;</button>
            </div>
            <form action="Create_Folders.php" method="POST">
                <input type="hidden" name="parent_id" value="<?php echo $current_folder_id; ?>">
                
                <div class="form-group">
                    <label>Folder Name</label>
                    <input type="text" name="folder_name" placeholder="Enter folder name" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Enter folder description" rows="3"></textarea>
                </div>
                
                <?php if ($current_folder_id): ?>
                    <!-- Subfolder - Year inherited from parent -->
                    <div class="inheritance-info">
                        <i class="fas fa-share-alt"></i>
                        <span>This folder will automatically inherit the year <strong><?php echo $parent_year; ?></strong> from its parent folder.</span>
                    </div>
                    
                    <div class="form-group">
                        <label>Month (Optional - Override Parent)</label>
                        <select name="month">
                            <option value="">Use Parent Month</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                        <small>Leave empty to inherit month from parent folder</small>
                    </div>
                    
                <?php else: ?>
                    <!-- Main Folder - Year required -->
                    <div class="form-group">
                        <label>Year <span style="color: #ff4757;">*</span></label>
                        <input type="number" name="year" placeholder="Enter year" min="2000" max="<?php echo date('Y'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Month (Optional)</label>
                        <select name="month">
                            <option value="">Select Month</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeModal('folderModal')">Cancel</button>
                    <button type="submit" class="submit-btn">Create Folder</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload File Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Upload File</h3>
                <button class="close-btn" onclick="closeModal('uploadModal')">&times;</button>
            </div>
            <form action="Upload_Files.php" method="POST" enctype="multipart/form-data">
                <?php if ($current_folder_id): ?>
                    <input type="hidden" name="folder_id" value="<?php echo $current_folder_id; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Select File</label>
                    <input type="file" name="file" id="fileInput" required onchange="updateFileName()">
                </div>
                
                <div class="form-group">
                    <label>Custom File Name (Optional)</label>
                    <input type="text" name="custom_filename" id="customFilename" placeholder="Enter custom name without extension">
                </div>
                
                <?php if (!$current_folder_id): ?>
                    <!-- Root directory upload - need to select folder -->
                    <div class="form-group">
                        <label>Select Folder</label>
                        <select name="folder_id" required>
                            <option value="">Root Directory</option>
                            <?php foreach ($folder_tree as $folder): ?>
                                <option value="<?php echo $folder['id']; ?>">
                                    <?php echo str_repeat('&nbsp;&nbsp;&nbsp;', $folder['level']) . '└─ ' . htmlspecialchars($folder['folder_name']); ?>
                                    <?php if (!empty($folder['year'])): ?>
                                        (Year: <?php echo $folder['year']; ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Year <span style="color: #ff4757;">*</span></label>
                        <input type="number" name="year" placeholder="Enter year" min="2000" max="<?php echo date('Y'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Month</label>
                        <select name="month" required>
                            <option value="">Select Month</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                <?php else: ?>
                    <!-- Inside a folder - Year inherited -->
                    <div class="inheritance-info">
                        <i class="fas fa-share-alt"></i>
                        <span>This file will automatically inherit the year <strong><?php echo $parent_year; ?></strong> from the current folder.</span>
                    </div>
                    
                    <div class="form-group">
                        <label>Month (Optional)</label>
                        <select name="month">
                            <option value="">Select Month</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Day (Optional)</label>
                        <select name="day">
                            <option value="">Select Day</option>
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeModal('uploadModal')">Cancel</button>
                    <button type="submit" class="submit-btn">Upload File</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="close-btn" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="delete-content">
                <p id="deleteMessage">Are you sure you want to move this item to trash?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="button" class="delete-btn" id="confirmDeleteBtn">Move to Trash</button>
            </div>
        </div>
    </div>

    <!-- Permanent Delete Confirmation Modal -->
    <div class="modal" id="permanentDeleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Permanently Delete</h3>
                <button class="close-btn" onclick="closeModal('permanentDeleteModal')">&times;</button>
            </div>
            <div class="delete-content">
                <p class="warning">This action cannot be undone!</p>
                <p>Are you sure you want to permanently delete this item?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeModal('permanentDeleteModal')">Cancel</button>
                <button type="button" class="delete-btn" id="confirmPermanentDeleteBtn">Delete Permanently</button>
            </div>
        </div>
    </div>

    <!-- Empty Trash Confirmation Modal -->
    <div class="modal" id="emptyTrashModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Empty Trash</h3>
                <button class="close-btn" onclick="closeModal('emptyTrashModal')">&times;</button>
            </div>
            <div class="delete-content">
                <p class="warning">This action cannot be undone!</p>
                <p>Are you sure you want to permanently delete all items in trash?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeModal('emptyTrashModal')">Cancel</button>
                <button type="button" class="delete-btn" id="confirmEmptyTrashBtn">Empty Trash</button>
            </div>
        </div>
    </div>

    <!-- Rename Modal -->
    <div class="modal" id="renameModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="renameModalTitle">Rename Item</h3>
                <button class="close-btn" onclick="closeModal('renameModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Current Name:</label>
                    <p id="currentNameDisplay"></p>
                </div>
                <div class="form-group">
                    <label for="renameInput">New Name:</label>
                    <input type="text" id="renameInput" placeholder="Enter new name">
                    <small id="fileExtensionDisplay"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeModal('renameModal')">Cancel</button>
                <button type="button" class="submit-btn" id="confirmRenameBtn">
                    <i class="fas fa-edit"></i> Rename
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="js/main.js?v=<?php echo time(); ?>"></script>
</body>
</html>
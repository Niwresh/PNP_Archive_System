<?php
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

// Get current folder ID from URL
$current_folder_id = isset($_GET['folder_id']) ? intval($_GET['folder_id']) : null;

// Get search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get year filter parameter
$year_filter = isset($_GET['year']) ? $_GET['year'] : '';

// Get semester filter parameter
$semester_filter = isset($_GET['semester']) ? $_GET['semester'] : '';

// Get view mode (dashboard, drive, or trash)
$view = isset($_GET['view']) ? $_GET['view'] : 'drive';

// Get trash view filter
$trash_filter = isset($_GET['trash_filter']) ? $_GET['trash_filter'] : 'all';

// Get current folder info
$current_folder = null;
$folder_path = [];
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
$total_files = $total_files_query->fetch_assoc()['count'];

$total_folders_query = $conn->query("SELECT COUNT(*) as count FROM folders WHERE deleted_at IS NULL");
$total_folders = $total_folders_query->fetch_assoc()['count'];

// Calculate total subfolders (folders that have a parent)
$subfolders_query = $conn->query("SELECT COUNT(*) as count FROM folders WHERE parent_id IS NOT NULL AND deleted_at IS NULL");
$total_subfolders = $subfolders_query->fetch_assoc()['count'];

// Calculate root folders (folders with no parent)
$root_folders_query = $conn->query("SELECT COUNT(*) as count FROM folders WHERE parent_id IS NULL AND deleted_at IS NULL");
$total_root_folders = $root_folders_query->fetch_assoc()['count'];

// Get trash statistics
$trash_files_query = $conn->query("SELECT COUNT(*) as count FROM files WHERE deleted_at IS NOT NULL");
$trash_files = $trash_files_query->fetch_assoc()['count'];

$trash_folders_query = $conn->query("SELECT COUNT(*) as count FROM folders WHERE deleted_at IS NOT NULL");
$trash_folders = $trash_folders_query->fetch_assoc()['count'];

// Calculate total storage used using the function from PNP_Archive.php
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
// Determine storage bar color based on usage
$storage_bar_color = '#4caf50'; // Green for normal usage
if ($storage_percent > 80) {
    $storage_bar_color = '#ff9800'; // Orange for warning
}
if ($storage_percent > 95) {
    $storage_bar_color = '#f44336'; // Red for critical
}

// Format storage used for display
if ($storage_used >= 1073741824) {
    $storage_display = number_format($storage_used / 1073741824, 2) . ' GB';
} elseif ($storage_used >= 1048576) {
    $storage_display = number_format($storage_used / 1048576, 2) . ' MB';
} elseif ($storage_used >= 1024) {
    $storage_display = number_format($storage_used / 1024, 2) . ' KB';
} else {
    $storage_display = $storage_used . ' bytes';
}

// Format total storage
$total_storage_display = '5 GB';

// Calculate remaining storage
$remaining_storage = $total_storage_limit - $storage_used;
if ($remaining_storage >= 1073741824) {
    $remaining_display = number_format($remaining_storage / 1073741824, 2) . ' GB';
} elseif ($remaining_storage >= 1048576) {
    $remaining_display = number_format($remaining_storage / 1048576, 2) . ' MB';
} elseif ($remaining_storage >= 1024) {
    $remaining_display = number_format($remaining_storage / 1024, 2) . ' KB';
} else {
    $remaining_display = $remaining_storage . ' bytes';
}

// Get folder hierarchy statistics for dashboard
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

// Function to determine semester from month
function getSemesterFromMonth($month) {
    if ($month >= 1 && $month <= 6) {
        return 'first';
    } elseif ($month >= 7 && $month <= 12) {
        return 'second';
    }
    return '';
}

// Function to get month range for semester
function getSemesterMonths($semester) {
    if ($semester == 'first') {
        return [1, 6]; // January to June
    } elseif ($semester == 'second') {
        return [7, 12]; // July to December
    }
    return [1, 12]; // All months if no semester selected
}

// Build folder query for drive view
if ($view == 'drive') {

    // GLOBAL SEARCH (search entire system)
    if (!empty($search)) {

        $folder_query = "
            SELECT * FROM folders
            WHERE deleted_at IS NULL
            AND folder_name LIKE '%" . $conn->real_escape_string($search) . "%'
            ORDER BY folder_name ASC
        ";

        $folders = $conn->query($folder_query);

        $file_query = "
            SELECT files.*, folders.folder_name
            FROM files
            LEFT JOIN folders ON files.folder_id = folders.id
            WHERE files.deleted_at IS NULL
            AND files.file_name LIKE '%" . $conn->real_escape_string($search) . "%'
            ORDER BY files.uploaded_at DESC
        ";

        $files = $conn->query($file_query);
    }

    // NORMAL DRIVE VIEW
    else {

        $folder_query = "SELECT * FROM folders WHERE deleted_at IS NULL";

        // Show folders in current location
        if ($current_folder_id) {
            $folder_query .= " AND parent_id = $current_folder_id";
        } else {
            $folder_query .= " AND parent_id IS NULL";
        }

        // Year filter for folders
        if (!empty($year_filter)) {
            $folder_query .= " AND year = " . intval($year_filter);
            
            // Add semester filter for folders if selected
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

        // Show files in current folder
        if ($current_folder_id) {
            $file_query .= " AND files.folder_id = $current_folder_id";
        } else {
            $file_query .= " AND files.folder_id IS NULL";
        }

        // Year filter for files
        if (!empty($year_filter)) {
            $file_query .= " AND files.year = " . intval($year_filter);
            
            // Semester filter for files
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

// Get all folders for dropdown (for moving files)
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PNP Archive - Google Drive Style</title>
    <link rel="stylesheet" href="css/Home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

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
                    <a href="homepage.php?view=dashboard">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="<?php echo $view == 'drive' ? 'active' : ''; ?>">
                    <a href="homepage.php?view=drive">
                        <i class="fas fa-folder"></i> My Drive
                    </a>
                </li>
                <li class="<?php echo $view == 'trash' ? 'active' : ''; ?>">
                    <a href="homepage.php?view=trash">
                        <i class="fas fa-trash"></i> Trash 
                        <?php if ($trash_files + $trash_folders > 0): ?>
                            <span class="trash-badge"><?php echo $trash_files + $trash_folders; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>

            <!-- Enhanced Storage Info -->
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
                        <a href="homepage.php?view=trash"><i class="fas fa-trash"></i> Trash</a>
                    <?php else: ?>
                        <a href="homepage.php?view=drive"><i class="fas fa-home"></i> My Drive</a>
                        <?php foreach ($folder_path as $folder): ?>
                            <i class="fas fa-chevron-right"></i>
                            <a href="homepage.php?view=drive&folder_id=<?php echo $folder['id']; ?>">
                                <?php echo htmlspecialchars($folder['folder_name']); ?>
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

                    <!-- QUICK ACTIONS AND FOLDER HIERARCHY -->
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
                                <a href="homepage.php?view=drive" class="quick-action-btn">
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
                                            <span class="recent-file-date">
                                                <i class="fas fa-sitemap"></i> Level
                                            </span>
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

                    <!-- SYSTEM INFO AND TRASH SUMMARY -->
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
                            <div class="info-item">
                                <span>PHP Version:</span>
                                <span><?php echo phpversion(); ?></span>
                            </div>
                            <div class="info-item">
                                <span>Database:</span>
                                <span>MySQL</span>
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
                                <span>Total Items:</span>
                                <span><?php echo $trash_files + $trash_folders; ?></span>
                            </div>
                            <div class="info-item">
                                <a href="homepage.php?view=trash" class="quick-action-btn" style="width: 100%; justify-content: center; margin-top: 10px;">
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
                                <a href="homepage.php?view=trash&trash_filter=all" class="filter-btn <?php echo $trash_filter == 'all' ? 'active' : ''; ?>">All</a>
                                <a href="homepage.php?view=trash&trash_filter=folders" class="filter-btn <?php echo $trash_filter == 'folders' ? 'active' : ''; ?>">Folders</a>
                                <a href="homepage.php?view=trash&trash_filter=files" class="filter-btn <?php echo $trash_filter == 'files' ? 'active' : ''; ?>">Files</a>
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
                            <?php 
                            if (isset($folders) && $folders && $folders->num_rows > 0): 
                                while ($folder = $folders->fetch_assoc()): 
                                    $days_until_deletion = 30 - floor((time() - strtotime($folder['deleted_at'])) / (60 * 60 * 24));
                            ?>
                                <div class="file-item folder-item trash-item" data-id="<?php echo $folder['id']; ?>">
                                    <div class="file-name">
                                        <i class="fas fa-folder folder-icon"></i>
                                        <span>
                                            <?php echo htmlspecialchars($folder['folder_name']); ?>
                                            <?php if ($days_until_deletion > 0): ?>
                                                <span style="font-size: 11px; opacity: 0.7; margin-left: 5px;">(Deletes in <?php echo $days_until_deletion; ?> days)</span>
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: #ff4757; margin-left: 5px;">(Will be deleted soon)</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="file-modified">
                                        <?php echo date('M d, Y', strtotime($folder['deleted_at'])); ?>
                                    </div>
                                    <div class="file-size">Folder</div>
                                    <div class="file-actions">
                                        <button class="action-btn" onclick="restoreFolder(<?php echo $folder['id']; ?>)" title="Restore">
                                            <i class="fas fa-undo-alt"></i>
                                        </button>
                                        <button class="action-btn" onclick="permanentlyDeleteFolder(<?php echo $folder['id']; ?>)" title="Delete Permanently">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php 
                                endwhile; 
                            endif; 
                            ?>

                            <!-- Display Files in Trash -->
                            <?php 
                            if (isset($files) && $files && $files->num_rows > 0): 
                                while ($file = $files->fetch_assoc()): 
                                    $file_path = $file['file_path'];
                                    $file_size = file_exists($file_path) ? filesize($file_path) : 0;
                                    $days_until_deletion = 30 - floor((time() - strtotime($file['deleted_at'])) / (60 * 60 * 24));
                            ?>
                                    <div class="file-item trash-item" data-id="<?php echo $file['id']; ?>">
                                        <div class="file-name">
                                            <i class="fas <?php echo getFileIcon($file['file_name']); ?> file-icon"></i>
                                            <span>
                                                <?php echo htmlspecialchars($file['file_name']); ?>
                                                <?php if ($days_until_deletion > 0): ?>
                                                    <span style="font-size: 11px; opacity: 0.7; margin-left: 5px;">(Deletes in <?php echo $days_until_deletion; ?> days)</span>
                                                <?php else: ?>
                                                    <span style="font-size: 11px; color: #ff4757; margin-left: 5px;">(Will be deleted soon)</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="file-modified">
                                            <?php echo date('M d, Y', strtotime($file['deleted_at'])); ?>
                                        </div>
                                        <div class="file-size"><?php echo formatFileSize($file_size); ?></div>
                                        <div class="file-actions">
                                            <button class="action-btn" onclick="restoreFile(<?php echo $file['id']; ?>)" title="Restore">
                                                <i class="fas fa-undo-alt"></i>
                                            </button>
                                            <button class="action-btn" onclick="permanentlyDeleteFile(<?php echo $file['id']; ?>)" title="Delete Permanently">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                            <?php 
                                endwhile; 
                            endif; 
                            ?>

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
                            
                            <!-- Year Filter Dropdown -->
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
                                
                                <?php if (!empty($year_filter)): ?>
                                    <a href="homepage.php?view=drive<?php echo $current_folder_id ? '&folder_id='.$current_folder_id : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $semester_filter ? '&semester='.$semester_filter : ''; ?>" 
                                       class="clear-filter" title="Clear year filter">
                                        <i class="fas fa-times-circle"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Semester Filter Dropdown (Now applies to both folders and files) -->
                            <div class="filter-dropdown semester-filter">
                                <i class="fas fa-layer-group"></i>
                                <select name="semester" onchange="this.form.submit()" <?php echo empty($year_filter) ? 'disabled' : ''; ?>>
                                    <option value="">All Semesters</option>
                                    <option value="first" <?php echo $semester_filter == 'first' ? 'selected' : ''; ?>>First Semester (Jan - Jun)</option>
                                    <option value="second" <?php echo $semester_filter == 'second' ? 'selected' : ''; ?>>Second Semester (Jul - Dec)</option>
                                </select>
                                
                                <?php if (!empty($semester_filter)): ?>
                                    <a href="homepage.php?view=drive<?php echo $current_folder_id ? '&folder_id='.$current_folder_id : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $year_filter ? '&year='.$year_filter : ''; ?>" 
                                       class="clear-filter" title="Clear semester filter">
                                        <i class="fas fa-times-circle"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <button type="submit" class="filter-btn">Search</button>
                            <?php if ($current_folder_id || $search || $year_filter || $semester_filter): ?>
                                <a href="homepage.php?view=drive" class="clear-filters">Clear All</a>
                            <?php endif; ?>
                        </form>
                        
                        <!-- Active Filters Display -->
                        <?php if (!empty($year_filter) || !empty($semester_filter)): ?>
                            <div class="filter-info" style="margin-top: 10px; padding: 5px 10px; background: rgba(255,255,255,0.1); border-radius: 5px;">
                                <i class="fas fa-filter"></i> Active Filters: 
                                <?php if (!empty($year_filter)): ?>
                                    <span class="filter-tag">Year: <?php echo $year_filter; ?></span>
                                <?php endif; ?>
                                <?php if (!empty($semester_filter)): ?>
                                    <span class="filter-tag">Semester: <?php echo $semester_filter == 'first' ? 'First Semester (Jan-Jun)' : 'Second Semester (Jul-Dec)'; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
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
                                    // Count subfolders for each folder
                                    $subfolder_count_query = $conn->query("SELECT COUNT(*) as count FROM folders WHERE parent_id = " . $folder['id'] . " AND deleted_at IS NULL");
                                    $subfolder_count = $subfolder_count_query->fetch_assoc()['count'];
                                    
                                    // Get month name if available
                                    $month_name = !empty($folder['month']) ? date('F', mktime(0, 0, 0, $folder['month'], 1)) : '';
                                    $semester = !empty($folder['month']) ? getSemesterFromMonth($folder['month']) : '';
                                    $semester_display = $semester == 'first' ? '1st Sem' : ($semester == 'second' ? '2nd Sem' : '');
                            ?>
                                <div class="file-item folder-item" data-id="<?php echo $folder['id']; ?>">
                                    <div class="file-name">
                                        <i class="fas fa-folder folder-icon"></i>
                                        <a href="homepage.php?view=drive&folder_id=<?php echo $folder['id']; ?><?php echo $year_filter ? '&year='.$year_filter : ''; ?><?php echo $semester_filter ? '&semester='.$semester_filter : ''; ?>">
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
                                            <?php if (!empty($semester_display)): ?>
                                                <span class="semester-badge"><?php echo $semester_display; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <div class="file-modified">
                                        <?php echo date('M d, Y', strtotime($folder['created_at'])); ?>
                                    </div>
                                    <div class="file-size">--</div>
                                    <div class="file-actions">
                                        <button class="action-btn" onclick="renameFolder(<?php echo $folder['id']; ?>, '<?php echo htmlspecialchars(addslashes($folder['folder_name'])); ?>')" title="Rename">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn" onclick="deleteFolder(<?php echo $folder['id']; ?>)" title="Move to Trash">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button class="action-btn" onclick="showFolderDetails(<?php echo $folder['id']; ?>)" title="Details">
                                            <i class="fas fa-info-circle"></i>
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
                                    
                                    // Check multiple possible paths for file size
                                    if (file_exists($file_path)) {
                                        $file_size = filesize($file_path);
                                    } elseif (file_exists('uploads/' . basename($file_path))) {
                                        $file_size = filesize('uploads/' . basename($file_path));
                                    } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $file_path)) {
                                        $file_size = filesize($_SERVER['DOCUMENT_ROOT'] . '/' . $file_path);
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
                                                <?php if (!empty($semester_display)): ?>
                                                    <span class="semester-badge"><?php echo $semester_display; ?></span>
                                                <?php endif; ?>
                                            </a>
                                        </div>
                                        <div class="file-modified">
                                            <?php echo date('M d, Y', strtotime($file['uploaded_at'])); ?>
                                        </div>
                                        <div class="file-size"><?php echo formatFileSize($file_size); ?></div>
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
                                            <button class="action-btn" onclick="showFileDetails(<?php echo $file['id']; ?>)" title="Details">
                                                <i class="fas fa-info-circle"></i>
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
                                    <?php if (!empty($year_filter) || !empty($semester_filter)): ?>
                                        <div class="filter-info">
                                            <i class="fas fa-filter"></i> 
                                            Filtered by: 
                                            <?php if (!empty($year_filter)): ?>
                                                <span class="filter-tag">Year: <?php echo $year_filter; ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($semester_filter)): ?>
                                                <span class="filter-tag">Semester: <?php echo $semester_filter == 'first' ? 'First' : 'Second'; ?></span>
                                            <?php endif; ?>
                                            <a href="homepage.php?view=drive<?php echo $current_folder_id ? '&folder_id='.$current_folder_id : ''; ?>" class="clear-filter-link">Clear all filters</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Floating Action Button (Only in Drive View) -->
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
                
                <?php if (!$current_folder_id): ?>
                    <!-- Main Folder Creation -->
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" name="year" id="folderYear" placeholder="Enter year" min="2000" max="<?php echo date('Y'); ?>" required>
                    </div>
                    
                    <!-- Month Selection for Main Folder -->
                    <div class="form-group" id="folderMonthField">
                        <label>Month</label>
                        <select name="month" id="folderMonth">
                            <option value="">Select Month (Optional)</option>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                        <small style="color: #666; display: block; margin-top: 5px;">Select month for this folder (optional)</small>
                    </div>
                <?php else: ?>
                    <!-- Subfolder Creation - Inherit from parent -->
                    <div class="info-text">
                        <i class="fas fa-info-circle"></i>
                        This folder will be created inside: <strong><?php echo htmlspecialchars($current_folder['folder_name']); ?></strong>
                    </div>
                    
                    <?php
                    // Get parent folder's year and month
                    $parent_info_query = $conn->query("SELECT year, month FROM folders WHERE id = $current_folder_id");
                    $parent_info = $parent_info_query->fetch_assoc();
                    ?>
                    
                    <?php if (!empty($parent_info['year'])): ?>
                        <div class="info-text" style="background: rgba(255, 215, 0, 0.1); border-left-color: #ffd700;">
                            <i class="fas fa-calendar-alt" style="color: #ffd700;"></i>
                            This subfolder will inherit year: <strong><?php echo $parent_info['year']; ?></strong>
                            <?php if (!empty($parent_info['month'])): ?>
                                and month: <strong><?php echo date('F', mktime(0, 0, 0, $parent_info['month'], 1)); ?></strong>
                            <?php endif; ?>
                            <input type="hidden" name="year" value="<?php echo $parent_info['year']; ?>">
                        </div>
                    <?php endif; ?>
                    
                    <!-- Month Selection for Subfolder (can override parent's month or set if parent doesn't have one) -->
                    <div class="form-group" id="subfolderMonthField">
                        <label>Month</label>
                        <select name="month" id="subfolderMonth">
                            <option value=""><?php echo !empty($parent_info['month']) ? 'Use Parent Month' : 'Select Month (Optional)'; ?></option>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            <?php echo !empty($parent_info['month']) ? 'Select a different month to override parent, or leave empty to use parent month' : 'Select month for this folder (optional)'; ?>
                        </small>
                    </div>
                    
                    <?php if (!empty($parent_info['month'])): ?>
                        <script>
                            // Set the parent month as the default selected option text
                            document.addEventListener('DOMContentLoaded', function() {
                                const monthSelect = document.getElementById('subfolderMonth');
                                const parentMonth = <?php echo $parent_info['month']; ?>;
                                monthSelect.options[0].text = 'Use Parent Month (<?php echo date('F', mktime(0, 0, 0, $parent_info['month'], 1)); ?>)';
                            });
                        </script>
                    <?php endif; ?>
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
                    <small style="color: #666; display: block; margin-top: 5px;">Leave empty to use original filename</small>
                </div>
                
                <?php if (!$current_folder_id): ?>
                    <div class="form-group">
                        <label>Select Folder</label>
                        <select name="folder_id" id="folderSelect" required onchange="toggleMonthField()">
                            <option value="">Root Directory</option>
                            <?php foreach ($folder_tree as $folder): ?>
                                <option value="<?php echo $folder['id']; ?>" data-has-year="<?php echo !empty($folder['year']) ? '1' : '0'; ?>">
                                    <?php echo str_repeat('&nbsp;&nbsp;&nbsp;', $folder['level']) . '└─ ' . htmlspecialchars($folder['folder_name']); ?>
                                    <?php if (!empty($folder['year'])): ?>
                                        [<?php echo $folder['year']; ?>]
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <!-- Month Selection Dropdown -->
                <div class="form-group" id="monthField">
                    <label>Month</label>
                    <select name="month" id="monthSelect">
                        <option value="">Select Month</option>
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                    <small style="color: #666; display: block; margin-top: 5px;">Select the month for this file</small>
                </div>
                
                <!-- Year Field (Hidden by default, shown when needed) -->
                <div class="form-group" id="yearField" style="display: none;">
                    <label>Year</label>
                    <input type="number" name="year" id="yearInput" placeholder="Enter year" min="2000" max="<?php echo date('Y'); ?>">
                    <small style="color: #666; display: block; margin-top: 5px;">Enter the year for this file</small>
                </div>
                
                <?php if ($current_folder_id): ?>
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
                <p style="color: #ff4757; font-weight: bold;">This action cannot be undone!</p>
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
                <p style="color: #ff4757; font-weight: bold;">This action cannot be undone!</p>
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
            <div class="modal-body" style="padding: 20px;">
                <div class="form-group">
                    <label>Current Name:</label>
                    <p id="currentNameDisplay" style="background: #f5f5f5; padding: 10px; border-radius: 5px; margin: 5px 0 15px 0; color: #333; word-break: break-all;"></p>
                </div>
                <div class="form-group">
                    <label for="renameInput">New Name:</label>
                    <input type="text" id="renameInput" placeholder="Enter new name" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                    <small id="fileExtensionDisplay" style="color: #666; display: block; margin-top: 5px;"></small>
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

</body>
<script src="js/main.js"></script>
</html>
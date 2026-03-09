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

// Calculate total storage used
$storage_used = 0;
$all_files_query = $conn->query("SELECT file_path FROM files WHERE deleted_at IS NULL");
while ($file = $all_files_query->fetch_assoc()) {
    if (file_exists($file['file_path'])) {
        $storage_used += filesize($file['file_path']);
    }
}

// Format storage used
if ($storage_used >= 1073741824) {
    $storage_display = number_format($storage_used / 1073741824, 2) . ' GB';
} elseif ($storage_used >= 1048576) {
    $storage_display = number_format($storage_used / 1048576, 2) . ' MB';
} elseif ($storage_used >= 1024) {
    $storage_display = number_format($storage_used / 1024, 2) . ' KB';
} else {
    $storage_display = $storage_used . ' bytes';
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

// Build folder query for drive view
if ($view == 'drive') {
    $folder_query = "SELECT * FROM folders WHERE deleted_at IS NULL";
    if ($current_folder_id) {
        $folder_query .= " AND parent_id = $current_folder_id";
    } else {
        $folder_query .= " AND parent_id IS NULL";
    }
    if ($search) {
        $folder_query .= " AND folder_name LIKE '%" . $conn->real_escape_string($search) . "%'";
    }
    $folder_query .= " ORDER BY folder_name ASC";
    $folders = $conn->query($folder_query);

    // Build files query for drive view
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
    if ($search) {
        $file_query .= " AND files.file_name LIKE '%" . $conn->real_escape_string($search) . "%'";
    }
    $file_query .= " ORDER BY files.uploaded_at DESC";
    $files = $conn->query($file_query);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/Home.css">
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

            <div class="storage-info">
                <i class="fas fa-database"></i>
                <span>Storage: <?php echo $storage_display; ?> / 10GB</span>
                <?php 
                $storage_percent = min(100, round(($storage_used / (10 * 1073741824)) * 100));
                ?>
                <div class="storage-bar">
                    <div class="storage-used" style="width: <?php echo $storage_percent; ?>%"></div>
                </div>
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
                    <div class="system-info" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
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
                            <div style="display: flex; gap: 10px;">
                                <a href="homepage.php?view=trash&trash_filter=all" class="filter-btn <?php echo $trash_filter == 'all' ? 'active' : ''; ?>">All</a>
                                <a href="homepage.php?view=trash&trash_filter=folders" class="filter-btn <?php echo $trash_filter == 'folders' ? 'active' : ''; ?>">Folders</a>
                                <a href="homepage.php?view=trash&trash_filter=files" class="filter-btn <?php echo $trash_filter == 'files' ? 'active' : ''; ?>">Files</a>
                                <button onclick="emptyTrash()" class="filter-btn" style="background: #ff4757;">
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
                    <!-- Search Section -->
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
                            
                            <button type="submit" class="filter-btn">Search</button>
                            <?php if ($current_folder_id): ?>
                                <a href="homepage.php?view=drive&folder_id=<?php echo $current_folder_id; ?>" class="clear-filters">Clear</a>
                            <?php else: ?>
                                <a href="homepage.php?view=drive" class="clear-filters">Clear</a>
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
                                    // Count subfolders for each folder
                                    $subfolder_count_query = $conn->query("SELECT COUNT(*) as count FROM folders WHERE parent_id = " . $folder['id'] . " AND deleted_at IS NULL");
                                    $subfolder_count = $subfolder_count_query->fetch_assoc()['count'];
                            ?>
                                <div class="file-item folder-item" data-id="<?php echo $folder['id']; ?>">
                                    <div class="file-name">
                                        <i class="fas fa-folder folder-icon"></i>
                                        <a href="homepage.php?view=drive&folder_id=<?php echo $folder['id']; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                            <?php echo htmlspecialchars($folder['folder_name']); ?>
                                            <?php if ($subfolder_count > 0): ?>
                                                <span style="font-size: 12px; opacity: 0.7; margin-left: 5px;">(<?php echo $subfolder_count; ?> subfolders)</span>
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
                                    $file_size = file_exists($file_path) ? filesize($file_path) : 0;
                            ?>
                                    <div class="file-item" data-id="<?php echo $file['id']; ?>">
                                        <div class="file-name">
                                            <i class="fas <?php echo getFileIcon($file['file_name']); ?> file-icon"></i>
                                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank">
                                                <?php echo htmlspecialchars($file['file_name']); ?>
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
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" name="year" placeholder="Enter year" min="2000" max="<?php echo date('Y'); ?>" required>
                    </div>
                <?php else: ?>
                    <div class="info-text">
                        <i class="fas fa-info-circle"></i>
                        This folder will be created inside: <strong><?php echo htmlspecialchars($current_folder['folder_name']); ?></strong>
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
                    <small style="color: #666; display: block; margin-top: 5px;">Leave empty to use original filename</small>
                </div>
                
                <?php if (!$current_folder_id): ?>
                    <div class="form-group">
                        <label>Select Folder</label>
                        <select name="folder_id" required>
                            <option value="">Root Directory</option>
                            <?php foreach ($folder_tree as $folder): ?>
                                <option value="<?php echo $folder['id']; ?>">
                                    <?php echo str_repeat('&nbsp;&nbsp;&nbsp;', $folder['level']) . '└─ ' . htmlspecialchars($folder['folder_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
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

    <script>
        let currentDeleteId = null;
        let currentDeleteType = null;
        let currentAction = null;

        function toggleFabMenu() {
            document.getElementById('fabMenu').classList.toggle('show');
        }

        function openFolderModal() {
            document.getElementById('folderModal').style.display = 'flex';
        }

        function openUploadModal() {
            document.getElementById('uploadModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function updateFileName() {
            const fileInput = document.getElementById('fileInput');
            const customFilename = document.getElementById('customFilename');
            
            if (fileInput.files.length > 0) {
                const fullName = fileInput.files[0].name;
                const nameWithoutExt = fullName.substring(0, fullName.lastIndexOf('.')) || fullName;
                
                if (!customFilename.value) {
                    customFilename.value = nameWithoutExt;
                }
            }
        }

        function showFolderDetails(folderId) {
            window.location.href = 'folder_details.php?id=' + folderId;
        }

        function showFileDetails(fileId) {
            window.location.href = 'file_details.php?id=' + fileId;
        }

        function deleteFolder(id) {
    currentDeleteId = id;
    currentDeleteType = 'folder';
    currentAction = 'trash';
    document.getElementById('deleteMessage').innerHTML = 'Are you sure you want to move this folder and all its contents to trash?';
    document.getElementById('deleteModal').style.display = 'flex';
}

function deleteFile(id) {
    currentDeleteId = id;
    currentDeleteType = 'file';
    currentAction = 'trash';
    document.getElementById('deleteMessage').innerHTML = 'Are you sure you want to move this file to trash?';
    document.getElementById('deleteModal').style.display = 'flex';
}

function permanentlyDeleteFolder(id) {
    currentDeleteId = id;
    currentDeleteType = 'folder';
    currentAction = 'permanent';
    document.getElementById('permanentDeleteModal').style.display = 'flex';
}

function permanentlyDeleteFile(id) {
    currentDeleteId = id;
    currentDeleteType = 'file';
    currentAction = 'permanent';
    document.getElementById('permanentDeleteModal').style.display = 'flex';
}

// Update the confirm delete button
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (currentDeleteId && currentDeleteType) {
        window.location.href = `delete_item.php?type=${currentDeleteType}&id=${currentDeleteId}&action=${currentAction}`;
    }
});

// Update the confirm permanent delete button
document.getElementById('confirmPermanentDeleteBtn').addEventListener('click', function() {
    if (currentDeleteId && currentDeleteType) {
        window.location.href = `delete_item.php?type=${currentDeleteType}&id=${currentDeleteId}&action=permanent`;
    }
});

        // Restore functions
        function restoreFolder(id) {
            if (confirm('Restore this folder and all its contents?')) {
                window.location.href = `restore_item.php?type=folder&id=${id}`;
            }
        }

        function restoreFile(id) {
            if (confirm('Restore this file?')) {
                window.location.href = `restore_item.php?type=file&id=${id}`;
            }
        }

        // Permanent delete functions
        function permanentlyDeleteFolder(id) {
            currentDeleteId = id;
            currentDeleteType = 'folder';
            currentAction = 'permanent';
            document.getElementById('permanentDeleteModal').style.display = 'flex';
        }

        function permanentlyDeleteFile(id) {
            currentDeleteId = id;
            currentDeleteType = 'file';
            currentAction = 'permanent';
            document.getElementById('permanentDeleteModal').style.display = 'flex';
        }

        function emptyTrash() {
            document.getElementById('emptyTrashModal').style.display = 'flex';
        }

        function renameFolder(id, currentName) {
            const newName = prompt('Enter new folder name:', currentName);
            if (newName && newName !== currentName) {
                window.location.href = `rename_folder.php?id=${id}&name=${encodeURIComponent(newName)}`;
            }
        }

        function renameFile(id, currentName) {
            const nameWithoutExt = currentName.substring(0, currentName.lastIndexOf('.')) || currentName;
            const newName = prompt('Enter new file name (without extension):', nameWithoutExt);
            if (newName && newName !== nameWithoutExt) {
                window.location.href = `rename_file.php?id=${id}&name=${encodeURIComponent(newName)}`;
            }
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (currentDeleteId && currentDeleteType) {
                window.location.href = `delete_item.php?type=${currentDeleteType}&id=${currentDeleteId}`;
            }
        });

        document.getElementById('confirmPermanentDeleteBtn').addEventListener('click', function() {
            if (currentDeleteId && currentDeleteType) {
                window.location.href = `homepage.php?type=${currentDeleteType}&id=${currentDeleteId}`;
            }
        });

        document.getElementById('confirmEmptyTrashBtn').addEventListener('click', function() {
            window.location.href = 'empty_trash.php';
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Close FAB menu when clicking outside
        document.addEventListener('click', function(event) {
            const fabMenu = document.getElementById('fabMenu');
            const fab = document.querySelector('.fab');
            
            if (fabMenu && fab && !fab.contains(event.target) && !fabMenu.contains(event.target)) {
                fabMenu.classList.remove('show');
            }
        });

        // Mobile menu toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Create mobile menu toggle button
            const mobileToggle = document.createElement('div');
            mobileToggle.className = 'mobile-menu-toggle';
            mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';
            document.body.appendChild(mobileToggle);
            
            // Toggle sidebar on mobile
            mobileToggle.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('active');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const sidebar = document.querySelector('.sidebar');
                const isClickInside = sidebar.contains(event.target) || mobileToggle.contains(event.target);
                
                if (!isClickInside && window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                const sidebar = document.querySelector('.sidebar');
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
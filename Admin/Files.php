<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Include your database connection
require_once 'PNP_Archive.php';

// Handle file deletion
if (isset($_GET['delete_file'])) {
    $file_id = (int)$_GET['delete_file'];
    
    // Get file info to delete physical file
    $stmt = $conn->prepare("SELECT file_path, folder_id FROM files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $file = $result->fetch_assoc();
    
    if ($file) {
        // Delete physical file
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        
        // Delete database record
        $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        
        $redirect_folder = $file['folder_id'] ?? '';
        header("Location: files.php?folder=" . $redirect_folder . "&success=File deleted successfully");
        exit();
    }
}

// Handle folder deletion
if (isset($_GET['delete_folder'])) {
    $folder_id = (int)$_GET['delete_folder'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First, get all files in this folder to delete physical files
        $stmt = $conn->prepare("SELECT file_path FROM files WHERE folder_id = ?");
        $stmt->bind_param("i", $folder_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Delete physical files
        while ($file = $result->fetch_assoc()) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
        
        // Delete all files in the folder (database records)
        $stmt = $conn->prepare("DELETE FROM files WHERE folder_id = ?");
        $stmt->bind_param("i", $folder_id);
        $stmt->execute();
        
        // Delete the folder
        $stmt = $conn->prepare("DELETE FROM folders WHERE id = ?");
        $stmt->bind_param("i", $folder_id);
        $stmt->execute();
        
        $conn->commit();
        header("Location: files.php?success=Folder and all its contents deleted successfully");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: files.php?error=Failed to delete folder");
        exit();
    }
}

// Get current folder ID from URL (if any)
$current_folder = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

// Get filter parameters
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$year_filter = isset($_GET['year']) ? (int)$_GET['year'] : '';
$semester_filter = isset($_GET['semester']) ? $_GET['semester'] : '';

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

// AJAX handler to get parent folder details for folder creation
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

// AJAX handler to get folder details for file upload (only year is inherited)
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_folder_details' && isset($_GET['folder_id'])) {
    header('Content-Type: application/json');
    $folder_id = (int)$_GET['folder_id'];
    
    // Get folder details including its parent's year if needed
    $stmt = $conn->prepare("
        SELECT f.*, 
               p.folder_year as parent_year,
               p.folder_name as parent_name
        FROM folders f
        LEFT JOIN folders p ON f.parent_id = p.id
        WHERE f.id = ?
    ");
    $stmt->bind_param("i", $folder_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $folder = $result->fetch_assoc();
    
    if ($folder) {
        // Determine the effective year (from parent if subfolder, from itself if main folder)
        if ($folder['parent_id']) {
            // This is a subfolder - get year from parent
            $effective_year = $folder['parent_year'];
        } else {
            // This is a main folder - use its own year
            $effective_year = $folder['folder_year'];
        }
        
        $response = [
            'success' => true, 
            'data' => [
                'folder_name' => $folder['folder_name'],
                'folder_year' => $effective_year,
                'is_subfolder' => !empty($folder['parent_id']),
                'parent_name' => $folder['parent_name']
            ]
        ];
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Folder not found']);
    }
    exit();
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_file'])) {
    $folder_id = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
    $document_name = trim($_POST['document_name']);
    $description = trim($_POST['description']);
    $document_year = null;
    
    // Get folder details for year inheritance
    if ($folder_id) {
        // Check if this is a subfolder or main folder
        $stmt = $conn->prepare("
            SELECT f.*, 
                   p.folder_year as parent_year
            FROM folders f
            LEFT JOIN folders p ON f.parent_id = p.id
            WHERE f.id = ?
        ");
        $stmt->bind_param("i", $folder_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $folder = $result->fetch_assoc();
        
        if ($folder) {
            if ($folder['parent_id']) {
                // This is a subfolder - get year from parent (main folder)
                $document_year = $folder['parent_year'];
            } else {
                // This is a main folder - use its own year
                $document_year = $folder['folder_year'];
            }
        }
    } else {
        // If at root, use submitted year
        $document_year = (int)$_POST['document_year'];
    }
    
    // Get month and day from form (user selected)
    $document_month = !empty($_POST['document_month']) ? $_POST['document_month'] : null;
    $document_date = !empty($_POST['document_date']) ? $_POST['document_date'] : null;
    
    // Validate date if provided
    if ($document_date && $document_year && $document_month) {
        $date_parts = explode('-', $document_date);
        if (count($date_parts) == 3) {
            $date_year = $date_parts[0];
            $date_month = $date_parts[1];
            
            // Check if date year matches inherited year
            if ($date_year != $document_year) {
                $_SESSION['error'] = "The selected date year ($date_year) must match the folder year ($document_year)";
                header("Location: files.php?folder=" . ($folder_id ?: '') . "&error=Date year mismatch");
                exit();
            }
            
            // Check if date month matches selected month
            if ($date_month != $document_month) {
                $_SESSION['error'] = "The selected date month must match the selected month";
                header("Location: files.php?folder=" . ($folder_id ?: '') . "&error=Date month mismatch");
                exit();
            }
        }
    }
    
    $document_category = trim($_POST['document_category']);
    
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
            $stmt = $conn->prepare("INSERT INTO files (folder_id, file_name, original_name, file_path, file_size, file_type, document_name, description, document_year, document_month, document_date, document_category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssisssssss", 
                $folder_id, 
                $new_filename, 
                $file['name'], 
                $upload_path, 
                $file['size'], 
                $file['type'], 
                $document_name, 
                $description, 
                $document_year, 
                $document_month,
                $document_date,
                $document_category
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

// Get files in current folder with search and filters
function getFilesInFolder($conn, $folder_id, $search_term = '', $year_filter = '', $semester_filter = '') {
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
    
    // Add semester filter based on month
    if (!empty($semester_filter)) {
        if ($semester_filter == 'first') {
            // First Semester: January to June (months 1-6)
            $sql .= " AND document_month BETWEEN 1 AND 6";
        } elseif ($semester_filter == 'second') {
            // Second Semester: July to December (months 7-12)
            $sql .= " AND document_month BETWEEN 7 AND 12";
        }
    }
    
    // Add search filter
    if (!empty($search_term)) {
        $sql .= " AND (document_name LIKE ? OR description LIKE ?)";
        $search_param = "%$search_term%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }
    
    $sql .= " ORDER BY document_year DESC, document_month, document_date DESC, uploaded_at DESC";
    
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

// Format date for display
function formatDate($date) {
    if (empty($date)) return '';
    return date('M d, Y', strtotime($date));
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
$files = getFilesInFolder($conn, $current_folder, $search_term, $year_filter, $semester_filter);

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
                
                <a href="files.php?folder=<?php echo $folder['id']; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?><?php echo !empty($year_filter) ? '&year='.$year_filter : ''; ?><?php echo !empty($semester_filter) ? '&semester='.$semester_filter : ''; ?>" class="folder-link">
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
        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_GET['error']); ?>
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

        <!-- Toolbar with Search and Filters -->
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
                        placeholder="Search by name or description..." 
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

                <div class="semester-filter">
                    <select name="semester" id="semesterSelect">
                        <option value="">All Semesters</option>
                        <option value="first" <?php echo $semester_filter == 'first' ? 'selected' : ''; ?>>First Semester (Jan-Jun)</option>
                        <option value="second" <?php echo $semester_filter == 'second' ? 'selected' : ''; ?>>Second Semester (Jul-Dec)</option>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                
                <?php if (!empty($search_term) || !empty($year_filter) || !empty($semester_filter)): ?>
                    <a href="files.php?folder=<?php echo $current_folder; ?>" class="clear-btn">
                        <i class="fas fa-times"></i> Clear All
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Active Filters Display -->
        <?php if (!empty($search_term) || !empty($year_filter) || !empty($semester_filter)): ?>
            <div class="active-filters">
                <i class="fas fa-filter" style="color: #0038a8;"></i>
                <span style="color: #666;">Active Filters:</span>
                
                <?php if (!empty($search_term)): ?>
                    <span class="filter-tag">
                        <i class="fas fa-search"></i> "<?php echo htmlspecialchars($search_term); ?>"
                        <a href="?folder=<?php echo $current_folder; ?><?php echo !empty($year_filter) ? '&year='.$year_filter : ''; ?><?php echo !empty($semester_filter) ? '&semester='.$semester_filter : ''; ?>">×</a>
                    </span>
                <?php endif; ?>
                
                <?php if (!empty($year_filter)): ?>
                    <span class="filter-tag">
                        <i class="fas fa-calendar"></i> Year: <?php echo $year_filter; ?>
                        <a href="?folder=<?php echo $current_folder; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?><?php echo !empty($semester_filter) ? '&semester='.$semester_filter : ''; ?>">×</a>
                    </span>
                <?php endif; ?>

                <?php if (!empty($semester_filter)): ?>
                    <span class="filter-tag">
                        <i class="fas fa-calendar-alt"></i> Semester: <?php echo $semester_filter == 'first' ? 'First (Jan-Jun)' : 'Second (Jul-Dec)'; ?>
                        <a href="?folder=<?php echo $current_folder; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?><?php echo !empty($year_filter) ? '&year='.$year_filter : ''; ?>">×</a>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb">
            <a href="files.php?<?php echo !empty($search_term) ? 'search='.urlencode($search_term) : ''; ?><?php echo !empty($year_filter) ? '&year='.$year_filter : ''; ?><?php echo !empty($semester_filter) ? '&semester='.$semester_filter : ''; ?>" class="breadcrumb-item <?php echo !$current_folder ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> My Drive
            </a>
            <?php foreach ($folder_path as $index => $folder): ?>
                <span class="breadcrumb-separator">/</span>
                <a href="files.php?folder=<?php echo $folder['id']; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?><?php echo !empty($year_filter) ? '&year='.$year_filter : ''; ?><?php echo !empty($semester_filter) ? '&semester='.$semester_filter : ''; ?>" 
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
                                    <div class="folder-card" onclick="window.location.href='files.php?folder=<?php echo $folder['id']; ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?><?php echo !empty($year_filter) ? '&year='.$year_filter : ''; ?><?php echo !empty($semester_filter) ? '&semester='.$semester_filter : ''; ?>'">
                                        <div class="folder-actions">
                                            <button class="folder-action-btn" onclick="event.stopPropagation(); openRenameFolderModal(<?php echo $folder['id']; ?>, '<?php echo htmlspecialchars($folder['folder_name']); ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="folder-action-btn" onclick="event.stopPropagation(); confirmDeleteFolder(<?php echo $folder['id']; ?>)">
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
                                <?php if (!empty($search_term) || !empty($year_filter) || !empty($semester_filter)): ?>
                                    <p>No files match your search criteria. <a href="?folder=<?php echo $current_folder; ?>">Clear all filters</a></p>
                                <?php else: ?>
                                    <p>Click "Upload Files" to upload your first file</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <table class="files-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Year</th>
                                        <th>Month</th>
                                        <th>Semester</th>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Size</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($files as $file): ?>
                                        <?php 
                                        // Determine semester based on month
                                        $semester = '';
                                        $semester_class = '';
                                        if (!empty($file['document_month'])) {
                                            if ($file['document_month'] >= 1 && $file['document_month'] <= 6) {
                                                $semester = 'First Sem';
                                                $semester_class = 'first-sem-badge';
                                            } elseif ($file['document_month'] >= 7 && $file['document_month'] <= 12) {
                                                $semester = 'Second Sem';
                                                $semester_class = 'second-sem-badge';
                                            }
                                        }
                                        ?>
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
                                                <?php if (!empty($file['document_month'])): ?>
                                                    <span class="badge month-badge"><?php echo $months[$file['document_month']]; ?></span>
                                                <?php else: ?>
                                                    <span>-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($semester)): ?>
                                                    <span class="badge <?php echo $semester_class; ?>"><?php echo $semester; ?></span>
                                                <?php else: ?>
                                                    <span>-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($file['document_date'])): ?>
                                                    <span class="badge date-badge"><?php echo formatDate($file['document_date']); ?></span>
                                                <?php else: ?>
                                                    <span>-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($file['document_category'])): ?>
                                                    <span class="badge badge-category"><?php echo htmlspecialchars($file['document_category']); ?></span>
                                                <?php else: ?>
                                                    <span>-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatFileSize($file['file_size']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="download.php?id=<?php echo $file['id']; ?>" class="action-btn btn-download" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <button class="action-btn btn-view" title="View Details" onclick="viewFileDetails(<?php echo $file['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="action-btn btn-delete" title="Delete" onclick="confirmDeleteFile(<?php echo $file['id']; ?>)">
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
            <form method="POST" action="" enctype="multipart/form-data" id="uploadFileForm">
                <div class="modal-body">
                    <input type="hidden" name="folder_id" id="upload_folder_id" value="<?php echo $current_folder; ?>">
                    
                    <!-- Folder Info (will be shown via JavaScript) -->
                    <div id="folderInfo" class="folder-info" style="display: none;">
                        <i class="fas fa-info-circle"></i>
                        <span id="folderMessage"></span>
                    </div>

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

                    <div class="form-group" id="fileYearGroup">
                        <label for="document_year">Year (Inherited from Folder)</label>
                        <input type="text" id="document_year_display" class="form-control" readonly disabled>
                        <input type="hidden" id="document_year" name="document_year">
                        <small id="fileYearHelpText" style="color: #666; display: block; margin-top: 5px;"></small>
                    </div>

                    <div class="form-group">
                        <label for="document_month">Month</label>
                        <select id="document_month" name="document_month" class="month-select">
                            <option value="">Select Month</option>
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Select the month for this document
                        </small>
                    </div>

                    <div class="form-group" id="dateGroup" style="display: none;">
                        <label for="document_date">Select Day</label>
                        <select id="document_date" name="document_date" class="date-select">
                            <option value="">Select Day</option>
                        </select>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Choose the specific day of the month for this document
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="document_category">Category</label>
                        <input type="text" id="document_category" name="document_category" placeholder="e.g., Financial, Legal, Personal">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="toolbar-btn btn-outline" onclick="closeUploadFileModal()">Cancel</button>
                    <button type="submit" name="upload_file" class="toolbar-btn btn-success">Upload File</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        /* Additional styles for semester badges */
        .first-sem-badge {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .second-sem-badge {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .semester-filter {
            flex: 1;
            min-width: 150px;
        }

        .semester-filter select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        .semester-filter select:focus {
            outline: none;
            border-color: #0038a8;
        }

        @media (max-width: 768px) {
            .semester-filter {
                width: 100%;
            }
        }
    </style>

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

        // Function to populate days in month based on selected month and year
        function populateDays() {
            const monthSelect = document.getElementById('document_month');
            const yearInput = document.getElementById('document_year');
            const daySelect = document.getElementById('document_date');
            const dateGroup = document.getElementById('dateGroup');
            
            const month = monthSelect.value;
            const year = yearInput.value;
            
            if (month && year) {
                // Get days in month
                const daysInMonth = new Date(year, month, 0).getDate();
                
                // Clear and populate days
                daySelect.innerHTML = '<option value="">Select Day</option>';
                
                for (let i = 1; i <= daysInMonth; i++) {
                    const day = i.toString().padStart(2, '0');
                    const dateValue = `${year}-${month.toString().padStart(2, '0')}-${day}`;
                    const option = document.createElement('option');
                    option.value = dateValue;
                    option.textContent = `${year}-${month.toString().padStart(2, '0')}-${day}`;
                    daySelect.appendChild(option);
                }
                
                // Show date group
                dateGroup.style.display = 'block';
            } else {
                // Hide date group if no month selected
                dateGroup.style.display = 'none';
                daySelect.innerHTML = '<option value="">Select Day</option>';
            }
        }

        // Modal functions for Upload File
        function openUploadFileModal() {
            const modal = document.getElementById('uploadFileModal');
            modal.classList.add('active');
            
            // Get folder ID
            const folderId = document.getElementById('upload_folder_id').value;
            
            // Reset form
            document.getElementById('uploadFileForm').reset();
            document.getElementById('folderInfo').style.display = 'none';
            document.getElementById('fileYearGroup').style.display = 'block';
            document.getElementById('dateGroup').style.display = 'none';
            
            // Clear selects
            const monthSelect = document.getElementById('document_month');
            monthSelect.value = '';
            
            const daySelect = document.getElementById('document_date');
            daySelect.innerHTML = '<option value="">Select Day</option>';
            
            // If we're in a folder, fetch folder details
            if (folderId) {
                fetchFolderDetails(folderId);
            }
        }

        function closeUploadFileModal() {
            document.getElementById('uploadFileModal').classList.remove('active');
            // Reset form
            document.getElementById('uploadFileForm').reset();
            document.getElementById('folderInfo').style.display = 'none';
            document.getElementById('fileYearGroup').style.display = 'block';
            document.getElementById('dateGroup').style.display = 'none';
        }

        // Fetch folder details for file upload via AJAX
        function fetchFolderDetails(folderId) {
            fetch(`files.php?ajax=get_folder_details&folder_id=${folderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const folder = data.data;
                        const folderInfo = document.getElementById('folderInfo');
                        const folderMessage = document.getElementById('folderMessage');
                        const yearDisplay = document.getElementById('document_year_display');
                        const yearHidden = document.getElementById('document_year');
                        
                        // Show folder info
                        let message = `Uploading to folder: <strong>${folder.folder_name}</strong>`;
                        if (folder.is_subfolder) {
                            message += ` (Subfolder under ${folder.parent_name})`;
                        }
                        folderMessage.innerHTML = message;
                        folderInfo.style.display = 'flex';
                        
                        // Set year (always inherited)
                        yearDisplay.value = folder.folder_year;
                        yearHidden.value = folder.folder_year;
                        
                        // Add helpful message based on folder type
                        if (folder.is_subfolder) {
                            document.getElementById('fileYearHelpText').textContent = 
                                `Year ${folder.folder_year} is inherited from the main folder`;
                        } else {
                            document.getElementById('fileYearHelpText').textContent = 
                                `Year ${folder.folder_year} is set from this folder`;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching folder details:', error);
                });
        }

        // Confirmation functions for deletions
        function confirmDeleteFile(fileId) {
            if (confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
                window.location.href = 'files.php?delete_file=' + fileId;
            }
        }

        function confirmDeleteFolder(folderId) {
            if (confirm('Are you sure you want to delete this folder? ALL FILES inside will be permanently deleted. This action cannot be undone.')) {
                window.location.href = 'files.php?delete_folder=' + folderId;
            }
        }

        // Add event listener for month selection change
        document.getElementById('document_month').addEventListener('change', populateDays);

        // Auto-submit form when filters change
        document.getElementById('yearSelect').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        document.getElementById('semesterSelect').addEventListener('change', function() {
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
                // Reset form when closing
                document.getElementById('uploadFileForm').reset();
                document.getElementById('folderInfo').style.display = 'none';
                document.getElementById('fileYearGroup').style.display = 'block';
                document.getElementById('dateGroup').style.display = 'none';
            }
        }

        // Delete folder function (legacy - keep for backward compatibility)
        function deleteFolder(folderId) {
            confirmDeleteFolder(folderId);
        }

        // Delete file function (legacy - keep for backward compatibility)
        function deleteFile(fileId) {
            confirmDeleteFile(fileId);
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
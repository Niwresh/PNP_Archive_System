<?php
session_start();
require_once "PNP_Archive.php";

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Get current folder ID from URL
$current_folder_id = isset($_GET['folder_id']) ? intval($_GET['folder_id']) : null;

// Get search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get current folder info
$current_folder = null;
$folder_path = [];
if ($current_folder_id) {
    $folder_query = $conn->query("SELECT * FROM folders WHERE id = $current_folder_id");
    if ($folder_query && $folder_query->num_rows > 0) {
        $current_folder = $folder_query->fetch_assoc();
        
        // Build folder path (breadcrumb)
        $path_id = $current_folder_id;
        while ($path_id) {
            $path_query = $conn->query("SELECT id, folder_name, parent_id FROM folders WHERE id = $path_id");
            if ($path_query && $path_row = $path_query->fetch_assoc()) {
                array_unshift($folder_path, $path_row);
                $path_id = $path_row['parent_id'];
            } else {
                break;
            }
        }
    }
}

// Build folder query - show only folders in current directory
$folder_query = "SELECT * FROM folders WHERE 1=1";
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

// Check if query was successful
if (!$folders) {
    die("Error in folder query: " . $conn->error);
}

// Build files query - show only files in current folder
$file_query = "
    SELECT files.*, folders.folder_name
    FROM files 
    LEFT JOIN folders ON files.folder_id = folders.id 
    WHERE 1=1
";
if ($current_folder_id) {
    // IMPORTANT: Only show files that belong EXACTLY to this folder
    $file_query .= " AND files.folder_id = $current_folder_id";
} else {
    // In root, show files with no folder_id (files in root directory)
    $file_query .= " AND files.folder_id IS NULL";
}
if ($search) {
    $file_query .= " AND files.file_name LIKE '%" . $conn->real_escape_string($search) . "%'";
}
$file_query .= " ORDER BY files.uploaded_at DESC";
$files = $conn->query($file_query);

// Check if query was successful
if (!$files) {
    die("Error in files query: " . $conn->error);
}

// Get all folders for dropdown (for moving files)
$folders_list_query = $conn->query("SELECT * FROM folders ORDER BY folder_name ASC");
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
    <title>File Archive - Admin Drive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/Files.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-archive"></i> PNP Archive</h2>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="homepage.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="active"><a href="files.php"><i class="fas fa-folder"></i> My Drive</a></li>
                <li><a href="files.php?recent=1"><i class="fas fa-clock"></i> Recent</a></li>
                <li><a href="files.php?starred=1"><i class="fas fa-star"></i> Starred</a></li>
                <li><a href="files.php?trash=1"><i class="fas fa-trash"></i> Trash</a></li>
            </ul>

            <div class="storage-info">
                <i class="fas fa-database"></i>
                <span>Storage: 2.5GB / 10GB</span>
                <div class="storage-bar">
                    <div class="storage-used" style="width: 25%"></div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="breadcrumb">
                    <a href="files.php"><i class="fas fa-home"></i> My Drive</a>
                    <?php foreach ($folder_path as $folder): ?>
                        <i class="fas fa-chevron-right"></i>
                        <a href="files.php?folder_id=<?php echo $folder['id']; ?>">
                            <?php echo htmlspecialchars($folder['folder_name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <div class="user-info">
                    <span><?php echo htmlspecialchars($_SESSION['admin_email']); ?></span>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>

            <!-- Search Section -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
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
                        <a href="files.php?folder_id=<?php echo $current_folder_id; ?>" class="clear-filters">Clear</a>
                    <?php else: ?>
                        <a href="files.php" class="clear-filters">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Files Area -->
            <div class="files-area">
                <div class="files-header">
                    <div class="header-name">Name</div>
                    <div class="header-modified">Date Created</div>
                    <div class="header-size">Size</div>
                    <div class="header-actions">Actions</div>
                </div>

                <div class="files-list">
                    <!-- Display Folders -->
                    <?php 
                    $has_folders = false;
                    if ($folders && $folders->num_rows > 0): 
                        $has_folders = true;
                        while ($folder = $folders->fetch_assoc()): 
                    ?>
                        <div class="file-item folder-item" data-id="<?php echo $folder['id']; ?>">
                            <div class="file-name">
                                <i class="fas fa-folder folder-icon"></i>
                                <a href="files.php?folder_id=<?php echo $folder['id']; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                    <?php echo htmlspecialchars($folder['folder_name']); ?>
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
                                <button class="action-btn" onclick="deleteFolder(<?php echo $folder['id']; ?>)" title="Delete">
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
                    if ($files && $files->num_rows > 0): 
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
                                    <button class="action-btn" onclick="deleteFile(<?php echo $file['id']; ?>)" title="Delete">
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

        <!-- Floating Action Button -->
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
                    <!-- Main folder - show year input -->
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
                <p>Are you sure you want to delete this item? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="button" class="delete-btn" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <script>
        let currentDeleteId = null;
        let currentDeleteType = null;

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
                // Remove extension
                const nameWithoutExt = fullName.substring(0, fullName.lastIndexOf('.')) || fullName;
                
                // Only auto-fill if custom filename is empty
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
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function deleteFile(id) {
            currentDeleteId = id;
            currentDeleteType = 'file';
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function renameFolder(id, currentName) {
            const newName = prompt('Enter new folder name:', currentName);
            if (newName && newName !== currentName) {
                window.location.href = `rename_folder.php?id=${id}&name=${encodeURIComponent(newName)}`;
            }
        }

        function renameFile(id, currentName) {
            // Remove extension for the prompt
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
    </script>
</body>
</html>
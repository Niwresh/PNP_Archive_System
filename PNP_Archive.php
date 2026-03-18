<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "PNP_Archive_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Define total storage limit (5GB)
define('TOTAL_STORAGE_LIMIT', 5 * 1024 * 1024 * 1024); // 5GB in bytes

/* =========================
   SEMESTER FUNCTIONS - MUST BE FIRST
========================= */

/**
 * Function to determine semester from month
 * @param int $month Month number (1-12)
 * @return string Semester ('first', 'second', or empty string)
 */
function getSemesterFromMonth($month) {
    if (empty($month)) return '';
    
    $month = intval($month);
    if ($month >= 1 && $month <= 6) {
        return 'first';
    } elseif ($month >= 7 && $month <= 12) {
        return 'second';
    }
    return '';
}

/**
 * Function to get month range for semester
 * @param string $semester 'first' or 'second'
 * @return array [start_month, end_month]
 */
function getSemesterMonths($semester) {
    if ($semester == 'first') {
        return [1, 6]; // January to June
    } elseif ($semester == 'second') {
        return [7, 12]; // July to December
    }
    return [1, 12]; // All months if no semester selected
}

/**
 * Function to get semester name for display
 * @param string $semester 'first' or 'second'
 * @return string Semester name
 */
function getSemesterName($semester) {
    if ($semester == 'first') {
        return 'First Semester';
    } elseif ($semester == 'second') {
        return 'Second Semester';
    }
    return '';
}

/* =========================
   STORAGE FUNCTIONS
========================= */

// Function to calculate total storage used
function calculateTotalStorage($conn) {
    $storage_used = 0;
    $query = $conn->query("SELECT file_path FROM files WHERE deleted_at IS NULL");
    
    if ($query && $query->num_rows > 0) {
        while ($file = $query->fetch_assoc()) {
            $file_path = $file['file_path'];
            
            // Check multiple possible paths
            if (file_exists($file_path)) {
                $storage_used += filesize($file_path);
            } elseif (file_exists('uploads/' . basename($file_path))) {
                $storage_used += filesize('uploads/' . basename($file_path));
            } elseif (file_exists(__DIR__ . '/' . $file_path)) {
                $storage_used += filesize(__DIR__ . '/' . $file_path);
            }
        }
    }
    
    return $storage_used;
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}

/* =========================
   FOLDER FUNCTIONS
========================= */

// Function to get folder path hierarchy
function getFolderPath($folder_id, $conn) {
    $path = [];
    $current_id = $folder_id;
    
    while ($current_id) {
        $query = $conn->query("SELECT id, folder_name, parent_id FROM folders WHERE id = $current_id");
        if ($query && $folder = $query->fetch_assoc()) {
            array_unshift($path, $folder);
            $current_id = $folder['parent_id'];
        } else {
            break;
        }
    }
    
    return $path;
}

// Function to get folder year
function getFolderYear($folder_id, $conn) {
    if (!$folder_id) return null;
    
    $query = $conn->query("SELECT year FROM folders WHERE id = $folder_id");
    if ($query && $folder = $query->fetch_assoc()) {
        return $folder['year'];
    }
    return null;
}

// Function to get file icon based on extension
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel',
        'xlsx' => 'fa-file-excel',
        'ppt' => 'fa-file-powerpoint',
        'pptx' => 'fa-file-powerpoint',
        'jpg' => 'fa-file-image',
        'jpeg' => 'fa-file-image',
        'png' => 'fa-file-image',
        'gif' => 'fa-file-image',
        'txt' => 'fa-file-alt',
        'zip' => 'fa-file-archive',
        'rar' => 'fa-file-archive',
        'mp4' => 'fa-file-video',
        'mp3' => 'fa-file-audio'
    ];
    
    return isset($icons[$ext]) ? $icons[$ext] : 'fa-file';
}

// Function to sanitize filename
function sanitizeFilename($filename) {
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9\-\.\s_]/', '', $filename);
    return $filename;
}

// Function to create directory recursively
function createDirectory($path) {
    if (!file_exists($path)) {
        return mkdir($path, 0777, true);
    }
    return true;
}

// Function to check if folder has children
function hasChildren($folder_id, $conn) {
    $query = $conn->query("SELECT id FROM folders WHERE parent_id = $folder_id AND deleted_at IS NULL LIMIT 1");
    return $query && $query->num_rows > 0;
}

// Function to count files in folder
function countFilesInFolder($folder_id, $conn) {
    $query = $conn->query("SELECT COUNT(*) as count FROM files WHERE folder_id = $folder_id AND deleted_at IS NULL");
    if ($query && $row = $query->fetch_assoc()) {
        return $row['count'];
    }
    return 0;
}

// Function to get folder size (recursive)
function getFolderSize($folder_id, $conn) {
    $total_size = 0;
    
    $files_query = $conn->query("SELECT file_path FROM files WHERE folder_id = $folder_id AND deleted_at IS NULL");
    while ($file = $files_query->fetch_assoc()) {
        if (file_exists($file['file_path'])) {
            $total_size += filesize($file['file_path']);
        }
    }
    
    $subfolders_query = $conn->query("SELECT id FROM folders WHERE parent_id = $folder_id AND deleted_at IS NULL");
    while ($subfolder = $subfolders_query->fetch_assoc()) {
        $total_size += getFolderSize($subfolder['id'], $conn);
    }
    
    return $total_size;
}

// Function to get human readable file size
function getHumanReadableSize($file_path) {
    if (file_exists($file_path)) {
        return formatFileSize(filesize($file_path));
    }
    return '0 bytes';
}

/* =========================
   TRASH FUNCTIONS
========================= */

// Function to move item to trash
function moveToTrash($type, $id, $conn) {
    $deleted_at = date('Y-m-d H:i:s');
    
    if ($type == 'folder') {
        $conn->query("UPDATE folders SET deleted_at = '$deleted_at' WHERE id = $id");
        
        $subfolders = $conn->query("SELECT id FROM folders WHERE parent_id = $id");
        while ($subfolder = $subfolders->fetch_assoc()) {
            moveToTrash('folder', $subfolder['id'], $conn);
        }
        
        $files = $conn->query("SELECT id FROM files WHERE folder_id = $id");
        while ($file = $files->fetch_assoc()) {
            $conn->query("UPDATE files SET deleted_at = '$deleted_at' WHERE id = " . $file['id']);
        }
        
        return true;
    } elseif ($type == 'file') {
        $conn->query("UPDATE files SET deleted_at = '$deleted_at' WHERE id = $id");
        return true;
    }
    
    return false;
}

// Function to restore item from trash
function restoreFromTrash($type, $id, $conn) {
    if ($type == 'folder') {
        $conn->query("UPDATE folders SET deleted_at = NULL WHERE id = $id");
        
        $subfolders = $conn->query("SELECT id FROM folders WHERE parent_id = $id");
        while ($subfolder = $subfolders->fetch_assoc()) {
            restoreFromTrash('folder', $subfolder['id'], $conn);
        }
        
        $files = $conn->query("SELECT id FROM files WHERE folder_id = $id");
        while ($file = $files->fetch_assoc()) {
            $conn->query("UPDATE files SET deleted_at = NULL WHERE id = " . $file['id']);
        }
        
        return true;
    } elseif ($type == 'file') {
        $conn->query("UPDATE files SET deleted_at = NULL WHERE id = $id");
        return true;
    }
    
    return false;
}

// Function to permanently delete item
function permanentlyDelete($type, $id, $conn) {
    if ($type == 'folder') {
        $files = $conn->query("SELECT file_path FROM files WHERE folder_id = $id");
        while ($file = $files->fetch_assoc()) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
        
        $conn->query("DELETE FROM files WHERE folder_id = $id");
        
        $subfolders = $conn->query("SELECT id FROM folders WHERE parent_id = $id");
        while ($subfolder = $subfolders->fetch_assoc()) {
            permanentlyDelete('folder', $subfolder['id'], $conn);
        }
        
        $conn->query("DELETE FROM folders WHERE id = $id");
        
        $path = getFolderPath($id, $conn);
        if (!empty($path)) {
            $physical_path = "uploads/";
            foreach ($path as $p) {
                $physical_path .= $p['folder_name'] . "/";
            }
            if (is_dir($physical_path)) {
                rmdir($physical_path);
            }
        }
        
        return true;
    } elseif ($type == 'file') {
        $file_query = $conn->query("SELECT file_path FROM files WHERE id = $id");
        if ($file = $file_query->fetch_assoc()) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
        
        $conn->query("DELETE FROM files WHERE id = $id");
        return true;
    }
    
    return false;
}

// Function to get storage statistics
function getStorageStats($conn) {
    $storage_used = calculateTotalStorage($conn);
    $storage_percent = min(100, round(($storage_used / TOTAL_STORAGE_LIMIT) * 100, 1));
    
    $color = '#4caf50';
    if ($storage_percent > 80) {
        $color = '#ff9800';
    }
    if ($storage_percent > 95) {
        $color = '#f44336';
    }
    
    return [
        'used' => $storage_used,
        'used_formatted' => formatFileSize($storage_used),
        'percent' => $storage_percent,
        'color' => $color,
        'total_formatted' => '5 GB',
        'free' => TOTAL_STORAGE_LIMIT - $storage_used,
        'free_formatted' => formatFileSize(TOTAL_STORAGE_LIMIT - $storage_used)
    ];
}
?>
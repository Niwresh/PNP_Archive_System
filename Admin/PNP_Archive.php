<?php
$conn = new mysqli("localhost", "root", "", "PNP_Archive_db");

if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

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
    // Remove any path information
    $filename = basename($filename);
    // Replace special characters
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

// Function to get human readable file size
function getHumanReadableSize($file_path) {
    if (file_exists($file_path)) {
        return formatFileSize(filesize($file_path));
    }
    return '0 bytes';
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
    
    // Get all files in this folder
    $files_query = $conn->query("SELECT file_path FROM files WHERE folder_id = $folder_id AND deleted_at IS NULL");
    while ($file = $files_query->fetch_assoc()) {
        if (file_exists($file['file_path'])) {
            $total_size += filesize($file['file_path']);
        }
    }
    
    // Get all subfolders
    $subfolders_query = $conn->query("SELECT id FROM folders WHERE parent_id = $folder_id AND deleted_at IS NULL");
    while ($subfolder = $subfolders_query->fetch_assoc()) {
        $total_size += getFolderSize($subfolder['id'], $conn);
    }
    
    return $total_size;
}

// Function to move item to trash
function moveToTrash($type, $id, $conn) {
    $deleted_at = date('Y-m-d H:i:s');
    
    if ($type == 'folder') {
        // Recursively move all subfolders and files to trash
        $conn->query("UPDATE folders SET deleted_at = '$deleted_at' WHERE id = $id");
        
        // Get all subfolders
        $subfolders = $conn->query("SELECT id FROM folders WHERE parent_id = $id");
        while ($subfolder = $subfolders->fetch_assoc()) {
            moveToTrash('folder', $subfolder['id'], $conn);
        }
        
        // Get all files in this folder
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
        // Restore folder
        $conn->query("UPDATE folders SET deleted_at = NULL WHERE id = $id");
        
        // Restore all subfolders
        $subfolders = $conn->query("SELECT id FROM folders WHERE parent_id = $id");
        while ($subfolder = $subfolders->fetch_assoc()) {
            restoreFromTrash('folder', $subfolder['id'], $conn);
        }
        
        // Restore all files in this folder
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
        // Get all files in this folder and delete physically
        $files = $conn->query("SELECT file_path FROM files WHERE folder_id = $id");
        while ($file = $files->fetch_assoc()) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
        
        // Delete files from database
        $conn->query("DELETE FROM files WHERE folder_id = $id");
        
        // Get all subfolders and delete recursively
        $subfolders = $conn->query("SELECT id FROM folders WHERE parent_id = $id");
        while ($subfolder = $subfolders->fetch_assoc()) {
            permanentlyDelete('folder', $subfolder['id'], $conn);
        }
        
        // Delete folder from database
        $conn->query("DELETE FROM folders WHERE id = $id");
        
        // Try to delete physical folder
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
        // Get file info
        $file_query = $conn->query("SELECT file_path FROM files WHERE id = $id");
        if ($file = $file_query->fetch_assoc()) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
        
        // Delete from database
        $conn->query("DELETE FROM files WHERE id = $id");
        return true;
    }
    
    return false;
}
?>
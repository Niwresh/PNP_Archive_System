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
?>
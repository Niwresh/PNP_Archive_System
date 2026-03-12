<?php
session_start();
require_once "PNP_Archive.php";

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Get parameters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'trash'; // 'trash' or 'permanent'
$redirect_folder = isset($_GET['folder_id']) ? $_GET['folder_id'] : null;

// Validate input
if (empty($type) || $id <= 0) {
    $_SESSION['error'] = "Invalid request parameters.";
    header("Location: homepage.php");
    exit();
}

if ($action == 'permanent') {
    // PERMANENT DELETE ACTION
    if ($type == 'folder') {
        // Get folder info to find its path
        $folder_query = $conn->query("SELECT * FROM folders WHERE id = $id");
        if ($folder_query && $folder_query->num_rows > 0) {
            $folder = $folder_query->fetch_assoc();
            
            // Get all files in this folder and subfolders
            $files_query = $conn->query("SELECT file_path FROM files WHERE folder_id = $id");
            while ($file = $files_query->fetch_assoc()) {
                if (file_exists($file['file_path'])) {
                    unlink($file['file_path']);
                }
            }
            
            // Delete files from database
            $conn->query("DELETE FROM files WHERE folder_id = $id");
            
            // Get all subfolders and delete them recursively
            $subfolders = $conn->query("SELECT id FROM folders WHERE parent_id = $id");
            while ($subfolder = $subfolders->fetch_assoc()) {
                // Recursively delete subfolders (simplified - you might want to create a recursive function)
                $subfiles = $conn->query("SELECT file_path FROM files WHERE folder_id = " . $subfolder['id']);
                while ($subfile = $subfiles->fetch_assoc()) {
                    if (file_exists($subfile['file_path'])) {
                        unlink($subfile['file_path']);
                    }
                }
                $conn->query("DELETE FROM files WHERE folder_id = " . $subfolder['id']);
                $conn->query("DELETE FROM folders WHERE id = " . $subfolder['id']);
            }
            
            // Delete folder record
            if ($conn->query("DELETE FROM folders WHERE id = $id")) {
                // Try to delete physical folder if empty
                $path = getFolderPath($id, $conn);
                $physical_path = "uploads/";
                if (!empty($path)) {
                    foreach ($path as $p) {
                        $physical_path .= $p['folder_name'] . "/";
                    }
                    if (is_dir($physical_path)) {
                        rmdir($physical_path);
                    }
                }
                $_SESSION['success'] = "Folder permanently deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting folder: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "Folder not found.";
        }
        
        // Redirect to trash view
        header("Location: homepage.php?view=trash");
        exit();
        
    } elseif ($type == 'file') {
        // Get file info
        $file_query = $conn->query("SELECT * FROM files WHERE id = $id");
        if ($file_query && $file_query->num_rows > 0) {
            $file = $file_query->fetch_assoc();
            
            // Delete physical file
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
            
            // Delete database record
            if ($conn->query("DELETE FROM files WHERE id = $id")) {
                $_SESSION['success'] = "File permanently deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting file: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "File not found.";
        }
        
        // Redirect to trash view
        header("Location: homepage.php?view=trash");
        exit();
    }
    
} else {
    // MOVE TO TRASH ACTION (default)
    if ($type == 'folder') {
        // Check if folder exists
        $check_query = $conn->query("SELECT id FROM folders WHERE id = $id");
        if ($check_query && $check_query->num_rows > 0) {
            // Move folder to trash using the function from PNP_Archive.php
            if (function_exists('moveToTrash')) {
                moveToTrash('folder', $id, $conn);
                $_SESSION['success'] = "Folder moved to trash successfully!";
            } else {
                // Fallback if function doesn't exist
                $deleted_at = date('Y-m-d H:i:s');
                $conn->query("UPDATE folders SET deleted_at = '$deleted_at' WHERE id = $id");
                
                // Move all subfolders to trash
                $subfolders = $conn->query("SELECT id FROM folders WHERE parent_id = $id");
                while ($subfolder = $subfolders->fetch_assoc()) {
                    $conn->query("UPDATE folders SET deleted_at = '$deleted_at' WHERE id = " . $subfolder['id']);
                }
                
                // Move all files in this folder to trash
                $conn->query("UPDATE files SET deleted_at = '$deleted_at' WHERE folder_id = $id");
                
                $_SESSION['success'] = "Folder moved to trash successfully!";
            }
            
            // Get parent_id for redirect
            $folder_query = $conn->query("SELECT parent_id FROM folders WHERE id = $id");
            $folder = $folder_query->fetch_assoc();
            $redirect_folder = $folder['parent_id'];
        } else {
            $_SESSION['error'] = "Folder not found.";
        }
        
    } elseif ($type == 'file') {
        // Check if file exists
        $check_query = $conn->query("SELECT id FROM files WHERE id = $id");
        if ($check_query && $check_query->num_rows > 0) {
            // Move file to trash using the function from PNP_Archive.php
            if (function_exists('moveToTrash')) {
                moveToTrash('file', $id, $conn);
                $_SESSION['success'] = "File moved to trash successfully!";
            } else {
                // Fallback if function doesn't exist
                $deleted_at = date('Y-m-d H:i:s');
                $conn->query("UPDATE files SET deleted_at = '$deleted_at' WHERE id = $id");
                $_SESSION['success'] = "File moved to trash successfully!";
            }
            
            // Get folder_id for redirect
            $file_query = $conn->query("SELECT folder_id FROM files WHERE id = $id");
            $file = $file_query->fetch_assoc();
            $redirect_folder = $file['folder_id'];
        } else {
            $_SESSION['error'] = "File not found.";
        }
    }
    
    // Redirect to appropriate location
    $redirect = "homepage.php?view=drive";
    if ($redirect_folder) {
        $redirect .= "&folder_id=" . $redirect_folder;
    }
    header("Location: $redirect");
    exit();
}
?>
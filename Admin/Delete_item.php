<?php
session_start();
require_once "PNP_Archive.php";

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$type = $_GET['type'];
$id = intval($_GET['id']);
$redirect_folder = isset($_GET['folder_id']) ? $_GET['folder_id'] : null;

if ($type == 'folder') {
    // Get folder info to find its path
    $folder_query = $conn->query("SELECT * FROM folders WHERE id = $id");
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
    
    // Delete folder record
    if ($conn->query("DELETE FROM folders WHERE id = $id")) {
        // Try to delete physical folder if empty
        $path = getFolderPath($id, $conn);
        $physical_path = "uploads/";
        foreach ($path as $p) {
            $physical_path .= $p['folder_name'] . "/";
        }
        if (is_dir($physical_path)) {
            rmdir($physical_path);
        }
        $_SESSION['success'] = "Folder deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting folder.";
    }
} elseif ($type == 'file') {
    // Get file info
    $file_query = $conn->query("SELECT * FROM files WHERE id = $id");
    $file = $file_query->fetch_assoc();
    
    // Delete physical file
    if (file_exists($file['file_path'])) {
        unlink($file['file_path']);
    }
    
    // Delete database record
    if ($conn->query("DELETE FROM files WHERE id = $id")) {
        $_SESSION['success'] = "File deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting file.";
    }
}

$redirect = "files.php";
if ($redirect_folder) {
    $redirect .= "?folder_id=" . $redirect_folder;
}
header("Location: $redirect");
exit();
?>
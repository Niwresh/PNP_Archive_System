<?php
session_start();
require_once 'PNP_Archive.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$file_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
}

header("Location: files.php?folder=" . ($file['folder_id'] ?? '') . "&success=File deleted successfully");
exit();
?>
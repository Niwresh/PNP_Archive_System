<?php
session_start();
require_once "PNP_Archive.php";

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$id = intval($_GET['id']);
$new_name = $_GET['name'];

// Get old file info
$file_query = $conn->query("SELECT * FROM files WHERE id = $id");
$old_file = $file_query->fetch_assoc();

$file_extension = pathinfo($old_file['file_name'], PATHINFO_EXTENSION);
$new_filename = $new_name . '.' . $file_extension;

// Update database
$sql = "UPDATE files SET file_name = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $new_filename, $id);

if ($stmt->execute()) {
    // Rename physical file
    $old_path = $old_file['file_path'];
    $new_path = str_replace($old_file['file_name'], $new_filename, $old_path);
    
    if (file_exists($old_path)) {
        rename($old_path, $new_path);
        
        // Update file_path in database
        $conn->query("UPDATE files SET file_path = '$new_path' WHERE id = $id");
    }
    
    $_SESSION['success'] = "File renamed successfully!";
} else {
    $_SESSION['error'] = "Error renaming file.";
}

header("Location: homepage.php?folder_id=" . $old_file['folder_id']);
exit();
?>
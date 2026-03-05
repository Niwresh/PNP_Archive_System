<?php
session_start();
require_once 'PNP_Archive.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$folder_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Update files to remove folder association
$stmt = $conn->prepare("UPDATE files SET folder_id = NULL WHERE folder_id = ?");
$stmt->bind_param("i", $folder_id);
$stmt->execute();

// Delete the folder
$stmt = $conn->prepare("DELETE FROM folders WHERE id = ?");
$stmt->bind_param("i", $folder_id);
$stmt->execute();

header("Location: files.php?success=Folder deleted successfully");
exit();
?>
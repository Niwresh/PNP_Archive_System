<?php
session_start();
require_once "PNP_Archive.php";

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Get all folders in trash
$folders = $conn->query("SELECT id FROM folders WHERE deleted_at IS NOT NULL");
while ($folder = $folders->fetch_assoc()) {
    permanentlyDelete('folder', $folder['id'], $conn);
}

// Get all files in trash
$files = $conn->query("SELECT id FROM files WHERE deleted_at IS NOT NULL");
while ($file = $files->fetch_assoc()) {
    permanentlyDelete('file', $file['id'], $conn);
}

$_SESSION['success'] = "Trash emptied successfully!";
header("Location: Homepage.php?view=trash");
exit();
?>
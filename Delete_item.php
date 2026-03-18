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

if (empty($type) || $id <= 0) {
    $_SESSION['error'] = "Invalid request parameters.";
    header("Location: homepage.php");
    exit();
}

// Move to trash using the function from PNP_Archive.php
if (function_exists('moveToTrash')) {
    moveToTrash($type, $id, $conn);
    $_SESSION['success'] = ucfirst($type) . " moved to trash successfully!";
} else {
    $_SESSION['error'] = "Function moveToTrash not found.";
}

// Get redirect info
$redirect_folder = null;
if ($type == 'folder') {
    $folder_query = $conn->query("SELECT parent_id FROM folders WHERE id = $id");
    if ($folder_query && $folder_row = $folder_query->fetch_assoc()) {
        $redirect_folder = $folder_row['parent_id'];
    }
} elseif ($type == 'file') {
    $file_query = $conn->query("SELECT folder_id FROM files WHERE id = $id");
    if ($file_query && $file_row = $file_query->fetch_assoc()) {
        $redirect_folder = $file_row['folder_id'];
    }
}

$redirect = "homepage.php?view=drive";
if ($redirect_folder) {
    $redirect .= "&folder_id=" . $redirect_folder;
}

header("Location: $redirect");
exit();
?>
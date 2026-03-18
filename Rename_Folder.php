<?php
session_start();
require_once "PNP_Archive.php";

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$new_name = isset($_GET['name']) ? trim($_GET['name']) : '';

if ($id <= 0 || empty($new_name)) {
    $_SESSION['error'] = "Invalid request parameters.";
    header("Location: homepage.php");
    exit();
}

// Get old folder info
$folder_query = $conn->query("SELECT * FROM folders WHERE id = $id");
if (!$folder_query || $folder_query->num_rows == 0) {
    $_SESSION['error'] = "Folder not found.";
    header("Location: homepage.php");
    exit();
}

$old_folder = $folder_query->fetch_assoc();

// Update database
$sql = "UPDATE folders SET folder_name = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $new_name, $id);

if ($stmt->execute()) {
    // Rename physical folder
    $path = getFolderPath($id, $conn);
    array_pop($path); // Remove current folder from path
    
    $physical_path = "uploads/";
    foreach ($path as $p) {
        $physical_path .= $p['folder_name'] . "/";
    }
    
    $old_path = $physical_path . $old_folder['folder_name'];
    $new_path = $physical_path . $new_name;
    
    if (file_exists($old_path)) {
        rename($old_path, $new_path);
    }
    
    $_SESSION['success'] = "Folder renamed successfully!";
} else {
    $_SESSION['error'] = "Error renaming folder.";
}

$redirect = "homepage.php";
if ($old_folder['parent_id']) {
    $redirect .= "?folder_id=" . $old_folder['parent_id'];
}

header("Location: " . $redirect);
exit();
?>
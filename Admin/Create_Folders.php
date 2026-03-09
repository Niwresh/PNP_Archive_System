<?php
session_start();
require_once "PNP_Archive.php";

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$folder_name = $_POST['folder_name'];
$description = $_POST['description'];
$parent_id = $_POST['parent_id'] ? intval($_POST['parent_id']) : null;

// Check if it's a main folder or subfolder
if ($parent_id) {
    // Subfolder - inherit year from parent
    $parent_query = $conn->query("SELECT year FROM folders WHERE id = $parent_id");
    $parent = $parent_query->fetch_assoc();
    $year = $parent['year'];
    $month = $_POST['month']; // Month is selected for subfolders
    
    $sql = "INSERT INTO folders (folder_name, description, parent_id, year) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $folder_name, $description, $parent_id, $year);
} else {
    // Main folder - year is provided
    $year = $_POST['year'];
    
    $sql = "INSERT INTO folders (folder_name, description, year) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $folder_name, $description, $year);
}

if ($stmt->execute()) {
    $folder_id = $stmt->insert_id;
    
    // Create physical folder
    $base_path = "uploads/";
    if (!file_exists($base_path)) {
        mkdir($base_path, 0777, true);
    }
    
    if ($parent_id) {
        // Get parent path
        $path = getFolderPath($parent_id, $conn);
        $folder_path = $base_path;
        foreach ($path as $p) {
            $folder_path .= $p['folder_name'] . "/";
            if (!file_exists($folder_path)) {
                mkdir($folder_path, 0777, true);
            }
        }
        $folder_path .= $folder_name . "/";
    } else {
        $folder_path = $base_path . $folder_name . "/";
    }
    
    if (!file_exists($folder_path)) {
        mkdir($folder_path, 0777, true);
    }
    
    $_SESSION['success'] = "Folder created successfully!";
} else {
    $_SESSION['error'] = "Error creating folder: " . $conn->error;
}

$redirect = "files.php";
if ($parent_id) {
    $redirect .= "?folder_id=" . $parent_id;
}
header("Location: $redirect");
exit();
?>
<?php
session_start();
require_once "PNP_Archive.php";

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$folder_id = !empty($_POST['folder_id']) ? intval($_POST['folder_id']) : null;
$day = !empty($_POST['day']) ? intval($_POST['day']) : null;
$month = !empty($_POST['month']) ? intval($_POST['month']) : null;
$year = !empty($_POST['year']) ? intval($_POST['year']) : null;
$custom_filename = isset($_POST['custom_filename']) ? trim($_POST['custom_filename']) : '';

// Validate month if provided
if ($month && ($month < 1 || $month > 12)) {
    $_SESSION['error'] = "Invalid month selected.";
    header("Location: homepage.php" . ($folder_id ? "?folder_id=" . $folder_id : ""));
    exit();
}

// Validate day if provided
if ($day && ($day < 1 || $day > 31)) {
    $_SESSION['error'] = "Invalid day selected.";
    header("Location: homepage.php" . ($folder_id ? "?folder_id=" . $folder_id : ""));
    exit();
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] != UPLOAD_ERR_OK) {
    $_SESSION['error'] = "File upload error. Please try again.";
    header("Location: homepage.php" . ($folder_id ? "?folder_id=" . $folder_id : ""));
    exit();
}

// Get file information
$file_tmp = $_FILES['file']['tmp_name'];
$original_filename = $_FILES['file']['name'];
$file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

// Determine final filename
if (!empty($custom_filename)) {
    $custom_filename = preg_replace('/[^a-zA-Z0-9\-\_\s]/', '', $custom_filename);
    if (empty($custom_filename)) {
        $custom_filename = "file_" . time();
    }
    $final_filename = $custom_filename . '.' . $file_extension;
} else {
    $filename_without_ext = pathinfo($original_filename, PATHINFO_FILENAME);
    $filename_without_ext = preg_replace('/[^a-zA-Z0-9\-\_\s]/', '', $filename_without_ext);
    if (empty($filename_without_ext)) {
        $filename_without_ext = "file_" . time();
    }
    $final_filename = $filename_without_ext . '.' . $file_extension;
}

// Determine upload path
$base_path = "uploads/";

if (!file_exists($base_path)) {
    mkdir($base_path, 0777, true);
}

$folder_year = null;
$upload_dir = $base_path;

if ($folder_id) {
    // Get folder information
    $folder_query = $conn->query("SELECT folder_name, year FROM folders WHERE id = $folder_id");
    if ($folder_query && $folder_query->num_rows > 0) {
        $folder = $folder_query->fetch_assoc();
        $upload_dir = $base_path . $folder['folder_name'] . "/";
        $folder_year = $folder['year']; // Get folder's year for inheritance
    } else {
        $_SESSION['error'] = "Selected folder not found.";
        header("Location: homepage.php");
        exit();
    }
}

// Create upload directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle duplicate filenames
$file_path = $upload_dir . $final_filename;
$counter = 1;
while (file_exists($file_path)) {
    $filename_without_ext = pathinfo($final_filename, PATHINFO_FILENAME);
    $new_filename = $filename_without_ext . " ($counter)." . $file_extension;
    $file_path = $upload_dir . $new_filename;
    $final_filename = $new_filename;
    $counter++;
}

if (move_uploaded_file($file_tmp, $file_path)) {
    // Determine year to save:
    if ($folder_id) {
        // File inside a folder - INHERIT YEAR FROM FOLDER
        $final_year = $folder_year;
    } else {
        // File in root directory - Year is required
        if (empty($year)) {
            $_SESSION['error'] = "Year is required for files in root directory.";
            unlink($file_path);
            header("Location: homepage.php");
            exit();
        }
        
        // Validate year
        if ($year < 2000 || $year > intval(date('Y'))) {
            $_SESSION['error'] = "Invalid year selected.";
            unlink($file_path);
            header("Location: homepage.php");
            exit();
        }
        $final_year = $year;
    }
    
    // Insert file record
    $sql = "INSERT INTO files (folder_id, file_name, file_path, year, month, day) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issiii", $folder_id, $final_filename, $file_path, $final_year, $month, $day);
    
    if ($stmt->execute()) {
        $success_msg = "File uploaded successfully!";
        if ($final_year) {
            $success_msg .= " (Year: $final_year)";
        }
        $_SESSION['success'] = $success_msg;
    } else {
        $_SESSION['error'] = "Error saving file record: " . $conn->error;
        unlink($file_path);
    }
} else {
    $_SESSION['error'] = "Error moving uploaded file.";
}

// Redirect back
$redirect = "homepage.php";
if ($folder_id) {
    $redirect .= "?folder_id=" . $folder_id;
}

header("Location: " . $redirect);
exit();
?>
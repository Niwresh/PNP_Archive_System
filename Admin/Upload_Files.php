<?php
session_start();
require_once "PNP_Archive.php";

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}


$folder_id = !empty($_POST['folder_id']) ? intval($_POST['folder_id']) : null;
$day = !empty($_POST['day']) ? intval($_POST['day']) : null;
$custom_filename = isset($_POST['custom_filename']) ? trim($_POST['custom_filename']) : '';


if (!isset($_FILES['file']) || $_FILES['file']['error'] != UPLOAD_ERR_OK) {
    $error_message = "File upload error: ";
    switch ($_FILES['file']['error']) {
        case UPLOAD_ERR_INI_SIZE:
            $error_message .= "The uploaded file exceeds the upload_max_filesize directive in php.ini";
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $error_message .= "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
            break;
        case UPLOAD_ERR_PARTIAL:
            $error_message .= "The uploaded file was only partially uploaded";
            break;
        case UPLOAD_ERR_NO_FILE:
            $error_message .= "No file was uploaded";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $error_message .= "Missing a temporary folder";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $error_message .= "Failed to write file to disk";
            break;
        case UPLOAD_ERR_EXTENSION:
            $error_message .= "A PHP extension stopped the file upload";
            break;
        default:
            $error_message .= "Unknown upload error";
    }
    $_SESSION['error'] = $error_message;
    header("Location: files.php" . ($folder_id ? "?folder_id=" . $folder_id : ""));
    exit();
}

// Get file information
$file_tmp = $_FILES['file']['tmp_name'];
$original_filename = $_FILES['file']['name'];
$file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

// Determine final filename
if (!empty($custom_filename)) {
    // Sanitize custom filename
    $custom_filename = preg_replace('/[^a-zA-Z0-9\-\_\s]/', '', $custom_filename);
    if (empty($custom_filename)) {
        $custom_filename = "file_" . time();
    }
    $final_filename = $custom_filename . '.' . $file_extension;
} else {
    // Use original filename but sanitize it
    $filename_without_ext = pathinfo($original_filename, PATHINFO_FILENAME);
    $filename_without_ext = preg_replace('/[^a-zA-Z0-9\-\_\s]/', '', $filename_without_ext);
    if (empty($filename_without_ext)) {
        $filename_without_ext = "file_" . time();
    }
    $final_filename = $filename_without_ext . '.' . $file_extension;
}

// Determine upload path
$base_path = "uploads/";

// Create base uploads directory if it doesn't exist
if (!file_exists($base_path)) {
    mkdir($base_path, 0777, true);
}

if ($folder_id) {
    // Get folder information
    $folder_query = $conn->query("SELECT folder_name FROM folders WHERE id = $folder_id");
    if ($folder_query && $folder_query->num_rows > 0) {
        $folder = $folder_query->fetch_assoc();
        $upload_dir = $base_path . $folder['folder_name'] . "/";
    } else {
        $_SESSION['error'] = "Selected folder not found.";
        header("Location: files.php");
        exit();
    }
} else {
    $upload_dir = $base_path;
}

// Ensure directory exists
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Check if file with same name already exists and handle it
$file_path = $upload_dir . $final_filename;
$counter = 1;
while (file_exists($file_path)) {
    $filename_without_ext = pathinfo($final_filename, PATHINFO_FILENAME);
    $new_filename = $filename_without_ext . " ($counter)." . $file_extension;
    $file_path = $upload_dir . $new_filename;
    $final_filename = $new_filename;
    $counter++;
}

// Upload file
if (move_uploaded_file($file_tmp, $file_path)) {
    // Get year and month from folder if available
    $year = null;
    $month = null;
    
    if ($folder_id) {
        $folder_info = $conn->query("SELECT year FROM folders WHERE id = $folder_id");
        if ($folder_info && $folder_info->num_rows > 0) {
            $folder_data = $folder_info->fetch_assoc();
            $year = $folder_data['year'];
        }
    }
    
    // Insert file record
    $sql = "INSERT INTO files (folder_id, file_name, file_path, year, month, day) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issiii", $folder_id, $final_filename, $file_path, $year, $month, $day);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "File uploaded successfully!";
    } else {
        $_SESSION['error'] = "Error saving file record: " . $conn->error;
        // Delete uploaded file if database insert fails
        unlink($file_path);
    }
} else {
    $_SESSION['error'] = "Error moving uploaded file. Please check directory permissions.";
}

// Redirect back
$redirect = "files.php";
if ($folder_id) {
    $redirect .= "?folder_id=" . $folder_id;
}
header("Location: " . $redirect);
exit();
?>
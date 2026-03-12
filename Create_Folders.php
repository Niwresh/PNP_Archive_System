<?php
session_start();
require_once "PNP_Archive.php";

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Get form data
$folder_name = trim($_POST['folder_name']);
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
$year = isset($_POST['year']) ? intval($_POST['year']) : null;
$month = isset($_POST['month']) ? intval($_POST['month']) : null;

// Validate folder name
if (empty($folder_name)) {
    $_SESSION['error'] = "Folder name is required.";
    header("Location: homepage.php" . ($parent_id ? "?folder_id=" . $parent_id : ""));
    exit();
}

// Validate month if provided
if ($month && ($month < 1 || $month > 12)) {
    $_SESSION['error'] = "Invalid month selected.";
    header("Location: homepage.php" . ($parent_id ? "?folder_id=" . $parent_id : ""));
    exit();
}

// Validate year if provided
if ($year && ($year < 2000 || $year > intval(date('Y')))) {
    $_SESSION['error'] = "Invalid year selected.";
    header("Location: homepage.php" . ($parent_id ? "?folder_id=" . $parent_id : ""));
    exit();
}

// Check if it's a main folder or subfolder
if ($parent_id) {
    // Subfolder - get parent info
    $parent_query = $conn->query("SELECT year, month, folder_name FROM folders WHERE id = $parent_id");
    if (!$parent_query || $parent_query->num_rows == 0) {
        $_SESSION['error'] = "Parent folder not found.";
        header("Location: homepage.php");
        exit();
    }
    
    $parent = $parent_query->fetch_assoc();
    
    // Determine year for subfolder
    // Priority: 1. Provided year, 2. Parent's year
    $folder_year = $year ?: $parent['year'];
    
    // Determine month for subfolder
    // Priority: 1. Provided month, 2. Parent's month
    $folder_month = $month ?: $parent['month'];
    
    // Insert subfolder
    $sql = "INSERT INTO folders (folder_name, description, parent_id, year, month) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiii", $folder_name, $description, $parent_id, $folder_year, $folder_month);
} else {
    // Main folder - use provided year and month
    $sql = "INSERT INTO folders (folder_name, description, year, month) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $folder_name, $description, $year, $month);
}

if ($stmt->execute()) {
    $folder_id = $stmt->insert_id;
    
    // Create physical folder in uploads directory
    $base_path = "uploads/";
    if (!file_exists($base_path)) {
        mkdir($base_path, 0777, true);
    }
    
    // Build the full folder path
    if ($parent_id) {
        // Get full parent path
        $path = [];
        $current_id = $parent_id;
        
        // Build path array from parent up to root
        while ($current_id) {
            $path_query = $conn->query("SELECT id, folder_name, parent_id FROM folders WHERE id = $current_id");
            if ($path_query && $path_row = $path_query->fetch_assoc()) {
                array_unshift($path, $path_row);
                $current_id = $path_row['parent_id'];
            } else {
                break;
            }
        }
        
        // Create folder path
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
    
    // Create the final folder
    if (!file_exists($folder_path)) {
        if (mkdir($folder_path, 0777, true)) {
            // Success message with month info if provided
            $success_msg = "Folder created successfully!";
            if ($folder_month) {
                $month_name = date('F', mktime(0, 0, 0, $folder_month, 1));
                $success_msg .= " (Month: $month_name)";
            }
            $_SESSION['success'] = $success_msg;
        } else {
            $_SESSION['error'] = "Folder created in database but could not create physical directory.";
        }
    } else {
        $_SESSION['error'] = "A folder with this name already exists in the physical directory.";
    }
} else {
    $_SESSION['error'] = "Error creating folder: " . $conn->error;
}

// Redirect back
$redirect = "homepage.php";
if ($parent_id) {
    $redirect .= "?folder_id=" . $parent_id;
}
// Preserve filters if they exist
$params = [];
if (!empty($_GET['year'])) $params[] = "year=" . urlencode($_GET['year']);
if (!empty($_GET['semester'])) $params[] = "semester=" . urlencode($_GET['semester']);
if (!empty($_GET['search'])) $params[] = "search=" . urlencode($_GET['search']);

if (!empty($params)) {
    $redirect .= (strpos($redirect, '?') === false ? '?' : '&') . implode('&', $params);
}

header("Location: " . $redirect);
exit();
?>
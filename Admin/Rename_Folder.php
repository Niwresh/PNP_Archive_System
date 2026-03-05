<?php
session_start();
require_once 'PNP_Archive.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$folder_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$new_name = isset($_GET['name']) ? trim($_GET['name']) : '';

if ($folder_id && !empty($new_name)) {
    $stmt = $conn->prepare("UPDATE folders SET folder_name = ? WHERE id = ?");
    $stmt->bind_param("si", $new_name, $folder_id);
    $stmt->execute();
}

header("Location: files.php?success=Folder renamed successfully");
exit();
?>
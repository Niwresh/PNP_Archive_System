<?php
session_start();
require_once "PNP_Archive.php";

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($type) || $id <= 0) {
    $_SESSION['error'] = "Invalid request parameters.";
    header("Location: Homepage.php?view=trash");
    exit();
}

if ($type == 'folder') {
    restoreFromTrash('folder', $id, $conn);
    $_SESSION['success'] = "Folder restored successfully!";
} elseif ($type == 'file') {
    restoreFromTrash('file', $id, $conn);
    $_SESSION['success'] = "File restored successfully!";
}

header("Location: Homepage.php?view=trash");
exit();
?>
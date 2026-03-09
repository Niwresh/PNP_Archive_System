<?php
session_start();
require_once "PNP_Archive.php";

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$type = $_GET['type'];
$id = intval($_GET['id']);

if ($type == 'folder') {
    // Restore folder from trash
    restoreFromTrash('folder', $id, $conn);
    $_SESSION['success'] = "Folder restored successfully!";
} elseif ($type == 'file') {
    // Restore file from trash
    restoreFromTrash('file', $id, $conn);
    $_SESSION['success'] = "File restored successfully!";
}

header("Location: homepage.php?view=trash");
exit();
?>
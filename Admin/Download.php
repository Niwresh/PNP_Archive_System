<?php
session_start();
require_once 'PNP_Archive.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$file_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM files WHERE id = ?");
$stmt->bind_param("i", $file_id);
$stmt->execute();
$result = $stmt->get_result();
$file = $result->fetch_assoc();

if ($file && file_exists($file['file_path'])) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . filesize($file['file_path']));
    readfile($file['file_path']);
    exit;
}

header("Location: files.php?error=File not found");
exit();
?>
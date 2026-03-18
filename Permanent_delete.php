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
    header("Location: homepage.php?view=trash");
    exit();
}

if (function_exists('permanentlyDelete')) {
    permanentlyDelete($type, $id, $conn);
    $_SESSION['success'] = ucfirst($type) . " permanently deleted!";
} else {
    $_SESSION['error'] = "Function permanentlyDelete not found.";
}

header("Location: homepage.php?view=trash");
exit();
?>
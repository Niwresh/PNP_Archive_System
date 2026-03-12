<?php
require_once "PNP_Archive.php";

// Get current date minus 30 days
$cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));

// Get folders that have been in trash for more than 30 days
$folders = $conn->query("SELECT id FROM folders WHERE deleted_at IS NOT NULL AND deleted_at < '$cutoff_date'");
while ($folder = $folders->fetch_assoc()) {
    permanentlyDelete('folder', $folder['id'], $conn);
}

// Get files that have been in trash for more than 30 days
$files = $conn->query("SELECT id FROM files WHERE deleted_at IS NOT NULL AND deleted_at < '$cutoff_date'");
while ($file = $files->fetch_assoc()) {
    permanentlyDelete('file', $file['id'], $conn);
}

// Log the cleanup
error_log("Trash cleanup completed at " . date('Y-m-d H:i:s'));
?>
<?php
include 'db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

if ($id <= 0 || !in_array($type, ['audio', 'doc'])) {
    http_response_code(400);
    echo "Invalid file request.";
    exit;
}

$sql = "SELECT audio_file, docStu FROM submission WHERE submission_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    http_response_code(404);
    echo "Submission not found.";
    exit;
}

$dbPath = ($type === 'audio') ? $row['audio_file'] : $row['docStu'];

// ============================================================
// FUNCTION: Get file URL for server
// ============================================================
function getFileUrl($dbPath) {
    // Base URL for your server
    $base_url = "https://bitp3353.utem.edu.my/2026/all/";
    
    // Clean the path
    $path = trim((string)$dbPath);
    $path = str_replace('\\', '/', $path);
    
    // Remove any duplicate folder names
    $path = preg_replace('#GW06/GW06/#', '', $path);
    
    // Get just the filename
    $filename = basename($path);
    
    // Return full URL
    return $base_url . 'uploads/' . $filename;
}

// ============================================================
// REDIRECT TO FILE
// ============================================================
$fileUrl = getFileUrl($dbPath);

// Redirect to the actual file
header('Location: ' . $fileUrl);
exit;
?>
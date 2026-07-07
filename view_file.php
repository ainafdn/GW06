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

function cleanPath($path) {
    $path = trim((string)$path);
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^https?://[^/]+/#i', '', $path);
    $path = preg_replace('#^/+?#', '', $path);
    return $path;
}

function findLocalFile($dbPath) {
    $path = cleanPath($dbPath);
    if ($path === '') return ['', []];

    $base = basename($path);
    $candidates = [];

    // Exact path saved in database
    $candidates[] = __DIR__ . '/' . $path;

    // If database accidentally saves full project path with GW06/GW06, cut before uploads/
    $pos = stripos($path, 'uploads/');
    if ($pos !== false) {
        $afterUploads = substr($path, $pos);
        $candidates[] = __DIR__ . '/' . $afterUploads;
    }

    // Common upload locations
    $candidates[] = __DIR__ . '/uploads/' . $base;
    $candidates[] = dirname(__DIR__) . '/uploads/' . $base;
    $candidates[] = __DIR__ . '/../uploads/' . $base;

    // If database contains uploads/student/file, try the same under current folder
    if (stripos($path, 'uploads/') !== 0 && strpos($path, '/') !== false) {
        $candidates[] = __DIR__ . '/uploads/' . $path;
    }

    foreach ($candidates as $candidate) {
        $real = realpath($candidate);
        if ($real && is_file($real) && filesize($real) > 0) {
            return [$real, $candidates];
        }
    }

    return ['', $candidates];
}

list($file, $tried) = findLocalFile($dbPath);

if ($file === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "File not found on server.\n\n";
    echo "Database path: " . $dbPath . "\n\n";
    echo "Fix needed: make sure the uploaded file exists in the uploads folder OR update the database path to the real filename.\n\n";
    echo "Tried paths:\n";
    foreach ($tried as $t) {
        echo "- " . $t . "\n";
    }
    exit;
}

$filename = basename($file);
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeTypes = [
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'm4a' => 'audio/mp4',
    'ogg' => 'audio/ogg',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'txt' => 'text/plain',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm'
];

$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
header('Accept-Ranges: bytes');
readfile($file);
exit;
?>

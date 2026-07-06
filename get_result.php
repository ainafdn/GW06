<?php
// NO session_start() needed - public access
include 'db.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if submission_id is provided
if (!isset($_GET['submission_id'])) {
    echo json_encode(['error' => 'Missing submission ID']);
    exit();
}

$submission_id = intval($_GET['submission_id']);

$sql = "SELECT 
            s.submission_id,
            s.file_format,
            s.file_size_kb,
            s.upload_date,
            s.status,
            u.user_name,
            u.matric_no,
            m.ocr_text,
            m.word_count,
            m.audio_duration_sec,
            e.evaluation_score,
            e.grade,
            e.remarks,
            e.evaluated_at,
            lecturer.user_name AS evaluator_name
        FROM submission s
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN metadata m ON s.submission_id = m.submission_id
        LEFT JOIN evaluation e ON s.submission_id = e.submission_id
        LEFT JOIN users lecturer ON e.evaluator_id = lecturer.user_id
        WHERE s.submission_id = '$submission_id'
        LIMIT 1";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['error' => 'Query failed: ' . mysqli_error($conn)]);
    exit();
}

$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo json_encode(['error' => 'Submission not found']);
    exit();
}

// Format data for response
function formatDuration($seconds) {
    if (!$seconds || $seconds == 0) return "—";
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    return $minutes . ":" . str_pad($secs, 2, '0', STR_PAD_LEFT);
}

function formatFileSize($kb) {
    if (!$kb || $kb == 0) return "—";
    if ($kb < 1024) {
        return round($kb, 2) . " KB";
    } else {
        return round($kb / 1024, 2) . " MB";
    }
}

$response = [
    'submission_id' => $data['submission_id'],
    'user_name' => $data['user_name'],
    'matric_no' => $data['matric_no'],
    'file_format' => $data['file_format'],
    'file_size' => formatFileSize($data['file_size_kb']),
    'duration' => formatDuration($data['audio_duration_sec']),
    'word_count' => $data['word_count'] ?: '—',
    'ocr_text' => $data['ocr_text'],
    'evaluation_score' => $data['evaluation_score'],
    'grade' => $data['grade'],
    'remarks' => $data['remarks'],
    'evaluated_at' => $data['evaluated_at'] ? date("F d, Y h:i A", strtotime($data['evaluated_at'])) : '—',
    'evaluator_name' => $data['evaluator_name'] ?: '—'
];

// Return JSON response
echo json_encode($response);
exit();
?>
<?php
session_start();
include 'db.php';

// Check if user is logged in and is a lecturer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'lecturer') {
    header("Location: login.php");
    exit();
}

// Get filter values
$name = isset($_GET['student_name']) ? mysqli_real_escape_string($conn, trim($_GET['student_name'])) : '';
$matric = isset($_GET['matric_no']) ? mysqli_real_escape_string($conn, trim($_GET['matric_no'])) : '';
$format = isset($_GET['file_format']) ? mysqli_real_escape_string($conn, trim($_GET['file_format'])) : '';
$upload_date = isset($_GET['upload_date']) ? mysqli_real_escape_string($conn, trim($_GET['upload_date'])) : '';
$status = isset($_GET['status']) ? mysqli_real_escape_string($conn, trim($_GET['status'])) : '';
$min_score = isset($_GET['min_score']) && $_GET['min_score'] !== '' ? floatval($_GET['min_score']) : null;
$max_score = isset($_GET['max_score']) && $_GET['max_score'] !== '' ? floatval($_GET['max_score']) : null;
$keyword = isset($_GET['keyword']) ? mysqli_real_escape_string($conn, trim($_GET['keyword'])) : '';
$min_duration = isset($_GET['min_duration']) && $_GET['min_duration'] !== '' ? intval($_GET['min_duration']) : null;
$max_duration = isset($_GET['max_duration']) && $_GET['max_duration'] !== '' ? intval($_GET['max_duration']) : null;

// ============================================================
// BUILD THE SQL QUERY
// ============================================================
$sql = "SELECT 
            s.submission_id,
            s.audio_file,
            s.file_format,
            s.file_size_kb,
            s.docStu,
            s.upload_date,
            s.status,
            u.user_name,
            u.matric_no,
            m.ocr_text,
            m.word_count,
            m.audio_duration_sec,
            e.evaluation_score,
            e.grade,
            e.remarks
        FROM submission s
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN metadata m ON s.submission_id = m.submission_id
        LEFT JOIN evaluation e ON s.submission_id = e.submission_id
        WHERE 1=1";

// === ABR: Attribute-Based Retrieval ===
if (!empty($name)) {
    $sql .= " AND u.user_name LIKE '%$name%'";
}
if (!empty($matric)) {
    $sql .= " AND u.matric_no LIKE '%$matric%'";
}
if (!empty($format)) {
    $sql .= " AND s.file_format = '$format'";
}
if (!empty($upload_date)) {
    $sql .= " AND DATE(s.upload_date) = '$upload_date'";
}
if (!empty($status)) {
    $sql .= " AND s.status = '$status'";
}
if ($min_score !== null) {
    $sql .= " AND e.evaluation_score >= $min_score";
}
if ($max_score !== null) {
    $sql .= " AND e.evaluation_score <= $max_score";
}

// === TBR: Text-Based Retrieval ===
if (!empty($keyword)) {
    $sql .= " AND m.ocr_text LIKE '%$keyword%'";
}

// === CBR: Content-Based Retrieval ===
if ($min_duration !== null) {
    $sql .= " AND m.audio_duration_sec >= $min_duration";
}
if ($max_duration !== null) {
    $sql .= " AND m.audio_duration_sec <= $max_duration";
}

$sql .= " ORDER BY s.upload_date DESC";

// ============================================================
// EXECUTE QUERY
// ============================================================
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

$total_rows = mysqli_num_rows($result);

// ============================================================
// HELPER FUNCTIONS
// ============================================================
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

function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => '📄',
        'doc' => '📝',
        'docx' => '📝',
        'txt' => '📃',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'gif' => '🖼️',
        'mp3' => '🎵',
        'wav' => '🎵',
        'm4a' => '🎵',
        'ogg' => '🎵',
    ];
    return $icons[$ext] ?? '📎';
}

function getFileType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $audio_exts = ['mp3', 'wav', 'm4a', 'ogg', 'flac', 'aac'];
    $doc_exts = ['pdf', 'doc', 'docx', 'txt', 'rtf'];
    $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'];
    
    if (in_array($ext, $audio_exts)) return 'audio';
    if (in_array($ext, $doc_exts)) return 'document';
    if (in_array($ext, $image_exts)) return 'image';
    return 'other';
}

function getFileStatus($filepath) {
    if (empty($filepath)) return 'none';
    if (file_exists($filepath)) {
        $size = filesize($filepath);
        if ($size > 0) {
            return 'exists';
        } else {
            return 'empty';
        }
    }
    return 'missing';
}

function getFileSizeHuman($filepath) {
    if (empty($filepath) || !file_exists($filepath)) return '—';
    $bytes = filesize($filepath);
    if ($bytes == 0) return '0 KB';
    $kb = $bytes / 1024;
    if ($kb < 1024) {
        return round($kb, 2) . ' KB';
    } else {
        return round($kb / 1024, 2) . ' MB';
    }
}

function getScoreClass($score) {
    if ($score === null) return "";
    if ($score >= 85) return "color: #16a34a; font-weight: 700;";
    if ($score >= 70) return "color: #2563eb; font-weight: 700;";
    if ($score >= 50) return "color: #d97706; font-weight: 700;";
    return "color: #dc2626; font-weight: 700;";
}

function highlightKeyword($text, $keyword) {
    if (empty($keyword) || empty($text)) return $text;
    return str_ireplace($keyword, "<mark>$keyword</mark>", htmlspecialchars($text));
}

// Check if any filter is active
$has_active_filters = !empty($name) || !empty($matric) || !empty($format) || !empty($upload_date) || 
                      !empty($status) || $min_score !== null || $max_score !== null || 
                      !empty($keyword) || $min_duration !== null || $max_duration !== null;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Search | Lecturer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #0f172a; }

        .header { background: linear-gradient(135deg, #0f172a, #1e293b); padding: 16px 40px; color: white; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .header-brand { font-size: 20px; font-weight: 800; letter-spacing: -0.5px; }
        .header-brand span { color: #60a5fa; }
        .header-user { display: flex; align-items: center; gap: 20px; font-size: 14px; }
        .header-user .name { color: #e2e8f0; }
        .header-user .logout { color: #fca5a5; text-decoration: none; font-weight: 600; transition: color 0.2s; }
        .header-user .logout:hover { color: #f87171; }

        .nav { background: white; padding: 0 40px; display: flex; gap: 0; border-bottom: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.04); overflow-x: auto; }
        .nav a { padding: 16px 24px; text-decoration: none; color: #475569; font-weight: 600; font-size: 14px; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; }
        .nav a:hover { color: #0f172a; background: #f8fafc; }
        .nav a.active { color: #0f172a; border-bottom-color: #3b82f6; background: #f8fafc; }

        .container { max-width: 1600px; margin: 32px auto; padding: 0 32px; }
        .page-title { font-size: 28px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; margin-bottom: 4px; }
        .page-subtitle { color: #64748b; font-size: 15px; margin-bottom: 28px; }

        .search-layout {
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 24px;
            align-items: start;
        }

        .search-box { 
            background: white; 
            padding: 24px 24px; 
            border-radius: 16px; 
            box-shadow: 0 2px 16px rgba(0,0,0,0.05); 
            border-left: 5px solid #3b82f6;
            position: sticky;
            top: 100px;
            max-height: calc(100vh - 140px);
            overflow-y: auto;
        }
        .search-box::-webkit-scrollbar { width: 4px; }
        .search-box::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .search-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .search-box::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .search-title { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 2px; }
        .search-subtitle { color: #64748b; font-size: 12px; margin-bottom: 16px; }

        .collapsible-section { border: 1.5px solid #e2e8f0; border-radius: 10px; margin-bottom: 10px; overflow: hidden; transition: border-color 0.2s; }
        .collapsible-section:hover { border-color: #cbd5e1; }
        .collapsible-section.active { border-color: #3b82f6; }

        .section-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 10px 14px; 
            background: #f8fafc; 
            cursor: pointer; 
            transition: background 0.2s;
            user-select: none;
        }
        .section-header:hover { background: #f1f5f9; }
        .section-header .left { display: flex; align-items: center; gap: 8px; }
        .section-header .icon { font-size: 15px; }
        .section-header .title { font-weight: 600; font-size: 12px; color: #0f172a; }
        .section-header .badge { 
            font-size: 10px; 
            font-weight: 600; 
            padding: 1px 10px; 
            border-radius: 20px; 
            background: #e2e8f0; 
            color: #475569; 
        }
        .section-header .badge.active { background: #3b82f6; color: white; }
        .section-header .toggle-icon { 
            font-size: 14px; 
            color: #94a3b8; 
            transition: transform 0.3s ease; 
            font-weight: 300;
        }
        .collapsible-section.active .toggle-icon { transform: rotate(180deg); }
        .section-header .count-badge { 
            font-size: 10px; 
            font-weight: 600; 
            padding: 1px 8px; 
            border-radius: 20px; 
            background: #dcfce7; 
            color: #16a34a; 
        }

        .section-content { 
            padding: 0 14px 14px 14px; 
            display: none; 
            animation: slideDown 0.25s ease;
        }
        .collapsible-section.active .section-content { display: block; }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .filter-grid { display: grid; grid-template-columns: 1fr; gap: 10px; padding-top: 10px; }
        .filter-group label { display: block; font-weight: 600; font-size: 10px; color: #64748b; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.3px; }
        .filter-group input, .filter-group select { 
            width: 100%; 
            padding: 8px 12px; 
            border: 1.5px solid #e2e8f0; 
            border-radius: 8px; 
            font-size: 13px; 
            font-family: 'Inter', sans-serif; 
            transition: border-color 0.2s, box-shadow 0.2s; 
            background: #fafbfc; 
        }
        .filter-group input:focus, .filter-group select:focus { 
            border-color: #3b82f6; 
            outline: none; 
            background: white; 
            box-shadow: 0 0 0 3px rgba(59,130,246,0.08);
        }
        .filter-row-inline { display: flex; gap: 8px; align-items: center; }
        .filter-row-inline input { width: 42%; }
        .filter-row-inline span { color: #94a3b8; font-weight: 600; font-size: 12px; }

        .search-actions { 
            display: flex; 
            gap: 8px; 
            margin-top: 14px; 
            flex-wrap: wrap; 
            padding-top: 12px; 
            border-top: 1.5px solid #f1f5f9; 
        }
        .btn-search { 
            background: #0f172a; 
            color: white; 
            padding: 10px 28px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 13px; 
            font-weight: 700; 
            font-family: 'Inter', sans-serif; 
            transition: background 0.2s, transform 0.1s; 
            flex: 1;
        }
        .btn-search:hover { background: #1e293b; }
        .btn-search:active { transform: scale(0.97); }
        .btn-reset { 
            background: #e2e8f0; 
            color: #475569; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block; 
            font-size: 13px; 
            font-weight: 600; 
            font-family: 'Inter', sans-serif; 
            transition: background 0.2s; 
        }
        .btn-reset:hover { background: #cbd5e1; }

        .results { 
            background: white; 
            padding: 20px 24px; 
            border-radius: 16px; 
            box-shadow: 0 2px 16px rgba(0,0,0,0.05); 
            overflow-x: auto; 
            min-height: 400px;
        }
        .results-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 14px; 
            flex-wrap: wrap; 
            gap: 8px; 
        }
        .results-count { font-weight: 700; font-size: 14px; color: #0f172a; }
        .results-count span { background: #e2e8f0; padding: 2px 12px; border-radius: 20px; font-size: 12px; color: #475569; margin-left: 6px; }
        .results-hint { color: #94a3b8; font-size: 12px; }

        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { background: #f8fafc; padding: 10px 12px; text-align: left; border-bottom: 2px solid #e2e8f0; font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 600; letter-spacing: 0.5px; }
        td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #1e293b; font-size: 12px; }
        tr:hover td { background: #f8fafc; }
        tr:last-child td { border-bottom: none; }

        .student-name { font-weight: 600; color: #0f172a; }
        .student-matric { color: #64748b; font-size: 11px; display: block; }
        .status-badge { padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .status-evaluated { background: #dcfce7; color: #16a34a; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .no-data { color: #94a3b8; font-style: italic; }

        .btn-evaluate { 
            background: #0f172a; 
            color: white; 
            padding: 4px 14px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block; 
            font-size: 11px; 
            font-weight: 600; 
            transition: background 0.2s; 
            white-space: nowrap;
        }
        .btn-evaluate:hover { background: #1e293b; }
        .btn-evaluate.btn-done { background: #22c55e; cursor: default; }
        .btn-evaluate.btn-done:hover { background: #22c55e; }

        .file-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 2px 8px 2px 4px;
            border-radius: 4px;
            font-size: 11px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .file-item .icon { font-size: 14px; }
        .file-item .name { 
            max-width: 120px; 
            overflow: hidden; 
            text-overflow: ellipsis; 
            white-space: nowrap;
        }
        .file-item .size { 
            font-size: 9px; 
            color: #94a3b8; 
        }
        .file-item .status-badge-file {
            font-size: 8px;
            padding: 1px 6px;
            border-radius: 10px;
            font-weight: 600;
        }
        .file-item .status-badge-file.exists {
            background: #dcfce7;
            color: #16a34a;
        }
        .file-item .status-badge-file.missing {
            background: #fee2e2;
            color: #dc2626;
        }
        .file-item .status-badge-file.empty {
            background: #fef3c7;
            color: #d97706;
        }
        
        .file-item.audio-item {
            background: #f0fdf4;
            color: #16a34a;
        }
        .file-item.audio-item:hover {
            background: #dcfce7;
        }
        .file-item.doc-item {
            background: #eff6ff;
            color: #3b82f6;
        }
        .file-item.doc-item:hover {
            background: #dbeafe;
        }
        .file-item.missing-item {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .files-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .audio-player-mini {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }
        .audio-player-mini audio {
            height: 28px;
            max-width: 130px;
        }

        .meta-tag {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 10px;
            padding: 1px 8px;
            border-radius: 12px;
            background: #f1f5f9;
            color: #475569;
            font-weight: 500;
        }
        .meta-tag .icon { font-size: 11px; }
        .meta-tag.has-data { background: #dcfce7; color: #16a34a; }
        .meta-tag.no-data-tag { background: #f1f5f9; color: #94a3b8; }

        .no-results { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .no-results h3 { font-size: 18px; color: #64748b; margin-bottom: 6px; }
        .no-results p { font-size: 13px; max-width: 400px; margin: 0 auto; }
        .no-results .tip { font-size: 12px; color: #94a3b8; margin-top: 10px; }

        mark { background: #fef3c7; padding: 0 3px; border-radius: 3px; }

        .score-high { color: #16a34a; font-weight: 700; }
        .score-mid-high { color: #2563eb; font-weight: 700; }
        .score-mid { color: #d97706; font-weight: 700; }
        .score-low { color: #dc2626; font-weight: 700; }

        @media (max-width: 1200px) {
            .search-layout { grid-template-columns: 1fr; }
            .search-box { position: static; max-height: none; overflow-y: visible; }
            .filter-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 768px) {
            .header { padding: 12px 20px; flex-direction: column; gap: 8px; text-align: center; }
            .nav { padding: 0 16px; }
            .nav a { padding: 12px 16px; font-size: 13px; }
            .container { padding: 0 16px; margin: 16px auto; }
            .search-box { padding: 16px; }
            .results { padding: 14px; }
            .filter-grid { grid-template-columns: 1fr; }
            .search-actions { flex-direction: column; }
            .btn-search, .btn-reset { width: 100%; text-align: center; }
            .page-title { font-size: 20px; }
            .page-subtitle { font-size: 13px; margin-bottom: 16px; }
            th, td { padding: 8px 10px; font-size: 11px; }
            .results-header { flex-direction: column; align-items: flex-start; }
            .audio-player-mini audio { max-width: 80px; }
            .file-item .name { max-width: 80px; }
        }
        @media (max-width: 480px) {
            table { font-size: 10px; }
            th, td { padding: 6px 8px; }
            .btn-evaluate { font-size: 10px; padding: 3px 10px; }
            .section-header { padding: 8px 12px; }
            .section-content { padding: 0 12px 12px 12px; }
            .audio-player-mini audio { max-width: 60px; height: 24px; }
            .file-item .name { max-width: 50px; }
            .file-item { font-size: 9px; padding: 1px 4px; }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-brand">🎵 Audio<span>Poetry</span></div>
    <div class="header-user">
        <span class="name">👋 <?php echo $_SESSION['user_name']; ?></span>
        <a href="logout.php" class="logout">🚪 Logout</a>
    </div>
</div>

<div class="nav">
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="search.php" class="active">🔍 Search</a>
</div>

<div class="container">

    <h1 class="page-title">🔍 Search Submissions</h1>
    <p class="page-subtitle">Filter and search audio poetry submissions</p>

    <div class="search-layout">

        <!-- LEFT COLUMN: Search / Filter -->
        <div class="search-box">
            <div class="search-title">🔎 Filters</div>
            <div class="search-subtitle">Expand sections to apply filters</div>

            <form method="GET" action="search.php" id="searchForm">

                <!-- ABR: Attribute-Based Retrieval -->
                <div class="collapsible-section <?php 
                    $abr_active = !empty($name) || !empty($matric) || !empty($format) || !empty($upload_date) || 
                                  !empty($status) || $min_score !== null || $max_score !== null;
                    echo $abr_active ? 'active' : ''; 
                ?>">
                    <div class="section-header" onclick="toggleSection(this)">
                        <div class="left">
                            <span class="icon">📁</span>
                            <span class="title">ABR</span>
                            <span class="badge <?php echo $abr_active ? 'active' : ''; ?>">
                                <?php echo $abr_active ? 'Active' : 'Closed'; ?>
                            </span>
                            <?php 
                            $abr_count = 0;
                            if (!empty($name)) $abr_count++;
                            if (!empty($matric)) $abr_count++;
                            if (!empty($format)) $abr_count++;
                            if (!empty($upload_date)) $abr_count++;
                            if (!empty($status)) $abr_count++;
                            if ($min_score !== null) $abr_count++;
                            if ($max_score !== null) $abr_count++;
                            if ($abr_count > 0) {
                                echo "<span class='count-badge'>$abr_count</span>";
                            }
                            ?>
                        </div>
                        <span class="toggle-icon">▼</span>
                    </div>
                    <div class="section-content">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label>Student Name</label>
                                <input type="text" name="student_name" placeholder="e.g., Emily" value="<?php echo htmlspecialchars($name); ?>">
                            </div>
                            <div class="filter-group">
                                <label>Matric Number</label>
                                <input type="text" name="matric_no" placeholder="e.g., B0324" value="<?php echo htmlspecialchars($matric); ?>">
                            </div>
                            <div class="filter-group">
                                <label>File Format</label>
                                <select name="file_format">
                                    <option value="">All Formats</option>
                                    <option value="MP3" <?php if ($format == 'MP3') echo 'selected'; ?>>MP3</option>
                                    <option value="WAV" <?php if ($format == 'WAV') echo 'selected'; ?>>WAV</option>
                                    <option value="M4A" <?php if ($format == 'M4A') echo 'selected'; ?>>M4A</option>
                                    <option value="OGG" <?php if ($format == 'OGG') echo 'selected'; ?>>OGG</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Upload Date</label>
                                <input type="date" name="upload_date" value="<?php echo htmlspecialchars($upload_date); ?>">
                            </div>
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php if ($status == 'pending') echo 'selected'; ?>>Pending</option>
                                    <option value="evaluated" <?php if ($status == 'evaluated') echo 'selected'; ?>>Evaluated</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Score Range</label>
                                <div class="filter-row-inline">
                                    <input type="number" name="min_score" placeholder="Min" value="<?php echo $min_score !== null ? $min_score : ''; ?>">
                                    <span>to</span>
                                    <input type="number" name="max_score" placeholder="Max" value="<?php echo $max_score !== null ? $max_score : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TBR: Text-Based Retrieval -->
                <div class="collapsible-section <?php echo !empty($keyword) ? 'active' : ''; ?>">
                    <div class="section-header" onclick="toggleSection(this)">
                        <div class="left">
                            <span class="icon">📝</span>
                            <span class="title">TBR</span>
                            <span class="badge <?php echo !empty($keyword) ? 'active' : ''; ?>">
                                <?php echo !empty($keyword) ? 'Active' : 'Closed'; ?>
                            </span>
                            <?php if (!empty($keyword)) { ?>
                                <span class="count-badge">1</span>
                            <?php } ?>
                        </div>
                        <span class="toggle-icon">▼</span>
                    </div>
                    <div class="section-content">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label>Keyword</label>
                                <input type="text" name="keyword" placeholder="e.g., nature, love" value="<?php echo htmlspecialchars($keyword); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CBR: Content-Based Retrieval -->
                <div class="collapsible-section <?php echo ($min_duration !== null || $max_duration !== null) ? 'active' : ''; ?>">
                    <div class="section-header" onclick="toggleSection(this)">
                        <div class="left">
                            <span class="icon">🎵</span>
                            <span class="title">CBR</span>
                            <span class="badge <?php echo ($min_duration !== null || $max_duration !== null) ? 'active' : ''; ?>">
                                <?php echo ($min_duration !== null || $max_duration !== null) ? 'Active' : 'Closed'; ?>
                            </span>
                            <?php 
                            $cbr_count = 0;
                            if ($min_duration !== null) $cbr_count++;
                            if ($max_duration !== null) $cbr_count++;
                            if ($cbr_count > 0) {
                                echo "<span class='count-badge'>$cbr_count</span>";
                            }
                            ?>
                        </div>
                        <span class="toggle-icon">▼</span>
                    </div>
                    <div class="section-content">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label>Duration (seconds)</label>
                                <div class="filter-row-inline">
                                    <input type="number" name="min_duration" placeholder="Min" value="<?php echo $min_duration !== null ? $min_duration : ''; ?>">
                                    <span>to</span>
                                    <input type="number" name="max_duration" placeholder="Max" value="<?php echo $max_duration !== null ? $max_duration : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="search-actions">
                    <button type="submit" class="btn-search">🔍 Search</button>
                    <a href="search.php" class="btn-reset">↺ Reset</a>
                </div>

            </form>
        </div>

        <!-- RIGHT COLUMN: Results -->
        <div class="results">
            <div class="results-header">
                <div class="results-count">
                    📊 <?php echo $total_rows; ?> <span>submission(s)</span>
                </div>
                <div class="results-hint">
                    💡 <?php echo $has_active_filters ? 'Filters applied' : 'Showing all'; ?>
                </div>
            </div>

            <?php if ($total_rows == 0) { ?>
                <div class="no-results">
                    <h3>📭 No submissions found</h3>
                    <p>Try adjusting your filters or click <strong>Reset</strong> to see all submissions.</p>
                    <p class="tip">💡 Tip: Make sure there are submissions in the database.</p>
                </div>
            <?php } else { ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Files</th>
                            <th style="width:90px;">Duration</th>
                            <th style="width:70px;">Words</th>
                            <th>Text Preview</th>
                            <th style="width:60px;">Score</th>
                            <th style="width:80px;">Status</th>
                            <th style="text-align:center;width:80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)) { 
                            $score = $row['evaluation_score'];
                            $duration = $row['audio_duration_sec'];
                            $word_count = $row['word_count'];
                            $audio_file = $row['audio_file'];
                            $doc_file = $row['docStu'];
                            $file_size = $row['file_size_kb'];
                            
                            // Check file status
                            $audio_status = getFileStatus($audio_file);
                            $doc_status = getFileStatus($doc_file);
                            $audio_size = ($audio_status != 'none') ? getFileSizeHuman($audio_file) : '—';
                            $doc_size = ($doc_status != 'none') ? getFileSizeHuman($doc_file) : '—';
                            
                            if ($score !== null) {
                                if ($score >= 85) $scoreClass = 'score-high';
                                elseif ($score >= 70) $scoreClass = 'score-mid-high';
                                elseif ($score >= 50) $scoreClass = 'score-mid';
                                else $scoreClass = 'score-low';
                            } else {
                                $scoreClass = '';
                            }
                            
                            $is_evaluated = ($row['status'] == 'evaluated');
                            $has_word_count = ($word_count !== null && $word_count > 0);
                        ?>
                            <tr>
                                <td>
                                    <span class="student-name"><?php echo htmlspecialchars($row['user_name']); ?></span>
                                    <span class="student-matric">🎓 <?php echo htmlspecialchars($row['matric_no']); ?></span>
                                </td>
                                <td>
                                    <div class="files-cell">
                                        <!-- AUDIO FILE -->
                                        <?php if ($audio_status == 'none') { ?>
                                            <span class="no-data">— No audio</span>
                                        <?php } else { 
                                            $audio_class = ($audio_status == 'exists') ? 'audio-item' : 'missing-item';
                                            $status_label = ($audio_status == 'exists') ? '✅' : ($audio_status == 'empty' ? '⚠️' : '❌');
                                            $status_text = ($audio_status == 'exists') ? 'exists' : ($audio_status == 'empty' ? 'empty' : 'missing');
                                        ?>
                                            <div class="audio-player-mini">
                                                <?php if ($audio_status == 'exists' && getFileType($audio_file) == 'audio') { ?>
                                                    <audio controls preload="none">
                                                        <source src="<?php echo $audio_file; ?>">
                                                        Your browser does not support the audio element.
                                                    </audio>
                                                <?php } ?>
                                                <a href="<?php echo $audio_file; ?>" target="_blank" class="file-item <?php echo $audio_class; ?>" <?php echo ($audio_status != 'exists') ? 'onclick="return false;"' : ''; ?>>
                                                    <span class="icon"><?php echo getFileIcon($audio_file); ?></span>
                                                    <span class="name"><?php echo basename($audio_file); ?></span>
                                                    <span class="size"><?php echo $audio_size; ?></span>
                                                    <span class="status-badge-file <?php echo $status_text; ?>"><?php echo $status_label; ?></span>
                                                </a>
                                            </div>
                                        <?php } ?>
                                        
                                        <!-- DOCUMENT FILE -->
                                        <?php if ($doc_status == 'none') { ?>
                                            <span class="no-data">— No document</span>
                                        <?php } else { 
                                            $doc_class = ($doc_status == 'exists') ? 'doc-item' : 'missing-item';
                                            $status_label = ($doc_status == 'exists') ? '✅' : ($doc_status == 'empty' ? '⚠️' : '❌');
                                            $status_text = ($doc_status == 'exists') ? 'exists' : ($doc_status == 'empty' ? 'empty' : 'missing');
                                        ?>
                                            <a href="<?php echo $doc_file; ?>" target="_blank" class="file-item <?php echo $doc_class; ?>" <?php echo ($doc_status != 'exists') ? 'onclick="return false;"' : ''; ?>>
                                                <span class="icon"><?php echo getFileIcon($doc_file); ?></span>
                                                <span class="name"><?php echo basename($doc_file); ?></span>
                                                <span class="size"><?php echo $doc_size; ?></span>
                                                <span class="status-badge-file <?php echo $status_text; ?>"><?php echo $status_label; ?></span>
                                            </a>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="meta-tag <?php echo $duration ? 'has-data' : 'no-data-tag'; ?>">
                                        <span class="icon">⏱️</span> <?php echo formatDuration($duration); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="meta-tag <?php echo $has_word_count ? 'has-data' : 'no-data-tag'; ?>">
                                        <span class="icon">📝</span> 
                                        <?php echo $has_word_count ? $word_count : '—'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $text = $row['ocr_text'] ?? '';
                                    if (!empty($text)) {
                                        if (!empty($keyword) && !empty($text)) {
                                            echo highlightKeyword(substr($text, 0, 80), $keyword) . (strlen($text) > 80 ? '...' : '');
                                        } else {
                                            echo htmlspecialchars(substr($text, 0, 60)) . (strlen($text) > 60 ? '...' : '');
                                        }
                                    } else {
                                        echo '<span class="no-data">— No text</span>';
                                    }
                                    ?>
                                </td>
                                <td class="<?php echo $scoreClass; ?>">
                                    <?php echo $score !== null ? $score : '<span class="no-data">—</span>'; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $is_evaluated ? 'status-evaluated' : 'status-pending'; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($is_evaluated) { ?>
                                        <span class="btn-evaluate btn-done">✅ Done</span>
                                    <?php } else { ?>
                                        <a href="evaluation.php?submission_id=<?php echo $row['submission_id']; ?>" class="btn-evaluate">
                                            ⭐ Evaluate
                                        </a>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>

    </div>

</div>

<script>
    function toggleSection(headerElement) {
        const section = headerElement.closest('.collapsible-section');
        section.classList.toggle('active');
    }

    document.addEventListener('DOMContentLoaded', function() {});
</script>

</body>
</html>
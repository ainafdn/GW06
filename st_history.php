<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT s.*, m.audio_duration_sec, m.ocr_text, m.word_count
        FROM submission s
        LEFT JOIN metadata m ON s.submission_id = m.submission_id
        WHERE s.user_id = '$user_id'
        ORDER BY s.upload_date DESC
        LIMIT 1";

$result = mysqli_query($conn, $sql);
$submission = mysqli_fetch_assoc($result);

function formatDuration($seconds) {
    if (!$seconds) return "—";
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    return $minutes . ":" . str_pad($secs, 2, '0', STR_PAD_LEFT);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Submission | Student</title>
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

        .container { max-width: 900px; margin: 32px auto; padding: 0 32px; }
        .page-title { font-size: 26px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; margin-bottom: 4px; }
        .page-subtitle { color: #64748b; font-size: 15px; margin-bottom: 24px; }

        .card { background: white; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #0f172a, #1e293b); padding: 24px 32px; color: white; }
        .card-header h2 { font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
        .card-header .subtitle { font-size: 14px; color: #94a3b8; font-weight: 400; margin-top: 4px; }
        .card-body { padding: 32px; }

        .empty { text-align: center; padding: 50px 20px; }
        .empty .icon { font-size: 64px; display: block; margin-bottom: 12px; }
        .empty h3 { font-size: 22px; color: #0f172a; margin-bottom: 6px; }
        .empty p { color: #64748b; font-size: 15px; }
        .empty .btn-submit { 
            display: inline-block; 
            margin-top: 16px; 
            padding: 12px 36px; 
            background: #0f172a; 
            color: white; 
            text-decoration: none; 
            border-radius: 10px; 
            font-weight: 600;
            transition: background 0.2s;
        }
        .empty .btn-submit:hover { background: #1e293b; }

        .status-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .status-badge { 
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 18px; 
            border-radius: 20px; 
            font-weight: 600; 
            font-size: 14px;
        }
        .status-badge .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.pending .dot { background: #f59e0b; }
        .status-badge.evaluated { background: #dcfce7; color: #166534; }
        .status-badge.evaluated .dot { background: #22c55e; }
        .status-badge .label { text-transform: capitalize; }
        
        .user-email { 
            color: #94a3b8; 
            font-size: 14px; 
            font-weight: 500;
            background: #f1f5f9;
            padding: 4px 14px;
            border-radius: 20px;
        }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 20px 0; }
        .info-item { 
            background: #f8fafc; 
            padding: 16px 20px; 
            border-radius: 12px; 
            border-left: 4px solid #3b82f6;
            transition: background 0.2s;
        }
        .info-item:hover { background: #f1f5f9; }
        .info-item .label { 
            font-size: 11px; 
            font-weight: 600; 
            color: #94a3b8; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }
        .info-item .value { 
            font-size: 16px; 
            font-weight: 600; 
            color: #0f172a; 
            margin-top: 4px; 
        }
        .info-item .value a { 
            color: #3b82f6; 
            text-decoration: none; 
            font-weight: 600;
            transition: color 0.2s;
        }
        .info-item .value a:hover { color: #2563eb; text-decoration: underline; }
        
        .info-item.full-width { grid-column: 1 / -1; }
        
        .poem-box {
            background: #f8fafc;
            padding: 16px 20px;
            border-radius: 12px;
            border-left: 4px solid #8b5cf6;
            margin-top: 4px;
            max-height: 200px;
            overflow-y: auto;
            line-height: 1.8;
            font-size: 14px;
            color: #1e293b;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .poem-box::-webkit-scrollbar {
            width: 6px;
        }
        .poem-box::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .poem-box::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        .poem-box::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        .note-box { 
            background: #eff6ff; 
            padding: 16px 20px; 
            border-radius: 12px; 
            margin-top: 20px; 
            border-left: 4px solid #3b82f6;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .note-box .icon { font-size: 20px; }
        .note-box .content { font-size: 14px; color: #1e293b; line-height: 1.6; }
        .note-box .content strong { color: #0f172a; }

        @media (max-width: 768px) {
            .header { padding: 12px 20px; flex-direction: column; gap: 8px; text-align: center; }
            .nav { padding: 0 16px; }
            .nav a { padding: 12px 16px; font-size: 13px; }
            .container { padding: 0 16px; margin: 20px auto; }
            .card-body { padding: 20px; }
            .card-header { padding: 20px; }
            .card-header h2 { font-size: 19px; }
            .info-grid { grid-template-columns: 1fr; }
            .status-header { flex-direction: column; align-items: flex-start; }
            .page-title { font-size: 22px; }
            .info-item.full-width { grid-column: 1; }
            .poem-box { max-height: 150px; font-size: 13px; }
        }
        @media (max-width: 480px) {
            .empty .icon { font-size: 48px; }
            .empty h3 { font-size: 18px; }
            .info-item { padding: 14px 16px; }
            .info-item .value { font-size: 14px; }
            .poem-box { max-height: 120px; font-size: 12px; padding: 12px 14px; }
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
    <a href="st_submission.php">📤 Submit</a>
    <a href="st_history.php" class="active">📂 My Submission</a>
    <a href="st_results.php">📊 My Result</a>
</div>

<div class="container">
    <h1 class="page-title">📂 My Audio Submission</h1>
    <p class="page-subtitle">View your submitted audio poetry details</p>

    <div class="card">
        <div class="card-header">
            <h2>📂 Submission Details</h2>
            <div class="subtitle">Your audio poetry submission information</div>
        </div>
        <div class="card-body">

            <?php if (!$submission) { ?>
                <div class="empty">
                    <span class="icon">📭</span>
                    <h3>No submission found</h3>
                    <p>You haven't submitted your audio poetry yet.</p>
                    <a href="st_submission.php" class="btn-submit">📤 Submit Audio Now</a>
                </div>

            <?php } else { ?>

                <div class="status-header">
                    <span class="status-badge <?php echo $submission['status'] == 'evaluated' ? 'evaluated' : 'pending'; ?>">
                        <span class="dot"></span>
                        <span class="label"><?php echo ucfirst($submission['status']); ?></span>
                    </span>
                    <span class="user-email">📧 <?php echo $_SESSION['email'] ?? 'N/A'; ?></span>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Submission ID</div>
                        <div class="value">#<?php echo $submission['submission_id']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Upload Date</div>
                        <div class="value"><?php echo date("F d, Y h:i A", strtotime($submission['upload_date'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">File Format</div>
                        <div class="value"><?php echo $submission['file_format']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">File Size</div>
                        <div class="value"><?php echo number_format($submission['file_size_kb'], 2); ?> KB</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Audio Duration</div>
                        <div class="value">⏱️ <?php echo formatDuration($submission['audio_duration_sec']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Word Count</div>
                        <div class="value">📝 <?php echo $submission['word_count'] ?: '—'; ?> words</div>
                    </div>
                    
                    <div class="info-item full-width">
                        <div class="label">📝 Poem Text</div>
                        <div class="poem-box">
                            <?php 
                            if (!empty($submission['ocr_text'])) {
                                echo nl2br(htmlspecialchars($submission['ocr_text']));
                            } else {
                                echo '<span style="color: #94a3b8;">No poem text found.</span>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="label">Audio File</div>
                        <div class="value">
                            <a href="<?php echo $submission['audio_file']; ?>" target="_blank">🎵 Listen to Audio</a>
                        </div>
                    </div>
                </div>

                <div class="note-box">
                    <span class="icon">📌</span>
                    <div class="content">
                        <strong>Note:</strong> Your submission is currently <strong><?php echo ucfirst($submission['status']); ?></strong>.
                        <?php if ($submission['status'] == 'pending') { ?>
                            It is awaiting review by the lecturer. You will be notified once evaluated.
                        <?php } else { ?>
                            It has been evaluated. <a href="st_results.php" style="color: #3b82f6; text-decoration: none; font-weight: 600;">View your results →</a>
                        <?php } ?>
                    </div>
                </div>

            <?php } ?>

        </div>
    </div>
</div>

</body>
</html>
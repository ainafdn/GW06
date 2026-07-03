<?php
session_start();
include 'db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get student's submission with evaluation results
$sql = "SELECT 
            s.submission_id,
            s.audio_file,
            s.file_format,
            s.file_size_kb,
            s.upload_date,
            s.status,
            u.user_name,
            u.email,
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
        WHERE s.user_id = '$user_id'
        ORDER BY s.upload_date DESC
        LIMIT 1";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

$data = mysqli_fetch_assoc($result);

function getRatingText($score) {
    if ($score === null) return "Not Evaluated";
    if ($score >= 85) return "🌟 Excellent";
    if ($score >= 70) return "👍 Good";
    if ($score >= 50) return "📊 Average";
    return "📈 Needs Improvement";
}

function getScoreColor($score) {
    if ($score === null) return "#94a3b8";
    if ($score >= 85) return "#16a34a";
    if ($score >= 70) return "#2563eb";
    if ($score >= 50) return "#d97706";
    return "#dc2626";
}

function getStatusBadge($score) {
    if ($score === null) return "status-pending";
    if ($score >= 50) return "status-passed";
    return "status-failed";
}

function getStatusText($score) {
    if ($score === null) return "⏳ Pending";
    if ($score >= 50) return "✅ PASSED";
    return "❌ FAILED";
}

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
    <title>My Result | Student</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #0f172a; }

        /* Header */
        .header { background: linear-gradient(135deg, #0f172a, #1e293b); padding: 16px 40px; color: white; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .header-brand { font-size: 20px; font-weight: 800; letter-spacing: -0.5px; }
        .header-brand span { color: #60a5fa; }
        .header-user { display: flex; align-items: center; gap: 20px; font-size: 14px; }
        .header-user .name { color: #e2e8f0; }
        .header-user .logout { color: #fca5a5; text-decoration: none; font-weight: 600; transition: color 0.2s; }
        .header-user .logout:hover { color: #f87171; }

        /* Navigation */
        .nav { background: white; padding: 0 40px; display: flex; gap: 0; border-bottom: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.04); overflow-x: auto; }
        .nav a { padding: 16px 24px; text-decoration: none; color: #475569; font-weight: 600; font-size: 14px; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; }
        .nav a:hover { color: #0f172a; background: #f8fafc; }
        .nav a.active { color: #0f172a; border-bottom-color: #3b82f6; background: #f8fafc; }

        /* Container */
        .container { max-width: 900px; margin: 32px auto; padding: 0 32px; }
        .page-title { font-size: 26px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; margin-bottom: 4px; }
        .page-subtitle { color: #64748b; font-size: 15px; margin-bottom: 24px; }

        /* Card */
        .card { background: white; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #0f172a, #1e293b); padding: 24px 32px; color: white; }
        .card-header h2 { font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
        .card-header .subtitle { font-size: 14px; color: #94a3b8; font-weight: 400; margin-top: 4px; }
        .card-body { padding: 32px; }

        /* Empty State */
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
        .empty .info-box {
            margin-top: 16px;
            background: #f8fafc;
            padding: 16px 20px;
            border-radius: 12px;
            text-align: left;
            border-left: 4px solid #f59e0b;
        }
        .empty .info-box p { margin: 4px 0; font-size: 14px; }
        .empty .info-box p strong { color: #0f172a; }
        .empty .info-box .status-pending-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            background: #fef3c7;
            color: #92400e;
            font-weight: 600;
            font-size: 13px;
        }

        /* Score Section */
        .score-section { 
            text-align: center; 
            padding: 32px; 
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px; 
            margin: 16px 0 20px 0;
            border: 2px solid #e2e8f0;
        }
        .score-section .label { 
            color: #64748b; 
            font-size: 14px; 
            font-weight: 600;
            margin-bottom: 4px; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .score-section .value { 
            font-size: 64px; 
            font-weight: 800; 
            letter-spacing: -2px;
            line-height: 1.1;
        }
        .score-section .rating { 
            font-size: 20px; 
            font-weight: 700; 
            margin-top: 4px; 
        }
        .score-section .sub-text {
            font-size: 14px;
            color: #94a3b8;
            margin-top: 8px;
        }

        /* Status Badge */
        .status-container { text-align: center; margin: 12px 0 20px 0; }
        .status-badge { 
            display: inline-block; 
            padding: 8px 28px; 
            border-radius: 20px; 
            font-weight: 700; 
            font-size: 16px;
            letter-spacing: 0.3px;
        }
        .status-passed { background: #dcfce7; color: #166534; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .status-pending { background: #fef3c7; color: #92400e; }

        /* Info Grid */
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
        .info-item .value .grade-badge {
            display: inline-block;
            padding: 2px 14px;
            border-radius: 20px;
            background: #0f172a;
            color: white;
            font-weight: 700;
            font-size: 15px;
        }

        /* Full width item for poem text */
        .info-item.full-width { grid-column: 1 / -1; }

        /* Poem Text Box */
        .poem-box {
            background: #f8fafc;
            padding: 14px 18px;
            border-radius: 10px;
            border-left: 4px solid #8b5cf6;
            margin-top: 4px;
            max-height: 180px;
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

        /* Remarks Box */
        .remarks-box { 
            background: #eff6ff; 
            padding: 20px 24px; 
            border-radius: 12px; 
            margin-top: 20px; 
            border-left: 4px solid #3b82f6;
        }
        .remarks-box h4 { 
            font-size: 15px; 
            font-weight: 700; 
            color: #0f172a; 
            margin-bottom: 8px; 
        }
        .remarks-box p { 
            line-height: 1.7; 
            margin: 0; 
            color: #1e293b; 
            font-size: 14px;
        }

        /* Divider */
        .divider { border: none; border-top: 2px solid #e2e8f0; margin: 24px 0; }

        /* Footer Note */
        .footer-note { 
            font-size: 13px; 
            color: #94a3b8; 
            text-align: center; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .footer-note .icon { font-size: 16px; }

        /* Responsive */
        @media (max-width: 768px) {
            .header { padding: 12px 20px; flex-direction: column; gap: 8px; text-align: center; }
            .nav { padding: 0 16px; }
            .nav a { padding: 12px 16px; font-size: 13px; }
            .container { padding: 0 16px; margin: 20px auto; }
            .card-body { padding: 20px; }
            .card-header { padding: 20px; }
            .card-header h2 { font-size: 19px; }
            .info-grid { grid-template-columns: 1fr; }
            .info-item.full-width { grid-column: 1; }
            .page-title { font-size: 22px; }
            .score-section .value { font-size: 48px; }
            .poem-box { max-height: 140px; font-size: 13px; }
        }
        @media (max-width: 480px) {
            .empty .icon { font-size: 48px; }
            .empty h3 { font-size: 18px; }
            .score-section { padding: 24px; }
            .score-section .value { font-size: 40px; }
            .info-item { padding: 14px 16px; }
            .info-item .value { font-size: 14px; }
            .remarks-box { padding: 16px 18px; }
            .poem-box { max-height: 120px; font-size: 12px; padding: 12px 14px; }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-brand">🎵 Audio<span>Poetry</span></div>
    <div class="header-user">
        <span class="name">👋 <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Student'; ?></span>
        <a href="logout.php" class="logout">🚪 Logout</a>
    </div>
</div>

<div class="nav">
    <a href="st_submission.php">📤 Submit</a>
    <a href="st_history.php">📂 My Submission</a>
    <a href="st_results.php" class="active">📊 My Result</a>
</div>

<div class="container">
    <h1 class="page-title">📊 My Evaluation Result</h1>
    <p class="page-subtitle">View your evaluation score and feedback</p>

    <div class="card">
        <div class="card-header">
            <h2>📊 Result Details</h2>
            <div class="subtitle">Your audio poetry evaluation summary</div>
        </div>
        <div class="card-body">

            <?php if (!$data) { ?>
                <!-- Case 1: No submission found -->
                <div class="empty">
                    <span class="icon">📭</span>
                    <h3>No submission found</h3>
                    <p>You haven't submitted your audio poetry yet.</p>
                    <a href="st_submission.php" class="btn-submit">📤 Submit Audio Now</a>
                </div>

            <?php } elseif ($data['evaluation_score'] === null) { ?>
                <!-- Case 2: Submission exists but not evaluated yet -->
                <div class="empty">
                    <span class="icon">⏳</span>
                    <h3>Result Pending</h3>
                    <p>Your audio has been submitted but not yet evaluated.</p>
                    <div class="info-box">
                        <p><strong>Submission ID:</strong> #<?php echo $data['submission_id']; ?></p>
                        <p><strong>Upload Date:</strong> <?php echo date("F d, Y", strtotime($data['upload_date'])); ?></p>
                        <p><strong>Status:</strong> <span class="status-pending-badge">⏳ Pending</span></p>
                    </div>
                </div>

            <?php } else { 
                // Case 3: Evaluated! Show results
                $score = $data['evaluation_score'];
                $rating = getRatingText($score);
                $color = getScoreColor($score);
                $statusClass = getStatusBadge($score);
                $statusText = getStatusText($score);
            ?>

                <!-- Score Display -->
                <div class="score-section">
                    <div class="label">🎯 Evaluation Score</div>
                    <div class="value" style="color: <?php echo $color; ?>">
                        <?php echo $score; ?><span style="font-size: 28px; font-weight: 600; color: #94a3b8;"> / 100</span>
                    </div>
                    <div class="rating" style="color: <?php echo $color; ?>">
                        <?php echo $rating; ?>
                    </div>
                    <div class="sub-text">Based on quality, content, and delivery</div>
                </div>

                <!-- Pass/Fail Status -->
                <div class="status-container">
                    <span class="status-badge <?php echo $statusClass; ?>">
                        <?php echo $statusText; ?>
                    </span>
                </div>

                <!-- Submission Details -->
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Submission ID</div>
                        <div class="value">#<?php echo $data['submission_id']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Grade</div>
                        <div class="value">
                            <span class="grade-badge"><?php echo $data['grade'] ?: '—'; ?></span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="label">File Format</div>
                        <div class="value">🎵 <?php echo $data['file_format']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">File Size</div>
                        <div class="value"><?php echo number_format($data['file_size_kb'] / 1024, 2); ?> MB</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Audio Duration</div>
                        <div class="value">⏱️ <?php echo formatDuration($data['audio_duration_sec']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Word Count</div>
                        <div class="value">📝 <?php echo $data['word_count'] ?: '—'; ?> words</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Evaluated By</div>
                        <div class="value">👨‍🏫 <?php echo $data['evaluator_name'] ?: 'Lecturer'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Evaluation Date</div>
                        <div class="value">📅 <?php echo date("F d, Y h:i A", strtotime($data['evaluated_at'])); ?></div>
                    </div>

                    <!-- Poem Text - Full Width -->
                    <div class="info-item full-width">
                        <div class="label">📝 Poem Text</div>
                        <div class="poem-box">
                            <?php 
                            if (!empty($data['ocr_text'])) {
                                echo nl2br(htmlspecialchars($data['ocr_text']));
                            } else {
                                echo '<span style="color: #94a3b8;">No poem text found.</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Lecturer Remarks -->
                <?php if (!empty($data['remarks'])) { ?>
                    <div class="remarks-box">
                        <h4>💬 Lecturer Remarks</h4>
                        <p><?php echo nl2br(htmlspecialchars($data['remarks'])); ?></p>
                    </div>
                <?php } ?>

                <hr class="divider">

                <div class="footer-note">
                    <span class="icon">ℹ️</span>
                    <span>This evaluation is final. Contact your lecturer for any questions.</span>
                </div>

            <?php } ?>

        </div>
    </div>
</div>

</body>
</html>
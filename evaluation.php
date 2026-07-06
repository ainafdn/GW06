<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'lecturer') {
    header("Location: login.php");
    exit();
}

$evaluator_id = $_SESSION['user_id'];
$error = "";
$success = "";

if (!isset($_GET['submission_id'])) {
    header("Location: search.php");
    exit();
}

$submission_id = intval($_GET['submission_id']);

// Save evaluation
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $score = floatval($_POST['evaluation_score']);
    $remarks = mysqli_real_escape_string($conn, trim($_POST['remarks']));

    if ($score < 0 || $score > 100) {
        $error = "Score must be between 0 and 100.";
    } else {
        if ($score >= 80) $grade = "A";
        elseif ($score >= 70) $grade = "B";
        elseif ($score >= 60) $grade = "C";
        elseif ($score >= 50) $grade = "D";
        else $grade = "F";

        $check = "SELECT * FROM evaluation WHERE submission_id = '$submission_id'";
        $check_result = mysqli_query($conn, $check);

        if (mysqli_num_rows($check_result) > 0) {
            $update = "UPDATE evaluation 
                       SET evaluator_id = '$evaluator_id',
                           evaluation_score = '$score',
                           grade = '$grade',
                           remarks = '$remarks',
                           evaluated_at = NOW()
                       WHERE submission_id = '$submission_id'";
            $save_result = mysqli_query($conn, $update);
        } else {
            $insert = "INSERT INTO evaluation (submission_id, evaluator_id, evaluation_score, grade, remarks) 
                       VALUES ('$submission_id', '$evaluator_id', '$score', '$grade', '$remarks')";
            $save_result = mysqli_query($conn, $insert);
        }

        if ($save_result) {
            mysqli_query($conn, "UPDATE submission SET status = 'evaluated' WHERE submission_id = '$submission_id'");
            $success = "✅ Evaluation saved successfully!";
        } else {
            $error = "Failed to save evaluation: " . mysqli_error($conn);
        }
    }
}

// Get submission details
$sql = "SELECT 
            s.*,
            u.user_name,
            u.matric_no,
            m.audio_duration_sec,
            m.word_count,
            m.ocr_text,
            e.evaluation_score,
            e.grade,
            e.remarks,
            e.evaluated_at
        FROM submission s
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN metadata m ON s.submission_id = m.submission_id
        LEFT JOIN evaluation e ON s.submission_id = e.submission_id
        WHERE s.submission_id = '$submission_id'
        LIMIT 1";

$result = mysqli_query($conn, $sql);
$submission = mysqli_fetch_assoc($result);

if (!$submission) {
    die("Submission not found.");
}

// ============================================================
// AUTO-SUGGEST SCORE CALCULATION
// ============================================================
function calculateAutoScore($duration, $word_count, $file_size_kb) {
    $score = 0;
    $details = [];

    // === CBR: Audio Duration (max 30 points) ===
    if ($duration) {
        if ($duration >= 120 && $duration <= 240) {
            $score += 30;
            $details[] = "Duration: $duration sec (2-4 min) → +30 ✅";
        } elseif ($duration >= 90 && $duration < 120) {
            $score += 20;
            $details[] = "Duration: $duration sec (1.5-2 min) → +20";
        } elseif ($duration > 240 && $duration <= 300) {
            $score += 20;
            $details[] = "Duration: $duration sec (4-5 min) → +20";
        } elseif ($duration >= 60 && $duration < 90) {
            $score += 10;
            $details[] = "Duration: $duration sec (1-1.5 min) → +10";
        } elseif ($duration > 300) {
            $score += 10;
            $details[] = "Duration: $duration sec (>5 min) → +10";
        } else {
            $details[] = "Duration: $duration sec (<1 min) → +0";
        }
    } else {
        $details[] = "Duration: Not entered → +0";
    }

    // === TBR: Word Count (max 35 points) ===
    if ($word_count) {
        if ($word_count >= 30) {
            $score += 35;
            $details[] = "Word Count: $word_count words (30+) → +35 ✅";
        } elseif ($word_count >= 20) {
            $score += 25;
            $details[] = "Word Count: $word_count words (20-29) → +25";
        } elseif ($word_count >= 10) {
            $score += 15;
            $details[] = "Word Count: $word_count words (10-19) → +15";
        } else {
            $score += 5;
            $details[] = "Word Count: $word_count words (<10) → +5";
        }
    } else {
        $details[] = "Word Count: Not entered → +0";
    }

    // === ABR: File Size (max 20 points) ===
    if ($file_size_kb) {
        if ($file_size_kb >= 5000) {
            $score += 20;
            $details[] = "File Size: " . round($file_size_kb/1024, 1) . " MB (5+ MB) → +20 ✅";
        } elseif ($file_size_kb >= 2000) {
            $score += 15;
            $details[] = "File Size: " . round($file_size_kb/1024, 1) . " MB (2-5 MB) → +15";
        } elseif ($file_size_kb >= 1000) {
            $score += 10;
            $details[] = "File Size: " . round($file_size_kb/1024, 1) . " MB (1-2 MB) → +10";
        } else {
            $score += 5;
            $details[] = "File Size: " . round($file_size_kb/1024, 1) . " MB (<1 MB) → +5";
        }
    } else {
        $details[] = "File Size: Not available → +0";
    }

    // === File Format Bonus (max 15 points) ===
    $details[] = "📌 Format bonus not applied (optional)";

    return [
        'score' => min($score, 100),
        'details' => $details
    ];
}

// Calculate auto-suggest score
$auto_result = calculateAutoScore(
    $submission['audio_duration_sec'],
    $submission['word_count'],
    $submission['file_size_kb']
);

$auto_score = $auto_result['score'];
$auto_details = $auto_result['details'];

function formatDuration($seconds) {
    if (!$seconds) return "—";
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    return $minutes . ":" . str_pad($secs, 2, '0', STR_PAD_LEFT);
}

function getGradeFromScore($score) {
    if ($score >= 80) return "A";
    if ($score >= 70) return "B";
    if ($score >= 60) return "C";
    if ($score >= 50) return "D";
    return "F";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Evaluation | Lecturer</title>
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
        .container { max-width: 1100px; margin: 32px auto; padding: 0 32px; }

        /* Card */
        .card { background: white; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #0f172a, #1e293b); padding: 24px 32px; color: white; }
        .card-header h2 { font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
        .card-header .subtitle { font-size: 14px; color: #94a3b8; font-weight: 400; margin-top: 4px; }
        .card-body { padding: 32px; }

        /* Alerts */
        .error { color: #b91c1c; padding: 14px 18px; background: #fee2e2; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid #b91c1c; font-weight: 500; }
        .success { color: #15803d; padding: 14px 18px; background: #dcfce7; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid #15803d; font-weight: 500; }

        /* Sections */
        .section { background: #f8fafc; padding: 20px 24px; border-radius: 12px; margin: 16px 0; border-left: 5px solid #3b82f6; }
        .section h3 { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 12px; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .info-item { background: white; padding: 14px 18px; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
        .info-item .label { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-item .value { font-size: 16px; font-weight: 600; color: #0f172a; margin-top: 2px; }

        .text-preview { background: white; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0; max-height: 140px; overflow-y: auto; font-size: 14px; line-height: 1.7; color: #1e293b; margin-top: 10px; }

        /* Auto-Suggest Box - Auto Accept (No Buttons) */
        .auto-box { 
            background: #f0fdf4; 
            padding: 24px; 
            border-radius: 12px; 
            margin: 16px 0; 
            border: 2px solid #22c55e;
            position: relative;
        }
        .auto-box::before {
            content: '✅ Auto-Accepted';
            position: absolute;
            top: -10px;
            right: 20px;
            background: #22c55e;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 14px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }
        .auto-box h3 { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 12px; }
        .auto-box .flex { display: flex; align-items: flex-start; gap: 30px; flex-wrap: wrap; }
        .auto-box .score-display { text-align: center; min-width: 140px; }
        .auto-box .score-number { font-size: 48px; font-weight: 800; color: #16a34a; letter-spacing: -1px; line-height: 1; }
        .auto-box .score-grade { font-size: 16px; font-weight: 600; color: #475569; margin-top: 4px; }
        .auto-box .details { font-size: 13px; color: #475569; flex: 1; }
        .auto-box .details ul { margin: 8px 0 0 20px; padding-left: 4px; }
        .auto-box .details li { margin-bottom: 2px; }
        .auto-box .details .note { font-size: 12px; color: #94a3b8; margin-top: 6px; }
        .auto-box .auto-badge {
            display: inline-block;
            padding: 4px 16px;
            background: #dcfce7;
            color: #16a34a;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }

        /* Form */
        label { display: block; font-weight: 600; font-size: 14px; color: #0f172a; margin-top: 18px; }
        label .required { color: #ef4444; }
        input, textarea { width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 10px; margin-top: 4px; font-size: 14px; font-family: 'Inter', sans-serif; transition: border-color 0.2s, box-shadow 0.2s; background: #fafbfc; }
        input:focus, textarea:focus { border-color: #3b82f6; outline: none; background: white; box-shadow: 0 0 0 4px rgba(59,130,246,0.08); }
        textarea { min-height: 90px; resize: vertical; }
        input[type="number"] { -moz-appearance: textfield; }
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

        /* Button Group */
        .btn-group { display: flex; gap: 16px; margin-top: 28px; flex-wrap: wrap; }
        .btn-save { background: #0f172a; color: white; padding: 14px 44px; border: none; border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer; transition: background 0.2s, transform 0.1s; }
        .btn-save:hover { background: #1e293b; }
        .btn-save:active { transform: scale(0.97); }
        .btn-back { background: #e2e8f0; color: #475569; padding: 14px 34px; border: none; border-radius: 10px; font-weight: 600; font-size: 15px; cursor: pointer; text-decoration: none; display: inline-block; transition: background 0.2s; }
        .btn-back:hover { background: #cbd5e1; }

        /* Current Result */
        .current-result { background: #f0fdf4; padding: 16px 20px; border-radius: 10px; border-left: 5px solid #22c55e; margin-top: 12px; }
        .current-result .grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .current-result .item .label { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; }
        .current-result .item .value { font-size: 15px; font-weight: 600; color: #0f172a; }

        .matric-badge {
            display: inline-block;
            padding: 2px 12px;
            background: #e2e8f0;
            border-radius: 12px;
            font-size: 13px;
            color: #475569;
            font-weight: 600;
        }

        .score-disabled {
            background: #f0fdf4 !important;
            border-color: #22c55e !important;
            color: #16a34a !important;
            font-weight: 700 !important;
            cursor: not-allowed;
        }

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
            .auto-box .flex { flex-direction: column; align-items: stretch; text-align: left; }
            .auto-box .score-display { text-align: left; }
            .current-result .grid { grid-template-columns: 1fr; }
            .btn-group { flex-direction: column; }
            .btn-save, .btn-back { width: 100%; text-align: center; }
            .auto-box::before { right: 10px; font-size: 10px; padding: 2px 10px; }
        }
        @media (max-width: 480px) {
            .auto-box .score-number { font-size: 36px; }
            .section { padding: 16px; }
            .info-item { padding: 12px 14px; }
            .info-item .value { font-size: 14px; }
            .auto-box::before { font-size: 9px; padding: 1px 8px; top: -8px; }
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
    <a href="search.php">🔍 Search</a>
    <a href="evaluation.php?submission_id=<?php echo $submission_id; ?>" class="active">⭐ Evaluation</a>
</div>

<div class="container">

    <div class="card">
        <div class="card-header">
            <h2>⭐ Evaluate Submission</h2>
            <div class="subtitle">Review and evaluate student's audio poetry</div>
        </div>
        <div class="card-body">

            <?php if ($success) { echo "<div class='success'>✅ $success</div>"; } ?>
            <?php if ($error) { echo "<div class='error'>❌ $error</div>"; } ?>

            <!-- Student Details -->
            <div class="section">
                <h3>👤 Student Details</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Student Name</div>
                        <div class="value"><?php echo htmlspecialchars($submission['user_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Matric Number</div>
                        <div class="value"><span class="matric-badge"><?php echo htmlspecialchars($submission['matric_no']); ?></span></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Submission ID</div>
                        <div class="value">#<?php echo $submission['submission_id']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Upload Date</div>
                        <div class="value"><?php echo date("F d, Y h:i A", strtotime($submission['upload_date'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Audio Details -->
            <div class="section">
                <h3>🎵 Audio Details</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">File Format</div>
                        <div class="value"><?php echo $submission['file_format']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">File Size</div>
                        <div class="value"><?php echo round($submission['file_size_kb']/1024, 2); ?> MB</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Duration</div>
                        <div class="value"><?php echo formatDuration($submission['audio_duration_sec']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Word Count</div>
                        <div class="value"><?php echo $submission['word_count'] ?: '—'; ?> words</div>
                    </div>
                </div>

                <?php if ($submission['ocr_text']) { ?>
                    <div style="margin-top: 15px;">
                        <div class="label" style="margin-top:0; font-size:12px; color:#94a3b8;">📝 Poem Text</div>
                        <div class="text-preview">
                            <?php echo nl2br(htmlspecialchars($submission['ocr_text'])); ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- Auto-Suggest Score - Auto Accepted (No Buttons) -->
            <div class="auto-box">
                <h3>🤖 Auto-Suggested Score</h3>
                <div class="flex">
                    <div class="score-display">
                        <div class="score-number"><?php echo $auto_score; ?> / 100</div>
                        <div class="score-grade">Grade: <strong><?php echo getGradeFromScore($auto_score); ?></strong></div>
                        <span class="auto-badge">✅ Auto-Accepted</span>
                    </div>
                    <div class="details">
                        <strong>Based on:</strong>
                        <ul>
                            <?php foreach ($auto_details as $detail) { ?>
                                <li><?php echo $detail; ?></li>
                            <?php } ?>
                        </ul>
                        <div class="note">💡 Maximum: 100 points (Duration 30 + Word Count 35 + File Size 20 + Format 15)</div>
                    </div>
                </div>
            </div>

            <!-- Evaluation Form -->
            <form method="POST" id="evaluationForm">

                <label>📊 Evaluation Score <span class="required">*</span></label>
                <input type="number" name="evaluation_score" id="scoreInput" 
                       min="0" max="100" step="0.01" 
                       value="<?php echo $auto_score; ?>" 
                       required placeholder="Enter score between 0-100"
                       class="score-disabled"
                       readonly>

                <label>💬 Remarks / Feedback</label>
                <textarea name="remarks" rows="4" placeholder="Provide feedback to the student..."><?php echo htmlspecialchars($submission['remarks'] ?? ''); ?></textarea>

                <?php if ($submission['evaluation_score'] !== null) { ?>
                    <div class="current-result">
                        <h4 style="font-size:14px; color:#0f172a; margin-bottom:10px;">📊 Current Result</h4>
                        <div class="grid">
                            <div class="item">
                                <div class="label">Score</div>
                                <div class="value"><?php echo $submission['evaluation_score']; ?> / 100</div>
                            </div>
                            <div class="item">
                                <div class="label">Grade</div>
                                <div class="value"><?php echo $submission['grade']; ?></div>
                            </div>
                            <div class="item">
                                <div class="label">Evaluated At</div>
                                <div class="value"><?php echo date("F d, Y h:i A", strtotime($submission['evaluated_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                <?php } ?>

                <div class="btn-group">
                    <button type="submit" class="btn-save">💾 Save Evaluation</button>
                    <a href="search.php" class="btn-back">⬅ Back to Search</a>
                </div>

            </form>

        </div>
    </div>

</div>

</body>
</html>
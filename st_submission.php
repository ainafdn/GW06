<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Check if student already submitted
$check_sql = "SELECT * FROM submission WHERE user_id = '$user_id' LIMIT 1";
$check_result = mysqli_query($conn, $check_sql);
$already_submitted = mysqli_num_rows($check_result) > 0;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['audio_file'])) {

    if ($already_submitted) {
        $error = "You have already submitted your audio.";
    } else {

        // Get form data
        $audio_duration = intval($_POST['audio_duration']);
        $poem_text = mysqli_real_escape_string($conn, trim($_POST['poem_text']));

        // Validate poem text
        if (empty($poem_text)) {
            $error = "Please paste your poem text.";
        } else {
            // Count words more accurately for poetry using regex
            $word_count = preg_match_all('/\b[a-zA-Z\']+\b/', $poem_text, $matches);
            
            if ($word_count < 5) {
                $error = "Please enter at least 5 words for your poem.";
            } else {

                $upload_dir = "uploads/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file = $_FILES['audio_file'];
                $original_name = basename($file['name']);
                $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                $new_name = "audio_" . time() . "_" . uniqid() . "." . $extension;
                $target_path = $upload_dir . $new_name;

                $allowed_formats = ['mp3', 'wav', 'm4a', 'ogg'];

                if (!in_array($extension, $allowed_formats)) {
                    $error = "Invalid file format. Only MP3, WAV, M4A, OGG allowed.";
                } elseif ($file['size'] > 25 * 1024 * 1024) {
                    $error = "File too large. Max 25MB.";
                } elseif (move_uploaded_file($file['tmp_name'], $target_path)) {

                    $file_format = strtoupper($extension);
                    $file_size_kb = round($file['size'] / 1024, 2);

                    // Insert into submission table
                    $insert = "INSERT INTO submission (user_id, audio_file, file_format, file_size_kb, status) 
                               VALUES ('$user_id', '$target_path', '$file_format', '$file_size_kb', 'pending')";

                    if (mysqli_query($conn, $insert)) {
                        $submission_id = mysqli_insert_id($conn);

                        // Insert into metadata table with duration AND poem text
                        $insert_meta = "INSERT INTO metadata (submission_id, ocr_text, word_count, audio_duration_sec) 
                                       VALUES ('$submission_id', '$poem_text', '$word_count', '$audio_duration')";
                        mysqli_query($conn, $insert_meta);

                        $success = "✅ Audio uploaded successfully! Your submission is pending review.";

                    } else {
                        $error = "Failed to save submission: " . mysqli_error($conn);
                    }
                } else {
                    $error = "File upload failed.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Submit Audio | Student</title>
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

        .container { max-width: 800px; margin: 32px auto; padding: 0 32px; }

        .card { background: white; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #0f172a, #1e293b); padding: 24px 32px; color: white; }
        .card-header h2 { font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
        .card-header .subtitle { font-size: 14px; color: #94a3b8; font-weight: 400; margin-top: 4px; }
        .card-body { padding: 32px; }

        .error { color: #b91c1c; padding: 14px 18px; background: #fee2e2; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid #b91c1c; font-weight: 500; display: flex; align-items: center; gap: 8px; }
        .success { color: #15803d; padding: 14px 18px; background: #dcfce7; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid #15803d; font-weight: 500; display: flex; align-items: center; gap: 8px; }

        .info-box { background: #eff6ff; padding: 20px 24px; border-radius: 12px; margin-bottom: 24px; border-left: 5px solid #3b82f6; }
        .info-box h4 { font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .info-box ul { margin: 8px 0 0 20px; padding-left: 4px; color: #475569; font-size: 14px; line-height: 1.8; }
        .info-box ul li strong { color: #0f172a; }

        .form-group { margin-top: 20px; }
        .form-group label { display: block; font-weight: 600; font-size: 14px; color: #0f172a; margin-bottom: 4px; }
        .form-group label .required { color: #ef4444; }
        .form-group input, .form-group textarea { 
            width: 100%; 
            padding: 12px 14px; 
            border: 2px solid #e2e8f0; 
            border-radius: 10px; 
            font-size: 14px; 
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fafbfc;
        }
        .form-group input:focus, .form-group textarea:focus { 
            border-color: #3b82f6; 
            outline: none; 
            background: white; 
            box-shadow: 0 0 0 4px rgba(59,130,246,0.08);
        }
        .form-group input::placeholder, .form-group textarea::placeholder { color: #94a3b8; }
        .form-group textarea { min-height: 150px; resize: vertical; line-height: 1.6; }
        .form-group small { display: block; color: #94a3b8; font-size: 12px; margin-top: 6px; }
        
        .file-upload-wrapper input[type="file"] {
            padding: 12px;
            border: 2px dashed #e2e8f0;
            border-radius: 10px;
            background: #fafbfc;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            width: 100%;
        }
        .file-upload-wrapper input[type="file"]:hover {
            border-color: #94a3b8;
            background: #f1f5f9;
        }
        .file-upload-wrapper input[type="file"]::-webkit-file-upload-button {
            padding: 8px 20px;
            margin-right: 12px;
            border: none;
            border-radius: 8px;
            background: #0f172a;
            color: white;
            font-weight: 600;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: background 0.2s;
        }
        .file-upload-wrapper input[type="file"]::-webkit-file-upload-button:hover {
            background: #1e293b;
        }
        .file-upload-wrapper input[type="file"]::file-selector-button {
            padding: 8px 20px;
            margin-right: 12px;
            border: none;
            border-radius: 8px;
            background: #0f172a;
            color: white;
            font-weight: 600;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: background 0.2s;
        }
        .file-upload-wrapper input[type="file"]::file-selector-button:hover {
            background: #1e293b;
        }

        .btn-submit { 
            width: 100%; 
            padding: 14px; 
            background: #0f172a; 
            color: white; 
            border: none; 
            border-radius: 10px; 
            margin-top: 28px; 
            cursor: pointer; 
            font-size: 16px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-submit:hover { background: #1e293b; }
        .btn-submit:active { transform: scale(0.98); }

        .already-submitted { text-align: center; padding: 30px 20px; }
        .already-submitted .icon { font-size: 64px; display: block; margin-bottom: 8px; }
        .already-submitted h3 { font-size: 22px; color: #0f172a; margin-bottom: 6px; }
        .already-submitted p { color: #64748b; font-size: 15px; }
        .already-submitted .btn-view { 
            display: inline-block; 
            margin-top: 16px; 
            padding: 12px 32px; 
            background: #0f172a; 
            color: white; 
            text-decoration: none; 
            border-radius: 10px; 
            font-weight: 600;
            transition: background 0.2s;
        }
        .already-submitted .btn-view:hover { background: #1e293b; }

        @media (max-width: 768px) {
            .header { padding: 12px 20px; flex-direction: column; gap: 8px; text-align: center; }
            .nav { padding: 0 16px; }
            .nav a { padding: 12px 16px; font-size: 13px; }
            .container { padding: 0 16px; margin: 20px auto; }
            .card-body { padding: 20px; }
            .card-header { padding: 20px; }
            .card-header h2 { font-size: 19px; }
            .info-box { padding: 16px 18px; }
            .form-group { margin-top: 16px; }
        }
        @media (max-width: 480px) {
            .already-submitted .icon { font-size: 48px; }
            .already-submitted h3 { font-size: 18px; }
            .file-upload-wrapper input[type="file"] {
                font-size: 12px;
                padding: 10px;
            }
            .file-upload-wrapper input[type="file"]::-webkit-file-upload-button {
                padding: 6px 14px;
                font-size: 12px;
            }
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
    <a href="st_submission.php" class="active">📤 Submit</a>
    <a href="st_history.php">📂 My Submission</a>
    <a href="st_results.php">📊 My Result</a>
</div>

<div class="container">

    <div class="card">
        <div class="card-header">
            <h2>📤 Submit Your Audio Poetry</h2>
            <div class="subtitle">Upload your poetry recording and submit for evaluation</div>
        </div>
        <div class="card-body">

            <?php if ($already_submitted) { ?>
                <div class="already-submitted">
                    <span class="icon">✅</span>
                    <h3>You have already submitted your audio!</h3>
                    <p>You can only submit once. View your submission details below.</p>
                    <a href="st_history.php" class="btn-view">📂 View My Submission</a>
                </div>

            <?php } else { ?>

                <?php if ($error) { echo "<div class='error'>❌ $error</div>"; } ?>
                <?php if ($success) { echo "<div class='success'>✅ $success</div>"; } ?>

                <?php if (!$success) { ?>

                    <div class="info-box">
                        <h4>📋 What You Need to Submit</h4>
                        <ul>
                            <li><strong>Audio File</strong> — Your poetry recording (MP3, WAV, M4A, OGG)</li>
                            <li><strong>Poem Text</strong> — Copy and paste your full poem below</li>
                            <li><strong>Duration</strong> — How long is your audio (in seconds)</li>
                        </ul>
                    </div>

                    <form method="POST" enctype="multipart/form-data">

                        <div class="form-group">
                            <label>🎵 Audio File <span class="required">*</span></label>
                            <div class="file-upload-wrapper">
                                <input type="file" name="audio_file" accept=".mp3,.wav,.m4a,.ogg" required>
                            </div>
                            <small>Max 25MB. Allowed: MP3, WAV, M4A, OGG</small>
                        </div>

                        <div class="form-group">
                            <label>📝 Poem Text <span class="required">*</span></label>
                            <textarea name="poem_text" placeholder="Paste your full poem here..." required></textarea>
                            <small>This text will be used for keyword searching (TBR).</small>
                        </div>

                        <div class="form-group">
                            <label>⏱️ Audio Duration (in seconds) <span class="required">*</span></label>
                            <input type="number" name="audio_duration" placeholder="e.g., 180 = 3 minutes" required min="1" max="600">
                            <small>Example: 60 = 1 minute, 180 = 3 minutes, 300 = 5 minutes</small>
                        </div>

                        <button type="submit" class="btn-submit">🚀 Submit Audio</button>

                    </form>

                <?php } ?>

            <?php } ?>

        </div>
    </div>

</div>

</body>
</html>
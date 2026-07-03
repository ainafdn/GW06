<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'lecturer') {
    header("Location: login.php");
    exit();
}

// Get statistics
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users"))['total'];
$total_submissions = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM submission"))['total'];
$total_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM submission WHERE status = 'pending'"))['total'];

$avg_score = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(evaluation_score) AS avg FROM evaluation"))['avg'];
$avg_score = $avg_score ? round($avg_score, 1) : "—";

$top_student = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT u.user_name, e.evaluation_score 
    FROM evaluation e 
    JOIN submission s ON e.submission_id = s.submission_id 
    JOIN users u ON s.user_id = u.user_id 
    ORDER BY e.evaluation_score DESC LIMIT 1
"));

// Top performers
$top_performers = mysqli_query($conn, "
    SELECT u.user_name, e.evaluation_score, m.ocr_text 
    FROM evaluation e 
    JOIN submission s ON e.submission_id = s.submission_id 
    JOIN users u ON s.user_id = u.user_id 
    LEFT JOIN metadata m ON s.submission_id = m.submission_id 
    ORDER BY e.evaluation_score DESC LIMIT 5
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard | Lecturer</title>
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
        .nav { background: white; padding: 0 40px; display: flex; gap: 0; border-bottom: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        .nav a { padding: 16px 24px; text-decoration: none; color: #475569; font-weight: 600; font-size: 14px; border-bottom: 3px solid transparent; transition: all 0.2s; }
        .nav a:hover { color: #0f172a; background: #f8fafc; }
        .nav a.active { color: #0f172a; border-bottom-color: #3b82f6; background: #f8fafc; }
        
        /* Container */
        .container { max-width: 1280px; margin: 32px auto; padding: 0 32px; }
        .page-title { font-size: 28px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; margin-bottom: 4px; }
        .page-subtitle { color: #64748b; font-size: 15px; margin-bottom: 28px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 22px 24px; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); border-left: 5px solid #3b82f6; transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
        .stat-card .number { font-size: 32px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; }
        .stat-card .label { color: #64748b; font-size: 13px; font-weight: 500; margin-top: 2px; }
        .stat-card.highlight { background: linear-gradient(135deg, #0f172a, #1e293b); border-left-color: #f59e0b; }
        .stat-card.highlight .number { color: #fbbf24; }
        .stat-card.highlight .label { color: #94a3b8; }
        .stat-card .sub { font-size: 13px; color: #94a3b8; margin-top: 4px; }
        .stat-card.highlight .sub { color: #94a3b8; }
        .stat-card.green { border-left-color: #22c55e; }
        .stat-card.purple { border-left-color: #8b5cf6; }
        .stat-card.orange { border-left-color: #f59e0b; }
        
        /* Table Section */
        .table-section { background: white; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); overflow: hidden; }
        .table-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .table-header h3 { font-size: 18px; font-weight: 700; color: #0f172a; }
        .table-header .badge-count { background: #e2e8f0; padding: 2px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; color: #475569; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #f8fafc; padding: 14px 20px; text-align: left; font-weight: 600; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; }
        td { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }
        .rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; font-weight: 700; font-size: 13px; }
        .rank-1 { background: #fef3c7; color: #d97706; }
        .rank-2 { background: #e2e8f0; color: #475569; }
        .rank-3 { background: #fed7aa; color: #c2410c; }
        .rank-other { background: #f1f5f9; color: #64748b; }
        .score-high { color: #16a34a; font-weight: 700; }
        .score-mid { color: #d97706; font-weight: 700; }
        .score-low { color: #dc2626; font-weight: 700; }
        .preview-text { color: #64748b; font-size: 13px; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
        .student-name { font-weight: 600; color: #0f172a; }
        .empty-state { text-align: center; padding: 40px 20px; color: #94a3b8; }
        .empty-state p { font-size: 14px; }
        
        @media (max-width: 768px) {
            .header { padding: 12px 20px; flex-direction: column; gap: 8px; text-align: center; }
            .nav { padding: 0 16px; overflow-x: auto; }
            .nav a { padding: 12px 16px; font-size: 13px; white-space: nowrap; }
            .container { padding: 0 16px; margin: 20px auto; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card { padding: 16px; }
            .stat-card .number { font-size: 24px; }
            .table-section { overflow-x: auto; }
            th, td { padding: 10px 14px; font-size: 13px; }
            .page-title { font-size: 22px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
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
    <a href="dashboard.php" class="active">📊 Dashboard</a>
    <a href="search.php">🔍 Search</a>
</div>

<div class="container">
    <h1 class="page-title">📊 Dashboard</h1>
    <p class="page-subtitle">Audio poetry submission statistics overview</p>

    <div class="stats-grid">
        <div class="stat-card green">
            <div class="number"><?php echo $total_users; ?></div>
            <div class="label">👥 Total Users</div>
        </div>
        <div class="stat-card purple">
            <div class="number"><?php echo $total_submissions; ?></div>
            <div class="label">📤 Total Submissions</div>
        </div>
        <div class="stat-card orange">
            <div class="number"><?php echo $avg_score; ?></div>
            <div class="label">⭐ Average Score</div>
        </div>
        <div class="stat-card highlight">
            <div class="number"><?php echo $top_student ? $top_student['evaluation_score'] . '/100' : '—'; ?></div>
            <div class="label">🏆 Top Score</div>
            <div class="sub"><?php echo $top_student ? $top_student['user_name'] : 'No data'; ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #ef4444;">
            <div class="number"><?php echo $total_pending; ?></div>
            <div class="label">⏳ Pending Evaluations</div>
        </div>
    </div>

    <div class="table-section">
        <div class="table-header">
            <h3>🏆 Top Performing Students</h3>
            <span class="badge-count">Top 5</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width:60px;">#</th>
                    <th>Student Name</th>
                    <th style="width:100px;">Score</th>
                    <th>Poem Preview</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                if (mysqli_num_rows($top_performers) > 0) {
                    while ($row = mysqli_fetch_assoc($top_performers)) { 
                        $preview = $row['ocr_text'] ? substr($row['ocr_text'], 0, 60) . '...' : '—';
                        $rankClass = $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : 'rank-other'));
                        $scoreClass = $row['evaluation_score'] >= 80 ? 'score-high' : ($row['evaluation_score'] >= 60 ? 'score-mid' : 'score-low');
                ?>
                    <tr>
                        <td><span class="rank-badge <?php echo $rankClass; ?>"><?php echo $rank++; ?></span></td>
                        <td><span class="student-name"><?php echo $row['user_name']; ?></span></td>
                        <td><span class="<?php echo $scoreClass; ?>"><?php echo $row['evaluation_score']; ?>/100</span></td>
                        <td><span class="preview-text"><?php echo $preview; ?></span></td>
                    </tr>
                <?php } 
                } else { ?>
                    <tr><td colspan="4" class="empty-state"><p>📭 No evaluation data available yet.</p></td></tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
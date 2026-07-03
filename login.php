<?php
session_start();
include 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    if (empty($email) || empty($password) || empty($role)) {
        $error = "Please fill in all fields.";
    } else {
        $sql = "SELECT * FROM users WHERE email = '$email' AND password = '$password' AND role = '$role' LIMIT 1";
        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['user_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] == 'student') {
                header("Location: st_submission.php");
                exit();
            } else {
                header("Location: dashboard.php");
                exit();
            }
        } else {
            $error = "Invalid email, password, or role.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login | Audio Poetry System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { 
            background: white; 
            padding: 48px 40px; 
            border-radius: 20px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
            width: 420px; 
            max-width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #60a5fa, #3b82f6);
            background-size: 200% 100%;
            animation: gradientMove 3s ease-in-out infinite;
        }
        
        @keyframes gradientMove {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .logo { 
            text-align: center; 
            margin-bottom: 8px;
        }
        .logo-icon { 
            font-size: 48px; 
            display: block;
            margin-bottom: 4px;
        }
        .logo h1 { 
            font-size: 26px; 
            font-weight: 800; 
            color: #0f172a; 
            letter-spacing: -0.5px;
        }
        .logo h1 span { color: #3b82f6; }
        .logo .subtitle { 
            color: #94a3b8; 
            font-size: 14px; 
            font-weight: 400;
            margin-top: 2px;
        }
        
        .error { 
            color: #dc2626; 
            padding: 12px 16px; 
            background: #fef2f2; 
            border-radius: 10px; 
            margin: 16px 0 8px 0; 
            font-size: 14px;
            font-weight: 500;
            border-left: 4px solid #dc2626;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group { 
            margin-top: 18px; 
        }
        .form-group label { 
            display: block; 
            font-weight: 600; 
            font-size: 13px; 
            color: #0f172a; 
            margin-bottom: 4px;
        }
        .form-group label .required { color: #ef4444; }
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 12px 14px; 
            border: 2px solid #e2e8f0; 
            border-radius: 10px; 
            font-size: 14px; 
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fafbfc;
        }
        .form-group input:focus, .form-group select:focus { 
            border-color: #3b82f6; 
            outline: none; 
            background: white; 
            box-shadow: 0 0 0 4px rgba(59,130,246,0.08);
        }
        .form-group input::placeholder { color: #94a3b8; }
        
        .btn-login { 
            width: 100%; 
            padding: 14px; 
            background: #0f172a; 
            color: white; 
            border: none; 
            border-radius: 10px; 
            margin-top: 24px; 
            cursor: pointer; 
            font-size: 16px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-login:hover { background: #1e293b; }
        .btn-login:active { transform: scale(0.98); }
        
        .link { 
            text-align: center; 
            margin-top: 18px; 
            font-size: 14px;
            color: #64748b;
        }
        .link a { 
            color: #3b82f6; 
            text-decoration: none; 
            font-weight: 600;
            transition: color 0.2s;
        }
        .link a:hover { color: #2563eb; text-decoration: underline; }
        
        .divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 20px 0 4px 0;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }
        .divider span {
            color: #94a3b8;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 4px;
        }
        .role-option {
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: #fafbfc;
            font-weight: 500;
            font-size: 14px;
            color: #64748b;
        }
        .role-option:hover {
            border-color: #94a3b8;
            background: #f1f5f9;
        }
        .role-option.selected {
            border-color: #3b82f6;
            background: #eff6ff;
            color: #0f172a;
        }
        .role-option .icon { font-size: 20px; display: block; margin-bottom: 2px; }
        .role-option .label { font-size: 12px; }
        
        .role-select-hidden {
            display: none;
        }
        
        @media (max-width: 480px) {
            .container { padding: 32px 24px; }
            .logo h1 { font-size: 22px; }
            .role-selector { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <span class="logo-icon">🎵</span>
            <h1>Audio<span>Poetry</span></h1>
            <div class="subtitle">Sign in to your account</div>
        </div>

        <?php if ($error) { echo "<div class='error'>❌ $error</div>"; } ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label>Email Address <span class="required">*</span></label>
                <input type="email" name="email" placeholder="your@email.com" required autofocus>
            </div>

            <div class="form-group">
                <label>Password <span class="required">*</span></label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <div class="form-group">
                <label>Role <span class="required">*</span></label>
                <div class="role-selector" id="roleSelector">
                    <div class="role-option selected" data-role="student" onclick="selectRole('student')">
                        <span class="icon">👨‍🎓</span>
                        <span class="label">Student</span>
                    </div>
                    <div class="role-option" data-role="lecturer" onclick="selectRole('lecturer')">
                        <span class="icon">👨‍🏫</span>
                        <span class="label">Lecturer</span>
                    </div>
                </div>
                <select name="role" id="roleSelect" class="role-select-hidden" required>
                    <option value="student" selected>Student</option>
                    <option value="lecturer">Lecturer / Admin</option>
                </select>
            </div>

            <button type="submit" class="btn-login">🔐 Sign In</button>
        </form>

        <div class="divider">
            <span>New here?</span>
        </div>

        <div class="link">
            <a href="register.php">Create an account →</a>
        </div>
    </div>

    <script>
        function selectRole(role) {
            // Update visual selection
            const options = document.querySelectorAll('.role-option');
            options.forEach(opt => {
                opt.classList.remove('selected');
                if (opt.dataset.role === role) {
                    opt.classList.add('selected');
                }
            });
            
            // Update hidden select
            document.getElementById('roleSelect').value = role;
        }
        
        // Set initial role based on selected class
        document.addEventListener('DOMContentLoaded', function() {
            const selected = document.querySelector('.role-option.selected');
            if (selected) {
                document.getElementById('roleSelect').value = selected.dataset.role;
            }
        });
    </script>
</body>
</html>
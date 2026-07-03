<?php
include 'db.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_name = trim($_POST['user_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = "student";

    if (empty($user_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $check = "SELECT * FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $check);

        if (mysqli_num_rows($result) > 0) {
            $error = "Email already registered.";
        } else {
            $sql = "INSERT INTO users (user_name, email, password, role) VALUES ('$user_name', '$email', '$password', '$role')";

            if (mysqli_query($conn, $sql)) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register | Audio Poetry System</title>
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
        
        .success { 
            color: #16a34a; 
            padding: 12px 16px; 
            background: #f0fdf4; 
            border-radius: 10px; 
            margin: 16px 0 8px 0; 
            font-size: 14px;
            font-weight: 500;
            border-left: 4px solid #16a34a;
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
        .form-group input { 
            width: 100%; 
            padding: 12px 14px; 
            border: 2px solid #e2e8f0; 
            border-radius: 10px; 
            font-size: 14px; 
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fafbfc;
        }
        .form-group input:focus { 
            border-color: #3b82f6; 
            outline: none; 
            background: white; 
            box-shadow: 0 0 0 4px rgba(59,130,246,0.08);
        }
        .form-group input::placeholder { color: #94a3b8; }
        .form-group input.error-input {
            border-color: #dc2626;
            background: #fef2f2;
        }
        .form-group input.success-input {
            border-color: #16a34a;
            background: #f0fdf4;
        }
        
        .btn-register { 
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
        .btn-register:hover { background: #1e293b; }
        .btn-register:active { transform: scale(0.98); }
        .btn-register:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
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
        
        .password-strength {
            margin-top: 6px;
            font-size: 12px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .password-strength .bar {
            flex: 1;
            height: 4px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .password-strength .bar .fill {
            height: 100%;
            width: 0%;
            border-radius: 4px;
            transition: width 0.3s, background 0.3s;
        }
        .password-strength .bar .fill.weak { width: 25%; background: #dc2626; }
        .password-strength .bar .fill.medium { width: 50%; background: #f59e0b; }
        .password-strength .bar .fill.strong { width: 75%; background: #3b82f6; }
        .password-strength .bar .fill.very-strong { width: 100%; background: #16a34a; }
        .password-strength .text { font-weight: 500; min-width: 60px; }
        
        .password-match {
            margin-top: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .password-match.match { color: #16a34a; }
        .password-match.no-match { color: #dc2626; }
        
        @media (max-width: 480px) {
            .container { padding: 32px 24px; }
            .logo h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <span class="logo-icon">🎵</span>
            <h1>Audio<span>Poetry</span></h1>
            <div class="subtitle">Create your account</div>
        </div>

        <?php if ($error) { echo "<div class='error'>❌ $error</div>"; } ?>
        <?php if ($success) { echo "<div class='success'>✅ $success</div>"; } ?>

        <form method="POST" id="registerForm">
            <div class="form-group">
                <label>Full Name <span class="required">*</span></label>
                <input type="text" name="user_name" placeholder="Enter your full name" required autofocus>
            </div>

            <div class="form-group">
                <label>Email Address <span class="required">*</span></label>
                <input type="email" name="email" placeholder="your@email.com" required>
            </div>

            <div class="form-group">
                <label>Password <span class="required">*</span></label>
                <input type="password" name="password" id="password" placeholder="Create a password" required>
                <div class="password-strength">
                    <span>Strength:</span>
                    <div class="bar">
                        <div class="fill" id="strengthBar"></div>
                    </div>
                    <span class="text" id="strengthText">—</span>
                </div>
            </div>

            <div class="form-group">
                <label>Confirm Password <span class="required">*</span></label>
                <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm your password" required>
                <div class="password-match" id="matchMessage"></div>
            </div>

            <button type="submit" class="btn-register" id="registerBtn">🚀 Create Account</button>
        </form>

        <div class="divider">
            <span>Already a member?</span>
        </div>

        <div class="link">
            <a href="login.php">Sign in to your account →</a>
        </div>
    </div>

    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirmPassword');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const matchMessage = document.getElementById('matchMessage');
        const registerBtn = document.getElementById('registerBtn');

        function checkPasswordStrength(password) {
            let score = 0;
            if (password.length >= 8) score++;
            if (password.match(/[a-z]/)) score++;
            if (password.match(/[A-Z]/)) score++;
            if (password.match(/[0-9]/)) score++;
            if (password.match(/[^a-zA-Z0-9]/)) score++;
            return score;
        }

        function updateStrength() {
            const password = passwordInput.value;
            if (password.length === 0) {
                strengthBar.className = 'fill';
                strengthBar.style.width = '0%';
                strengthText.textContent = '—';
                return;
            }

            const score = checkPasswordStrength(password);
            let level, width, color;
            
            if (score <= 1) {
                level = 'Weak';
                width = '25%';
                color = '#dc2626';
            } else if (score <= 2) {
                level = 'Medium';
                width = '50%';
                color = '#f59e0b';
            } else if (score <= 3) {
                level = 'Strong';
                width = '75%';
                color = '#3b82f6';
            } else {
                level = 'Very Strong';
                width = '100%';
                color = '#16a34a';
            }
            
            strengthBar.className = 'fill';
            strengthBar.style.width = width;
            strengthBar.style.background = color;
            strengthText.textContent = level;
            strengthText.style.color = color;
        }

        function checkMatch() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            
            if (confirm.length === 0) {
                matchMessage.textContent = '';
                matchMessage.className = 'password-match';
                return;
            }
            
            if (password === confirm) {
                matchMessage.textContent = '✅ Passwords match';
                matchMessage.className = 'password-match match';
                registerBtn.disabled = false;
            } else {
                matchMessage.textContent = '❌ Passwords do not match';
                matchMessage.className = 'password-match no-match';
                registerBtn.disabled = true;
            }
        }

        passwordInput.addEventListener('input', function() {
            updateStrength();
            checkMatch();
        });

        confirmInput.addEventListener('input', function() {
            checkMatch();
        });

        // Initial check on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a success message (registration successful)
            const successDiv = document.querySelector('.success');
            if (successDiv) {
                // Don't disable button if already registered
                registerBtn.disabled = false;
            }
        });
    </script>
</body>
</html>
<?php
session_start();
require_once 'config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT e.*, d.name as department_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id WHERE e.email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'Active') {
                    $error = 'Your account is deactivated.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['department_id'] = $user['department_id'];

                    // Log action
                    $stmtLog = $db->prepare("INSERT INTO activity_logs (employee_id, action, details) VALUES (?, ?, ?)");
                    $stmtLog->execute([$user['id'], 'User Login', 'Logged in successfully via Multi-Page portal.']);

                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            $error = 'Database Connection Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill out all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AssetFlow - Log In</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;750&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: radial-gradient(circle at center, rgba(99, 102, 241, 0.08), transparent 700px), #080c14;
        }
        .auth-container {
            width: 420px;
            max-width: 90%;
        }
    </style>
</head>
<body class="dark-theme">
    <div class="auth-container">
        <div class="auth-box" style="width: 100%;">
            <div class="auth-logo">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="logo-icon"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                <span>AssetFlow</span>
            </div>
            
            <form action="login.php" method="POST" class="auth-form">
                <h2>Welcome Back</h2>
                <p class="subtitle">Log in to manage assets and resources</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom: 16px; padding: 10px; font-size: 0.85rem; border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.2); background: rgba(239, 68, 68, 0.05); color: #f87171;">
                        ⚠️ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="login-email">Email Address</label>
                    <input type="email" name="email" id="login-email" required placeholder="name@company.com">
                </div>
                <div class="form-group">
                    <div class="label-wrapper">
                        <label for="login-password">Password</label>
                        <a href="forgot_password.php" id="forgot-password" class="text-link">Forgot?</a>
                    </div>
                    <input type="password" name="password" id="login-password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Log In</button>
                <p class="auth-switch">Don't have an employee account? <a href="signup.php">Sign up</a></p>
                
                <div class="mock-credentials">
                    <strong>Demo Logins (Password: <code>password123</code>):</strong><br>
                    • Admin: <code>admin@assetflow.com</code><br>
                    • Manager: <code>vikram@assetflow.com</code><br>
                    • Dept Head: <code>priya@assetflow.com</code><br>
                    • Employee: <code>sneha@assetflow.com</code>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('forgot-password').addEventListener('click', (e) => {
            e.preventDefault();
            alert('Demo mode: Please contact the system Administrator to reset your password.');
        });
    </script>
</body>
</html>

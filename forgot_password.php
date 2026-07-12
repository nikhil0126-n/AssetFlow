<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if ($email === '') {
        $error = 'Email address is required.';
    } else {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT id, name FROM employees WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Reset password to password123 (hashed)
                $newHashed = password_hash('password123', PASSWORD_DEFAULT);
                $stmtUpdate = $db->prepare("UPDATE employees SET password = ? WHERE id = ?");
                $stmtUpdate->execute([$newHashed, $user['id']]);
                
                log_activity($db, $user['id'], 'Password Reset Requested', 'Password reset successfully to default: password123');
                
                $success = "Password for " . htmlspecialchars($user['name']) . " has been reset to default: <strong>password123</strong>. You can log in now.";
            } else {
                $error = 'No user registered with this email address.';
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AssetFlow - Reset Password</title>
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
        <div class="auth-box" style="width: 100%; background: var(--bg-sidebar); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 32px; box-shadow: var(--shadow);">
            <div class="auth-logo" style="display: flex; align-items: center; gap: 12px; font-family: var(--font-outfit); font-size: 1.6rem; font-weight: 800; color: var(--color-primary); margin-bottom: 24px; justify-content: center;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="logo-icon"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                <span>AssetFlow</span>
            </div>
            
            <form action="forgot_password.php" method="POST" class="auth-form" style="display:flex; flex-direction:column; gap:16px;">
                <h2 style="margin:0; font-family: var(--font-outfit); font-size: 1.4rem; font-weight: 700; text-align:center;">Forgot Password?</h2>
                <p class="subtitle" style="margin:0; font-size: 0.88rem; color: var(--text-secondary); text-align:center; line-height: 1.4;">Enter your email below to reset your password to the default system password.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" style="padding: 10px; font-size: 0.85rem; border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.2); background: rgba(239, 68, 68, 0.05); color: #f87171;">
                        ⚠️ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="padding: 10px; font-size: 0.85rem; border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.2); background: rgba(16, 185, 129, 0.05); color: #34d399;">
                        ✓ <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <div class="form-group" style="display:flex; flex-direction:column; gap:6px;">
                    <label for="reset-email" style="font-size:0.85rem; font-weight:600; color:var(--text-secondary);">Email Address</label>
                    <input type="email" name="email" id="reset-email" required placeholder="name@company.com" class="form-control" style="width:100%;">
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="width:100%; height:42px; margin-top:8px;">Reset Password</button>
                
                <div style="text-align: center; margin-top: 12px; font-size: 0.85rem;">
                    <a href="login.php" class="text-link">Back to Log In</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

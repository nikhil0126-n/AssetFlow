<?php
session_start();
require_once 'config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$db = getDBConnection();

// Fetch active departments for select element
try {
    $depts = $db->query("SELECT id, name FROM departments WHERE status = 'Active'")->fetchAll();
} catch (Exception $e) {
    $depts = [];
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $department_id = $_POST['department_id'] ?? null;

    if ($name && $email && $password) {
        try {
            // Email validation
            $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'An account with this email already exists.';
            } else {
                $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                $stmtIns = $db->prepare("INSERT INTO employees (name, email, password, department_id, role, status) VALUES (?, ?, ?, ?, 'employee', 'Active')");
                $stmtIns->execute([$name, $email, $hashedPass, $department_id ? (int)$department_id : null]);
                $newId = $db->lastInsertId();

                // Log Activity
                $db->prepare("INSERT INTO activity_logs (employee_id, action, details) VALUES (?, 'User Signup', 'Created an Employee account.')")
                   ->execute([$newId]);

                // Create alert notification
                $db->prepare("INSERT INTO notifications (employee_id, title, message, type) VALUES (NULL, 'New User Signed Up', ?, 'info')")
                   ->execute(["$name ($email) registered an Employee account."]);

                $success = 'Account created successfully! Redirecting to login...';
            }
        } catch (Exception $e) {
            $error = 'Signup execution failed: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill out all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AssetFlow - Sign Up</title>
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
            
            <form action="signup.php" method="POST" class="auth-form">
                <h2>Create Account</h2>
                <p class="subtitle">Join your organization's AssetFlow platform</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom: 16px; padding: 10px; font-size: 0.85rem; border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.2); background: rgba(239, 68, 68, 0.05); color: #f87171;">
                        ⚠️ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 16px; padding: 10px; font-size: 0.85rem; border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.2); background: rgba(16, 185, 129, 0.05); color: #34d399;">
                        ✓ <?php echo htmlspecialchars($success); ?>
                    </div>
                    <script>
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 2000);
                    </script>
                <?php endif; ?>

                <div class="form-group">
                    <label for="signup-name">Full Name</label>
                    <input type="text" name="name" id="signup-name" required placeholder="Jane Doe">
                </div>
                <div class="form-group">
                    <label for="signup-email">Email Address</label>
                    <input type="email" name="email" id="signup-email" required placeholder="jane@company.com">
                </div>
                <div class="form-group">
                    <label for="signup-password">Password</label>
                    <input type="password" name="password" id="signup-password" required placeholder="Min 6 characters">
                </div>
                <div class="form-group">
                    <label for="signup-dept">Department</label>
                    <select name="department_id" id="signup-dept">
                        <option value="">Select Department</option>
                        <?php foreach ($depts as $d): ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="alert alert-info" style="font-size: 0.8rem; margin: 12px 0 4px 0; padding: 8px;">
                    ℹ️ Signups default to <strong>Employee</strong> role only. Admin can promote roles.
                </div>
                <button type="submit" class="btn btn-primary btn-block">Sign Up</button>
                <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
            </form>
        </div>
    </div>
</body>
</html>

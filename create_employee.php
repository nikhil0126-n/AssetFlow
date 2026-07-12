<?php
require_once 'header.php';

// Guard: Admin only
if ($user_role !== 'admin') {
    header('Location: org_setup.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $department_id = $_POST['department_id'] ?? '';
    $role = $_POST['role'] ?? 'employee';
    $status = $_POST['status'] ?? 'Active';

    if ($name === '') {
        $error = 'Full Name is required.';
    } elseif ($email === '') {
        $error = 'Email Address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address format.';
    } elseif ($password === '' || strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            // Check if email already exists
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetchColumn() > 0) {
                $error = "An account with the email '$email' already exists.";
            } else {
                $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO employees (name, email, password, department_id, role, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $name,
                    $email,
                    $hashedPass,
                    $department_id !== '' ? (int)$department_id : null,
                    $role,
                    $status
                ]);
                
                $newId = $db->lastInsertId();
                log_activity($db, $user_id, 'Create Employee', "Created employee: $name ($email)");
                create_notification($db, $newId, 'Welcome to AssetFlow', "Your employee profile has been created by the administrator.", 'info');

                header('Location: org_setup.php?tab=tab-directory&msg=Employee+created+successfully.&type=success');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch active departments for select element
$departments = $db->query("SELECT * FROM departments WHERE status = 'Active'")->fetchAll();
?>

<section class="app-view">
    <div class="card-glow" style="max-width: 600px; margin: 40px auto; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 32px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; border-bottom:1px solid var(--border-color); padding-bottom:12px;">
            <h3 style="margin:0; font-family: var(--font-outfit); font-size:1.3rem;">Create Employee</h3>
            <a href="org_setup.php?tab=tab-directory" class="text-link" style="font-size:0.9rem;">← Back to directory</a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.15); color: #f87171; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 16px;">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="create_employee.php" method="POST">
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="emp-name" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Full Name</label>
                <input type="text" name="name" id="emp-name" required placeholder="e.g. John Doe" class="form-control" style="width:100%;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="emp-email" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Email Address</label>
                <input type="email" name="email" id="emp-email" required placeholder="e.g. john.doe@example.com" class="form-control" style="width:100%;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="emp-password" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Password</label>
                <input type="password" name="password" id="emp-password" required placeholder="Minimum 6 characters" class="form-control" style="width:100%;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="emp-dept" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Assigned Department</label>
                <select name="department_id" id="emp-dept" class="form-control" style="width:100%;">
                    <option value="">Select Department (None/Unassigned)</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="emp-role" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">System Role</label>
                <select name="role" id="emp-role" class="form-control" style="width:100%;">
                    <option value="employee" selected>Employee</option>
                    <option value="dept_head">Department Head</option>
                    <option value="asset_manager">Asset Manager</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="emp-status" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Account Status</label>
                <select name="status" id="emp-status" class="form-control" style="width:100%;">
                    <option value="Active" selected>Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <div style="display:flex; gap:12px; justify-content:flex-end; border-top:1px solid var(--border-color); padding-top:20px;">
                <a href="org_setup.php?tab=tab-directory" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Employee</button>
            </div>
        </form>
    </div>
</section>

<?php require_once 'footer.php'; ?>
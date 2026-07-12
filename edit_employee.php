<?php
require_once 'header.php';

// Guard: Admin only
if ($user_role !== 'admin') {
    header('Location: org_setup.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

// Fetch Employee Record
$stmtEmp = $db->prepare("SELECT * FROM employees WHERE id = ?");
$stmtEmp->execute([$id]);
$emp = $stmtEmp->fetch();

if (!$emp) {
    header('Location: org_setup.php?tab=tab-directory&msg=Employee+not+found.&type=error');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'employee';
    $department_id = $_POST['department_id'] ?? '';
    $status = $_POST['status'] ?? 'Active';

    try {
        $stmt = $db->prepare("UPDATE employees SET role = ?, department_id = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $role,
            $department_id !== '' ? (int)$department_id : null,
            $status,
            $id
        ]);

        log_activity($db, $user_id, 'Edit Employee', "Updated employee role/assignment: " . $emp['name']);
        create_notification($db, $id, 'Role/Department Updated', "Your system profile was updated by Admin.", 'info');

        header('Location: org_setup.php?tab=tab-directory&msg=Employee+updated+successfully.&type=success');
        exit;
    } catch (Exception $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Fetch active departments
$departments = $db->query("SELECT * FROM departments WHERE status = 'Active'")->fetchAll();
?>

<section class="app-view">
    <div class="card-glow" style="max-width: 600px; margin: 40px auto; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 32px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; border-bottom:1px solid var(--border-color); padding-bottom:12px;">
            <h3 style="margin:0; font-family: var(--font-outfit); font-size:1.3rem;">Promote / Edit Employee</h3>
            <a href="org_setup.php?tab=tab-directory" class="text-link" style="font-size:0.9rem;">← Back to directory</a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.15); color: #f87171; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 16px;">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="edit_employee.php?id=<?php echo $id; ?>" method="POST">
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Employee Name</label>
                <input type="text" readonly value="<?php echo htmlspecialchars($emp['name']); ?>" class="form-control" style="width:100%; opacity: 0.65; background: rgba(255,255,255,0.02) !important;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Email Address</label>
                <input type="text" readonly value="<?php echo htmlspecialchars($emp['email']); ?>" class="form-control" style="width:100%; opacity: 0.65; background: rgba(255,255,255,0.02) !important;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="promote-role" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">System Privilege Role</label>
                <select name="role" id="promote-role" required class="form-control" style="width:100%;">
                    <option value="admin" <?php echo $emp['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="asset_manager" <?php echo $emp['role'] === 'asset_manager' ? 'selected' : ''; ?>>Asset Manager</option>
                    <option value="dept_head" <?php echo $emp['role'] === 'dept_head' ? 'selected' : ''; ?>>Department Head</option>
                    <option value="employee" <?php echo $emp['role'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="promote-dept" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Assigned Department</label>
                <select name="department_id" id="promote-dept" class="form-control" style="width:100%;">
                    <option value="">Select Department (None/Unassigned)</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo $emp['department_id'] == $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="promote-status" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Operational Status</label>
                <select name="status" id="promote-status" class="form-control" style="width:100%;">
                    <option value="Active" <?php echo $emp['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $emp['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div style="display:flex; gap:12px; justify-content:flex-end; border-top:1px solid var(--border-color); padding-top:20px;">
                <a href="org_setup.php?tab=tab-directory" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Permissions</button>
            </div>
        </form>
    </div>
</section>

<?php require_once 'footer.php'; ?>

<?php
require_once 'header.php';

// Guard: Admin only
if ($user_role !== 'admin') {
    header('Location: org_setup.php');
    exit;
}

$error = '';
$success = '';

// Handle POST Form Submission natively
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $parent_id = $_POST['parent_id'] ?? '';
    $status = $_POST['status'] ?? 'Active';

    if ($name === '') {
        $error = 'Department Name is required.';
    } else {
        try {
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM departments WHERE name = ?");
            $stmtCheck->execute([$name]);
            if ($stmtCheck->fetchColumn() > 0) {
                $error = "A department named '$name' already exists.";
            } else {
                $stmt = $db->prepare("INSERT INTO departments (name, parent_id, status) VALUES (?, ?, ?)");
                $stmt->execute([
                    $name,
                    $parent_id !== '' ? (int)$parent_id : null,
                    $status
                ]);
                
                log_activity($db, $user_id, 'Create Department', "Created department: $name");
                create_notification($db, null, 'New Department Created', "Department '$name' was created in Organization Setup.", 'info');
                
                // Redirect back to org setup tab A with success msg
                header('Location: org_setup.php?tab=tab-departments&msg=Department+created+successfully.&type=success');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch potential parents
$departments = $db->query("SELECT * FROM departments WHERE status = 'Active'")->fetchAll();
?>

<section class="app-view">
    <div class="card-glow" style="max-width: 600px; margin: 40px auto; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 32px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; border-bottom:1px solid var(--border-color); padding-bottom:12px;">
            <h3 style="margin:0; font-family: var(--font-outfit); font-size:1.3rem;">Create Department</h3>
            <a href="org_setup.php?tab=tab-departments" class="text-link" style="font-size:0.9rem;">← Back to list</a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.15); color: #f87171; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 16px;">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="create_dept.php" method="POST">
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="dept-name" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Department Name</label>
                <input type="text" name="name" id="dept-name" required placeholder="e.g. Sales & Marketing" class="form-control" style="width:100%;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="dept-parent" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Parent Department (Hierarchy)</label>
                <select name="parent_id" id="dept-parent" class="form-control" style="width:100%;">
                    <option value="">None (Top Level)</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="dept-status" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Operational Status</label>
                <select name="status" id="dept-status" class="form-control" style="width:100%;">
                    <option value="Active" selected>Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <div style="display:flex; gap:12px; justify-content:flex-end; border-top:1px solid var(--border-color); padding-top:20px;">
                <a href="org_setup.php?tab=tab-departments" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Department</button>
            </div>
        </form>
    </div>
</section>

<?php require_once 'footer.php'; ?>

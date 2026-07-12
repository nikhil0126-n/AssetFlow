<?php
require_once 'header.php';

// Guard: Admin only
if ($user_role !== 'admin') {
    header('Location: org_setup.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

// Fetch department record
$stmtDept = $db->prepare("SELECT * FROM departments WHERE id = ?");
$stmtDept->execute([$id]);
$dept = $stmtDept->fetch();

if (!$dept) {
    header('Location: org_setup.php?tab=tab-departments&msg=Department+not+found.&type=error');
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $parent_id = $_POST['parent_id'] ?? '';
    $head_id = $_POST['head_id'] ?? '';
    $status = $_POST['status'] ?? 'Active';

    if ($name === '') {
        $error = 'Department Name is required.';
    } else {
        try {
            // Check self parenting
            if ($parent_id !== '' && (int)$parent_id === $id) {
                $error = 'A department cannot be its own parent.';
            } else {
                $stmt = $db->prepare("UPDATE departments SET name = ?, parent_id = ?, head_id = ?, status = ? WHERE id = ?");
                $stmt->execute([
                    $name,
                    $parent_id !== '' ? (int)$parent_id : null,
                    $head_id !== '' ? (int)$head_id : null,
                    $status,
                    $id
                ]);

                log_activity($db, $user_id, 'Edit Department', "Updated department: $name");
                
                header('Location: org_setup.php?tab=tab-departments&msg=Department+updated+successfully.&type=success');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch potential parent departments (excluding self)
$stmtParents = $db->prepare("SELECT * FROM departments WHERE id != ? AND status = 'Active'");
$stmtParents->execute([$id]);
$departments = $stmtParents->fetchAll();

// Fetch potential heads (active employees)
$employees = $db->query("SELECT id, name, email FROM employees WHERE status = 'Active'")->fetchAll();
?>

<section class="app-view">
    <div class="card-glow" style="max-width: 600px; margin: 40px auto; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 32px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; border-bottom:1px solid var(--border-color); padding-bottom:12px;">
            <h3 style="margin:0; font-family: var(--font-outfit); font-size:1.3rem;">Edit Department</h3>
            <a href="org_setup.php?tab=tab-departments" class="text-link" style="font-size:0.9rem;">← Back to list</a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.15); color: #f87171; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 16px;">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="edit_dept.php?id=<?php echo $id; ?>" method="POST">
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="dept-name" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Department Name</label>
                <input type="text" name="name" id="dept-name" required value="<?php echo htmlspecialchars($dept['name']); ?>" class="form-control" style="width:100%;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="dept-parent" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Parent Department</label>
                <select name="parent_id" id="dept-parent" class="form-control" style="width:100%;">
                    <option value="">None (Top Level)</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo $dept['parent_id'] == $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="dept-head" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Department Head</label>
                <select name="head_id" id="dept-head" class="form-control" style="width:100%;">
                    <option value="">None (Unassigned)</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php echo $dept['head_id'] == $emp['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['name'] . ' (' . $emp['email'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="dept-status" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Operational Status</label>
                <select name="status" id="dept-status" class="form-control" style="width:100%;">
                    <option value="Active" <?php echo $dept['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $dept['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div style="display:flex; gap:12px; justify-content:flex-end; border-top:1px solid var(--border-color); padding-top:20px;">
                <a href="org_setup.php?tab=tab-departments" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</section>

<?php require_once 'footer.php'; ?>

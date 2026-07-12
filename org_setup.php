<?php
require_once 'header.php';

// Auth Guard - Admin/Manager only
if ($user_role !== 'admin' && $user_role !== 'asset_manager') {
    header('Location: dashboard.php');
    exit;
}

// Fetch departments
$departments = $db->query("
    SELECT d.*, e.name as head_name, p.name as parent_name 
    FROM departments d 
    LEFT JOIN employees e ON d.head_id = e.id
    LEFT JOIN departments p ON d.parent_id = p.id
")->fetchAll();

// Fetch categories
$categories = $db->query("SELECT * FROM categories")->fetchAll();

// Fetch employees
$employees = $db->query("
    SELECT e.id, e.name, e.email, e.role, e.status, d.name as department_name, e.department_id 
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.id
")->fetchAll();
?>

<section id="view-org-setup" class="app-view">
    <div class="tab-container">
        <div class="tab-headers">
            <button class="tab-btn active" data-tab="tab-departments">Tab A - Department Management</button>
            <button class="tab-btn" data-tab="tab-categories">Tab B - Category Management</button>
            <button class="tab-btn" data-tab="tab-directory">Tab C - Employee Directory</button>
        </div>
        
        <div class="tab-contents">
            <!-- Tab A: Department Management -->
            <div id="tab-departments" class="tab-pane active">
                <div class="pane-header">
                    <h3>Departments</h3>
                    <?php if ($user_role === 'admin'): ?>
                        <button class="btn btn-primary btn-sm" id="btn-add-dept">Create Department</button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Department Name</th>
                                <th>Department Head</th>
                                <th>Parent Department</th>
                                <th>Status</th>
                                <?php if ($user_role === 'admin'): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($dept['head_name'] ?? 'None'); ?></td>
                                    <td><?php echo htmlspecialchars($dept['parent_name'] ?? 'None'); ?></td>
                                    <td><span class="status-pill <?php echo $dept['status'] === 'Active' ? 'status-available' : 'status-retired'; ?>"><?php echo $dept['status']; ?></span></td>
                                    <?php if ($user_role === 'admin'): ?>
                                        <td>
                                            <button class="btn btn-secondary btn-sm btn-edit-dept" data-id="<?php echo $dept['id']; ?>" data-name="<?php echo htmlspecialchars($dept['name']); ?>" data-parent="<?php echo $dept['parent_id'] ?? ''; ?>" data-head="<?php echo $dept['head_id'] ?? ''; ?>" data-status="<?php echo $dept['status']; ?>">Edit</button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab B: Asset Category Management -->
            <div id="tab-categories" class="tab-pane">
                <div class="pane-header">
                    <h3>Asset Categories</h3>
                    <?php if ($user_role === 'admin'): ?>
                        <button class="btn btn-primary btn-sm" id="btn-add-category">Create Category</button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Category-Specific Fields</th>
                                <?php if ($user_role === 'admin'): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <?php 
                                $fieldsStr = 'None';
                                try {
                                    $fields = json_decode($cat['custom_fields'] ?? '[]', true);
                                    if (!empty($fields)) {
                                        $fieldsStr = implode(', ', array_map(function($f) {
                                            return '<code>' . htmlspecialchars($f['name']) . ' (' . htmlspecialchars($f['type']) . ')</code>';
                                        }, $fields));
                                    }
                                } catch (Exception $e) {}
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                    <td><?php echo $fieldsStr; ?></td>
                                    <?php if ($user_role === 'admin'): ?>
                                        <td>
                                            <button class="btn btn-secondary btn-sm btn-edit-cat" data-id="<?php echo $cat['id']; ?>" data-name="<?php echo htmlspecialchars($cat['name']); ?>" data-fields='<?php echo htmlspecialchars($cat['custom_fields'] ?? '[]'); ?>'>Edit</button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab C: Employee Directory -->
            <div id="tab-directory" class="tab-pane">
                <div class="pane-header">
                    <h3>Employee Directory</h3>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Status</th>
                                <?php if ($user_role === 'admin'): ?>
                                    <th>Promotion Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($emp['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['department_name'] ?? 'Unassigned'); ?></td>
                                    <td><span class="badge badge-role-<?php echo str_replace('_', '-', $emp['role']); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $emp['role']))); ?></span></td>
                                    <td><span class="status-pill <?php echo $emp['status'] === 'Active' ? 'status-available' : 'status-retired'; ?>"><?php echo $emp['status']; ?></span></td>
                                    <?php if ($user_role === 'admin'): ?>
                                        <td>
                                            <button class="btn btn-secondary btn-sm btn-promote-emp" data-id="<?php echo $emp['id']; ?>" data-name="<?php echo htmlspecialchars($emp['name']); ?>" data-role="<?php echo $emp['role']; ?>" data-dept="<?php echo $emp['department_id'] ?? ''; ?>" data-status="<?php echo $emp['status']; ?>">Promote/Edit</button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Include Modal Structures in footer, handled by footer.php -->
<?php require_once 'footer.php'; ?>

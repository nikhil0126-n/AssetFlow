<?php
require_once 'header.php';

// Fetch department details
$dept_name = 'Unassigned';
$manager_name = 'None';
if ($user_dept) {
    $stmtDept = $db->prepare("
        SELECT d.name, e.name as head_name 
        FROM departments d 
        LEFT JOIN employees e ON d.head_id = e.id 
        WHERE d.id = ?
    ");
    $stmtDept->execute([$user_dept]);
    $dept = $stmtDept->fetch();
    if ($dept) {
        $dept_name = $dept['name'];
        $manager_name = $dept['head_name'] ?? 'Unassigned';
    }
}

// Fetch currently allocated assets count
$stmtCount = $db->prepare("SELECT COUNT(*) FROM allocations WHERE employee_id = ? AND status = 'Active'");
$stmtCount->execute([$user_id]);
$allocated_count = $stmtCount->fetchColumn();

// Fetch list of currently allocated assets
$stmtAssets = $db->prepare("
    SELECT a.*, c.name as category_name, al.allocation_date, al.expected_return_date 
    FROM allocations al
    JOIN assets a ON al.asset_id = a.id
    JOIN categories c ON a.category_id = c.id
    WHERE al.employee_id = ? AND al.status = 'Active'
    ORDER BY al.allocation_date DESC
");
$stmtAssets->execute([$user_id]);
$holdings = $stmtAssets->fetchAll();

// Fetch user's recent login activity logs
$stmtLogs = $db->prepare("
    SELECT * FROM activity_logs 
    WHERE employee_id = ? 
    ORDER BY created_at DESC 
    LIMIT 8
");
$stmtLogs->execute([$user_id]);
$user_logs = $stmtLogs->fetchAll();
?>

<section id="view-profile" class="app-view">
    <div style="display: grid; grid-template-columns: 350px 1fr; gap: 24px; align-items: start;">
        <!-- Employee Profile Card -->
        <div class="card-glow" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 24px; text-align: center;">
            <div class="avatar" style="width: 80px; height: 80px; font-size: 2.2rem; margin: 0 auto 16px auto; box-shadow: 0 0 15px rgba(99, 102, 241, 0.4);">
                <?php echo substr(htmlspecialchars($user_name), 0, 1); ?>
            </div>
            <h3 style="margin: 0; font-family: var(--font-outfit); font-size: 1.4rem; font-weight: 700;"><?php echo htmlspecialchars($user_name); ?></h3>
            <span class="badge badge-role-<?php echo str_replace('_', '-', $user_role); ?>" style="margin-top: 6px; padding: 4px 12px; font-size: 0.75rem;"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user_role))); ?></span>
            
            <div style="text-align: left; margin-top: 24px; border-top: 1px solid var(--border-color); padding-top: 16px; font-size: 0.88rem; display: flex; flex-direction: column; gap: 12px;">
                <div><span style="color: var(--text-muted); display: block;">Email Address</span><strong><?php echo htmlspecialchars($_SESSION['email']); ?></strong></div>
                <div><span style="color: var(--text-muted); display: block;">Department</span><strong><?php echo htmlspecialchars($dept_name); ?></strong></div>
                <div><span style="color: var(--text-muted); display: block;">Line Manager</span><strong><?php echo htmlspecialchars($manager_name); ?></strong></div>
                <div><span style="color: var(--text-muted); display: block;">Active Holdings</span><strong class="text-success"><?php echo $allocated_count; ?> assets held</strong></div>
            </div>
        </div>

        <!-- Right Side details -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- Current Holdings Table -->
            <div class="card-glow" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 24px;">
                <h3 style="margin-top:0; font-family: var(--font-outfit); font-size: 1.15rem; margin-bottom:16px;">My Active Assets</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Asset Tag</th>
                                <th>Asset Name</th>
                                <th>Category</th>
                                <th>Allocated Date</th>
                                <th>Expected Return</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($holdings)): ?>
                                <tr><td colspan="6" class="empty-cell">You currently hold no company assets.</td></tr>
                            <?php else: ?>
                                <?php foreach ($holdings as $asset): ?>
                                    <tr>
                                        <td><span class="asset-tag"><?php echo htmlspecialchars($asset['tag']); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($asset['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($asset['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($asset['allocation_date'], 0, 10)); ?></td>
                                        <td><?php echo htmlspecialchars($asset['expected_return_date'] ?? 'Flexible'); ?></td>
                                        <td>
                                            <button class="btn btn-secondary btn-sm btn-open-detail" data-id="<?php echo $asset['id']; ?>">Specs/Timeline</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Login / Action Log History -->
            <div class="card-glow" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 24px;">
                <h3 style="margin-top:0; font-family: var(--font-outfit); font-size: 1.15rem; margin-bottom:16px;">My Recent Activities</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Action Event</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($user_logs)): ?>
                                <tr><td colspan="3" class="empty-cell">No activities recorded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($user_logs as $log): ?>
                                    <tr>
                                        <td style="font-size:0.8rem; color:var(--text-secondary); width:150px;"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                        <td><span class="badge"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                        <td style="font-size:0.82rem; color:var(--text-secondary);"><?php echo htmlspecialchars($log['details']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>

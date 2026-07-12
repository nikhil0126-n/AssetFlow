<?php
require_once 'header.php';

// Build Query with strict Role-based filter
$sql = "SELECT mr.*, a.tag, a.name as asset_name, a.status as asset_status, e.name as reporter_name 
        FROM maintenance_requests mr
        JOIN assets a ON mr.asset_id = a.id
        JOIN employees e ON mr.reported_by = e.id";

$params = [];

if ($user_role === 'employee') {
    // Employees only see their own tickets
    $sql .= " WHERE mr.reported_by = ?";
    $params[] = $user_id;
} else if ($user_role === 'dept_head' && $user_dept) {
    // Dept heads see their own and their department's tickets
    $sql .= " WHERE mr.reported_by = ? OR e.department_id = ?";
    $params[] = $user_id;
    $params[] = $user_dept;
}

$sql .= " ORDER BY mr.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();
?>

<section id="view-maintenance" class="app-view">
    <div class="pane-header">
        <h3>Maintenance & Repairs Log</h3>
        <button class="btn btn-primary btn-sm" id="btn-raise-maintenance-modal">Raise Ticket</button>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Asset</th>
                    <th>Reported By</th>
                    <th>Description</th>
                    <th>Priority</th>
                    <th>Technician</th>
                    <th>Notes</th>
                    <th>Status</th>
                    <th>Work Management</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="8" class="empty-cell">No maintenance tickets logged in system.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                        <tr>
                            <td><span class="asset-tag"><?php echo htmlspecialchars($req['tag']); ?></span> <strong><?php echo htmlspecialchars($req['asset_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($req['reporter_name']); ?></td>
                            <td><?php echo htmlspecialchars($req['description']); ?></td>
                            <td><span class="badge <?php echo $req['priority'] === 'Critical' || $req['priority'] === 'High' ? 'badge-role-admin' : ''; ?>"><?php echo htmlspecialchars($req['priority']); ?></span></td>
                            <td><?php echo htmlspecialchars($req['assigned_technician'] ?? 'Unassigned'); ?></td>
                            <td><?php echo htmlspecialchars($req['notes'] ?? '—'); ?></td>
                            <td>
                                <span class="status-pill status-<?php echo $req['status'] === 'Pending' ? 'reserved' : ($req['status'] === 'Resolved' ? 'available' : 'maint'); ?>">
                                    <?php echo htmlspecialchars($req['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($req['status'] === 'Pending'): ?>
                                    <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                                        <button class="btn btn-success btn-sm btn-approve-maint" data-id="<?php echo $req['id']; ?>">Approve</button>
                                        <button class="btn btn-danger btn-sm btn-reject-maint" data-id="<?php echo $req['id']; ?>" style="margin-left:4px;">Reject</button>
                                    <?php else: ?>
                                        <span class="text-warning">Awaiting Approval</span>
                                    <?php endif; ?>
                                <?php elseif ($req['status'] !== 'Resolved' && $req['status'] !== 'Rejected'): ?>
                                    <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                                        <button class="btn btn-primary btn-sm btn-update-maint" data-id="<?php echo $req['id']; ?>" data-status="<?php echo $req['status']; ?>" data-tech="<?php echo htmlspecialchars($req['assigned_technician'] ?? ''); ?>" data-notes="<?php echo htmlspecialchars($req['notes'] ?? ''); ?>">Update status</button>
                                    <?php else: ?>
                                        <span class="text-success">In Repair: <?php echo htmlspecialchars($req['status']); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Archived Task</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once 'footer.php'; ?>

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
// Initialize column arrays
$columns = [
    'Pending' => [],
    'Approved' => [],
    'Technician Assigned' => [],
    'In Progress' => [],
    'Resolved' => []
];

// Group requests by status
foreach ($requests as $req) {
    $status = $req['status'];
    if ($status === 'Rejected') {
        $status = 'Resolved';
    }
    if (isset($columns[$status])) {
        $columns[$status][] = $req;
    }
}

function get_priority_class($priority) {
    if ($priority === 'Critical') return 'status-lost';
    if ($priority === 'High') return 'status-maint';
    if ($priority === 'Medium') return 'status-reserved';
    return 'status-available';
}
?>

<section id="view-maintenance" class="app-view">
    <div class="pane-header" style="margin-bottom: 20px;">
        <h3>Maintenance & Repairs Log (Kanban Board)</h3>
        <button class="btn btn-primary btn-sm" id="btn-raise-maintenance-modal">Raise Ticket</button>
    </div>

    <div class="kanban-board">
        <?php foreach ($columns as $colName => $items): ?>
            <div class="kanban-column">
                <div class="kanban-column-header">
                    <h4><?php echo htmlspecialchars($colName); ?></h4>
                    <span class="kanban-column-count"><?php echo count($items); ?></span>
                </div>
                
                <?php if (empty($items)): ?>
                    <div style="font-size: 0.78rem; color: var(--text-muted); text-align: center; padding: 30px 0; font-style: italic;">No tickets in this stage.</div>
                <?php else: ?>
                    <?php foreach ($items as $req): ?>
                        <div class="kanban-card">
                            <div class="kanban-card-title">
                                <span class="asset-tag" style="font-size: 0.72rem; padding: 2px 6px;"><?php echo htmlspecialchars($req['tag']); ?></span>
                                <span class="status-pill status-<?php echo get_priority_class($req['priority']); ?>" style="font-size: 0.68rem; padding: 1px 6px;"><?php echo htmlspecialchars($req['priority']); ?></span>
                            </div>
                            <strong style="font-size: 0.85rem; color: var(--text-primary); margin-top: 4px;"><?php echo htmlspecialchars($req['asset_name']); ?></strong>
                            <p class="kanban-card-desc" style="margin: 6px 0;"><?php echo htmlspecialchars($req['description']); ?></p>
                            
                            <div class="kanban-card-meta">
                                <span>Reporter: <strong><?php echo htmlspecialchars($req['reporter_name']); ?></strong></span>
                                <?php if ($req['assigned_technician']): ?>
                                    <span style="font-style: italic;">Tech: <?php echo htmlspecialchars($req['assigned_technician']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($req['notes'] !== '' && $req['notes'] !== null): ?>
                                <div style="font-size: 0.7rem; background: rgba(0,0,0,0.08); border-left: 2px solid var(--border-color); padding: 6px; border-radius: 4px; margin-top: 4px; color: var(--text-secondary);">
                                    <strong>Notes:</strong> <?php echo htmlspecialchars($req['notes']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="kanban-card-actions">
                                <?php if ($req['status'] === 'Pending'): ?>
                                    <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                                        <button class="btn btn-success btn-sm btn-approve-maint" data-id="<?php echo $req['id']; ?>" style="padding: 2px 8px; font-size: 0.7rem;">Approve</button>
                                        <button class="btn btn-danger btn-sm btn-reject-maint" data-id="<?php echo $req['id']; ?>" style="padding: 2px 8px; font-size: 0.7rem;">Reject</button>
                                    <?php else: ?>
                                        <span style="font-size: 0.72rem; color: var(--color-warning); font-style: italic;">Awaiting Review</span>
                                    <?php endif; ?>
                                <?php elseif ($req['status'] === 'Approved'): ?>
                                    <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                                        <button class="btn btn-primary btn-sm btn-update-maint" data-id="<?php echo $req['id']; ?>" data-status="Technician Assigned" data-tech="<?php echo htmlspecialchars($req['assigned_technician'] ?? ''); ?>" data-notes="<?php echo htmlspecialchars($req['notes'] ?? ''); ?>" style="padding: 2px 8px; font-size: 0.7rem;">Assign Tech</button>
                                    <?php else: ?>
                                        <span style="font-size: 0.72rem; color: var(--color-success); font-style: italic;">Approved</span>
                                    <?php endif; ?>
                                <?php elseif ($req['status'] === 'Technician Assigned'): ?>
                                    <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                                        <button class="btn btn-secondary btn-sm btn-update-maint" data-id="<?php echo $req['id']; ?>" data-status="In Progress" data-tech="<?php echo htmlspecialchars($req['assigned_technician'] ?? ''); ?>" data-notes="<?php echo htmlspecialchars($req['notes'] ?? ''); ?>" style="padding: 2px 8px; font-size: 0.7rem; background: var(--color-warning); border-color: var(--color-warning); color: #fff;">Start Work</button>
                                    <?php else: ?>
                                        <span style="font-size: 0.72rem; color: var(--text-muted); font-style: italic;">Assigned to <?php echo htmlspecialchars($req['assigned_technician']); ?></span>
                                    <?php endif; ?>
                                <?php elseif ($req['status'] === 'In Progress'): ?>
                                    <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                                        <button class="btn btn-success btn-sm btn-update-maint" data-id="<?php echo $req['id']; ?>" data-status="Resolved" data-tech="<?php echo htmlspecialchars($req['assigned_technician'] ?? ''); ?>" data-notes="<?php echo htmlspecialchars($req['notes'] ?? ''); ?>" style="padding: 2px 8px; font-size: 0.7rem;">Resolve</button>
                                    <?php else: ?>
                                        <span style="font-size: 0.72rem; color: var(--color-success); font-weight: 600;">In Progress</span>
                                    <?php endif; ?>
                                <?php elseif ($req['status'] === 'Resolved'): ?>
                                    <span style="font-size: 0.72rem; color: var(--color-success); font-weight: 600;">✓ Resolved</span>
                                <?php elseif ($req['status'] === 'Rejected'): ?>
                                    <span style="font-size: 0.72rem; color: var(--color-danger); font-weight: 600;">✗ Rejected</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once 'footer.php'; ?>

<?php
require_once 'header.php';

// Fetch KPIs
$kpis = [];
$kpis['assets_available'] = $db->query("SELECT COUNT(*) FROM assets WHERE status = 'Available'")->fetchColumn();
$kpis['assets_allocated'] = $db->query("SELECT COUNT(*) FROM assets WHERE status = 'Allocated'")->fetchColumn();
$kpis['maintenance_today'] = $db->query("SELECT COUNT(*) FROM assets WHERE status = 'Under Maintenance'")->fetchColumn();
$kpis['active_bookings'] = $db->query("SELECT COUNT(*) FROM bookings WHERE booking_date = CURRENT_DATE() AND status != 'Cancelled'")->fetchColumn();

if ($user_role === 'admin' || $user_role === 'asset_manager') {
    $kpis['pending_transfers'] = $db->query("SELECT COUNT(*) FROM transfers WHERE status = 'Pending'")->fetchColumn();
} else if ($user_role === 'dept_head' && $user_dept) {
    $kpis['pending_transfers'] = $db->query("SELECT COUNT(*) FROM transfers WHERE status = 'Pending' AND to_department_id = " . (int)$user_dept)->fetchColumn();
} else {
    $kpis['pending_transfers'] = $db->query("SELECT COUNT(*) FROM transfers WHERE status = 'Pending' AND requested_by = " . (int)$user_id)->fetchColumn();
}

$kpis['overdue_returns'] = $db->query("SELECT COUNT(*) FROM allocations WHERE status = 'Overdue' OR (status = 'Active' AND expected_return_date < CURRENT_DATE())")->fetchColumn();

// Fetch Overdue Items
$stmtOverdue = $db->query("
    SELECT a.id as asset_id, a.tag, a.name as asset_name, e.name as holder_name, e.email as holder_email, al.expected_return_date, al.id as allocation_id 
    FROM allocations al
    JOIN assets a ON al.asset_id = a.id
    LEFT JOIN employees e ON al.employee_id = e.id
    WHERE al.status = 'Overdue' OR (al.status = 'Active' AND al.expected_return_date < CURRENT_DATE())
    ORDER BY al.expected_return_date ASC
");
$overdue_items = $stmtOverdue->fetchAll();

// Fetch Pending Transfers for Approvals Queue
$pending_transfers = [];
if ($user_role === 'admin' || $user_role === 'asset_manager') {
    $pending_transfers = $db->query("
        SELECT t.id, a.tag, a.name as asset_name, e_from.name as from_employee, e_to.name as to_employee, d_to.name as to_department, t.request_date
        FROM transfers t
        JOIN assets a ON t.asset_id = a.id
        LEFT JOIN employees e_from ON t.from_employee_id = e_from.id
        LEFT JOIN employees e_to ON t.to_employee_id = e_to.id
        LEFT JOIN departments d_to ON t.to_department_id = d_to.id
        WHERE t.status = 'Pending'
    ")->fetchAll();
} else if ($user_role === 'dept_head' && $user_dept) {
    $pending_transfers = $db->query("
        SELECT t.id, a.tag, a.name as asset_name, e_from.name as from_employee, e_to.name as to_employee, d_to.name as to_department, t.request_date
        FROM transfers t
        JOIN assets a ON t.asset_id = a.id
        LEFT JOIN employees e_from ON t.from_employee_id = e_from.id
        LEFT JOIN employees e_to ON t.to_employee_id = e_to.id
        LEFT JOIN departments d_to ON t.to_department_id = d_to.id
        WHERE t.status = 'Pending' AND (t.to_department_id = " . (int)$user_dept . ")
    ")->fetchAll();
}

// Fetch Maintenance Requests for Approvals Queue
$maintenance_actions = [];
if ($user_role === 'admin' || $user_role === 'asset_manager') {
    $maintenance_actions = $db->query("
        SELECT m.id, a.tag, a.name as asset_name, m.priority, m.description, m.created_at
        FROM maintenance_requests m
        JOIN assets a ON m.asset_id = a.id
        WHERE m.status = 'Pending'
    ")->fetchAll();
} else {
    $maintenance_actions = $db->query("
        SELECT m.id, a.tag, a.name as asset_name, m.priority, m.status, m.created_at
        FROM maintenance_requests m
        JOIN assets a ON m.asset_id = a.id
        WHERE m.reported_by = " . (int)$user_id . " AND m.status != 'Resolved'
    ")->fetchAll();
}

// Helper mapped CSS pill colors
function get_pill_class($status) {
    $map = [
        'Available' => 'available', 'Allocated' => 'allocated', 'Reserved' => 'reserved',
        'Under Maintenance' => 'maint', 'Lost' => 'lost', 'Retired' => 'retired', 'Disposed' => 'disposed'
    ];
    return $map[$status] ?? 'retired';
}
?>

<section id="view-dashboard" class="app-view">
    <!-- KPI Cards Grid -->
    <div class="kpi-grid">
        <div class="kpi-card" onclick="window.location.href='assets.php?status=Available'">
            <div class="kpi-icon icon-available">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="kpi-details">
                <span class="kpi-title">Assets Available</span>
                <h2 class="kpi-value"><?php echo $kpis['assets_available']; ?></h2>
            </div>
        </div>
        <div class="kpi-card" onclick="window.location.href='assets.php?status=Allocated'">
            <div class="kpi-icon icon-allocated">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>
            </div>
            <div class="kpi-details">
                <span class="kpi-title">Assets Allocated</span>
                <h2 class="kpi-value"><?php echo $kpis['assets_allocated']; ?></h2>
            </div>
        </div>
        <div class="kpi-card" onclick="window.location.href='maintenance.php'">
            <div class="kpi-icon icon-maint">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
            </div>
            <div class="kpi-details">
                <span class="kpi-title">Maintenance Today</span>
                <h2 class="kpi-value"><?php echo $kpis['maintenance_today']; ?></h2>
            </div>
        </div>
        <div class="kpi-card" onclick="window.location.href='bookings.php'">
            <div class="kpi-icon icon-bookings">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
            <div class="kpi-details">
                <span class="kpi-title">Active Bookings</span>
                <h2 class="kpi-value"><?php echo $kpis['active_bookings']; ?></h2>
            </div>
        </div>
        <div class="kpi-card" onclick="window.location.href='allocations.php#subtab-transfers'">
            <div class="kpi-icon icon-transfers">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>
            </div>
            <div class="kpi-details">
                <span class="kpi-title">Pending Transfers</span>
                <h2 class="kpi-value"><?php echo $kpis['pending_transfers']; ?></h2>
            </div>
        </div>
        <div class="kpi-card border-warning-glow" onclick="window.location.href='assets.php?status=Lost'">
            <div class="kpi-icon icon-overdue">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <div class="kpi-details">
                <span class="kpi-title text-warning">Overdue Returns</span>
                <h2 class="kpi-value text-warning"><?php echo $kpis['overdue_returns']; ?></h2>
            </div>
        </div>
    </div>

    <!-- Dashboard Main Split -->
    <div class="dashboard-sections">
        <!-- Panel: Overdue Returns -->
        <div class="dashboard-panel card-glow">
            <div class="panel-header">
                <h3>⚠️ Overdue Returns & Flagged Items</h3>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Holder</th>
                                <th>Due Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($overdue_items)): ?>
                                <tr><td colspan="4" class="empty-cell">✓ No overdue asset returns!</td></tr>
                            <?php else: ?>
                                <?php foreach ($overdue_items as $item): ?>
                                    <tr>
                                        <td>
                                            <span class="asset-tag"><?php echo htmlspecialchars($item['tag']); ?></span> 
                                            <strong><a href="#" class="btn-detail-trigger" data-id="<?php echo $item['asset_id']; ?>" style="text-decoration:none; color:inherit;"><?php echo htmlspecialchars($item['asset_name']); ?></a></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['holder_name'] ?? 'Department'); ?> (<?php echo htmlspecialchars($item['holder_email'] ?? 'N/A'); ?>)</td>
                                        <td class="text-danger"><?php echo htmlspecialchars($item['expected_return_date']); ?></td>
                                        <td>
                                            <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                                                <button class="btn btn-secondary btn-sm btn-return-action" data-alloc-id="<?php echo $item['allocation_id']; ?>">Check-In Return</button>
                                            <?php else: ?>
                                                <span class="status-pill status-lost">Overdue</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Panel: Action Approvals Queue -->
        <div class="dashboard-panel card-glow">
            <div class="panel-header">
                <h3>⚡ Action Approvals Queue</h3>
            </div>
            <div class="panel-body">
                <div class="approvals-queue">
                    <?php 
                    $approvalTicketsCount = 0;

                    // Render transfers pending
                    foreach ($pending_transfers as $ticket) {
                        $approvalTicketsCount++;
                        ?>
                        <div class="approval-ticket">
                            <div class="approval-info">
                                <h5>🔄 Transfer: <?php echo htmlspecialchars($ticket['tag'] . ' (' . $ticket['asset_name'] . ')'); ?></h5>
                                <p>From: <strong><?php echo htmlspecialchars($ticket['from_employee'] ?? 'Storage'); ?></strong> → To: <strong><?php echo htmlspecialchars($ticket['to_employee'] ?? $ticket['to_department']); ?></strong></p>
                            </div>
                            <div class="approval-actions">
                                <button class="btn btn-success btn-sm btn-approve-transfer" data-id="<?php echo $ticket['id']; ?>">Approve</button>
                                <button class="btn btn-danger btn-sm btn-reject-transfer" data-id="<?php echo $ticket['id']; ?>">Reject</button>
                            </div>
                        </div>
                        <?php
                    }

                    // Render maintenance pending
                    foreach ($maintenance_actions as $ticket) {
                        $approvalTicketsCount++;
                        ?>
                        <div class="approval-ticket">
                            <div class="approval-info">
                                <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                                    <h5>🔧 Repair Request: <?php echo htmlspecialchars($ticket['tag'] . ' (' . $ticket['asset_name'] . ')'); ?></h5>
                                    <p><?php echo htmlspecialchars($ticket['description']); ?> | Priority: <strong class="text-danger"><?php echo htmlspecialchars($ticket['priority']); ?></strong></p>
                                <?php else: ?>
                                    <h5>🔧 Repair Status: <?php echo htmlspecialchars($ticket['tag'] . ' (' . $ticket['asset_name'] . ')'); ?></h5>
                                    <p>Priority: <?php echo htmlspecialchars($ticket['priority']); ?> | Current State: <strong class="text-warning"><?php echo htmlspecialchars($ticket['status']); ?></strong></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                                <div class="approval-actions">
                                    <button class="btn btn-success btn-sm btn-approve-maint" data-id="<?php echo $ticket['id']; ?>">Approve</button>
                                    <button class="btn btn-danger btn-sm btn-reject-maint" data-id="<?php echo $ticket['id']; ?>">Reject</button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php
                    }

                    if ($approvalTicketsCount === 0) {
                        echo '<div class="empty-state">✓ No pending approval requests. All caught up!</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>

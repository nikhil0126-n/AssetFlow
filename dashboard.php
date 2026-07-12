<?php
require_once 'header.php';

// Helper to determine status badges
function get_pill_class($status) {
    $map = [
        'Available' => 'available', 'Allocated' => 'allocated', 'Reserved' => 'reserved',
        'Under Maintenance' => 'maint', 'Lost' => 'lost', 'Retired' => 'retired', 'Disposed' => 'disposed'
    ];
    return $map[$status] ?? 'retired';
}

// Auto-update booking statuses based on current date & time
$db->query("
    UPDATE bookings 
    SET status = 'Completed' 
    WHERE status IN ('Upcoming', 'Ongoing') 
    AND (
        booking_date < CURRENT_DATE() 
        OR (booking_date = CURRENT_DATE() AND end_time <= CURRENT_TIME())
    )
");
$db->query("
    UPDATE bookings 
    SET status = 'Ongoing' 
    WHERE status = 'Upcoming' 
    AND booking_date = CURRENT_DATE() 
    AND start_time <= CURRENT_TIME() 
    AND end_time > CURRENT_TIME()
");

if ($user_role === 'admin' || $user_role === 'asset_manager'): 
    // ==========================================
    // ADMIN & ASSET MANAGER DASHBOARD
    // ==========================================
    
    // KPIs
    $kpis = [];
    $kpis['assets_available'] = $db->query("SELECT COUNT(*) FROM assets WHERE status = 'Available'")->fetchColumn();
    $kpis['assets_allocated'] = $db->query("SELECT COUNT(*) FROM assets WHERE status = 'Allocated'")->fetchColumn();
    $kpis['maintenance_today'] = $db->query("SELECT COUNT(*) FROM assets WHERE status = 'Under Maintenance'")->fetchColumn();
    $kpis['active_bookings'] = $db->query("SELECT COUNT(*) FROM bookings WHERE booking_date = CURRENT_DATE() AND status != 'Cancelled'")->fetchColumn();
    $kpis['total_assets'] = $db->query("SELECT COUNT(*) FROM assets")->fetchColumn();
    $kpis['overdue_returns'] = $db->query("SELECT COUNT(*) FROM allocations WHERE status = 'Overdue' OR (status = 'Active' AND expected_return_date < CURRENT_DATE())")->fetchColumn();

    // Overdue returns organization-wide
    $stmtOverdue = $db->query("
        SELECT a.id as asset_id, a.tag, a.name as asset_name, e.name as holder_name, e.email as holder_email, al.expected_return_date, al.id as allocation_id 
        FROM allocations al
        JOIN assets a ON al.asset_id = a.id
        LEFT JOIN employees e ON al.employee_id = e.id
        WHERE al.status = 'Overdue' OR (al.status = 'Active' AND al.expected_return_date < CURRENT_DATE())
        ORDER BY al.expected_return_date ASC
    ");
    $overdue_items = $stmtOverdue->fetchAll();

    // Pending Transfers Organisation-wide
    $pending_transfers = $db->query("
        SELECT t.id, a.tag, a.name as asset_name, e_from.name as from_employee, e_to.name as to_employee, d_to.name as to_department, t.request_date
        FROM transfers t
        JOIN assets a ON t.asset_id = a.id
        LEFT JOIN employees e_from ON t.from_employee_id = e_from.id
        LEFT JOIN employees e_to ON t.to_employee_id = e_to.id
        LEFT JOIN departments d_to ON t.to_department_id = d_to.id
        WHERE t.status = 'Pending'
    ")->fetchAll();

    // Pending Maintenance Tickets
    $maintenance_actions = $db->query("
        SELECT m.id, a.tag, a.name as asset_name, m.priority, m.description, m.created_at
        FROM maintenance_requests m
        JOIN assets a ON m.asset_id = a.id
        WHERE m.status = 'Pending'
    ")->fetchAll();

    // Fetch recent system-wide activity logs
    $recent_activity = $db->query("
        SELECT al.*, e.name as employee_name
        FROM activity_logs al
        LEFT JOIN employees e ON al.employee_id = e.id
        ORDER BY al.created_at DESC
        LIMIT 6
    ")->fetchAll();
    ?>

    <section id="view-dashboard-admin" class="app-view">
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
            <div class="kpi-card" onclick="window.location.href='assets.php'">
                <div class="kpi-icon icon-transfers">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line></svg>
                </div>
                <div class="kpi-details">
                    <span class="kpi-title">Total Assets</span>
                    <h2 class="kpi-value"><?php echo $kpis['total_assets']; ?></h2>
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

        <div class="dashboard-sections">
            <!-- Overdue Panel -->
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
                                                <button class="btn btn-secondary btn-sm btn-return-action" data-alloc-id="<?php echo $item['allocation_id']; ?>">Check-In Return</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Action Queue -->
            <div class="dashboard-panel card-glow">
                <div class="panel-header">
                    <h3>⚡ Action Approvals Queue</h3>
                </div>
                <div class="panel-body">
                    <div class="approvals-queue">
                        <?php 
                        $approvalTicketsCount = 0;
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
                        foreach ($maintenance_actions as $ticket) {
                            $approvalTicketsCount++;
                            ?>
                            <div class="approval-ticket">
                                <div class="approval-info">
                                    <h5>🔧 Repair Request: <?php echo htmlspecialchars($ticket['tag'] . ' (' . $ticket['asset_name'] . ')'); ?></h5>
                                    <p><?php echo htmlspecialchars($ticket['description']); ?> | Priority: <strong class="text-danger"><?php echo htmlspecialchars($ticket['priority']); ?></strong></p>
                                </div>
                                <div class="approval-actions">
                                    <button class="btn btn-success btn-sm btn-approve-maint" data-id="<?php echo $ticket['id']; ?>">Approve</button>
                                    <button class="btn btn-danger btn-sm btn-reject-maint" data-id="<?php echo $ticket['id']; ?>">Reject</button>
                                </div>
                            </div>
                            <?php
                        }
                        if ($approvalTicketsCount === 0) {
                            echo '<div class="empty-state">✓ No pending approvals organization-wide.</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="dashboard-panel card-glow" style="margin-top: 24px;">
            <div class="panel-header">
                <h3>Recent System Activity</h3>
            </div>
            <div class="panel-body">
                <div class="activity-feed-list" style="display: flex; flex-direction: column; gap: 12px;">
                    <?php if (empty($recent_activity)): ?>
                        <div class="empty-state">No recent activity logged.</div>
                    <?php else: ?>
                        <?php foreach ($recent_activity as $act): ?>
                            <div class="activity-feed-item" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                                <div>
                                    <strong style="color: var(--text-primary); font-size: 0.85rem;"><?php echo htmlspecialchars($act['action']); ?></strong>
                                    <p style="color: var(--text-secondary); font-size: 0.78rem; margin: 2px 0 0 0;"><?php echo htmlspecialchars($act['details']); ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">By: <?php echo htmlspecialchars($act['employee_name'] ?? 'System'); ?></span>
                                    <p style="font-size: 0.7rem; color: var(--text-muted); margin: 2px 0 0 0;"><?php echo htmlspecialchars(substr($act['created_at'], 0, 16)); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

<?php elseif ($user_role === 'dept_head'): 
    // ==========================================
    // DEPARTMENT HEAD DASHBOARD
    // ==========================================
    
    // Department stats
    $stmtDeptAlloc = $db->prepare("
        SELECT COUNT(*) FROM allocations al
        JOIN employees e ON al.employee_id = e.id
        WHERE e.department_id = ? AND al.status IN ('Active', 'Overdue')
    ");
    $stmtDeptAlloc->execute([$user_dept]);
    $dept_holdings_count = $stmtDeptAlloc->fetchColumn();

    $stmtDeptBook = $db->prepare("
        SELECT COUNT(*) FROM bookings b
        JOIN employees e ON b.employee_id = e.id
        WHERE e.department_id = ? AND b.booking_date = CURRENT_DATE() AND b.status != 'Cancelled'
    ");
    $stmtDeptBook->execute([$user_dept]);
    $dept_bookings_count = $stmtDeptBook->fetchColumn();

    $stmtDeptOverdue = $db->prepare("
        SELECT COUNT(*) FROM allocations al
        JOIN employees e ON al.employee_id = e.id
        WHERE e.department_id = ? AND (al.status = 'Overdue' OR (al.status = 'Active' AND al.expected_return_date < CURRENT_DATE()))
    ");
    $stmtDeptOverdue->execute([$user_dept]);
    $dept_overdue_count = $stmtDeptOverdue->fetchColumn();

    $stmtDeptPending = $db->prepare("
        SELECT COUNT(*) FROM transfers WHERE status = 'Pending' AND to_department_id = ?
    ");
    $stmtDeptPending->execute([$user_dept]);
    $dept_pending_count = $stmtDeptPending->fetchColumn();

    // Department Assets Holdings list
    $stmtHoldings = $db->prepare("
        SELECT a.id, a.tag, a.name as asset_name, e.name as holder_name, al.expected_return_date, al.status
        FROM allocations al
        JOIN assets a ON al.asset_id = a.id
        JOIN employees e ON al.employee_id = e.id
        WHERE e.department_id = ? AND al.status IN ('Active', 'Overdue')
        ORDER BY al.allocation_date DESC
        LIMIT 6
    ");
    $stmtHoldings->execute([$user_dept]);
    $dept_assets = $stmtHoldings->fetchAll();

    // Department pending approvals
    $stmtDeptTrans = $db->prepare("
        SELECT t.id, a.tag, a.name as asset_name, e_from.name as from_employee, e_to.name as to_employee, t.request_date
        FROM transfers t
        JOIN assets a ON t.asset_id = a.id
        LEFT JOIN employees e_from ON t.from_employee_id = e_from.id
        LEFT JOIN employees e_to ON t.to_employee_id = e_to.id
        WHERE t.status = 'Pending' AND t.to_department_id = ?
    ");
    $stmtDeptTrans->execute([$user_dept]);
    $dept_transfers = $stmtDeptTrans->fetchAll();
    ?>

    <section id="view-dashboard-dept" class="app-view">
        <h2 style="font-family:var(--font-outfit); font-size:1.2rem; margin-bottom:16px; color:var(--text-secondary);">🏢 Department Control Dashboard</h2>
        
        <!-- Department KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card" onclick="window.location.href='assets.php'">
                <div class="kpi-icon icon-allocated">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                </div>
                <div class="kpi-details">
                    <span class="kpi-title">Department Holdings</span>
                    <h2 class="kpi-value"><?php echo $dept_holdings_count; ?></h2>
                </div>
            </div>
            <div class="kpi-card" onclick="window.location.href='bookings.php'">
                <div class="kpi-icon icon-bookings">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line></svg>
                </div>
                <div class="kpi-details">
                    <span class="kpi-title">Dept Bookings Today</span>
                    <h2 class="kpi-value"><?php echo $dept_bookings_count; ?></h2>
                </div>
            </div>
            <div class="kpi-card" onclick="window.location.href='allocations.php#subtab-transfers'">
                <div class="kpi-icon icon-transfers">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path></svg>
                </div>
                <div class="kpi-details">
                    <span class="kpi-title">Pending Approvals</span>
                    <h2 class="kpi-value"><?php echo $dept_pending_count; ?></h2>
                </div>
            </div>
            <div class="kpi-card border-warning-glow" onclick="window.location.href='allocations.php'">
                <div class="kpi-icon icon-overdue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path></svg>
                </div>
                <div class="kpi-details">
                    <span class="kpi-title text-warning">Dept Overdue Returns</span>
                    <h2 class="kpi-value text-warning"><?php echo $dept_overdue_count; ?></h2>
                </div>
            </div>
        </div>

        <div class="dashboard-sections">
            <!-- Department Holdings -->
            <div class="dashboard-panel card-glow">
                <div class="panel-header">
                    <h3>👥 Recent Staff Holdings</h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Assigned Staff</th>
                                    <th>Expected Return</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dept_assets)): ?>
                                    <tr><td colspan="4" class="empty-cell">No assets currently checked out.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($dept_assets as $item): ?>
                                        <tr>
                                            <td><span class="asset-tag"><?php echo htmlspecialchars($item['tag']); ?></span> <strong><a href="#" class="btn-detail-trigger" data-id="<?php echo $item['id']; ?>" style="text-decoration:none; color:inherit;"><?php echo htmlspecialchars($item['asset_name']); ?></a></strong></td>
                                            <td><?php echo htmlspecialchars($item['holder_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['expected_return_date'] ?? 'Flexible'); ?></td>
                                            <td><span class="status-pill status-<?php echo get_pill_class($item['status']); ?>"><?php echo htmlspecialchars($item['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Dept Approvals -->
            <div class="dashboard-panel card-glow">
                <div class="panel-header">
                    <h3>⚡ Department Approvals Queue</h3>
                </div>
                <div class="panel-body">
                    <div class="approvals-queue">
                        <?php if (empty($dept_transfers)): ?>
                            <div class="empty-state">✓ No pending transfers for your department.</div>
                        <?php else: ?>
                            <?php foreach ($dept_transfers as $ticket): ?>
                                <div class="approval-ticket">
                                    <div class="approval-info">
                                        <h5>🔄 Incoming Transfer: <?php echo htmlspecialchars($ticket['tag'] . ' (' . $ticket['asset_name'] . ')'); ?></h5>
                                        <p>From: <strong><?php echo htmlspecialchars($ticket['from_employee'] ?? 'Storage'); ?></strong> → To: <strong><?php echo htmlspecialchars($ticket['to_employee']); ?></strong></p>
                                    </div>
                                    <div class="approval-actions">
                                        <button class="btn btn-success btn-sm btn-approve-transfer" data-id="<?php echo $ticket['id']; ?>">Approve</button>
                                        <button class="btn btn-danger btn-sm btn-reject-transfer" data-id="<?php echo $ticket['id']; ?>">Reject</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php else: 
    // ==========================================
    // EMPLOYEE DASHBOARD
    // ==========================================
    
    // Personal stats
    $stmtMyAlloc = $db->prepare("SELECT COUNT(*) FROM allocations WHERE employee_id = ? AND status IN ('Active', 'Overdue')");
    $stmtMyAlloc->execute([$user_id]);
    $my_holdings_count = $stmtMyAlloc->fetchColumn();

    $stmtMyBook = $db->prepare("SELECT COUNT(*) FROM bookings WHERE employee_id = ? AND booking_date >= CURRENT_DATE() AND status != 'Cancelled'");
    $stmtMyBook->execute([$user_id]);
    $my_bookings_count = $stmtMyBook->fetchColumn();

    $stmtMyMaint = $db->prepare("SELECT COUNT(*) FROM maintenance_requests WHERE reported_by = ? AND status != 'Resolved'");
    $stmtMyMaint->execute([$user_id]);
    $my_tickets_count = $stmtMyMaint->fetchColumn();

    $stmtMyOverdue = $db->prepare("SELECT COUNT(*) FROM allocations WHERE employee_id = ? AND (status = 'Overdue' OR (status = 'Active' AND expected_return_date < CURRENT_DATE()))");
    $stmtMyOverdue->execute([$user_id]);
    $my_overdue_count = $stmtMyOverdue->fetchColumn();

    // Employee holdings list
    $stmtMyHoldings = $db->prepare("
        SELECT a.id, a.tag, a.name as asset_name, al.allocation_date, al.expected_return_date, al.id as allocation_id
        FROM allocations al
        JOIN assets a ON al.asset_id = a.id
        WHERE al.employee_id = ? AND al.status IN ('Active', 'Overdue')
        ORDER BY al.allocation_date DESC
    ");
    $stmtMyHoldings->execute([$user_id]);
    $my_assets = $stmtMyHoldings->fetchAll();

    // Upcoming Bookings agenda
    $stmtMyAgenda = $db->prepare("
        SELECT b.*, a.name as asset_name, a.location
        FROM bookings b
        JOIN assets a ON b.asset_id = a.id
        WHERE b.employee_id = ? AND b.booking_date >= CURRENT_DATE() AND b.status != 'Cancelled'
        ORDER BY b.booking_date ASC, b.start_time ASC
        LIMIT 5
    ");
    $stmtMyAgenda->execute([$user_id]);
    $my_reservations = $stmtMyAgenda->fetchAll();

    // Active reported maintenance tickets
    $stmtMyTickets = $db->prepare("
        SELECT mr.id, a.tag, a.name as asset_name, mr.priority, mr.status, mr.created_at
        FROM maintenance_requests mr
        JOIN assets a ON mr.asset_id = a.id
        WHERE mr.reported_by = ? AND mr.status != 'Resolved' AND mr.status != 'Rejected'
        ORDER BY mr.created_at DESC
        LIMIT 5
    ");
    $stmtMyTickets->execute([$user_id]);
    $my_repairs = $stmtMyTickets->fetchAll();
    ?>

    <section id="view-dashboard-employee" class="app-view">
        <h2 style="font-family:var(--font-outfit); font-size:1.2rem; margin-bottom:16px; color:var(--text-secondary);">💻 My Personal Workspace</h2>

        <!-- Employee KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card" onclick="window.location.href='profile.php'">
                <div class="kpi-icon icon-allocated">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                </div>
                <div class="kpi-details">
                    <span class="kpi-title">My Checked-out Assets</span>
                    <h2 class="kpi-value"><?php echo $my_holdings_count; ?></h2>
                </div>
            </div>
            <div class="kpi-card" onclick="window.location.href='bookings.php'">
                <div class="kpi-icon icon-bookings">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line></svg>
                </div>
                <div class="kpi-details">
                    <span class="kpi-title">My Reservations</span>
                    <h2 class="kpi-value"><?php echo $my_bookings_count; ?></h2>
                </div>
            </div>
            <div class="kpi-card" onclick="window.location.href='maintenance.php'">
                <div class="kpi-icon icon-maint">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                </div>
                <div class="kpi-details">
                    <span class="kpi-title">Active Repair Tickets</span>
                    <h2 class="kpi-value"><?php echo $my_tickets_count; ?></h2>
                </div>
            </div>
            <div class="kpi-card <?php echo $my_overdue_count > 0 ? 'border-warning-glow' : ''; ?>" onclick="window.location.href='profile.php'">
                <div class="kpi-icon icon-overdue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path></svg>
                </div>
                <div class="kpi-details">
                    <span class="kpi-title <?php echo $my_overdue_count > 0 ? 'text-warning' : ''; ?>">My Overdue Warnings</span>
                    <h2 class="kpi-value <?php echo $my_overdue_count > 0 ? 'text-warning' : ''; ?>"><?php echo $my_overdue_count; ?></h2>
                </div>
            </div>
        </div>

        <div class="dashboard-sections" style="grid-template-columns: 1.2fr 1fr; gap: 24px;">
            <!-- Holdings table -->
            <div class="dashboard-panel card-glow">
                <div class="panel-header">
                    <h3>📦 Assets Currently Allocated To Me</h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Asset Tag</th>
                                    <th>Asset Name</th>
                                    <th>Checked Out</th>
                                    <th>Expected Due</th>
                                    <th>Initiate Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($my_assets)): ?>
                                    <tr><td colspan="5" class="empty-cell">You currently hold no company assets.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($my_assets as $item): ?>
                                        <tr>
                                            <td><span class="asset-tag"><?php echo htmlspecialchars($item['tag']); ?></span></td>
                                            <td><strong><a href="#" class="btn-detail-trigger" data-id="<?php echo $item['id']; ?>" style="text-decoration:none; color:inherit;"><?php echo htmlspecialchars($item['asset_name']); ?></a></strong></td>
                                            <td><?php echo htmlspecialchars(substr($item['allocation_date'], 0, 10)); ?></td>
                                            <td><?php echo htmlspecialchars($item['expected_return_date'] ?? 'Flexible'); ?></td>
                                            <td>
                                                <div style="display:flex; gap:6px;">
                                                    <button class="btn btn-secondary btn-sm" onclick="openTransferModal('<?php echo htmlspecialchars($item['tag']); ?>')">Transfer</button>
                                                    <button class="btn btn-danger btn-sm btn-initiate-return" data-alloc-id="<?php echo $item['allocation_id']; ?>">Return</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar: Agenda and Repairs -->
            <div style="display:flex; flex-direction:column; gap:24px;">
                <!-- Reservation Agenda -->
                <div class="card-glow" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 20px;">
                    <h4 style="margin-top:0; font-family: var(--font-outfit); font-size:1rem; margin-bottom:12px;">📅 My Upcoming Bookings</h4>
                    <div class="schedule-agenda" style="display:flex; flex-direction:column; gap:10px;">
                        <?php if (empty($my_reservations)): ?>
                            <div class="empty-state" style="padding:15px; font-size:0.85rem;">No active reservations slot booked.</div>
                        <?php else: ?>
                            <?php foreach ($my_reservations as $b): ?>
                                <div style="background:rgba(255,255,255,0.015); border:1px solid var(--border-color); padding:10px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <strong style="display:block; font-size:0.88rem;"><?php echo htmlspecialchars($b['asset_name']); ?></strong>
                                        <span style="font-size:0.75rem; color:var(--text-secondary);"><?php echo htmlspecialchars($b['booking_date'] . ' | ' . substr($b['start_time'], 0, 5) . ' - ' . substr($b['end_time'], 0, 5)); ?></span>
                                    </div>
                                    <span class="badge" style="font-size:0.7rem;"><?php echo htmlspecialchars($b['location']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Repair tickets progress -->
                <div class="card-glow" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 20px;">
                    <h4 style="margin-top:0; font-family: var(--font-outfit); font-size:1rem; margin-bottom:12px;">🔧 My Reported Repair Tickets</h4>
                    <div class="repairs-tracker" style="display:flex; flex-direction:column; gap:10px;">
                        <?php if (empty($my_repairs)): ?>
                            <div class="empty-state" style="padding:15px; font-size:0.85rem;">No open repair tickets logged.</div>
                        <?php else: ?>
                            <?php foreach ($my_repairs as $req): ?>
                                <div style="background:rgba(255,255,255,0.015); border:1px solid var(--border-color); padding:10px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <strong style="display:block; font-size:0.88rem;"><?php echo htmlspecialchars($req['asset_name']); ?></strong>
                                        <span style="font-size:0.75rem; color:var(--text-secondary);">Priority: <?php echo htmlspecialchars($req['priority']); ?> | Reported: <?php echo htmlspecialchars(substr($req['created_at'], 0, 10)); ?></span>
                                    </div>
                                    <span class="status-pill status-<?php echo $req['status'] === 'Pending' ? 'reserved' : 'maint'; ?>" style="font-size:0.7rem;"><?php echo htmlspecialchars($req['status']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php endif; ?>

<?php require_once 'footer.php'; ?>

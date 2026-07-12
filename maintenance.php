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
<?php
// Compute KPIs for the new UI
$total_tickets = count($requests);
$pending_count = 0;
$repair_count = 0;
$resolved_count = 0;
$high_priority_count = 0;

$assets_list = [];
$techs_list = [];

foreach ($requests as $r) {
    if ($r['status'] === 'Pending') $pending_count++;
    if ($r['status'] === 'In Progress') $repair_count++;
    if ($r['status'] === 'Resolved' || $r['status'] === 'Rejected') $resolved_count++;
    if ($r['priority'] === 'High' || $r['priority'] === 'Critical') $high_priority_count++;
    
    if (!empty($r['asset_name'])) $assets_list[$r['asset_name']] = true;
    if (!empty($r['assigned_technician'])) $techs_list[$r['assigned_technician']] = true;
}
$assets_list = array_keys($assets_list);
$techs_list = array_keys($techs_list);
?>


<style>
/* Maintenance page specific styles */
.maintenance-wrapper {
    display: flex;
    flex-direction: column;
    gap: 24px;
    width: 100%;
}

.maint-breadcrumb-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.maint-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
}

.maint-breadcrumbs .db {
    color: var(--text-muted);
}

.maint-breadcrumbs .slash {
    color: var(--border-color);
}

.maint-breadcrumbs .curr {
    color: var(--text-primary);
    font-weight: 500;
}

/* KPI Cards Layout */
.kpi-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: var(--shadow);
    transition: var(--transition);
}

.kpi-card:hover {
    transform: translateY(-3px);
    border-color: var(--border-hover);
}

.kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.kpi-c1 { background: rgba(99, 102, 241, 0.1); color: var(--color-primary); }
.kpi-c2 { background: rgba(245, 158, 11, 0.1); color: var(--color-warning); }
.kpi-c3 { background: rgba(59, 130, 246, 0.1); color: var(--color-info); }
.kpi-c4 { background: rgba(16, 185, 129, 0.1); color: var(--color-success); }
.kpi-c5 { background: rgba(239, 68, 68, 0.1); color: var(--color-danger); }

.kpi-content {
    display: flex;
    flex-direction: column;
}

.kpi-title {
    margin: 0;
    font-size: 0.76rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary);
}

.kpi-value {
    font-family: var(--font-outfit);
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 2px 0;
}

.kpi-sub {
    margin: 0;
    font-size: 0.7rem;
    color: var(--text-muted);
}

/* Toolbar & Filters */
.filter-toolbar {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: var(--shadow);
}

.filter-group {
    position: relative;
    display: flex;
    align-items: center;
    flex-grow: 1;
}

.search-input-group {
    flex-grow: 2.5;
}

.filter-group i {
    position: absolute;
    left: 12px;
    color: var(--text-muted);
    font-size: 0.9rem;
    pointer-events: none;
    z-index: 2;
}

.filter-input {
    width: 100%;
    background: rgba(255, 255, 255, 0.02) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    color: var(--text-primary) !important;
    font-size: 0.85rem !important;
    height: 38px !important;
    padding: 8px 12px !important;
    transition: var(--transition);
}

.search-input, .select-input {
    padding-left: 36px !important;
}

.filter-input:focus {
    border-color: var(--color-primary) !important;
    background: rgba(255, 255, 255, 0.04) !important;
    box-shadow: 0 0 8px rgba(99, 102, 241, 0.15);
    outline: none;
}

/* Kanban Board Layout styles */
.kanban-container {
    width: 100%;
    overflow-x: auto;
    padding-bottom: 12px;
}

.kanban-board {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    min-width: max-content;
    padding: 10px 2px;
}

.kb-col {
    width: 320px;
    flex-shrink: 0;
    background: rgba(15, 19, 34, 0.4);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-height: 72vh;
    overflow-y: auto;
}

.kb-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 10px;
}

.kb-title-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
}

.kb-title {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text-primary);
}

.kb-count {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    font-size: 0.72rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
}

.kb-items {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.t-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 14px;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    gap: 10px;
    transition: var(--transition);
}

.t-card:hover {
    transform: translateY(-2px);
    border-color: var(--border-hover);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.t-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.t-tag {
    font-size: 0.72rem;
    padding: 3px 8px;
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 4px;
    color: var(--color-primary);
    font-weight: 600;
}

/* Priority labels */
.t-prio {
    font-size: 0.68rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 4px;
    text-transform: uppercase;
}
.p-critical { background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.15); color: var(--color-danger); }
.p-high { background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.15); color: var(--color-warning); }
.p-medium { background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.15); color: var(--color-info); }
.p-low { background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.15); color: var(--color-success); }

.t-title {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-primary);
    font-weight: 600;
}

.t-desc {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text-secondary);
    line-height: 1.45;
}

.t-meta-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.78rem;
    color: var(--text-muted);
}

.t-meta-value {
    color: var(--color-primary);
    font-weight: 600;
}

.t-notes {
    font-size: 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border-radius: 6px;
    padding: 8px 12px;
    margin-top: 8px;
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
}

.t-actions {
    display: flex;
    gap: 6px;
    margin-top: 4px;
}

.t-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 4px 10px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
}

.t-actions .t-btn:not(.view) {
    flex-grow: 1;
}

.t-actions .t-btn.view {
    width: 32px;
    flex-shrink: 0;
}

.t-btn.primary {
    background-color: var(--color-primary);
    color: white;
}

.t-btn.primary:hover {
    background-color: var(--color-primary-hover);
}

.t-btn.start {
    background-color: var(--color-warning);
    color: white;
}

.t-btn.start:hover {
    background-color: #d97706;
}

.t-btn.resolve {
    background-color: var(--color-success);
    color: white;
}

.t-btn.resolve:hover {
    background-color: #059669;
}

.t-btn.view {
    background-color: var(--color-secondary);
    color: var(--text-primary);
    border-color: var(--border-color);
}

.t-btn.view:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

/* Empty column state */
.kb-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 30px 15px;
    border: 1.5px dashed var(--border-color);
    border-radius: 8px;
    color: var(--text-muted);
    gap: 8px;
    min-height: 200px;
}
.kb-empty i {
    font-size: 1.8rem;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.03);
    color: var(--text-muted);
    margin-bottom: 8px;
}
.kb-empty.success i {
    background: rgba(16, 185, 129, 0.08);
    color: var(--color-success);
}
.kb-empty h4 {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text-primary);
    font-weight: 600;
}
.kb-empty p {
    margin: 0;
    font-size: 0.7rem;
    line-height: 1.3;
}

/* Column Header DOT colors */
.kb-col[data-status="pending"] .kb-dot { width: 8px; height: 8px; border-radius: 50%; background-color: var(--text-muted); }
.kb-col[data-status="approved"] .kb-dot { width: 8px; height: 8px; border-radius: 50%; background-color: var(--color-info); }
.kb-col[data-status="tech_assigned"] .kb-dot { width: 8px; height: 8px; border-radius: 50%; background-color: var(--color-primary); }
.kb-col[data-status="in_progress"] .kb-dot { width: 8px; height: 8px; border-radius: 50%; background-color: var(--color-warning); }
.kb-col[data-status="resolved"] .kb-dot { width: 8px; height: 8px; border-radius: 50%; background-color: var(--color-success); }

/* Light Theme overrides */
body.light-theme .kb-col {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
}
body.light-theme .t-card {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03) !important;
}
body.light-theme .t-card:hover {
    border-color: var(--color-primary) !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02) !important;
}
body.light-theme .kb-empty {
    border: 1.5px dashed #cbd5e1 !important;
}
body.light-theme .kb-empty i {
    background: rgba(0, 0, 0, 0.03);
    color: #64748b;
}
body.light-theme .kb-empty.success i {
    background: rgba(16, 185, 129, 0.1);
    color: var(--color-success);
}
body.light-theme .kb-count {
    background: #e2e8f0 !important;
    border-color: #cbd5e1 !important;
    color: #475569 !important;
}
body.light-theme .filter-toolbar {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03) !important;
}
body.light-theme .filter-input {
    background: #ffffff !important;
    border-color: #cbd5e1 !important;
    color: #1e293b !important;
}
body.light-theme .filter-input:focus {
    border-color: var(--color-primary) !important;
    background: #ffffff !important;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1) !important;
}
body.light-theme .filter-group i {
    color: #64748b !important;
}
body.light-theme .t-notes {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
</style>

<div class="maintenance-wrapper">
    
    <!-- Breadcrumb Row -->
    <div class="maint-breadcrumb-row">
        <div class="maint-breadcrumbs">
            <span class="db">Dashboard</span>
            <span class="slash">/</span>
            <span class="curr">Maintenance</span>
        </div>
        <button class="btn btn-primary" id="btn-raise-maintenance-modal">
            <i class="bi bi-plus-lg"></i> Raise Maintenance Ticket
        </button>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-icon kpi-c1"><i class="bi bi-clipboard2-data"></i></div>
            <div class="kpi-content">
                <h4 class="kpi-title">Total Tickets</h4>
                <div class="kpi-value" id="kpi-total"><?php echo $total_tickets; ?></div>
                <p class="kpi-sub">All maintenance tickets</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon kpi-c2"><i class="bi bi-clock-history"></i></div>
            <div class="kpi-content">
                <h4 class="kpi-title">Pending</h4>
                <div class="kpi-value" id="kpi-pending"><?php echo $pending_count; ?></div>
                <p class="kpi-sub">Awaiting approval</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon kpi-c3"><i class="bi bi-wrench"></i></div>
            <div class="kpi-content">
                <h4 class="kpi-title">Under Repair</h4>
                <div class="kpi-value" id="kpi-repair"><?php echo $repair_count; ?></div>
                <p class="kpi-sub">Technician working</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon kpi-c4"><i class="bi bi-check-circle"></i></div>
            <div class="kpi-content">
                <h4 class="kpi-title">Resolved</h4>
                <div class="kpi-value" id="kpi-resolved"><?php echo $resolved_count; ?></div>
                <p class="kpi-sub">Successfully resolved</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon kpi-c5"><i class="bi bi-shield-exclamation"></i></div>
            <div class="kpi-content">
                <h4 class="kpi-title">High Priority</h4>
                <div class="kpi-value" id="kpi-high"><?php echo $high_priority_count; ?></div>
                <p class="kpi-sub">High & critical priority</p>
            </div>
        </div>
    </div>

    <!-- Filter Toolbar -->
    <div class="filter-toolbar">
        <div class="filter-group search-input-group">
            <i class="bi bi-search"></i>
            <input type="text" class="filter-input search-input" id="filter-search" placeholder="Search by asset, tag, reporter, technician or issue...">
        </div>
        <div class="filter-group">
            <i class="bi bi-flag"></i>
            <select class="filter-input select-input" id="filter-prio">
                <option value="">All Priorities</option>
                <option value="Critical">Critical</option>
                <option value="High">High</option>
                <option value="Medium">Medium</option>
                <option value="Low">Low</option>
            </select>
        </div>
        <div class="filter-group">
            <i class="bi bi-box"></i>
            <select class="filter-input select-input" id="filter-asset">
                <option value="">All Assets</option>
                <?php foreach($assets_list as $ast): ?>
                    <option value="<?php echo htmlspecialchars($ast); ?>"><?php echo htmlspecialchars($ast); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <i class="bi bi-person"></i>
            <select class="filter-input select-input" id="filter-tech">
                <option value="">All Technicians</option>
                <?php foreach($techs_list as $tech): ?>
                    <option value="<?php echo htmlspecialchars($tech); ?>"><?php echo htmlspecialchars($tech); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <i class="bi bi-sliders"></i>
            <select class="filter-input select-input" id="filter-status">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="tech_assigned">Technician Assigned</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
            </select>
        </div>
        <button class="btn btn-secondary btn-sm" id="btn-clear-filters" style="height: 38px;">
            <i class="bi bi-arrow-clockwise"></i> Clear Filters
        </button>
    </div>
    <span class="showing-text" id="showing-text">Showing <?php echo $total_tickets; ?> of <?php echo $total_tickets; ?> tickets</span>

    <!-- Kanban Wrapper -->
    <div class="kanban-container">
        <div class="kanban-board">
            
            <?php 
            $kanban_cols = [
                'Pending' => 'pending',
                'Approved' => 'approved',
                'Technician Assigned' => 'tech_assigned',
                'In Progress' => 'in_progress',
                'Resolved' => 'resolved'
            ];
            
            foreach ($kanban_cols as $colName => $colKey): 
                $items = $columns[$colName] ?? [];
            ?>
            <div class="kb-col" data-status="<?php echo $colKey; ?>">
                <div class="kb-header">
                    <div class="kb-title-wrap">
                        <div class="kb-dot"></div>
                        <span class="kb-title"><?php echo htmlspecialchars($colName); ?></span>
                    </div>
                    <span class="kb-count count-badge"><?php echo count($items); ?></span>
                </div>
                
                <div class="kb-items" style="display: flex; flex-direction: column; gap: 12px; min-height: 150px;">
                    <?php if (empty($items)): ?>
                        <div class="kb-empty <?php echo $colKey === 'resolved' ? 'success' : ''; ?>">
                            <?php if ($colKey === 'resolved'): ?>
                                <i class="bi bi-check-circle"></i>
                                <h4>No tickets in this stage</h4>
                                <p>Tickets will appear here when they move to this status.</p>
                            <?php else: ?>
                                <i class="bi bi-inbox"></i>
                                <h4>No tickets in this stage</h4>
                                <p>Tickets will appear here when they move to this status.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($items as $req): ?>
                        <div class="t-card" 
                             data-search="<?php echo htmlspecialchars(strtolower($req['tag'].' '.$req['asset_name'].' '.$req['reporter_name'].' '.$req['assigned_technician'].' '.$req['description'])); ?>"
                             data-prio="<?php echo htmlspecialchars($req['priority']); ?>"
                             data-asset="<?php echo htmlspecialchars($req['asset_name']); ?>"
                             data-tech="<?php echo htmlspecialchars($req['assigned_technician'] ?? ''); ?>">
                             
                            <div class="t-head">
                                <span class="t-tag"><?php echo htmlspecialchars($req['tag']); ?></span>
                                <span class="t-prio p-<?php echo strtolower($req['priority']); ?>"><?php echo htmlspecialchars($req['priority']); ?></span>
                            </div>
                             
                            <h4 class="t-title"><?php echo htmlspecialchars($req['asset_name']); ?></h4>
                            <p class="t-desc"><?php echo htmlspecialchars($req['description']); ?></p>
                             
                            <div class="t-meta-row">
                                <i class="bi bi-person"></i> Reporter: <span class="t-meta-value"><?php echo htmlspecialchars($req['reporter_name']); ?></span>
                            </div>
                            <?php if ($req['assigned_technician']): ?>
                            <div class="t-meta-row">
                                <i class="bi bi-wrench"></i> Technician: <span class="t-meta-value"><?php echo htmlspecialchars($req['assigned_technician']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="t-meta-row">
                                <i class="bi bi-calendar"></i> Created: <span class="t-meta-value-date"><?php echo date('d M Y', strtotime($req['created_at'])); ?></span>
                            </div>
                             
                            <?php if (!empty($req['notes'])): ?>
                            <div class="t-notes"><strong>Notes:</strong> <?php echo htmlspecialchars($req['notes']); ?></div>
                            <?php endif; ?>
                             
                            <div class="t-actions">
                                <?php if ($req['status'] === 'Pending' && ($user_role === 'admin' || $user_role === 'asset_manager')): ?>
                                    <button class="t-btn primary assign btn-approve-maint" data-id="<?php echo $req['id']; ?>">Approve</button>
                                    <button class="t-btn view btn-reject-maint" data-id="<?php echo $req['id']; ?>" title="Reject"><i class="bi bi-x-lg"></i></button>
                                <?php elseif ($req['status'] === 'Approved' && ($user_role === 'admin' || $user_role === 'asset_manager')): ?>
                                    <button class="t-btn primary assign btn-update-maint" data-id="<?php echo $req['id']; ?>" data-status="Technician Assigned" data-tech="<?php echo htmlspecialchars($req['assigned_technician'] ?? ''); ?>" data-notes="<?php echo htmlspecialchars($req['notes'] ?? ''); ?>">Assign Technician</button>
                                <?php elseif ($req['status'] === 'Technician Assigned' && ($user_role === 'admin' || $user_role === 'asset_manager')): ?>
                                    <button class="t-btn primary start btn-update-maint" data-id="<?php echo $req['id']; ?>" data-status="In Progress" data-tech="<?php echo htmlspecialchars($req['assigned_technician'] ?? ''); ?>" data-notes="<?php echo htmlspecialchars($req['notes'] ?? ''); ?>">Start Work</button>
                                <?php elseif ($req['status'] === 'In Progress' && ($user_role === 'admin' || $user_role === 'asset_manager')): ?>
                                    <button class="t-btn primary resolve btn-update-maint" data-id="<?php echo $req['id']; ?>" data-status="Resolved" data-tech="<?php echo htmlspecialchars($req['assigned_technician'] ?? ''); ?>" data-notes="<?php echo htmlspecialchars($req['notes'] ?? ''); ?>">Resolve</button>
                                <?php endif; ?>
                                
                                <button class="t-btn view" title="View details" onclick="alert('Asset: <?php echo htmlspecialchars($req['asset_name']); ?>\nTag: <?php echo htmlspecialchars($req['tag']); ?>\nDescription: <?php echo htmlspecialchars($req['description']); ?>')"><i class="bi bi-eye"></i></button>
                            </div>
                             
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('filter-search');
    const prioFilter = document.getElementById('filter-prio');
    const assetFilter = document.getElementById('filter-asset');
    const techFilter = document.getElementById('filter-tech');
    const statusFilter = document.getElementById('filter-status');
    const clearBtn = document.getElementById('btn-clear-filters');
    const showingText = document.getElementById('showing-text');
    
    const allCards = document.querySelectorAll('.t-card');
    const totalCount = allCards.length;

    function applyFilters() {
        const query = searchInput.value.toLowerCase();
        const prio = prioFilter.value;
        const asset = assetFilter.value;
        const tech = techFilter.value;
        const status = statusFilter.value;
        
        let visibleCount = 0;
        
        allCards.forEach(card => {
            let match = true;
            
            if (query && !card.dataset.search.includes(query)) match = false;
            if (prio && card.dataset.prio !== prio) match = false;
            if (asset && card.dataset.asset !== asset) match = false;
            if (tech && card.dataset.tech !== tech) match = false;
            if (status && card.closest('.kb-col').dataset.status !== status) match = false;
            
            if (match) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        showingText.textContent = `Showing ${visibleCount} of ${totalCount} tickets`;
        
        // Update column display and count badges
        document.querySelectorAll('.kb-col').forEach(col => {
            const colStatus = col.dataset.status;
            if (!status || colStatus === status) {
                col.style.display = 'flex';
            } else {
                col.style.display = 'none';
            }
            
            const visibleInCol = col.querySelectorAll('.t-card[style="display: block;"]').length;
            const badge = col.querySelector('.count-badge');
            if (badge) {
                const totalInCol = col.querySelectorAll('.t-card').length;
                if(totalInCol > 0) {
                    badge.textContent = visibleInCol;
                }
            }
        });
    }

    if(searchInput) searchInput.addEventListener('input', applyFilters);
    if(prioFilter) prioFilter.addEventListener('change', applyFilters);
    if(assetFilter) assetFilter.addEventListener('change', applyFilters);
    if(techFilter) techFilter.addEventListener('change', applyFilters);
    if(statusFilter) statusFilter.addEventListener('change', applyFilters);
    
    if(clearBtn) {
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            prioFilter.value = '';
            assetFilter.value = '';
            techFilter.value = '';
            statusFilter.value = '';
            applyFilters();
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>
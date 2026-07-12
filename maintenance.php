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

<div class="maintenance-wrapper">
    
    <!-- Breadcrumb Row -->
    <div class="maint-breadcrumb-row">
        <div class="maint-breadcrumbs">
            <span class="db">Dashboard</span>
            <span class="slash">/</span>
            <span class="curr">Maintenance</span>
        </div>
        <button class="btn-raise-main" id="btn-raise-maintenance-modal">
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
            <select class="filter-input select-input" id="filter-prio">
                <option value="">All Priorities</option>
                <option value="Critical">Critical</option>
                <option value="High">High</option>
                <option value="Medium">Medium</option>
                <option value="Low">Low</option>
            </select>
        </div>
        <div class="filter-group">
            <select class="filter-input select-input" id="filter-asset">
                <option value="">All Assets</option>
                <?php foreach($assets_list as $ast): ?>
                    <option value="<?php echo htmlspecialchars($ast); ?>"><?php echo htmlspecialchars($ast); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select class="filter-input select-input" id="filter-tech">
                <option value="">All Technicians</option>
                <?php foreach($techs_list as $tech): ?>
                    <option value="<?php echo htmlspecialchars($tech); ?>"><?php echo htmlspecialchars($tech); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn-clear" id="btn-clear-filters">
            <i class="bi bi-arrow-clockwise"></i> Clear Filters
        </button>
    </div>
    <span class="showing-text" id="showing-text">Showing <?php echo $total_tickets; ?> of <?php echo $total_tickets; ?> tickets</span>

    <!-- Kanban Wrapper -->
    <div class="kanban-container">
        <div class="kanban-scroll-wrapper">
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
                    
                    <div class="kb-items">
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
                                    <i class="bi bi-person"></i> Reporter: <?php echo htmlspecialchars($req['reporter_name']); ?>
                                </div>
                                <?php if ($req['assigned_technician']): ?>
                                <div class="t-meta-row">
                                    <i class="bi bi-wrench"></i> Technician: <?php echo htmlspecialchars($req['assigned_technician']); ?>
                                </div>
                                <?php endif; ?>
                                <div class="t-meta-row">
                                    <i class="bi bi-calendar"></i> Created: <?php echo date('d M Y', strtotime($req['created_at'])); ?>
                                </div>
                                
                                <?php if (!empty($req['notes'])): ?>
                                <div class="t-notes">Notes: <?php echo htmlspecialchars($req['notes']); ?></div>
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
                                    
                                    <button class="t-btn view" title="View details" onclick="alert('View details for <?php echo htmlspecialchars($req['tag']); ?>')"><i class="bi bi-eye"></i></button>
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
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('filter-search');
    const prioFilter = document.getElementById('filter-prio');
    const assetFilter = document.getElementById('filter-asset');
    const techFilter = document.getElementById('filter-tech');
    const clearBtn = document.getElementById('btn-clear-filters');
    const showingText = document.getElementById('showing-text');
    
    const allCards = document.querySelectorAll('.t-card');
    const totalCount = allCards.length;

    function applyFilters() {
        const query = searchInput.value.toLowerCase();
        const prio = prioFilter.value;
        const asset = assetFilter.value;
        const tech = techFilter.value;
        
        let visibleCount = 0;
        
        allCards.forEach(card => {
            let match = true;
            
            if (query && !card.dataset.search.includes(query)) match = false;
            if (prio && card.dataset.prio !== prio) match = false;
            if (asset && card.dataset.asset !== asset) match = false;
            if (tech && card.dataset.tech !== tech) match = false;
            
            if (match) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        showingText.textContent = `Showing ${visibleCount} of ${totalCount} tickets`;
        
        // Update column counts
        document.querySelectorAll('.kb-col').forEach(col => {
            const visibleInCol = col.querySelectorAll('.t-card[style="display: block;"]').length;
            const badge = col.querySelector('.count-badge');
            if (badge) {
                // If there were no cards before filtering, it might be an empty state, 
                // but if there are cards we just count the visible ones
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
    
    if(clearBtn) {
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            prioFilter.value = '';
            assetFilter.value = '';
            techFilter.value = '';
            applyFilters();
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>

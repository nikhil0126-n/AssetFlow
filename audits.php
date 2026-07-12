<?php
require_once 'header.php';

// Fetch audit cycles and their assigned auditors
$sql = "SELECT ac.*, d.name as department_name, e.name as creator_name
        FROM audit_cycles ac
        LEFT JOIN departments d ON ac.department_id = d.id
        JOIN employees e ON ac.created_by = e.id
        ORDER BY ac.created_at DESC";
$cycles = $db->query($sql)->fetchAll();

foreach ($cycles as &$c) {
    $stmtAud = $db->prepare("
        SELECT e.name 
        FROM audit_auditors aa
        JOIN employees e ON aa.employee_id = e.id
        WHERE aa.audit_cycle_id = ?
    ");
    $stmtAud->execute([$c['id']]);
    $c['auditors'] = $stmtAud->fetchAll(PDO::FETCH_COLUMN);
}

// Get selected audit cycle
$selected_id = isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : (count($cycles) > 0 ? $cycles[0]['id'] : null);
$selected_cycle = null;

foreach ($cycles as $c) {
    if ($c['id'] == $selected_id) {
        $selected_cycle = $c;
        break;
    }
}

// Fetch items inside selected cycle
$items = [];
if ($selected_id) {
    $stmtItems = $db->prepare("
        SELECT ai.*, a.tag, a.name as asset_name, a.location, a.status as current_status,
        e.name as holder_name
        FROM audit_items ai
        JOIN assets a ON ai.asset_id = a.id
        LEFT JOIN allocations al ON al.asset_id = a.id AND al.status = 'Active'
        LEFT JOIN employees e ON al.employee_id = e.id
        WHERE ai.audit_cycle_id = ?
    ");
    $stmtItems->execute([$selected_id]);
    $items = $stmtItems->fetchAll();
}
?>

<section id="view-audits" class="app-view">
    <div class="audits-layout">
        <!-- Cycles List -->
        <div class="audit-cycles-card card-glow">
            <div class="card-header-actions">
                <h3>Audit Cycles</h3>
                <?php if ($user_role === 'admin'): ?>
                    <button class="btn btn-primary btn-sm" id="btn-create-audit-modal">New Cycle</button>
                <?php endif; ?>
            </div>
            <div class="audit-cycles-list">
                <?php if (empty($cycles)): ?>
                    <div class="empty-state">No audits configured in system.</div>
                <?php else: ?>
                    <?php foreach ($cycles as $c): ?>
                        <div class="audit-cycle-item <?php echo $selected_id == $c['id'] ? 'active' : ''; ?>" onclick="window.location.href='audits.php?cycle_id=<?php echo $c['id']; ?>'">
                            <h4><?php echo htmlspecialchars($c['name']); ?></h4>
                            <p>Dept/Scope: <strong><?php echo htmlspecialchars($c['department_name'] ?? 'All'); ?></strong> | Location: <?php echo htmlspecialchars($c['location'] ?? 'All'); ?></p>
                            <p style="margin-top:2px;">Auditor(s): <strong><?php echo htmlspecialchars(implode(', ', $c['auditors'])); ?></strong></p>
                            <span class="audit-tag audit-tag-<?php echo strtolower($c['status']); ?>"><?php echo htmlspecialchars($c['status']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Auditor Check Area -->
        <div class="audit-workspace-card card-glow">
            <div class="workspace-header">
                <h3>
                    <?php if ($selected_cycle): ?>
                        📋 Checklist: <strong><?php echo htmlspecialchars($selected_cycle['name']); ?></strong> (<?php echo htmlspecialchars($selected_cycle['status']); ?>)
                    <?php else: ?>
                        Select Audit Cycle
                    <?php endif; ?>
                </h3>
                <?php if ($selected_cycle && $selected_cycle['status'] === 'Active' && ($user_role === 'admin' || $user_role === 'asset_manager')): ?>
                    <button class="btn btn-success btn-sm" onclick="closeAuditCycle(<?php echo $selected_cycle['id']; ?>)">Close & Generate Report</button>
                <?php endif; ?>
            </div>
            
            <div class="audit-workspace-body">
                <?php if (!$selected_cycle): ?>
                    <div class="empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <p>Select an active audit cycle on the left to mark verified, missing, or damaged assets in scope.</p>
                    </div>
                <?php elseif (empty($items)): ?>
                    <div class="empty-state">
                        <p>No assets match the scope filters of this audit cycle.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Asset Tag</th>
                                    <th>Asset Name</th>
                                    <th>Location</th>
                                    <th>Current Holder</th>
                                    <th>Notes</th>
                                    <th>Check Status</th>
                                    <th>Verify Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><span class="asset-tag"><?php echo htmlspecialchars($item['tag']); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($item['asset_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['location']); ?></td>
                                        <td><?php echo htmlspecialchars($item['holder_name'] ?? 'Storage'); ?></td>
                                        <td>
                                            <?php if ($selected_cycle['status'] === 'Closed'): ?>
                                                <span class="text-muted" style="font-size:0.82rem; font-style:italic;"><?php echo htmlspecialchars($item['notes'] !== '' ? $item['notes'] : 'No notes'); ?></span>
                                            <?php else: ?>
                                                <input type="text" class="form-control audit-notes-input" data-id="<?php echo $item['id']; ?>" value="<?php echo htmlspecialchars($item['notes'] ?? ''); ?>" placeholder="Log note..." style="height:30px; font-size:0.8rem; width:150px;">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-pill status-<?php echo $item['status'] === 'Pending' ? 'reserved' : ($item['status'] === 'Verified' ? 'available' : ($item['status'] === 'Damaged' ? 'maint' : 'lost')); ?>">
                                                <?php echo htmlspecialchars($item['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($selected_cycle['status'] === 'Active'): ?>
                                                <button class="btn btn-success btn-sm btn-audit-state" data-id="<?php echo $item['id']; ?>" data-status="Verified">Verify</button>
                                                <button class="btn btn-warning btn-sm btn-audit-state" data-id="<?php echo $item['id']; ?>" data-status="Damaged" style="margin-left:4px;">Damaged</button>
                                                <button class="btn btn-danger btn-sm btn-audit-state" data-id="<?php echo $item['id']; ?>" data-status="Missing" style="margin-left:4px;">Missing</button>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>

<?php
require_once 'header.php';

// Fetch allocations with role-based visibility
$sqlAlloc = "SELECT a.*, c.name as category_name, 
            e.name as holder_name, d.name as dept_holder_name,
            al.id as allocation_id, al.allocation_date, al.expected_return_date
            FROM assets a
            JOIN categories c ON a.category_id = c.id
            JOIN allocations al ON al.asset_id = a.id AND al.status = 'Active'
            LEFT JOIN employees e ON al.employee_id = e.id
            LEFT JOIN departments d ON al.department_id = d.id";

$paramsAlloc = [];

if ($user_role === 'employee') {
    $sqlAlloc .= " WHERE al.employee_id = ?";
    $paramsAlloc[] = $user_id;
} else if ($user_role === 'dept_head' && $user_dept) {
    $sqlAlloc .= " WHERE al.employee_id = ? OR al.department_id = ? OR e.department_id = ?";
    $paramsAlloc[] = $user_id;
    $paramsAlloc[] = $user_dept;
    $paramsAlloc[] = $user_dept;
}

$sqlAlloc .= " ORDER BY al.allocation_date DESC";
$stmtAlloc = $db->prepare($sqlAlloc);
$stmtAlloc->execute($paramsAlloc);
$allocations = $stmtAlloc->fetchAll();

// Fetch transfers with role-based visibility
$sqlTrans = "SELECT t.*, a.tag, a.name as asset_name, 
            e_from.name as from_employee_name, 
            e_to.name as to_employee_name,
            d_to.name as to_department_name,
            req.name as requester_name
            FROM transfers t
            JOIN assets a ON t.asset_id = a.id
            LEFT JOIN employees e_from ON t.from_employee_id = e_from.id
            LEFT JOIN employees e_to ON t.to_employee_id = e_to.id
            LEFT JOIN departments d_to ON t.to_department_id = d_to.id
            JOIN employees req ON t.requested_by = req.id";

$paramsTrans = [];

if ($user_role === 'employee') {
    $sqlTrans .= " WHERE t.requested_by = ? OR t.from_employee_id = ? OR t.to_employee_id = ?";
    $paramsTrans[] = $user_id;
    $paramsTrans[] = $user_id;
    $paramsTrans[] = $user_id;
} else if ($user_role === 'dept_head' && $user_dept) {
    $sqlTrans .= " WHERE t.requested_by = ? OR t.from_employee_id = ? OR t.to_employee_id = ? OR t.to_department_id = ?";
    $paramsTrans[] = $user_id;
    $paramsTrans[] = $user_id;
    $paramsTrans[] = $user_id;
    $paramsTrans[] = $user_dept;
}

$sqlTrans .= " ORDER BY t.request_date DESC";
$stmtTrans = $db->prepare($sqlTrans);
$stmtTrans->execute($paramsTrans);
$transfers = $stmtTrans->fetchAll();

function get_status_pill($status) {
    $map = [
        'Available' => 'available', 'Allocated' => 'allocated', 'Reserved' => 'reserved',
        'Under Maintenance' => 'maint', 'Lost' => 'lost', 'Retired' => 'retired', 'Disposed' => 'disposed'
    ];
    return $map[$status] ?? 'retired';
}
?>

<section id="view-allocations" class="app-view">
    <div class="allocations-container">
        <div class="sub-tab-container">
            <div class="sub-tab-headers">
                <button class="sub-tab-btn active" data-subtab="subtab-allocations">Active Allocations</button>
                <button class="sub-tab-btn" data-subtab="subtab-transfers">Asset Transfers Queue</button>
            </div>

            <!-- Tab: Active Allocations -->
            <div id="subtab-allocations" class="sub-tab-pane active">
                <div class="pane-header">
                    <h3>Active Company Allocations</h3>
                    <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                        <button class="btn btn-primary btn-sm" id="btn-allocate-asset-modal">Allocate Asset</button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Asset Tag</th>
                                <th>Asset Name</th>
                                <th>Allocated To</th>
                                <th>Allocation Date</th>
                                <th>Expected Return Date</th>
                                <th>Status</th>
                                <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                                    <th>Check-In Return</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allocations)): ?>
                                <tr><td colspan="7" class="empty-cell">No active allocations found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($allocations as $alloc): ?>
                                    <tr>
                                        <td><span class="asset-tag"><?php echo htmlspecialchars($alloc['tag']); ?></span></td>
                                        <td><strong><a href="#" class="btn-detail-trigger" data-id="<?php echo $alloc['id']; ?>" style="text-decoration:none; color:inherit;"><?php echo htmlspecialchars($alloc['name']); ?></a></strong></td>
                                        <td><?php echo htmlspecialchars($alloc['holder_name'] ?? $alloc['dept_holder_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(substr($alloc['allocation_date'], 0, 10)); ?></td>
                                        <td><?php echo htmlspecialchars($alloc['expected_return_date'] ?? 'Flexible'); ?></td>
                                        <td><span class="status-pill status-<?php echo get_status_pill($alloc['status']); ?>"><?php echo htmlspecialchars($alloc['status']); ?></span></td>
                                        <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                                            <td>
                                                <button class="btn btn-secondary btn-sm btn-return-action" data-alloc-id="<?php echo $alloc['allocation_id']; ?>">Check-In Return</button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Transfers Queue -->
            <div id="subtab-transfers" class="sub-tab-pane">
                <div class="pane-header">
                    <h3>Inter-Employee Asset Transfers</h3>
                    <button class="btn btn-primary btn-sm" id="btn-request-transfer-modal">Initiate Transfer Request</button>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>From Employee</th>
                                <th>To Employee/Dept</th>
                                <th>Requested By</th>
                                <th>Request Date</th>
                                <th>Status</th>
                                <th>Approve Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transfers)): ?>
                                <tr><td colspan="7" class="empty-cell">No transfer requests pending.</td></tr>
                            <?php else: ?>
                                <?php foreach ($transfers as $t): ?>
                                    <tr>
                                        <td><span class="asset-tag"><?php echo htmlspecialchars($t['tag']); ?></span> <strong><?php echo htmlspecialchars($t['asset_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($t['from_employee_name'] ?? 'Storage'); ?></td>
                                        <td><strong><?php echo htmlspecialchars($t['to_employee_name'] ?? $t['to_department_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($t['requester_name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($t['request_date'], 0, 10)); ?></td>
                                        <td>
                                            <span class="status-pill status-<?php echo $t['status'] === 'Pending' ? 'maint' : ($t['status'] === 'Approved' ? 'available' : 'lost'); ?>">
                                                <?php echo htmlspecialchars($t['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($t['status'] === 'Pending'): ?>
                                                <?php if ($user_role === 'admin' || $user_role === 'asset_manager' || ($user_role === 'dept_head' && $t['to_department_id'] == $user_dept)): ?>
                                                    <button class="btn btn-success btn-sm btn-approve-transfer" data-id="<?php echo $t['id']; ?>">Approve</button>
                                                    <button class="btn btn-danger btn-sm btn-reject-transfer" data-id="<?php echo $t['id']; ?>" style="margin-left:4px;">Reject</button>
                                                <?php else: ?>
                                                    <span class="text-warning">Pending Review</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
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
    </div>
</section>

<?php require_once 'footer.php'; ?>

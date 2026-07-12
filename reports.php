<?php
require_once 'header.php';

// Guard: Admin/Manager only
if ($user_role !== 'admin' && $user_role !== 'asset_manager') {
    header('Location: dashboard.php');
    exit;
}

// Fetch Metrics
$totalAssets = (int)$db->query("SELECT COUNT(*) FROM assets")->fetchColumn();
$allocatedAssets = (int)$db->query("SELECT COUNT(*) FROM assets WHERE status = 'Allocated'")->fetchColumn();
$underMaint = (int)$db->query("SELECT COUNT(*) FROM assets WHERE status = 'Under Maintenance'")->fetchColumn();
$lost = (int)$db->query("SELECT COUNT(*) FROM assets WHERE status = 'Lost'")->fetchColumn();
$available = (int)$db->query("SELECT COUNT(*) FROM assets WHERE status = 'Available'")->fetchColumn();

// Maintenance by Category
$maintFrequency = $db->query("
    SELECT c.name as category_name, COUNT(mr.id) as request_count 
    FROM maintenance_requests mr
    JOIN assets a ON mr.asset_id = a.id
    JOIN categories c ON a.category_id = c.id
    GROUP BY c.id
")->fetchAll();

// Department Allocations
$deptAllocations = $db->query("
    SELECT d.name as department_name, COUNT(al.id) as allocation_count 
    FROM allocations al
    JOIN departments d ON al.department_id = d.id OR al.employee_id IN (SELECT id FROM employees WHERE department_id = d.id)
    WHERE al.status IN ('Active', 'Overdue')
    GROUP BY d.id
")->fetchAll();

// Heatmap bookings
$bookingHeatmap = $db->query("
    SELECT HOUR(start_time) as booking_hour, COUNT(*) as booking_count 
    FROM bookings 
    WHERE status != 'Cancelled'
    GROUP BY booking_hour
    ORDER BY booking_hour ASC
")->fetchAll();

// Professional KPI 1: Most-Used Assets (Shared resource reservations)
$mostUsedAssets = $db->query("
    SELECT a.tag, a.name, COUNT(b.id) as use_count 
    FROM assets a 
    JOIN bookings b ON a.id = b.asset_id 
    WHERE b.status != 'Cancelled'
    GROUP BY a.id 
    ORDER BY use_count DESC 
    LIMIT 3
")->fetchAll();

// Professional KPI 2: Idle Assets (In Available state, not allocated, older acquisition dates)
$idleAssets = $db->query("
    SELECT tag, name, acquisition_date, location 
    FROM assets 
    WHERE status = 'Available' AND is_shared = 0
    ORDER BY acquisition_date ASC 
    LIMIT 3
")->fetchAll();

// Service / Warranty Warnings (Custom attributes check simulation)
$serviceDueAssets = $db->query("
    SELECT tag, name, condition_state, location
    FROM assets 
    WHERE condition_state IN ('Poor', 'Damaged') AND status != 'Under Maintenance'
    LIMIT 3
")->fetchAll();

// Nearing retirement (Older than 2 years)
$nearingRetirement = $db->query("
    SELECT tag, name, acquisition_date, condition_state 
    FROM assets 
    WHERE acquisition_date <= DATE_SUB(CURRENT_DATE(), INTERVAL 2 YEAR) AND status != 'Retired' AND status != 'Disposed'
    LIMIT 5
")->fetchAll();

?>

<section id="view-reports" class="app-view">
    <div class="reports-header" style="margin-bottom: 24px;">
        <h3>Actionable Analytics</h3>
        <button class="btn btn-outline btn-sm" id="btn-export-report" onclick="window.print()">Export Summary PDF</button>
    </div>

    <!-- KPI Analytics Cards -->
    <div class="charts-grid">
        <!-- Chart 1: Donut -->
        <div class="chart-card card-glow">
            <h4>Asset Utilization Status</h4>
            <div class="chart-container-svg">
                <?php 
                $total = $totalAssets > 0 ? $totalAssets : 1;
                $availPct = round(($available / $total) * 100);
                $allocPct = round(($allocatedAssets / $total) * 100);
                $maintPct = round(($underMaint / $total) * 100);
                $lostPct = round(($lost / $total) * 100);
                ?>
                <div style="display:flex; align-items:center; gap:20px; width:100%; justify-content:center;">
                    <svg width="120" height="120" viewBox="0 0 36 36" style="transform: rotate(-90deg);">
                        <circle cx="18" cy="18" r="15.91" fill="transparent" stroke="rgba(255,255,255,0.03)" stroke-width="4"></circle>
                        <circle cx="18" cy="18" r="15.91" fill="transparent" stroke="var(--color-primary)" stroke-width="4" stroke-dasharray="<?php echo $allocPct; ?> <?php echo 100 - $allocPct; ?>" stroke-dashoffset="0"></circle>
                        <circle cx="18" cy="18" r="15.91" fill="transparent" stroke="var(--color-success)" stroke-width="4" stroke-dasharray="<?php echo $availPct; ?> <?php echo 100 - $availPct; ?>" stroke-dashoffset="-<?php echo $allocPct; ?>"></circle>
                    </svg>
                    <div style="font-size:0.85rem; display:flex; flex-direction:column; gap:6px;">
                        <div>🟢 Available: <strong><?php echo $available; ?></strong> (<?php echo $availPct; ?>%)</div>
                        <div>🔵 Allocated: <strong><?php echo $allocatedAssets; ?></strong> (<?php echo $allocPct; ?>%)</div>
                        <div>🟡 Repair: <strong><?php echo $underMaint; ?></strong> (<?php echo $maintPct; ?>%)</div>
                        <div>🔴 Lost: <strong><?php echo $lost; ?></strong> (<?php echo $lostPct; ?>%)</div>
                        <div style="border-top:1px solid var(--border-color); padding-top:4px; margin-top:4px;">Total Assets: <strong><?php echo $totalAssets; ?></strong></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 2: Category Maintenance -->
        <div class="chart-card card-glow">
            <h4>Maintenance Frequency by Category</h4>
            <div class="chart-container-svg">
                <?php if (empty($maintFrequency)): ?>
                    <span class="text-muted">No maintenance tasks recorded yet.</span>
                <?php else: ?>
                    <?php 
                    $maxCount = max(array_merge(array_map(function($d) { return $d['request_count']; }, $maintFrequency), [1]));
                    ?>
                    <div style="width:100%; text-align:left; padding: 0 10px;">
                        <?php foreach ($maintFrequency as $d): ?>
                            <?php $widthPct = round(($d['request_count'] / $maxCount) * 100); ?>
                            <div style="margin-bottom:12px;">
                                <div style="display:flex; justify-content:space-between; font-size:0.78rem; margin-bottom:4px;">
                                    <span><?php echo htmlspecialchars($d['category_name']); ?></span>
                                    <strong><?php echo $d['request_count']; ?> tickets</strong>
                                </div>
                                <div style="background:rgba(255,255,255,0.03); height:8px; border-radius:4px; overflow:hidden; border:1px solid var(--border-color)">
                                    <div style="background:var(--color-warning); width:<?php echo $widthPct; ?>%; height:100%; border-radius:4px; box-shadow: 0 0 8px rgba(245, 158, 11, 0.4)"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chart 3: Department allocations -->
        <div class="chart-card card-glow">
            <h4>Department-wise Allocation Volume</h4>
            <div class="chart-container-svg">
                <?php if (empty($deptAllocations)): ?>
                    <span class="text-muted">No departments allocations registered.</span>
                <?php else: ?>
                    <?php 
                    $maxCount = max(array_merge(array_map(function($d) { return $d['allocation_count']; }, $deptAllocations), [1]));
                    ?>
                    <div style="display:flex; justify-content:space-around; align-items:end; width:100%; height:100%; padding-bottom:10px;">
                        <?php foreach ($deptAllocations as $d): ?>
                            <?php $heightPct = round(($d['allocation_count'] / $maxCount) * 100); ?>
                            <div style="display:flex; flex-direction:column; align-items:center; flex-grow:1; min-width:40px;">
                                <span style="font-size:0.75rem; font-weight:700; margin-bottom:6px;"><?php echo $d['allocation_count']; ?></span>
                                <div style="background:rgba(255,255,255,0.03); width:16px; height:120px; display:flex; align-items:end; border-radius:4px; border:1px solid var(--border-color);">
                                    <div style="background:var(--color-primary); width:100%; height:<?php echo $heightPct; ?>%; border-radius:3px; box-shadow: 0 0 8px rgba(99, 102, 241, 0.4)"></div>
                                </div>
                                <span style="font-size:0.65rem; color:var(--text-secondary); text-align:center; width:60px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:8px;"><?php echo htmlspecialchars($d['department_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chart 4: Heatmap -->
        <div class="chart-card card-glow">
            <h4>Resource Booking Peak Usage</h4>
            <div class="chart-container-svg">
                <?php 
                $heatMapHours = array_fill(0, 24, 0);
                foreach ($bookingHeatmap as $d) {
                    if ($d['booking_hour'] >= 0 && $d['booking_hour'] < 24) {
                        $heatMapHours[$d['booking_hour']] = $d['booking_count'];
                    }
                }
                ?>
                <div style="display:flex; flex-direction:column; width:100%; gap:10px;">
                    <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                        <?php for ($h = 8; $h <= 18; $h++): ?>
                            <?php 
                            $count = $heatMapHours[$h] ?? 0;
                            $opacity = $count === 0 ? 0.05 : min(0.2 + ($count * 0.25), 0.95);
                            $color = $count === 0 ? 'rgba(255,255,255,0.03)' : "rgba(99, 102, 241, $opacity)";
                            $border = $count === 0 ? 'var(--border-color)' : 'var(--color-primary)';
                            ?>
                            <div style="background:<?php echo $color; ?>; border:1px solid <?php echo $border; ?>; border-radius:6px; padding:10px; display:flex; flex-direction:column; align-items:center; flex-grow:1; min-width:50px;">
                                <span style="font-size:0.68rem; color:var(--text-muted);"><?php echo $h; ?>:00</span>
                                <strong style="font-size:0.9rem; margin-top:4px;"><?php echo $count; ?></strong>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <span style="font-size:0.75rem; text-align:center; color:var(--text-muted);">Density represents reservation counts inside business hours.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Row: Most Used & Idle Assets -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        <!-- Most Used -->
        <div class="card-glow" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 20px;">
            <h4 style="margin-top:0; color: var(--text-secondary); font-family: var(--font-outfit); font-size: 1rem; margin-bottom: 12px;">🔥 Most Active Shared Resources</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Name</th>
                        <th>Total Bookings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mostUsedAssets)): ?>
                        <tr><td colspan="3" class="empty-cell">No shared assets bookings recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($mostUsedAssets as $a): ?>
                            <tr>
                                <td><span class="asset-tag"><?php echo $a['tag']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($a['name']); ?></strong></td>
                                <td class="text-success"><?php echo $a['use_count']; ?> times reserved</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Idle Assets -->
        <div class="card-glow" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 20px;">
            <h4 style="margin-top:0; color: var(--text-secondary); font-family: var(--font-outfit); font-size: 1rem; margin-bottom: 12px;">❄️ Idle Inventory (Unallocated in Storage)</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Name</th>
                        <th>Acquired</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($idleAssets)): ?>
                        <tr><td colspan="4" class="empty-cell">All standard assets are currently allocated!</td></tr>
                    <?php else: ?>
                        <?php foreach ($idleAssets as $a): ?>
                            <tr>
                                <td><span class="asset-tag"><?php echo $a['tag']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($a['name']); ?></strong></td>
                                <td><?php echo $a['acquisition_date']; ?></td>
                                <td><?php echo htmlspecialchars($a['location']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Maintenance Due / Service flags -->
    <div class="reports-table-panel card-glow" style="margin-bottom: 24px;">
        <h4>🛠️ Urgent Action Required (Damaged / Faulty Assets)</h4>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Asset Tag</th>
                    <th>Name</th>
                    <th>Condition</th>
                    <th>Current Location</th>
                    <th>Recommendation</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($serviceDueAssets)): ?>
                    <tr><td colspan="5" class="empty-cell">No damaged assets in storage. All systems operational!</td></tr>
                <?php else: ?>
                    <?php foreach ($serviceDueAssets as $a): ?>
                        <tr>
                            <td><span class="asset-tag"><?php echo $a['tag']; ?></span></td>
                            <td><strong><?php echo htmlspecialchars($a['name']); ?></strong></td>
                            <td><span class="status-pill status-lost"><?php echo $a['condition_state']; ?></span></td>
                            <td><?php echo htmlspecialchars($a['location']); ?></td>
                            <td><strong class="text-warning">File repair ticket immediately</strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Nearing Retirement -->
    <div class="reports-table-panel card-glow">
        <h4>Assets Nearing Retirement (Over 2 years old)</h4>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Asset Tag</th>
                    <th>Name</th>
                    <th>Acquisition Date</th>
                    <th>Condition</th>
                    <th>Recommended Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($nearingRetirement)): ?>
                    <tr><td colspan="5" class="empty-cell">No assets older than 2 years in inventory.</td></tr>
                <?php else: ?>
                    <?php foreach ($nearingRetirement as $a): ?>
                        <tr>
                            <td><span class="asset-tag"><?php echo htmlspecialchars($a['tag']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($a['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($a['acquisition_date']); ?></td>
                            <td><span class="status-pill status-<?php echo $a['condition_state'] === 'Good' || $a['condition_state'] === 'New' ? 'available' : 'maint'; ?>"><?php echo htmlspecialchars($a['condition_state']); ?></span></td>
                            <td>
                                <?php if ($a['condition_state'] === 'Damaged' || $a['condition_state'] === 'Poor'): ?>
                                    <strong class="text-danger">Recommend Retirement/Disposal</strong>
                                <?php else: ?>
                                    <span class="text-success">Condition stable. Retain.</span>
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

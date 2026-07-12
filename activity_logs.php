<?php
require_once 'header.php';

// Pagination settings
$limit = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}

// Count total logs
if ($user_role === 'admin' || $user_role === 'asset_manager') {
    $total_logs = $db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
} else {
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE employee_id = ?");
    $stmtCount->execute([$user_id]);
    $total_logs = $stmtCount->fetchColumn();
}

$total_pages = ceil($total_logs / $limit);
if ($total_pages < 1) {
    $total_pages = 1;
}
if ($current_page > $total_pages) {
    $current_page = $total_pages;
}
$offset = ($current_page - 1) * $limit;

$start_log = $total_logs > 0 ? $offset + 1 : 0;
$end_log = min($offset + $limit, $total_logs);

// Get logs (Managers see all, Employees see their own activity)
if ($user_role === 'admin' || $user_role === 'asset_manager') {
    $stmtLogs = $db->prepare("
        SELECT l.*, e.name as employee_name, e.email as employee_email 
        FROM activity_logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        ORDER BY l.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmtLogs->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtLogs->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtLogs->execute();
    $logs = $stmtLogs->fetchAll();
} else {
    $stmtLogs = $db->prepare("
        SELECT l.*, e.name as employee_name, e.email as employee_email 
        FROM activity_logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        WHERE l.employee_id = :employee_id 
        ORDER BY l.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmtLogs->bindValue(':employee_id', $user_id, PDO::PARAM_INT);
    $stmtLogs->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtLogs->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtLogs->execute();
    $logs = $stmtLogs->fetchAll();
}
?>

<section id="view-activity-logs" class="app-view">
    <div class="logs-layout">
        <!-- All Logs Card -->
        <div class="logs-card card-glow">
            <h3>Full Audit Trail Logs</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="4" class="empty-cell">No activity records logged yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td style="font-size:0.8rem; color:var(--text-secondary);"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['employee_name'] ?? 'System / Auto'); ?></strong><br>
                                        <span style="font-size:0.72rem; color:var(--text-muted);"><?php echo htmlspecialchars($log['employee_email'] ?? ''); ?></span>
                                    </td>
                                    <td><span class="badge"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                    <td style="font-size:0.82rem;"><?php echo htmlspecialchars($log['details']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div class="pagination-container" style="display: flex; align-items: center; justify-content: space-between; margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color); flex-wrap: wrap; gap: 15px;">
                <div class="pagination-info" style="font-size: 0.85rem; color: var(--text-secondary);">
                    Showing <strong><?php echo $start_log; ?></strong> to <strong><?php echo $end_log; ?></strong> of <strong><?php echo $total_logs; ?></strong> logs
                </div>
                <div class="pagination-controls" style="display: flex; gap: 6px; align-items: center;">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-sm" disabled style="opacity: 0.5; cursor: not-allowed;">&larr; Previous</button>
                    <?php endif; ?>

                    <?php
                    $pages_to_show = [];
                    $range = 1; // 1 page before and after current
                    for ($i = 1; $i <= $total_pages; $i++) {
                        if ($i === 1 || $i === $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                            $pages_to_show[] = $i;
                        }
                    }

                    $last_num = 0;
                    foreach ($pages_to_show as $num) {
                        if ($last_num > 0 && $num - $last_num > 1) {
                            echo '<span style="color: var(--text-muted); align-self: center; padding: 0 4px;">...</span>';
                        }
                        if ($num == $current_page) {
                            echo '<button class="btn btn-primary btn-sm">' . $num . '</button>';
                        } else {
                            echo '<a href="?page=' . $num . '" class="btn btn-secondary btn-sm">' . $num . '</a>';
                        }
                        $last_num = $num;
                    }
                    ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-sm" disabled style="opacity: 0.5; cursor: not-allowed;">Next &rarr;</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>

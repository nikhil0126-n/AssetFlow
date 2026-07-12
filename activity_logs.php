<?php
require_once 'header.php';

// Get logs (Managers see all, Employees see their own activity)
if ($user_role === 'admin' || $user_role === 'asset_manager') {
    $logs = $db->query("
        SELECT l.*, e.name as employee_name, e.email as employee_email 
        FROM activity_logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        ORDER BY l.created_at DESC 
        LIMIT 50
    ")->fetchAll();
} else {
    $stmtLogs = $db->prepare("
        SELECT l.*, e.name as employee_name, e.email as employee_email 
        FROM activity_logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        WHERE l.employee_id = ? 
        ORDER BY l.created_at DESC 
        LIMIT 50
    ");
    $stmtLogs->execute([$user_id]);
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
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>

<?php
require_once 'header.php';

// Pagination settings
$limit = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Get logs (Managers see all, Employees see their own activity)
if ($user_role === 'admin' || $user_role === 'asset_manager') {
    // Get total rows for pagination
    $total_stmt = $db->query("SELECT COUNT(*) FROM activity_logs");
    $total_rows = $total_stmt->fetchColumn();

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
    // Get total rows for pagination
    $total_stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE employee_id = ?");
    $total_stmt->execute([$user_id]);
    $total_rows = $total_stmt->fetchColumn();

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

$total_pages = ceil($total_rows / $limit);
?>

<style>
    .logs-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.05));
    }
    .logs-header h3 {
        margin: 0;
        font-size: 1.4rem;
        color: var(--text-primary);
        font-weight: 600;
        letter-spacing: -0.5px;
    }
    .badge {
        padding: 5px 10px;
        border-radius: 6px;
        background: rgba(99, 102, 241, 0.15);
        color: #6366f1;
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
    }
    
    /* Table Redesign */
    .logs-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .logs-table th {
        padding: 12px 16px;
        text-align: left;
        color: var(--text-muted);
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--border-color, rgba(0,0,0,0.05));
    }
    .logs-table td {
        padding: 14px 16px;
        vertical-align: middle;
        border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.05));
    }
    .log-row {
        transition: background-color 0.2s ease;
    }
    .log-row:hover {
        background-color: var(--hover-bg, rgba(0,0,0,0.02));
    }
    
    .log-timestamp {
        font-size: 0.85rem;
        color: var(--text-secondary);
        white-space: nowrap;
    }
    .log-user {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .log-user-name {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--text-primary);
    }
    .log-user-email {
        font-size: 0.75rem;
        color: var(--text-muted);
    }
    .log-details {
        font-size: 0.88rem;
        color: var(--text-secondary);
        line-height: 1.4;
    }
    
    /* Pagination Styles */
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding-top: 15px;
    }
    .page-info {
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    .pagination-controls {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .btn-pagination {
        padding: 6px 14px;
        font-size: 0.85rem;
        font-weight: 500;
        border: 1px solid var(--border-color, rgba(0,0,0,0.1));
        background: transparent;
        color: var(--text-primary);
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-pagination:hover:not(.disabled) {
        background: var(--color-primary, #4f46e5);
        border-color: var(--color-primary, #4f46e5);
        color: #fff;
    }
    .btn-pagination.disabled {
        opacity: 0.4;
        cursor: not-allowed;
        background: var(--disabled-bg, rgba(0,0,0,0.05));
    }
</style>

<section id="view-activity-logs" class="app-view">
    <div class="logs-layout" style="width: 100%;">
        <div class="logs-card card-glow" style="padding: 24px;">
            
            <div class="logs-header">
                <h3>System Audit & Activity Logs</h3>
                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                    Total Records: <?php echo number_format($total_rows); ?>
                </span>
            </div>

            <div class="table-responsive">
                <table class="logs-table">
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
                            <tr>
                                <td colspan="4" class="empty-cell" style="padding: 40px; text-align: center; color: var(--text-muted); font-style: italic;">
                                    No activity records logged yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="log-row">
                                    <td>
                                        <div class="log-timestamp">
                                            <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($log['created_at']))); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="log-user">
                                            <span class="log-user-name"><?php echo htmlspecialchars($log['employee_name'] ?? 'System / Auto'); ?></span>
                                            <?php if (!empty($log['employee_email'])): ?>
                                                <span class="log-user-email"><?php echo htmlspecialchars($log['employee_email']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge"><?php echo htmlspecialchars($log['action']); ?></span>
                                    </td>
                                    <td>
                                        <div class="log-details">
                                            <?php echo htmlspecialchars($log['details']); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="page-info">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> entries
                </div>
                <div class="pagination-controls">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="btn-pagination">&laquo; Previous</a>
                    <?php else: ?>
                        <span class="btn-pagination disabled">&laquo; Previous</span>
                    <?php endif; ?>
                    
                    <span style="font-size: 0.85rem; color: var(--text-primary); margin: 0 10px;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="btn-pagination">Next &raquo;</a>
                    <?php else: ?>
                        <span class="btn-pagination disabled">Next &raquo;</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>

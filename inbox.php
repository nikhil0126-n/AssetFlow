<?php
require_once 'header.php';

// Fetch all notifications targeting user
$stmtNotifications = $db->prepare("
    SELECT * FROM notifications 
    WHERE employee_id = ? OR employee_id IS NULL 
    ORDER BY created_at DESC 
    LIMIT 100
");
$stmtNotifications->execute([$user_id]);
$inbox = $stmtNotifications->fetchAll();
?>

<section id="view-inbox" class="app-view">
    <div class="logs-layout" style="display: block;">
        <div class="card-glow" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 24px;">
            <div class="card-header-actions" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-family: var(--font-outfit); font-size: 1.25rem;">Alerts Inbox</h3>
                <button class="btn btn-secondary btn-sm" id="btn-inbox-clear-all" onclick="clearAllInboxNotifications()">Mark All Read</button>
            </div>
            
            <div class="inbox-list" style="display:flex; flex-direction:column; gap:12px;">
                <?php if (empty($inbox)): ?>
                    <div class="empty-state">Your alerts inbox is currently empty!</div>
                <?php else: ?>
                    <?php foreach ($inbox as $n): ?>
                        <div class="notification-row <?php echo !$n['is_read'] ? 'unread-row' : ''; ?>" 
                             data-id="<?php echo $n['id']; ?>" 
                             style="background: rgba(255,255,255,0.015); border: 1px solid var(--border-color); border-left: 4px solid <?php echo $n['type'] === 'danger' ? 'var(--color-danger)' : ($n['type'] === 'warning' ? 'var(--color-warning)' : 'var(--color-primary)'); ?>; border-radius: 8px; padding: 16px; display:flex; justify-content:space-between; align-items:center; transition: var(--transition); <?php echo !$n['is_read'] ? 'box-shadow: 0 0 10px rgba(99, 102, 241, 0.05); background: rgba(99, 102, 241, 0.02);' : ''; ?>">
                            <div>
                                <strong style="display:block; font-size:0.95rem; margin-bottom:4px;"><?php echo htmlspecialchars($n['title']); ?></strong>
                                <span style="font-size:0.85rem; color:var(--text-secondary); display:block;"><?php echo htmlspecialchars($n['message']); ?></span>
                                <span style="font-size:0.72rem; color:var(--text-muted); display:block; margin-top:6px;">⌚ Received: <?php echo htmlspecialchars($n['created_at']); ?></span>
                            </div>
                            <?php if (!$n['is_read']): ?>
                                <button class="btn btn-secondary btn-sm btn-mark-read" onclick="markReadInbox(this, <?php echo $n['id']; ?>)">Mark Read</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
    async function markReadInbox(btn, id) {
        try {
            const res = await fetch(`api.php?action=mark_notification_read`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: id })
            });
            const data = await res.json();
            if (data.success) {
                const row = btn.closest('.notification-row');
                row.classList.remove('unread-row');
                row.style.background = 'rgba(255,255,255,0.015)';
                row.style.boxShadow = 'none';
                btn.remove();
                
                // Update badge in header if visible
                const badge = document.getElementById('notif-badge-count');
                if (badge) {
                    const count = parseInt(badge.textContent) - 1;
                    if (count > 0) {
                        badge.textContent = count;
                    } else {
                        badge.classList.add('hidden');
                    }
                }
            }
        } catch(e){}
    }

    async function clearAllInboxNotifications() {
        try {
            const res = await fetch(`api.php?action=mark_notification_read`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: 'all' })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            }
        } catch(e){}
    }
</script>
<?php require_once 'footer.php'; ?>

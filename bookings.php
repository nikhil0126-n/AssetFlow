<?php
require_once 'header.php';

// Fetch bookable resources
$resources = $db->query("SELECT * FROM assets WHERE is_shared = 1 AND status = 'Available'")->fetchAll();

// Get selected resource
$selected_id = isset($_GET['resource_id']) ? (int)$_GET['resource_id'] : (count($resources) > 0 ? $resources[0]['id'] : null);
$selected_resource = null;

foreach ($resources as $res) {
    if ($res['id'] == $selected_id) {
        $selected_resource = $res;
        break;
    }
}

// Fetch bookings for selected resource
$bookings = [];
if ($selected_id) {
    $stmtBookings = $db->prepare("
        SELECT b.*, e.name as employee_name, e.email as employee_email 
        FROM bookings b
        JOIN employees e ON b.employee_id = e.id
        WHERE b.asset_id = ? AND b.status != 'Cancelled'
        ORDER BY b.booking_date ASC, b.start_time ASC
    ");
    $stmtBookings->execute([$selected_id]);
    $bookings = $stmtBookings->fetchAll();
}
?>

<section id="view-bookings" class="app-view">
    <div class="bookings-grid-layout">
        <!-- Resource Selector Side -->
        <div class="resource-selector-card card-glow">
            <h3>Select Resource</h3>
            <div class="bookable-resources-list">
                <?php if (empty($resources)): ?>
                    <div class="empty-state">No shared bookable resources registered.</div>
                <?php else: ?>
                    <?php foreach ($resources as $res): ?>
                        <div class="resource-item <?php echo $selected_id == $res['id'] ? 'active' : ''; ?>" onclick="window.location.href='bookings.php?resource_id=<?php echo $res['id']; ?>'">
                            <h4><?php echo htmlspecialchars($res['name']); ?></h4>
                            <span>Tag: <strong><?php echo htmlspecialchars($res['tag']); ?></strong> | Location: <?php echo htmlspecialchars($res['location']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Calendar/Schedule Side -->
        <div class="calendar-card card-glow">
            <div class="calendar-header">
                <h3>
                    <?php if ($selected_resource): ?>
                        📅 Bookings for <strong><?php echo htmlspecialchars($selected_resource['name']); ?> (<?php echo htmlspecialchars($selected_resource['tag']); ?>)</strong>
                    <?php else: ?>
                        No Resource Selected
                    <?php endif; ?>
                </h3>
                <?php if ($selected_resource): ?>
                    <button class="btn btn-primary btn-sm" id="btn-open-book-modal" onclick="openBookModal(<?php echo $selected_resource['id']; ?>)">Book This Resource</button>
                <?php endif; ?>
            </div>
            
            <div class="calendar-view">
                <?php if (!$selected_resource): ?>
                    <div class="calendar-empty">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <p>Select a bookable table, vehicle, or room on the left to view calendar slots and raise bookings.</p>
                    </div>
                <?php elseif (empty($bookings)): ?>
                    <div class="calendar-empty">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <p>No active reservations slot booked. Feel free to book a slot!</p>
                    </div>
                <?php else: ?>
                    <div class="calendar-schedule">
                        <?php foreach ($bookings as $b): ?>
                            <div class="schedule-row">
                                <div>
                                    <span class="schedule-time">⏱ <?php echo htmlspecialchars($b['booking_date']); ?> | <?php echo htmlspecialchars(substr($b['start_time'], 0, 5)); ?> - <?php echo htmlspecialchars(substr($b['end_time'], 0, 5)); ?></span>
                                    <div class="schedule-user" style="margin-top: 4px;">Reserved by: <strong><?php echo htmlspecialchars($b['employee_name']); ?></strong> (<?php echo htmlspecialchars($b['employee_email']); ?>)</div>
                                </div>
                                <?php if ($user_role === 'admin' || $user_role === 'asset_manager' || $b['employee_id'] == $user_id): ?>
                                    <button class="btn btn-danger btn-sm btn-cancel-booking" data-id="<?php echo $b['id']; ?>">Cancel Reservation</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
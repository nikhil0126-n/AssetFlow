<?php
require_once 'header.php';

// Fetch options for filters
$categories = $db->query("SELECT * FROM categories")->fetchAll();

// Build filter parameters
$search = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$bookable_filter = $_GET['bookable'] ?? '';

// Build Query with strict Role-Based access filtering
$sql = "SELECT a.*, c.name as category_name, 
        e.name as holder_name, d.name as dept_holder_name
        FROM assets a
        JOIN categories c ON a.category_id = c.id
        LEFT JOIN allocations al ON al.asset_id = a.id AND al.status = 'Active'
        LEFT JOIN employees e ON al.employee_id = e.id
        LEFT JOIN departments d ON al.department_id = d.id
        WHERE 1=1";

$params = [];

// Apply role-based data visibility boundary
if ($user_role === 'employee') {
    // Employees only see their own checked-out assets or shared bookable tools
    $sql .= " AND (a.is_shared = 1 OR al.employee_id = ?)";
    $params[] = $user_id;
} else if ($user_role === 'dept_head' && $user_dept) {
    // Department heads see their department's assets, their own assets, and shared ones
    $sql .= " AND (a.is_shared = 1 OR al.employee_id = ? OR al.department_id = ? OR e.department_id = ?)";
    $params[] = $user_id;
    $params[] = $user_dept;
    $params[] = $user_dept;
}

// Apply UI search filters
if ($search !== '') {
    $sql .= " AND (a.tag LIKE ? OR a.serial_number LIKE ? OR a.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter !== '') {
    $sql .= " AND a.category_id = ?";
    $params[] = (int)$category_filter;
}

if ($status_filter !== '') {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}

if ($bookable_filter !== '') {
    $sql .= " AND a.is_shared = ?";
    $params[] = (int)$bookable_filter;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll();

// Helper to determine status badges
function get_status_class($status) {
    $map = [
        'Available' => 'available', 'Allocated' => 'allocated', 'Reserved' => 'reserved',
        'Under Maintenance' => 'maint', 'Lost' => 'lost', 'Retired' => 'retired', 'Disposed' => 'disposed'
    ];
    return $map[$status] ?? 'retired';
}
?>

<section id="view-assets" class="app-view">
    <form action="assets.php" method="GET" class="filter-bar">
        <div class="search-input-wrapper">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" name="search" id="asset-search" class="form-control" placeholder="Search by tag, name, serial..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="button" class="btn btn-secondary" id="btn-scan-qr" title="Scan Asset QR Code" style="display: flex; align-items: center; justify-content: center; width: 44px; height: 38px; padding: 0; min-width: 44px; margin-right: 8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </button>
        <select name="category" id="asset-filter-category" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" id="asset-filter-status" class="form-control">
            <option value="">All Statuses</option>
            <option value="Available" <?php echo $status_filter === 'Available' ? 'selected' : ''; ?>>Available</option>
            <option value="Allocated" <?php echo $status_filter === 'Allocated' ? 'selected' : ''; ?>>Allocated</option>
            <option value="Reserved" <?php echo $status_filter === 'Reserved' ? 'selected' : ''; ?>>Reserved</option>
            <option value="Under Maintenance" <?php echo $status_filter === 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
            <option value="Lost" <?php echo $status_filter === 'Lost' ? 'selected' : ''; ?>>Lost</option>
            <option value="Retired" <?php echo $status_filter === 'Retired' ? 'selected' : ''; ?>>Retired</option>
            <option value="Disposed" <?php echo $status_filter === 'Disposed' ? 'selected' : ''; ?>>Disposed</option>
        </select>
        <select name="bookable" id="asset-filter-bookable" class="form-control">
            <option value="">All Types</option>
            <option value="0" <?php echo $bookable_filter === '0' ? 'selected' : ''; ?>>Standard Assigned Assets</option>
            <option value="1" <?php echo $bookable_filter === '1' ? 'selected' : ''; ?>>Shared Bookable Resources</option>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="assets.php" class="btn btn-secondary">Reset</a>
    </form>

    <div class="assets-grid">
        <?php if (empty($assets)): ?>
            <div class="empty-state" style="grid-column: 1/-1">No assets found matching filters.</div>
        <?php else: ?>
            <?php foreach ($assets as $asset): ?>
                <div class="asset-card btn-detail-trigger" data-id="<?php echo $asset['id']; ?>">
                    <div class="asset-card-header">
                        <div>
                            <h3><?php echo htmlspecialchars($asset['name']); ?></h3>
                            <span class="asset-tag" style="margin-top:4px; display:inline-block;"><?php echo htmlspecialchars($asset['tag']); ?></span>
                        </div>
                        <span class="status-pill status-<?php echo get_status_class($asset['status']); ?>"><?php echo htmlspecialchars($asset['status']); ?></span>
                    </div>
                    
                    <div class="asset-meta-row" style="margin-top: 8px;">
                        <span class="asset-meta-label">Category:</span>
                        <span><?php echo htmlspecialchars($asset['category_name']); ?></span>
                    </div>
                    <div class="asset-meta-row">
                        <span class="asset-meta-label">Location:</span>
                        <span><?php echo htmlspecialchars($asset['location']); ?></span>
                    </div>
                    <div class="asset-meta-row">
                        <span class="asset-meta-label">Condition:</span>
                        <span><?php echo htmlspecialchars($asset['condition_state']); ?></span>
                    </div>
                    <div class="asset-meta-row">
                        <span class="asset-meta-label">Holder:</span>
                        <strong class="<?php echo ($asset['holder_name'] || $asset['dept_holder_name']) ? 'text-success' : 'text-secondary'; ?>"><?php echo htmlspecialchars($asset['holder_name'] ?? $asset['dept_holder_name'] ?? 'None (In Storage)'); ?></strong>
                    </div>

                    <div class="asset-card-footer">
                        <span>$<?php echo number_format($asset['acquisition_cost'], 2); ?></span>
                        <?php if ($asset['is_shared']): ?>
                            <span class="shared-badge">Shared</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'footer.php'; ?>

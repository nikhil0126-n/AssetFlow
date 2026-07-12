<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
$theme = $_COOKIE['theme'] ?? 'dark';

// Auth Guard
$current_page = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['user_id']) && $current_page !== 'login.php' && $current_page !== 'signup.php') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'employee';
$user_name = $_SESSION['name'] ?? 'Loading...';
$user_dept = $_SESSION['department_id'] ?? null;

// Enforce strict page-level guards
if (isset($_SESSION['user_id'])) {
    // Standard employees are blocked from Audits, Reports, and Org Setup
    if ($user_role === 'employee') {
        if (in_array($current_page, ['org_setup.php', 'reports.php', 'audits.php'])) {
            header('Location: dashboard.php?msg=Access+denied.+Insufficient+permissions.&type=error');
            exit;
        }
    }
    // Dept heads are blocked from Org Setup and Reports, but can access Audits if they are assigned as auditors
    if ($user_role === 'dept_head') {
        if (in_array($current_page, ['org_setup.php', 'reports.php'])) {
            header('Location: dashboard.php?msg=Access+denied.+Insufficient+permissions.&type=error');
            exit;
        }
        if ($current_page === 'audits.php') {
            // Check if they are auditor for any active cycle
            $stmtAudCheck = $db->prepare("SELECT COUNT(*) FROM audit_auditors WHERE employee_id = ?");
            $stmtAudCheck->execute([$user_id]);
            if ($stmtAudCheck->fetchColumn() == 0) {
                header('Location: dashboard.php?msg=Access+denied.+You+are+not+assigned+as+an+auditor.&type=error');
                exit;
            }
        }
    }
}

// Determine active page
function is_active($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}

// Fetch unread notification counts
$unread_count = 0;
if ($user_id) {
    $stmtNotif = $db->prepare("SELECT COUNT(*) FROM notifications WHERE (employee_id = ? OR employee_id IS NULL) AND is_read = 0");
    $stmtNotif->execute([$user_id]);
    $unread_count = $stmtNotif->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AssetFlow - Enterprise Asset & Resource Management System</title>
    <meta name="description" content="AssetFlow simplifies enterprise asset tracking, resource booking, maintenance workflow approvals, and audit cycles.">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Stylesheet -->
    <link rel="stylesheet" href="css/style.css?v=2.2">
</head>
<body class="<?php echo $theme === 'light' ? 'light-theme' : 'dark-theme'; ?>">
    <!-- Toast notifications overlay -->
    <div id="toast-container" class="toast-container"></div>

    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="logo-icon"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                <span>AssetFlow</span>
            </div>

            <!-- Profile Widget -->
            <div class="profile-widget" onclick="window.location.href='profile.php'" style="cursor:pointer;">
                <div class="avatar" id="user-avatar"><?php echo substr(htmlspecialchars($user_name), 0, 1); ?></div>
                <div class="profile-info">
                    <h4 id="user-display-name"><?php echo htmlspecialchars($user_name); ?></h4>
                    <span id="user-role-badge" class="badge"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user_role))); ?></span>
                </div>
            </div>

            <!-- Navigation Menu Links -->
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item <?php echo is_active('dashboard.php'); ?>">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                    <span>Dashboard</span>
                </a>
                
                <a href="profile.php" class="nav-item <?php echo is_active('profile.php'); ?>">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <span>My Profile</span>
                </a>

                <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                <a href="org_setup.php" class="nav-item <?php echo is_active('org_setup.php'); ?>">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span>Organization Setup</span>
                </a>
                <?php endif; ?>

                <a href="assets.php" class="nav-item <?php echo is_active('assets.php'); ?>">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    <span>Assets Directory</span>
                </a>
                
                <a href="allocations.php" class="nav-item <?php echo is_active('allocations.php'); ?>">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>
                    <span>Allocations & Transfers</span>
                </a>
                
                <a href="bookings.php" class="nav-item <?php echo is_active('bookings.php'); ?>">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <span>Resource Bookings</span>
                </a>
                
                <a href="maintenance.php" class="nav-item <?php echo is_active('maintenance.php'); ?>">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                    <span>Maintenance</span>
                </a>
                
                <?php if ($user_role === 'admin' || $user_role === 'asset_manager' || ($user_role === 'dept_head' && $unread_count > 0)): ?>
                <!-- Dept heads see Audits tab only if they have pending tasks -->
                <a href="audits.php" class="nav-item <?php echo is_active('audits.php'); ?>">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Verification Audits</span>
                </a>
                <?php endif; ?>
                
                <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                <a href="reports.php" class="nav-item <?php echo is_active('reports.php'); ?>">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    <span>Reports & Analytics</span>
                </a>
                <?php endif; ?>

                <a href="activity_logs.php" class="nav-item <?php echo is_active('activity_logs.php'); ?>">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span>Logs & Notifications</span>
                </a>
            </nav>

            <!-- Bottom Actions -->
            <div class="sidebar-footer">
                <a href="logout.php" id="btn-logout" class="btn btn-outline btn-block">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    <span>Log Out</span>
                </a>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="main-wrapper">
            <header class="header">
                <div class="header-left">
                    <h1 id="screen-title" class="screen-title"><?php echo htmlspecialchars(str_replace(['.php', '_'], ['', ' '], ucwords($current_page))); ?></h1>
                </div>
                
                <div class="header-right">
                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <button class="btn btn-secondary btn-sm" id="btn-quick-booking">Book Resource</button>
                        <button class="btn btn-secondary btn-sm" id="btn-quick-maint">Raise Ticket</button>
                        <?php if ($user_role === 'admin' || $user_role === 'asset_manager'): ?>
                        <button class="btn btn-primary btn-sm" id="btn-quick-register">Register Asset</button>
                        <?php endif; ?>
                    </div>

                    <!-- Theme Switcher -->
                    <button class="icon-btn" id="btn-theme-toggle" aria-label="Toggle theme" style="margin-right: 12px; display:flex; align-items:center; justify-content:center;">
                        <svg class="sun-icon <?php echo $theme === 'light' ? '' : 'hidden'; ?>" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="4.22" x2="19.78" y2="5.64"/></svg>
                        <svg class="moon-icon <?php echo $theme === 'light' ? 'hidden' : ''; ?>" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div class="notification-center">
                        <button class="icon-btn" id="btn-notifications" aria-label="Notifications center">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            <span class="badge badge-pulse <?php echo $unread_count > 0 ? '' : 'hidden'; ?>" id="notif-badge-count"><?php echo $unread_count; ?></span>
                        </button>
                        <div class="notifications-dropdown hidden" id="notifications-dropdown">
                            <div class="dropdown-header">
                                <h3>Notifications</h3>
                                <button id="btn-mark-all-read" class="text-btn">Clear All</button>
                            </div>
                            <div class="dropdown-list" id="notifications-list">
                                <div class="empty-state">Loading notifications...</div>
                            </div>
                            <div style="padding: 10px; border-top: 1px solid var(--border-color); text-align: center;">
                                <a href="inbox.php" class="text-link" style="font-size: 0.8rem; font-weight: 600;">Go to Alerts Inbox</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content-viewport" id="content-viewport">
                <!-- Check if redirect msg is present in URL -->
                <?php if (isset($_GET['msg'])): ?>
                    <script>
                        localStorage.setItem('toast_msg', <?php echo json_encode($_GET['msg']); ?>);
                        localStorage.setItem('toast_type', <?php echo json_encode($_GET['type'] ?? 'success'); ?>);
                        // Strip query params to clean address bar
                        window.history.replaceState({}, document.title, window.location.pathname);
                    </script>
                <?php endif; ?>

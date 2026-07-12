<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Helper to check if request is POST/JSON and parse it
$data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    if ($json) {
        $data = json_decode($json, true) ?? [];
    } else {
        $data = $_POST;
    }
}

// Get action from query parameter or post body
$action = $_GET['action'] ?? $data['action'] ?? '';

if (!$action) {
    echo json_encode(['error' => 'No action specified']);
    exit;
}

// Global DB connection
$db = getDBConnection();

// Simple Auth helpers
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Unauthorized. Please log in.']);
        exit;
    }
}

function require_role($roles) {
    require_login();
    if (!in_array($_SESSION['role'], (array)$roles)) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'Access denied. Insufficient permissions.']);
        exit;
    }
}

// Helper to log system events
function log_activity($db, $employee_id, $action_name, $details = '') {
    $stmt = $db->prepare("INSERT INTO activity_logs (employee_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$employee_id, $action_name, $details]);
}

// Helper to create notifications
function create_notification($db, $employee_id, $title, $message, $type = 'info') {
    $stmt = $db->prepare("INSERT INTO notifications (employee_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$employee_id, $title, $message, $type]);
}

switch ($action) {
    // ------------------------------------------
    // 1. AUTHENTICATION ENDPOINTS
    // ------------------------------------------
    case 'auth_check':
        if (isset($_SESSION['user_id'])) {
            echo json_encode([
                'logged_in' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'name' => $_SESSION['name'],
                    'email' => $_SESSION['email'],
                    'role' => $_SESSION['role'],
                    'department_id' => $_SESSION['department_id']
                ]
            ]);
        } else {
            echo json_encode(['logged_in' => false]);
        }
        break;

    case 'login':
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            echo json_encode(['error' => 'Email and password are required.']);
            exit;
        }

        $stmt = $db->prepare("SELECT e.*, d.name as department_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id WHERE e.email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'Active') {
                echo json_encode(['error' => 'Your account is deactivated.']);
                exit;
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['department_id'] = $user['department_id'];

            log_activity($db, $user['id'], 'User Login', 'Logged into the system.');

            echo json_encode([
                'success' => 'Logged in successfully',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'department_id' => $user['department_id']
                ]
            ]);
        } else {
            echo json_encode(['error' => 'Invalid email or password.']);
        }
        break;

    case 'signup':
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $department_id = $data['department_id'] ?? null;

        if (!$name || !$email || !$password) {
            echo json_encode(['error' => 'Name, email and password are required.']);
            exit;
        }

        // Check if email already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['error' => 'An account with this email already exists.']);
            exit;
        }

        $hashedPass = password_hash($password, PASSWORD_DEFAULT);
        
        // Forced "employee" role, no self-elevation
        $stmt = $db->prepare("INSERT INTO employees (name, email, password, department_id, role, status) VALUES (?, ?, ?, ?, 'employee', 'Active')");
        $stmt->execute([$name, $email, $hashedPass, $department_id ? (int)$department_id : null]);
        $newId = $db->lastInsertId();

        log_activity($db, $newId, 'User Signup', 'Created an Employee account.');
        // Notify asset managers of a new signup
        create_notification($db, null, 'New User Signed Up', "$name ($email) registered an Employee account.", 'info');

        echo json_encode(['success' => 'Account created successfully! Please log in.']);
        break;

    case 'logout':
        if (isset($_SESSION['user_id'])) {
            log_activity($db, $_SESSION['user_id'], 'User Logout', 'Logged out of the system.');
        }
        session_destroy();
        echo json_encode(['success' => 'Logged out successfully']);
        break;

         // 2. DASHBOARD DATA

    case 'get_dashboard':
        require_login();
        $role = $_SESSION['role'];
        $emp_id = $_SESSION['user_id'];
        $dept_id = $_SESSION['department_id'];

        // KPIs
        $kpi = [];
        $kpi['assets_available'] = $db->query("SELECT COUNT(*) FROM assets WHERE status = 'Available'")->fetchColumn();
        $kpi['assets_allocated'] = $db->query("SELECT COUNT(*) FROM assets WHERE status = 'Allocated'")->fetchColumn();
        $kpi['maintenance_today'] = $db->query("SELECT COUNT(*) FROM assets WHERE status = 'Under Maintenance'")->fetchColumn();
        
        // Active bookings
        $kpi['active_bookings'] = $db->query("SELECT COUNT(*) FROM bookings WHERE booking_date = CURRENT_DATE() AND status != 'Cancelled'")->fetchColumn();
        
        // Pending transfers
        if ($role === 'admin' || $role === 'asset_manager') {
            $kpi['pending_transfers'] = $db->query("SELECT COUNT(*) FROM transfers WHERE status = 'Pending'")->fetchColumn();
        } else if ($role === 'dept_head' && $dept_id) {
            // Transfers pending approval that target this department head's department
            $kpi['pending_transfers'] = $db->query("SELECT COUNT(*) FROM transfers WHERE status = 'Pending' AND to_department_id = " . (int)$dept_id)->fetchColumn();
        } else {
            $kpi['pending_transfers'] = $db->query("SELECT COUNT(*) FROM transfers WHERE status = 'Pending' AND requested_by = " . (int)$emp_id)->fetchColumn();
        }

        // Overdue returns
        $kpi['overdue_returns'] = $db->query("SELECT COUNT(*) FROM allocations WHERE status = 'Overdue' OR (status = 'Active' AND expected_return_date < CURRENT_DATE())")->fetchColumn();

        // 1. Retrieve overdue returns items
        $overdue_items = $db->query("
            SELECT a.tag, a.name as asset_name, e.name as holder_name, e.email as holder_email, al.expected_return_date, al.id as allocation_id 
            FROM allocations al
            JOIN assets a ON al.asset_id = a.id
            LEFT JOIN employees e ON al.employee_id = e.id
            WHERE al.status = 'Overdue' OR (al.status = 'Active' AND al.expected_return_date < CURRENT_DATE())
            ORDER BY al.expected_return_date ASC
        ")->fetchAll();

        // 2. Pending transfers list
        $pending_transfers = [];
        if ($role === 'admin' || $role === 'asset_manager') {
            $pending_transfers = $db->query("
                SELECT t.id, a.tag, a.name as asset_name, e_from.name as from_employee, e_to.name as to_employee, d_to.name as to_department, t.request_date
                FROM transfers t
                JOIN assets a ON t.asset_id = a.id
                LEFT JOIN employees e_from ON t.from_employee_id = e_from.id
                LEFT JOIN employees e_to ON t.to_employee_id = e_to.id
                LEFT JOIN departments d_to ON t.to_department_id = d_to.id
                WHERE t.status = 'Pending'
            ")->fetchAll();
        } else if ($role === 'dept_head' && $dept_id) {
            $pending_transfers = $db->query("
                SELECT t.id, a.tag, a.name as asset_name, e_from.name as from_employee, e_to.name as to_employee, d_to.name as to_department, t.request_date
                FROM transfers t
                JOIN assets a ON t.asset_id = a.id
                LEFT JOIN employees e_from ON t.from_employee_id = e_from.id
                LEFT JOIN employees e_to ON t.to_employee_id = e_to.id
                LEFT JOIN departments d_to ON t.to_department_id = d_to.id
                WHERE t.status = 'Pending' AND (t.to_department_id = " . (int)$dept_id . " OR a.category_id IN (SELECT id FROM categories))
            ")->fetchAll();
        }

        // 3. Pending Maintenance requests (for Managers) or User's raised tickets
        $maintenance_actions = [];
        if ($role === 'admin' || $role === 'asset_manager') {
            $maintenance_actions = $db->query("
                SELECT m.id, a.tag, a.name as asset_name, m.priority, m.description, m.created_at
                FROM maintenance_requests m
                JOIN assets a ON m.asset_id = a.id
                WHERE m.status = 'Pending'
            ")->fetchAll();
        } else {
            $maintenance_actions = $db->query("
                SELECT m.id, a.tag, a.name as asset_name, m.priority, m.status, m.created_at
                FROM maintenance_requests m
                JOIN assets a ON m.asset_id = a.id
                WHERE m.reported_by = " . (int)$emp_id . " AND m.status != 'Resolved'
            ")->fetchAll();
        }

        // Recent Notifications
        $notifications = $db->query("
            SELECT * FROM notifications 
            WHERE employee_id = " . (int)$emp_id . " OR employee_id IS NULL 
            ORDER BY created_at DESC LIMIT 5
        ")->fetchAll();

        echo json_encode([
            'kpis' => $kpi,
            'overdue_items' => $overdue_items,
            'pending_transfers' => $pending_transfers,
            'maintenance_actions' => $maintenance_actions,
            'notifications' => $notifications
        ]);
        break;

            // 3. ORGANIZATION SETUP (ADMIN ONLY)

    case 'get_org_setup':
        require_role(['admin', 'asset_manager']); // Managers can view, Admin modifies

        // Departments
        $departments = $db->query("
            SELECT d.*, e.name as head_name, p.name as parent_name 
            FROM departments d 
            LEFT JOIN employees e ON d.head_id = e.id
            LEFT JOIN departments p ON d.parent_id = p.id
        ")->fetchAll();

        // Categories
        $categories = $db->query("SELECT * FROM categories")->fetchAll();

        // Employees
        $employees = $db->query("
            SELECT e.id, e.name, e.email, e.role, e.status, d.name as department_name, e.department_id 
            FROM employees e 
            LEFT JOIN departments d ON e.department_id = d.id
        ")->fetchAll();

        echo json_encode([
            'departments' => $departments,
            'categories' => $categories,
            'employees' => $employees
        ]);
        break;

    case 'add_department':
        require_role('admin');
        $name = trim($data['name'] ?? '');
        $parent_id = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        $status = $data['status'] ?? 'Active';

        if (!$name) {
            echo json_encode(['error' => 'Department name is required']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO departments (name, parent_id, status) VALUES (?, ?, ?)");
        $stmt->execute([$name, $parent_id, $status]);
        
        log_activity($db, $_SESSION['user_id'], 'Created Department', "Created department '$name'");
        echo json_encode(['success' => 'Department created successfully']);
        break;

    case 'edit_department':
        require_role('admin');
        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $parent_id = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        $status = $data['status'] ?? 'Active';
        $head_id = !empty($data['head_id']) ? (int)$data['head_id'] : null;

        if (!$id || !$name) {
            echo json_encode(['error' => 'ID and name are required']);
            exit;
        }

        // Prevent self-parenting loop
        if ($parent_id === $id) {
            echo json_encode(['error' => 'A department cannot be its own parent.']);
            exit;
        }

        $stmt = $db->prepare("UPDATE departments SET name = ?, parent_id = ?, status = ?, head_id = ? WHERE id = ?");
        $stmt->execute([$name, $parent_id, $status, $head_id, $id]);

        // If head_id is assigned, promote employee to dept_head automatically if currently employee
        if ($head_id) {
            $stmtRole = $db->prepare("SELECT role FROM employees WHERE id = ?");
            $stmtRole->execute([$head_id]);
            $currentRole = $stmtRole->fetchColumn();
            if ($currentRole === 'employee') {
                $db->prepare("UPDATE employees SET role = 'dept_head', department_id = ? WHERE id = ?")->execute([$id, $head_id]);
            }
        }

        log_activity($db, $_SESSION['user_id'], 'Updated Department', "Updated department ID $id ($name)");
        echo json_encode(['success' => 'Department updated successfully']);
        break;

    case 'add_category':
        require_role('admin');
        $name = trim($data['name'] ?? '');
        $custom_fields = $data['custom_fields'] ?? []; // Array of attributes

        if (!$name) {
            echo json_encode(['error' => 'Category name is required']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO categories (name, custom_fields) VALUES (?, ?)");
        $stmt->execute([$name, json_encode($custom_fields)]);

        log_activity($db, $_SESSION['user_id'], 'Created Category', "Created asset category '$name'");
        echo json_encode(['success' => 'Category created successfully']);
        break;

    case 'edit_category':
        require_role('admin');
        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $custom_fields = $data['custom_fields'] ?? [];

        if (!$id || !$name) {
            echo json_encode(['error' => 'Category ID and name are required']);
            exit;
        }

        $stmt = $db->prepare("UPDATE categories SET name = ?, custom_fields = ? WHERE id = ?");
        $stmt->execute([$name, json_encode($custom_fields), $id]);

        log_activity($db, $_SESSION['user_id'], 'Updated Category', "Updated asset category ID $id ($name)");
        echo json_encode(['success' => 'Category updated successfully']);
        break;

    case 'promote_employee':
        require_role('admin');
        $employee_id = (int)($data['employee_id'] ?? 0);
        $role = $data['role'] ?? 'employee';
        $department_id = !empty($data['department_id']) ? (int)$data['department_id'] : null;
        $status = $data['status'] ?? 'Active';

        if (!$employee_id) {
            echo json_encode(['error' => 'Employee ID is required']);
            exit;
        }

        $stmt = $db->prepare("UPDATE employees SET role = ?, department_id = ?, status = ? WHERE id = ?");
        $stmt->execute([$role, $department_id, $status, $employee_id]);

        // If status deactivated, notify or clean up?
        $stmtName = $db->prepare("SELECT name FROM employees WHERE id = ?");
        $stmtName->execute([$employee_id]);
        $empName = $stmtName->fetchColumn();

        log_activity($db, $_SESSION['user_id'], 'Employee Role Update', "Promoted/Updated role of $empName to $role");
        create_notification($db, $employee_id, 'Role Updated', "Your role has been updated to " . ucfirst($role) . " by the Admin.", 'info');

        echo json_encode(['success' => 'Employee promoted/updated successfully']);
        break;

 // 4. ASSET REGISTRATION & DIRECTORY
    // ------------------------------------------
    case 'get_assets':
        require_login();
        
        // Base Query
        $sql = "SELECT a.*, c.name as category_name, 
                e.name as holder_name, d.name as dept_holder_name
                FROM assets a
                JOIN categories c ON a.category_id = c.id
                LEFT JOIN allocations al ON al.asset_id = a.id AND al.status = 'Active'
                LEFT JOIN employees e ON al.employee_id = e.id
                LEFT JOIN departments d ON al.department_id = d.id
                WHERE 1=1";
        
        $params = [];
        
        // Filters
        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $sql .= " AND (a.tag LIKE ? OR a.serial_number LIKE ? OR a.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $category = $_GET['category'] ?? '';
        if ($category) {
            $sql .= " AND a.category_id = ?";
            $params[] = (int)$category;
        }

        $status = $_GET['status'] ?? '';
        if ($status) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }

        $location = $_GET['location'] ?? '';
        if ($location) {
            $sql .= " AND a.location = ?";
            $params[] = $location;
        }

        $bookable = $_GET['bookable'] ?? '';
        if ($bookable !== '') {
            $sql .= " AND a.is_shared = ?";
            $params[] = (int)$bookable;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $assets = $stmt->fetchAll();

        echo json_encode($assets);
        break;

    case 'get_asset_details':
        require_login();
        $id = (int)($_GET['id'] ?? 0);

        if (!$id) {
            echo json_encode(['error' => 'Asset ID required']);
            exit;
        }

        // General asset details
        $stmt = $db->prepare("
            SELECT a.*, c.name as category_name, c.custom_fields as category_fields
            FROM assets a
            JOIN categories c ON a.category_id = c.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $asset = $stmt->fetch();

        if (!$asset) {
            echo json_encode(['error' => 'Asset not found']);
            exit;
        }

        // Current active allocation
        $stmt = $db->prepare("
            SELECT al.*, e.name as employee_name, e.email as employee_email, d.name as department_name 
            FROM allocations al
            LEFT JOIN employees e ON al.employee_id = e.id
            LEFT JOIN departments d ON al.department_id = d.id
            WHERE al.asset_id = ? AND al.status IN ('Active', 'Overdue')
        ");
        $stmt->execute([$id]);
        $active_allocation = $stmt->fetch();

        // History: Allocations & returns
        $stmt = $db->prepare("
            SELECT al.*, e.name as employee_name, d.name as department_name, ab.name as allocator_name 
            FROM allocations al
            LEFT JOIN employees e ON al.employee_id = e.id
            LEFT JOIN departments d ON al.department_id = d.id
            LEFT JOIN employees ab ON al.allocated_by = ab.id
            WHERE al.asset_id = ?
            ORDER BY al.allocation_date DESC
        ");
        $stmt->execute([$id]);
        $allocation_history = $stmt->fetchAll();

        // History: Maintenance Logs
        $stmt = $db->prepare("
            SELECT mr.*, e.name as reporter_name 
            FROM maintenance_requests mr
            LEFT JOIN employees e ON mr.reported_by = e.id
            WHERE mr.asset_id = ?
            ORDER BY mr.created_at DESC
        ");
        $stmt->execute([$id]);
        $maintenance_history = $stmt->fetchAll();

        echo json_encode([
            'asset' => $asset,
            'active_allocation' => $active_allocation,
            'allocation_history' => $allocation_history,
            'maintenance_history' => $maintenance_history
        ]);
        break;

    case 'register_asset':
        require_role(['admin', 'asset_manager']);
        $name = trim($data['name'] ?? '');
        $category_id = (int)($data['category_id'] ?? 0);
        $serial_number = trim($data['serial_number'] ?? '');
        $acquisition_date = $data['acquisition_date'] ?? date('Y-m-d');
        $acquisition_cost = (float)($data['acquisition_cost'] ?? 0.0);
        $condition_state = $data['condition_state'] ?? 'Good';
        $location = trim($data['location'] ?? '');
        $is_shared = !empty($data['is_shared']) ? 1 : 0;

        if (!$name || !$category_id || !$serial_number || !$location) {
            echo json_encode(['error' => 'Name, category, serial number and location are required.']);
            exit;
        }

        // Verify serial number unique
        $stmt = $db->prepare("SELECT COUNT(*) FROM assets WHERE serial_number = ?");
        $stmt->execute([$serial_number]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['error' => 'An asset with this serial number already exists.']);
            exit;
        }

        // Auto-generate unique asset tag: Query highest ID + 1
        $maxId = (int)$db->query("SELECT MAX(id) FROM assets")->fetchColumn();
        $nextTag = sprintf("AF-%04d", $maxId + 1);

        $photo = trim($data['photo'] ?? '');
        if ($photo === '') {
            $photo = null;
        }

        $stmt = $db->prepare("
            INSERT INTO assets (name, category_id, tag, serial_number, acquisition_date, acquisition_cost, condition_state, location, is_shared, status, photo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available', ?)
        ");
        $stmt->execute([$name, $category_id, $nextTag, $serial_number, $acquisition_date, $acquisition_cost, $condition_state, $location, $is_shared, $photo]);
        $newAssetId = $db->lastInsertId();

        log_activity($db, $_SESSION['user_id'], 'Registered Asset', "Registered asset '$name' with tag $nextTag");
        echo json_encode(['success' => 'Asset registered successfully with tag ' . $nextTag, 'id' => $newAssetId]);
        break;

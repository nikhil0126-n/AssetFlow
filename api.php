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



    // ------------------------------------------
    // 2. DASHBOARD DATA
    // ------------------------------------------
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

    // ------------------------------------------
    // 3. ORGANIZATION SETUP (ADMIN ONLY)
    // ------------------------------------------
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

    // ------------------------------------------
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

    // ------------------------------------------
    // 5. ALLOCATION & TRANSFER WORKFLOWS
    // ------------------------------------------
    case 'allocate_asset':
        require_role(['admin', 'asset_manager']);
        $asset_id = (int)($data['asset_id'] ?? 0);
        $employee_id = !empty($data['employee_id']) ? (int)$data['employee_id'] : null;
        $department_id = !empty($data['department_id']) ? (int)$data['department_id'] : null;
        $expected_return_date = !empty($data['expected_return_date']) ? $data['expected_return_date'] : null;

        if (!$asset_id) {
            echo json_encode(['error' => 'Asset selection is required.']);
            exit;
        }
        if (!$employee_id && !$department_id) {
            echo json_encode(['error' => 'Must allocate to either an employee or a department.']);
            exit;
        }

        // Conflict check: Is asset currently available?
        $stmt = $db->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$asset_id]);
        $asset = $stmt->fetch();

        if (!$asset) {
            echo json_encode(['error' => 'Asset does not exist.']);
            exit;
        }

        if ($asset['status'] !== 'Available') {
            // Find who holds it currently
            $stmtHold = $db->prepare("
                SELECT e.name as holder_name, e.email as holder_email 
                FROM allocations al 
                JOIN employees e ON al.employee_id = e.id 
                WHERE al.asset_id = ? AND al.status IN ('Active', 'Overdue')
            ");
            $stmtHold->execute([$asset_id]);
            $holder = $stmtHold->fetch();
            
            $holder_name = $holder ? $holder['holder_name'] : 'another department/worker';
            $holder_email = $holder ? $holder['holder_email'] : '';

            echo json_encode([
                'error' => "Asset is currently {$asset['status']} (held by $holder_name).",
                'held_by' => $holder_name,
                'held_by_email' => $holder_email,
                'can_transfer' => ($asset['status'] === 'Allocated')
            ]);
            exit;
        }

        // Perform allocation
        $db->beginTransaction();
        
        // Insert allocation record
        $stmtAlloc = $db->prepare("
            INSERT INTO allocations (asset_id, employee_id, department_id, allocated_by, expected_return_date, status) 
            VALUES (?, ?, ?, ?, ?, 'Active')
        ");
        $stmtAlloc->execute([$asset_id, $employee_id, $department_id, $_SESSION['user_id'], $expected_return_date]);

        // Update Asset state to 'Allocated'
        $db->prepare("UPDATE assets SET status = 'Allocated' WHERE id = ?")->execute([$asset_id]);

        $db->commit();

        $allocated_to = '';
        if ($employee_id) {
            $stmtEmp = $db->prepare("SELECT name FROM employees WHERE id = ?");
            $stmtEmp->execute([$employee_id]);
            $allocated_to = $stmtEmp->fetchColumn();
            create_notification($db, $employee_id, 'Asset Allocated', "Asset {$asset['name']} ({$asset['tag']}) has been allocated to you.", 'info');
        } else {
            $stmtDept = $db->prepare("SELECT name FROM departments WHERE id = ?");
            $stmtDept->execute([$department_id]);
            $allocated_to = "Department: " . $stmtDept->fetchColumn();
        }

        log_activity($db, $_SESSION['user_id'], 'Allocated Asset', "Allocated {$asset['tag']} to $allocated_to");
        echo json_encode(['success' => 'Asset allocated successfully.']);
        break;

    case 'return_asset':
        require_role(['admin', 'asset_manager']);
        $allocation_id = (int)($data['allocation_id'] ?? 0);
        $condition_on_return = trim($data['condition_on_return'] ?? 'Good');
        $notes = trim($data['notes'] ?? '');

        if (!$allocation_id) {
            echo json_encode(['error' => 'Allocation record required.']);
            exit;
        }

        // Get details
        $stmt = $db->prepare("SELECT * FROM allocations WHERE id = ? AND status IN ('Active', 'Overdue')");
        $stmt->execute([$allocation_id]);
        $alloc = $stmt->fetch();

        if (!$alloc) {
            echo json_encode(['error' => 'Active allocation record not found.']);
            exit;
        }

        $db->beginTransaction();

        // Update allocation return fields
        $stmtRet = $db->prepare("
            UPDATE allocations 
            SET actual_return_date = CURRENT_TIMESTAMP, condition_on_return = ?, status = 'Returned' 
            WHERE id = ?
        ");
        $stmtRet->execute([$condition_on_return, $allocation_id]);

        // Update asset back to Available and its condition
        $db->prepare("UPDATE assets SET status = 'Available', condition_state = ? WHERE id = ?")
           ->execute([$condition_on_return, $alloc['asset_id']]);

        // Save check-in notes inside activity logs
        $stmtTag = $db->prepare("SELECT tag, name FROM assets WHERE id = ?");
        $stmtTag->execute([$alloc['asset_id']]);
        $asset = $stmtTag->fetch();

        log_activity($db, $_SESSION['user_id'], 'Asset Returned', "Asset {$asset['tag']} returned. Check-in Notes: $notes. Condition: $condition_on_return");

        $db->commit();

        echo json_encode(['success' => 'Asset marked as returned successfully. Status reset to Available.']);
        break;

    case 'request_transfer':
        require_login();
        $asset_tag = trim($data['asset_tag'] ?? '');
        $to_employee_id = !empty($data['to_employee_id']) ? (int)$data['to_employee_id'] : null;
        $to_department_id = !empty($data['to_department_id']) ? (int)$data['to_department_id'] : null;

        if (!$asset_tag) {
            echo json_encode(['error' => 'Asset tag is required.']);
            exit;
        }
        if (!$to_employee_id && !$to_department_id) {
            echo json_encode(['error' => 'Select a target employee or department for the transfer.']);
            exit;
        }

        // Get asset details
        $stmt = $db->prepare("SELECT * FROM assets WHERE tag = ?");
        $stmt->execute([$asset_tag]);
        $asset = $stmt->fetch();

        if (!$asset) {
            echo json_encode(['error' => 'Asset with this tag not found.']);
            exit;
        }

        // Check if allocated
        $stmtAlloc = $db->prepare("SELECT * FROM allocations WHERE asset_id = ? AND status IN ('Active', 'Overdue')");
        $stmtAlloc->execute([$asset['id']]);
        $alloc = $stmtAlloc->fetch();

        if (!$alloc) {
            echo json_encode(['error' => 'This asset is not currently allocated, you can allocate it directly.']);
            exit;
        }

        // Raise transfer request
        $stmtTrans = $db->prepare("
            INSERT INTO transfers (asset_id, from_employee_id, to_employee_id, to_department_id, requested_by, status) 
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");
        $stmtTrans->execute([
            $asset['id'],
            $alloc['employee_id'],
            $to_employee_id,
            $to_department_id,
            $_SESSION['user_id']
        ]);
        $transfer_id = $db->lastInsertId();

        log_activity($db, $_SESSION['user_id'], 'Requested Transfer', "Requested transfer for asset {$asset['tag']}");
        
        // Notify managers and the target recipient department head / employee
        if ($to_employee_id) {
            create_notification($db, $to_employee_id, 'Transfer Requested', "A transfer request for asset {$asset['tag']} has been raised targeting you.", 'info');
        }

        echo json_encode(['success' => 'Transfer request submitted successfully. Waiting for approval.']);
        break;

    case 'get_transfers':
        require_login();
        $role = $_SESSION['role'];
        $emp_id = $_SESSION['user_id'];
        $dept_id = $_SESSION['department_id'];

        $sql = "SELECT t.*, a.tag, a.name as asset_name, 
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
        
        if ($role === 'employee') {
            $sql .= " WHERE t.requested_by = $emp_id OR t.from_employee_id = $emp_id OR t.to_employee_id = $emp_id";
        } else if ($role === 'dept_head' && $dept_id) {
            $sql .= " WHERE t.requested_by = $emp_id OR t.from_employee_id = $emp_id OR t.to_employee_id = $emp_id OR t.to_department_id = $dept_id";
        }
        
        $sql .= " ORDER BY t.request_date DESC";
        $transfers = $db->query($sql)->fetchAll();

        echo json_encode($transfers);
        break;

    case 'approve_transfer':
        require_role(['admin', 'asset_manager', 'dept_head']);
        $transfer_id = (int)($data['transfer_id'] ?? 0);
        $decision = $data['decision'] ?? ''; // 'Approved' or 'Rejected'

        if (!$transfer_id || !in_array($decision, ['Approved', 'Rejected'])) {
            echo json_encode(['error' => 'Valid transfer ID and decision (Approved/Rejected) are required.']);
            exit;
        }

        // Fetch transfer record
        $stmt = $db->prepare("SELECT * FROM transfers WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$transfer_id]);
        $transfer = $stmt->fetch();

        if (!$transfer) {
            echo json_encode(['error' => 'Pending transfer request not found.']);
            exit;
        }

        // Dept heads can only approve if it is to their department or to staff in their department
        if ($_SESSION['role'] === 'dept_head') {
            $is_authorized = false;
            if ($transfer['to_department_id'] == $_SESSION['department_id']) {
                $is_authorized = true;
            } else if ($transfer['to_employee_id']) {
                $stmtEmpDept = $db->prepare("SELECT department_id FROM employees WHERE id = ?");
                $stmtEmpDept->execute([$transfer['to_employee_id']]);
                $emp_dept = $stmtEmpDept->fetchColumn();
                if ($emp_dept == $_SESSION['department_id']) {
                    $is_authorized = true;
                }
            }
            if (!$is_authorized) {
                echo json_encode(['error' => 'Unauthorized. Department heads can only approve transfers entering their department or targeting their department staff.']);
                exit;
            }
        }

        $db->beginTransaction();

        if ($decision === 'Approved') {
            // Close the old active allocation for this asset
            $db->prepare("
                UPDATE allocations 
                SET actual_return_date = CURRENT_TIMESTAMP, status = 'Returned', condition_on_return = 'Transferred' 
                WHERE asset_id = ? AND status IN ('Active', 'Overdue')
            ")->execute([$transfer['asset_id']]);

            // Create a new allocation record
            $stmtNewAlloc = $db->prepare("
                INSERT INTO allocations (asset_id, employee_id, department_id, allocated_by, status) 
                VALUES (?, ?, ?, ?, 'Active')
            ");
            $stmtNewAlloc->execute([
                $transfer['asset_id'],
                $transfer['to_employee_id'],
                $transfer['to_department_id'],
                $_SESSION['user_id']
            ]);

            // Update transfer record
            $stmtUp = $db->prepare("UPDATE transfers SET status = 'Approved', approved_by = ?, approval_date = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUp->execute([$_SESSION['user_id'], $transfer_id]);

            // Log activity
            $stmtTag = $db->prepare("SELECT tag, name FROM assets WHERE id = ?");
            $stmtTag->execute([$transfer['asset_id']]);
            $asset = $stmtTag->fetch();

            log_activity($db, $_SESSION['user_id'], 'Approved Asset Transfer', "Approved transfer for {$asset['tag']} to target recipient.");
            
            // Notify target recipient
            if ($transfer['to_employee_id']) {
                create_notification($db, $transfer['to_employee_id'], 'Transfer Approved', "The transfer of asset {$asset['name']} ({$asset['tag']}) to you was approved.", 'success');
            }
        } else {
            // Reject transfer request
            $stmtUp = $db->prepare("UPDATE transfers SET status = 'Rejected', approved_by = ?, approval_date = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUp->execute([$_SESSION['user_id'], $transfer_id]);
            log_activity($db, $_SESSION['user_id'], 'Rejected Asset Transfer', "Rejected transfer request ID $transfer_id");
        }

        $db->commit();

        echo json_encode(['success' => "Transfer request successfully " . strtolower($decision)]);
        break;

    // ------------------------------------------
    // 6. RESOURCE BOOKINGS
    // ------------------------------------------
    case 'get_bookings':
        require_login();
        $asset_id = (int)($_GET['asset_id'] ?? 0);

        if (!$asset_id) {
            echo json_encode(['error' => 'Asset ID required for bookings calendar.']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT b.*, e.name as employee_name, e.email as employee_email 
            FROM bookings b
            JOIN employees e ON b.employee_id = e.id
            WHERE b.asset_id = ? AND b.status != 'Cancelled'
            ORDER BY b.booking_date ASC, b.start_time ASC
        ");
        $stmt->execute([$asset_id]);
        $bookings = $stmt->fetchAll();

        echo json_encode($bookings);
        break;

    case 'book_resource':
        require_login();
        $asset_id = (int)($data['asset_id'] ?? 0);
        $booking_date = $data['booking_date'] ?? '';
        $start_time = $data['start_time'] ?? '';
        $end_time = $data['end_time'] ?? '';

        if (!$asset_id || !$booking_date || !$start_time || !$end_time) {
            echo json_encode(['error' => 'All fields (asset, date, start time, end time) are required.']);
            exit;
        }

        // Verify bookable
        $stmtAsset = $db->prepare("SELECT * FROM assets WHERE id = ?");
        $stmtAsset->execute([$asset_id]);
        $asset = $stmtAsset->fetch();

        if (!$asset) {
            echo json_encode(['error' => 'Asset not found.']);
            exit;
        }

        if (!$asset['is_shared']) {
            echo json_encode(['error' => 'This asset is not marked as a shared bookable resource.']);
            exit;
        }

        // Overlap Validation query
        // Checks if another booking exists on the same asset, same date, not cancelled, where times overlap:
        // (start_time < target_end AND end_time > target_start)
        $stmtCheck = $db->prepare("
            SELECT b.*, e.name as booker_name 
            FROM bookings b
            JOIN employees e ON b.employee_id = e.id
            WHERE b.asset_id = ? 
            AND b.booking_date = ? 
            AND b.status != 'Cancelled' 
            AND b.start_time < ? 
            AND b.end_time > ?
        ");
        $stmtCheck->execute([$asset_id, $booking_date, $end_time, $start_time]);
        $conflict = $stmtCheck->fetch();

        if ($conflict) {
            echo json_encode([
                'error' => "Overlap conflict! This resource is already booked by {$conflict['booker_name']} from " . substr($conflict['start_time'], 0, 5) . " to " . substr($conflict['end_time'], 0, 5) . " on this date."
            ]);
            exit;
        }

        // Insert booking
        $stmtBook = $db->prepare("
            INSERT INTO bookings (asset_id, employee_id, booking_date, start_time, end_time, status) 
            VALUES (?, ?, ?, ?, ?, 'Upcoming')
        ");
        $stmtBook->execute([$asset_id, $_SESSION['user_id'], $booking_date, $start_time, $end_time]);

        log_activity($db, $_SESSION['user_id'], 'Booked Shared Resource', "Booked {$asset['tag']} ({$asset['name']}) on $booking_date from $start_time to $end_time");
        
        echo json_encode(['success' => 'Resource booked successfully!']);
        break;

    case 'cancel_booking':
        require_login();
        $booking_id = (int)($data['booking_id'] ?? 0);

        if (!$booking_id) {
            echo json_encode(['error' => 'Booking ID required.']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();

        if (!$booking) {
            echo json_encode(['error' => 'Booking record not found.']);
            exit;
        }

        // Standard employees can only cancel their own bookings. Managers/heads can cancel any.
        if ($_SESSION['role'] === 'employee' && $booking['employee_id'] !== $_SESSION['user_id']) {
            echo json_encode(['error' => 'You can only cancel your own bookings.']);
            exit;
        }

        $db->prepare("UPDATE bookings SET status = 'Cancelled' WHERE id = ?")->execute([$booking_id]);
        
        $stmtAsset = $db->prepare("SELECT tag FROM assets WHERE id = ?");
        $stmtAsset->execute([$booking['asset_id']]);
        $tag = $stmtAsset->fetchColumn();

        log_activity($db, $_SESSION['user_id'], 'Cancelled Booking', "Cancelled booking ID $booking_id for asset $tag");
        echo json_encode(['success' => 'Booking cancelled successfully.']);
        break;

    // ------------------------------------------
    // 7. MAINTENANCE MANAGEMENT
    // ------------------------------------------
    case 'get_maintenance':
        require_login();
        
        $sql = "SELECT mr.*, a.tag, a.name as asset_name, a.status as asset_status, e.name as reporter_name 
                FROM maintenance_requests mr
                JOIN assets a ON mr.asset_id = a.id
                JOIN employees e ON mr.reported_by = e.id
                ORDER BY mr.created_at DESC";
        $requests = $db->query($sql)->fetchAll();

        echo json_encode($requests);
        break;

    case 'raise_maintenance':
        require_login();
        $asset_id = (int)($data['asset_id'] ?? 0);
        $description = trim($data['description'] ?? '');
        $priority = $data['priority'] ?? 'Medium';

        if (!$asset_id || !$description) {
            echo json_encode(['error' => 'Asset and description are required.']);
            exit;
        }

        // Record request
        $stmt = $db->prepare("
            INSERT INTO maintenance_requests (asset_id, reported_by, description, priority, status) 
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$asset_id, $_SESSION['user_id'], $description, $priority]);

        $stmtTag = $db->prepare("SELECT tag FROM assets WHERE id = ?");
        $stmtTag->execute([$asset_id]);
        $tag = $stmtTag->fetchColumn();

        log_activity($db, $_SESSION['user_id'], 'Raised Maintenance Ticket', "Raised maintenance ticket for $tag. Priority: $priority");
        create_notification($db, null, 'New Maintenance Request', "A ticket has been raised for asset $tag. Priority: $priority.", 'warning');

        echo json_encode(['success' => 'Maintenance request raised successfully. Waiting for Manager review.']);
        break;

    case 'approve_maintenance':
        require_role(['admin', 'asset_manager']);
        $request_id = (int)($data['request_id'] ?? 0);
        $decision = $data['decision'] ?? ''; // 'Approved' or 'Rejected'

        if (!$request_id || !in_array($decision, ['Approved', 'Rejected'])) {
            echo json_encode(['error' => 'Request ID and decision (Approved/Rejected) are required.']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM maintenance_requests WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch();

        if (!$req) {
            echo json_encode(['error' => 'Pending maintenance request not found.']);
            exit;
        }

        $db->beginTransaction();

        if ($decision === 'Approved') {
            // Update request
            $db->prepare("UPDATE maintenance_requests SET status = 'Approved' WHERE id = ?")->execute([$request_id]);
            // Flip Asset state to Under Maintenance
            $db->prepare("UPDATE assets SET status = 'Under Maintenance' WHERE id = ?")->execute([$req['asset_id']]);
            
            log_activity($db, $_SESSION['user_id'], 'Approved Maintenance', "Approved maintenance request for asset ID {$req['asset_id']}");
            create_notification($db, $req['reported_by'], 'Maintenance Approved', "Your maintenance request has been approved. Asset status flipped to Under Maintenance.", 'success');
        } else {
            $db->prepare("UPDATE maintenance_requests SET status = 'Rejected' WHERE id = ?")->execute([$request_id]);
            log_activity($db, $_SESSION['user_id'], 'Rejected Maintenance', "Rejected maintenance request ID $request_id");
            create_notification($db, $req['reported_by'], 'Maintenance Rejected', "Your maintenance request has been rejected.", 'danger');
        }

        $db->commit();

        echo json_encode(['success' => "Maintenance request successfully " . strtolower($decision)]);
        break;

    case 'update_maintenance_status':
        require_role(['admin', 'asset_manager']);
        $request_id = (int)($data['request_id'] ?? 0);
        $status = $data['status'] ?? ''; // 'Technician Assigned', 'In Progress', 'Resolved'
        $technician = trim($data['assigned_technician'] ?? '');
        $notes = trim($data['notes'] ?? '');

        if (!$request_id || !in_array($status, ['Technician Assigned', 'In Progress', 'Resolved'])) {
            echo json_encode(['error' => 'Valid request ID and target status required.']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM maintenance_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch();

        if (!$req) {
            echo json_encode(['error' => 'Maintenance request not found.']);
            exit;
        }

        $db->beginTransaction();

        // Update fields
        $sqlUp = "UPDATE maintenance_requests SET status = ?, notes = ?";
        $params = [$status, $notes];

        if ($technician) {
            $sqlUp .= ", assigned_technician = ?";
            $params[] = $technician;
        }
        $sqlUp .= " WHERE id = ?";
        $params[] = $request_id;

        $db->prepare($sqlUp)->execute($params);

        if ($status === 'Resolved') {
            // Flip Asset state back to Available
            $db->prepare("UPDATE assets SET status = 'Available' WHERE id = ?")->execute([$req['asset_id']]);
            
            log_activity($db, $_SESSION['user_id'], 'Resolved Maintenance', "Maintenance resolved for asset ID {$req['asset_id']}. Notes: $notes");
            create_notification($db, $req['reported_by'], 'Maintenance Resolved', "Repairs for your reported asset are complete. Status set to Available.", 'success');
        } else {
            log_activity($db, $_SESSION['user_id'], 'Updated Maintenance Status', "Status for maintenance request ID $request_id changed to: $status");
        }

        $db->commit();

        echo json_encode(['success' => 'Maintenance status updated successfully.']);
        break;

    // ------------------------------------------
    // 8. ASSET AUDIT
    // ------------------------------------------
    case 'get_audits':
        require_login();
        
        // Return audit cycles and the assigned auditors
        $sql = "SELECT ac.*, d.name as department_name, e.name as creator_name
                FROM audit_cycles ac
                LEFT JOIN departments d ON ac.department_id = d.id
                JOIN employees e ON ac.created_by = e.id
                ORDER BY ac.created_at DESC";
        $cycles = $db->query($sql)->fetchAll();

        foreach ($cycles as &$c) {
            // Get auditors names
            $stmtAud = $db->prepare("
                SELECT e.name 
                FROM audit_auditors aa
                JOIN employees e ON aa.employee_id = e.id
                WHERE aa.audit_cycle_id = ?
            ");
            $stmtAud->execute([$c['id']]);
            $c['auditors'] = $stmtAud->fetchAll(PDO::FETCH_COLUMN);
        }

        echo json_encode($cycles);
        break;

    case 'create_audit_cycle':
        require_role('admin');
        $name = trim($data['name'] ?? '');
        $department_id = !empty($data['department_id']) ? (int)$data['department_id'] : null;
        $location = trim($data['location'] ?? '');
        $start_date = $data['start_date'] ?? date('Y-m-d');
        $end_date = $data['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
        $auditor_ids = $data['auditor_ids'] ?? []; // Array of employee IDs

        if (!$name || (empty($department_id) && !$location) || empty($auditor_ids)) {
            echo json_encode(['error' => 'Name, at least one scope boundary (department or location), and at least one auditor are required.']);
            exit;
        }

        $db->beginTransaction();

        // Create cycle
        $stmtC = $db->prepare("
            INSERT INTO audit_cycles (name, department_id, location, start_date, end_date, status, created_by) 
            VALUES (?, ?, ?, ?, ?, 'Active', ?)
        ");
        $stmtC->execute([$name, $department_id, $location, $start_date, $end_date, $_SESSION['user_id']]);
        $cycleId = $db->lastInsertId();

        // Add auditors
        $stmtAud = $db->prepare("INSERT INTO audit_auditors (audit_cycle_id, employee_id) VALUES (?, ?)");
        foreach ($auditor_ids as $audId) {
            $stmtAud->execute([$cycleId, (int)$audId]);
            create_notification($db, $audId, 'Assigned as Auditor', "You have been assigned as an auditor for cycle: $name", 'info');
        }

        // Query assets within scope to pre-populate audit_items
        $sqlAssets = "SELECT id FROM assets WHERE 1=1";
        $scopeParams = [];

        if ($department_id) {
            // Assets currently allocated to this department or employees in this department
            $sqlAssets .= " AND id IN (
                SELECT asset_id FROM allocations 
                WHERE status IN ('Active', 'Overdue') 
                AND (department_id = ? OR employee_id IN (SELECT id FROM employees WHERE department_id = ?))
            )";
            $scopeParams[] = $department_id;
            $scopeParams[] = $department_id;
        }

        if ($location) {
            $sqlAssets .= " AND location = ?";
            $scopeParams[] = $location;
        }

        $stmtQuery = $db->prepare($sqlAssets);
        $stmtQuery->execute($scopeParams);
        $scopeAssets = $stmtQuery->fetchAll(PDO::FETCH_COLUMN);

        // Prepopulate items
        if (!empty($scopeAssets)) {
            $stmtItem = $db->prepare("INSERT INTO audit_items (audit_cycle_id, asset_id, status) VALUES (?, ?, 'Pending')");
            foreach ($scopeAssets as $aId) {
                $stmtItem->execute([$cycleId, $aId]);
            }
        }

        log_activity($db, $_SESSION['user_id'], 'Created Audit Cycle', "Created audit cycle '$name' with " . count($scopeAssets) . " assets in scope.");
        
        $db->commit();

        echo json_encode(['success' => 'Audit cycle created successfully.']);
        break;

    case 'get_audit_items':
        require_login();
        $cycle_id = (int)($_GET['cycle_id'] ?? 0);

        if (!$cycle_id) {
            echo json_encode(['error' => 'Audit cycle ID required.']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT ai.*, a.tag, a.name as asset_name, a.location, a.status as current_status,
            e.name as holder_name
            FROM audit_items ai
            JOIN assets a ON ai.asset_id = a.id
            LEFT JOIN allocations al ON al.asset_id = a.id AND al.status = 'Active'
            LEFT JOIN employees e ON al.employee_id = e.id
            WHERE ai.audit_cycle_id = ?
        ");
        $stmt->execute([$cycle_id]);
        $items = $stmt->fetchAll();

        echo json_encode($items);
        break;

    case 'update_audit_item':
        require_login();
        $item_id = (int)($data['item_id'] ?? 0);
        $status = $data['status'] ?? ''; // 'Verified', 'Missing', 'Damaged'
        $notes = trim($data['notes'] ?? '');

        if (!$item_id || !in_array($status, ['Verified', 'Missing', 'Damaged'])) {
            echo json_encode(['error' => 'Valid item ID and status (Verified/Missing/Damaged) are required.']);
            exit;
        }

        // Verify auditor is assigned to this cycle
        $stmtCycle = $db->prepare("
            SELECT ac.* FROM audit_cycles ac
            JOIN audit_items ai ON ai.audit_cycle_id = ac.id
            WHERE ai.id = ?
        ");
        $stmtCycle->execute([$item_id]);
        $cycle = $stmtCycle->fetch();

        if (!$cycle) {
            echo json_encode(['error' => 'Audit cycle context not found.']);
            exit;
        }

        if ($cycle['status'] === 'Closed') {
            echo json_encode(['error' => 'Audit cycle is closed and locked. No modifications allowed.']);
            exit;
        }

        // Verify user is auditor or admin
        if ($_SESSION['role'] !== 'admin') {
            $stmtCheckAud = $db->prepare("SELECT COUNT(*) FROM audit_auditors WHERE audit_cycle_id = ? AND employee_id = ?");
            $stmtCheckAud->execute([$cycle['id'], $_SESSION['user_id']]);
            if ($stmtCheckAud->fetchColumn() == 0) {
                echo json_encode(['error' => 'Unauthorized. You are not assigned as an auditor for this cycle.']);
                exit;
            }
        }

        // Update item status
        $stmtUp = $db->prepare("UPDATE audit_items SET status = ?, notes = ? WHERE id = ?");
        $stmtUp->execute([$status, $notes, $item_id]);

        echo json_encode(['success' => 'Audit item status logged.']);
        break;

    case 'close_audit_cycle':
        require_role(['admin', 'asset_manager']);
        $cycle_id = (int)($data['cycle_id'] ?? 0);

        if (!$cycle_id) {
            echo json_encode(['error' => 'Audit cycle ID required.']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM audit_cycles WHERE id = ? AND status = 'Active'");
        $stmt->execute([$cycle_id]);
        $cycle = $stmt->fetch();

        if (!$cycle) {
            echo json_encode(['error' => 'Active audit cycle not found.']);
            exit;
        }

        $db->beginTransaction();

        // 1. Fetch flagged items that are missing
        $stmtItems = $db->prepare("SELECT * FROM audit_items WHERE audit_cycle_id = ?");
        $stmtItems->execute([$cycle_id]);
        $items = $stmtItems->fetchAll();

        $missingCount = 0;
        $damagedCount = 0;

        foreach ($items as $item) {
            if ($item['status'] === 'Missing') {
                // Update Asset state to 'Lost'
                $db->prepare("UPDATE assets SET status = 'Lost' WHERE id = ?")->execute([$item['asset_id']]);
                
                // Cancel active allocation
                $db->prepare("
                    UPDATE allocations 
                    SET actual_return_date = CURRENT_TIMESTAMP, status = 'Returned', condition_on_return = 'Lost in Audit' 
                    WHERE asset_id = ? AND status IN ('Active', 'Overdue')
                ")->execute([$item['asset_id']]);

                $missingCount++;
            } else if ($item['status'] === 'Damaged') {
                // Update asset condition
                $db->prepare("UPDATE assets SET condition_state = 'Damaged' WHERE id = ?")->execute([$item['asset_id']]);
                $damagedCount++;
            }
        }

        // 2. Lock cycle
        $db->prepare("UPDATE audit_cycles SET status = 'Closed' WHERE id = ?")->execute([$cycle_id]);

        log_activity($db, $_SESSION['user_id'], 'Closed Audit Cycle', "Locked audit cycle ID $cycle_id. Results: $missingCount assets flagged Lost, $damagedCount flagged Damaged.");
        
        // System wide notification of results
        create_notification(
            $db, 
            null, 
            'Audit Cycle Closed', 
            "Audit '{$cycle['name']}' has been closed. Discrepancy report: $missingCount assets confirmed Missing (reverted to Lost), $damagedCount assets flagged Damaged.", 
            $missingCount > 0 ? 'danger' : 'info'
        );

        $db->commit();

        echo json_encode(['success' => 'Audit cycle locked and discrepancy reports generated. Affected asset states updated.']);
        break;

    // ------------------------------------------
    // 9. REPORTS & ANALYTICS
    // ------------------------------------------
    case 'get_reports':
        require_role(['admin', 'asset_manager']);

        // Metric A: Asset utilization trends (allocated vs total)
        $totalAssets = (int)$db->query("SELECT COUNT(*) FROM assets")->fetchColumn();
        $allocatedAssets = (int)$db->query("SELECT COUNT(*) FROM assets WHERE status = 'Allocated'")->fetchColumn();
        $underMaint = (int)$db->query("SELECT COUNT(*) FROM assets WHERE status = 'Under Maintenance'")->fetchColumn();
        $lost = (int)$db->query("SELECT COUNT(*) FROM assets WHERE status = 'Lost'")->fetchColumn();
        $available = (int)$db->query("SELECT COUNT(*) FROM assets WHERE status = 'Available'")->fetchColumn();
        
        // Metric B: Maintenance frequency by category
        $maintFrequency = $db->query("
            SELECT c.name as category_name, COUNT(mr.id) as request_count 
            FROM maintenance_requests mr
            JOIN assets a ON mr.asset_id = a.id
            JOIN categories c ON a.category_id = c.id
            GROUP BY c.id
        ")->fetchAll();

        // Metric C: Department-wise allocations
        $deptAllocations = $db->query("
            SELECT d.name as department_name, COUNT(al.id) as allocation_count 
            FROM allocations al
            JOIN departments d ON al.department_id = d.id OR al.employee_id IN (SELECT id FROM employees WHERE department_id = d.id)
            WHERE al.status IN ('Active', 'Overdue')
            GROUP BY d.id
        ")->fetchAll();

        // Metric D: Peak usage heatmap (resource booking frequencies by hour slot)
        $bookingHeatmap = $db->query("
            SELECT HOUR(start_time) as booking_hour, COUNT(*) as booking_count 
            FROM bookings 
            WHERE status != 'Cancelled'
            GROUP BY booking_hour
            ORDER BY booking_hour ASC
        ")->fetchAll();

        // Metric E: Nearing retirement (over 2 years since acquisition date)
        $nearingRetirement = $db->query("
            SELECT tag, name, acquisition_date, condition_state
            FROM assets 
            WHERE acquisition_date < DATE_SUB(CURRENT_DATE(), INTERVAL 2 YEAR) AND status != 'Retired' AND status != 'Disposed'
            LIMIT 5
        ")->fetchAll();

        echo json_encode([
            'utilization' => [
                'total' => $totalAssets,
                'allocated' => $allocatedAssets,
                'under_maintenance' => $underMaint,
                'lost' => $lost,
                'available' => $available
            ],
            'maintenance_by_category' => $maintFrequency,
            'department_allocations' => $deptAllocations,
            'booking_heatmap' => $bookingHeatmap,
            'nearing_retirement' => $nearingRetirement
        ]);
        break;

    // ------------------------------------------
    // 10. SYSTEM LOGS & NOTIFICATIONS
    // ------------------------------------------
    case 'get_logs_notifications':
        require_login();
        $emp_id = $_SESSION['user_id'];

        // Get notifications
        $stmtNotif = $db->prepare("
            SELECT * FROM notifications 
            WHERE employee_id = ? OR employee_id IS NULL 
            ORDER BY created_at DESC 
            LIMIT 30
        ");
        $stmtNotif->execute([$emp_id]);
        $notifications = $stmtNotif->fetchAll();

        // Get logs (Managers see all, Employees see their own activity)
        if (in_array($_SESSION['role'], ['admin', 'asset_manager'])) {
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
            $stmtLogs->execute([$emp_id]);
            $logs = $stmtLogs->fetchAll();
        }

        echo json_encode([
            'notifications' => $notifications,
            'logs' => $logs
        ]);
        break;

    case 'mark_notification_read':
        require_login();
        $id = (int)($data['notification_id'] ?? 0);
        
        if ($id) {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (employee_id = ? OR employee_id IS NULL)");
            $stmt->execute([$id, $_SESSION['user_id']]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'initiate_return':
        require_login();
        $allocation_id = (int)($data['allocation_id'] ?? 0);
        
        if (!$allocation_id) {
            echo json_encode(['error' => 'Allocation ID is required.']);
            exit;
        }
        
        // Find allocation details
        $stmt = $db->prepare("
            SELECT al.*, a.tag, a.name as asset_name, e.name as employee_name
            FROM allocations al
            JOIN assets a ON al.asset_id = a.id
            JOIN employees e ON al.employee_id = e.id
            WHERE al.id = ? AND al.employee_id = ?
        ");
        $stmt->execute([$allocation_id, $_SESSION['user_id']]);
        $alloc = $stmt->fetch();
        
        if (!$alloc) {
            echo json_encode(['error' => 'Allocation record not found or unauthorized.']);
            exit;
        }
        
        // Create notification for Asset Managers/Admins
        create_notification($db, null, 'Return Requested', "Employee " . $alloc['employee_name'] . " requested to return asset " . $alloc['tag'] . " (" . $alloc['asset_name'] . ") to storage.", 'warning');
        log_activity($db, $_SESSION['user_id'], 'Initiated Return', "Requested return of asset " . $alloc['tag'] . ".");
        
        echo json_encode(['success' => 'Return request submitted successfully. Please hand over the asset to the Asset Manager.']);
        break;

    default:
        echo json_encode(['error' => 'Unknown API action requested']);
        break;
}
?>

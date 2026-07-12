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


    }

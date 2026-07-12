<?php
// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'assetflow');

// Connect to MySQL server (without DB selected initially, for setup)
function getMySQLConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (PDOException $e) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['error' => 'MySQL connection failed: ' . $e->getMessage()]);
        exit;
    }
}

// Connect to the specific database
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Return connection error cleanly
        header('Content-Type: application/json', true, 500);
        echo json_encode([
            'error' => 'Database connection failed. Please run setup.php first.',
            'details' => $e->getMessage()
        ]);
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
?>

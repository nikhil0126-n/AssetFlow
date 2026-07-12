<?php
require_once 'config.php';

// Check if running via CLI or Browser
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AssetFlow Database Setup</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Inter:wght@300;400;500;600;750&display=swap" rel="stylesheet">
        <style>
            :root {
                --bg: #0b0f19;
                --card: rgba(17, 24, 39, 0.75);
                --text: #f3f4f6;
                --text-muted: #9ca3af;
                --primary: #6366f1;
                --success: #10b981;
                --error: #ef4444;
                --border: rgba(255, 255, 255, 0.08);
            }
            body {
                font-family: "Inter", sans-serif;
                background-color: var(--bg);
                color: var(--text);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                overflow-x: hidden;
            }
            .container {
                max-width: 650px;
                width: 90%;
                background: var(--card);
                border: 1px solid var(--border);
                backdrop-filter: blur(16px);
                border-radius: 16px;
                padding: 32px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.5), 0 0 100px rgba(99, 102, 241, 0.1);
            }
            h1 {
                font-family: "Outfit", sans-serif;
                font-weight: 800;
                margin-top: 0;
                background: linear-gradient(135deg, #a5b4fc, var(--primary));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                text-align: center;
            }
            .step {
                padding: 12px 16px;
                margin-bottom: 8px;
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.02);
                border-left: 4px solid var(--text-muted);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .step.success {
                border-left-color: var(--success);
                background: rgba(16, 185, 129, 0.05);
            }
            .step.error {
                border-left-color: var(--error);
                background: rgba(239, 68, 68, 0.05);
            }
            .status-badge {
                font-size: 0.8rem;
                font-weight: 600;
                padding: 4px 8px;
                border-radius: 9999px;
            }
            .success .status-badge {
                background: rgba(16, 185, 129, 0.2);
                color: #34d399;
            }
            .error .status-badge {
                background: rgba(239, 68, 68, 0.2);
                color: #f87171;
            }
            .btn {
                display: block;
                width: 100%;
                text-align: center;
                background: var(--primary);
                color: white;
                padding: 12px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
                margin-top: 24px;
                transition: all 0.2s;
                border: none;
                cursor: pointer;
            }
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>AssetFlow Setup</h1>';
}

function log_step($message, $success = true, $detail = '') {
    global $is_cli;
    if ($is_cli) {
        $status = $success ? '[SUCCESS]' : '[ERROR]';
        echo "$status $message" . ($detail ? " ($detail)" : "") . "\n";
    } else {
        $class = $success ? 'success' : 'error';
        $badge = $success ? 'Done' : 'Failed';
        echo '<div class="step ' . $class . '">';
        echo '<div><strong>' . htmlspecialchars($message) . '</strong>';
        if ($detail) {
            echo '<br><span style="font-size: 0.85rem; color: var(--text-muted);">' . htmlspecialchars($detail) . '</span>';
        }
        echo '</div>';
        echo '<span class="status-badge">' . $badge . '</span>';
        echo '</div>';
    }
}

try {
    // Connect to MySQL server
    $pdo = getMySQLConnection();
    log_step("Connected to MySQL server", true);

    // Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    log_step("Database '" . DB_NAME . "' verified/created", true);

    // Re-connect to specific database
    $pdo->exec("USE " . DB_NAME);

    // Create tables
    $queries = [
        "departments" => "
            CREATE TABLE IF NOT EXISTS departments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                head_id INT NULL,
                parent_id INT NULL,
                status ENUM('Active', 'Inactive') DEFAULT 'Active',
                FOREIGN KEY (parent_id) REFERENCES departments(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",

        "categories" => "
            CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                custom_fields TEXT NULL
            ) ENGINE=InnoDB",

        "employees" => "
            CREATE TABLE IF NOT EXISTS employees (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                department_id INT NULL,
                role ENUM('admin', 'asset_manager', 'dept_head', 'employee') DEFAULT 'employee',
                status ENUM('Active', 'Inactive') DEFAULT 'Active',
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",

        "assets" => "
            CREATE TABLE IF NOT EXISTS assets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                category_id INT NOT NULL,
                tag VARCHAR(50) NOT NULL UNIQUE,
                serial_number VARCHAR(100) NOT NULL UNIQUE,
                acquisition_date DATE NOT NULL,
                acquisition_cost DECIMAL(10, 2) NOT NULL,
                condition_state ENUM('New', 'Good', 'Fair', 'Poor', 'Damaged') DEFAULT 'Good',
                location VARCHAR(100) NOT NULL,
                is_shared TINYINT(1) DEFAULT 0,
                status ENUM('Available', 'Allocated', 'Reserved', 'Under Maintenance', 'Lost', 'Retired', 'Disposed') DEFAULT 'Available',
                photo VARCHAR(255) NULL,
                FOREIGN KEY (category_id) REFERENCES categories(id)
            ) ENGINE=InnoDB",

        "allocations" => "
            CREATE TABLE IF NOT EXISTS allocations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                asset_id INT NOT NULL,
                employee_id INT NULL,
                department_id INT NULL,
                allocated_by INT NOT NULL,
                allocation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expected_return_date DATE NULL,
                actual_return_date TIMESTAMP NULL,
                condition_on_return VARCHAR(255) NULL,
                status ENUM('Active', 'Returned', 'Overdue') DEFAULT 'Active',
                FOREIGN KEY (asset_id) REFERENCES assets(id),
                FOREIGN KEY (employee_id) REFERENCES employees(id),
                FOREIGN KEY (department_id) REFERENCES departments(id),
                FOREIGN KEY (allocated_by) REFERENCES employees(id)
            ) ENGINE=InnoDB",

        "transfers" => "
            CREATE TABLE IF NOT EXISTS transfers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                asset_id INT NOT NULL,
                from_employee_id INT NULL,
                to_employee_id INT NULL,
                to_department_id INT NULL,
                requested_by INT NOT NULL,
                approved_by INT NULL,
                request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                approval_date TIMESTAMP NULL,
                status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
                FOREIGN KEY (asset_id) REFERENCES assets(id),
                FOREIGN KEY (from_employee_id) REFERENCES employees(id),
                FOREIGN KEY (to_employee_id) REFERENCES employees(id),
                FOREIGN KEY (to_department_id) REFERENCES departments(id),
                FOREIGN KEY (requested_by) REFERENCES employees(id),
                FOREIGN KEY (approved_by) REFERENCES employees(id)
            ) ENGINE=InnoDB",

        "bookings" => "
            CREATE TABLE IF NOT EXISTS bookings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                asset_id INT NOT NULL,
                employee_id INT NOT NULL,
                booking_date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                status ENUM('Upcoming', 'Ongoing', 'Completed', 'Cancelled') DEFAULT 'Upcoming',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (asset_id) REFERENCES assets(id),
                FOREIGN KEY (employee_id) REFERENCES employees(id)
            ) ENGINE=InnoDB",

        "maintenance_requests" => "
            CREATE TABLE IF NOT EXISTS maintenance_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                asset_id INT NOT NULL,
                reported_by INT NOT NULL,
                description TEXT NOT NULL,
                priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
                photo_path VARCHAR(255) NULL,
                status ENUM('Pending', 'Approved', 'Rejected', 'Technician Assigned', 'In Progress', 'Resolved') DEFAULT 'Pending',
                assigned_technician VARCHAR(100) NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (asset_id) REFERENCES assets(id),
                FOREIGN KEY (reported_by) REFERENCES employees(id)
            ) ENGINE=InnoDB",

        "audit_cycles" => "
            CREATE TABLE IF NOT EXISTS audit_cycles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                department_id INT NULL,
                location VARCHAR(100) NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                status ENUM('Active', 'Closed') DEFAULT 'Active',
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (department_id) REFERENCES departments(id),
                FOREIGN KEY (created_by) REFERENCES employees(id)
            ) ENGINE=InnoDB",

        "audit_items" => "
            CREATE TABLE IF NOT EXISTS audit_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                audit_cycle_id INT NOT NULL,
                asset_id INT NOT NULL,
                status ENUM('Pending', 'Verified', 'Missing', 'Damaged') DEFAULT 'Pending',
                notes VARCHAR(255) NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (audit_cycle_id) REFERENCES audit_cycles(id) ON DELETE CASCADE,
                FOREIGN KEY (asset_id) REFERENCES assets(id)
            ) ENGINE=InnoDB",

        "audit_auditors" => "
            CREATE TABLE IF NOT EXISTS audit_auditors (
                audit_cycle_id INT NOT NULL,
                employee_id INT NOT NULL,
                PRIMARY KEY (audit_cycle_id, employee_id),
                FOREIGN KEY (audit_cycle_id) REFERENCES audit_cycles(id) ON DELETE CASCADE,
                FOREIGN KEY (employee_id) REFERENCES employees(id)
            ) ENGINE=InnoDB",

        "activity_logs" => "
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NULL,
                action VARCHAR(255) NOT NULL,
                details TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",

        "notifications" => "
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NULL,
                title VARCHAR(150) NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(50) NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
    ];

    foreach ($queries as $tableName => $sql) {
        $pdo->exec($sql);
        log_step("Table '$tableName' verified/created", true);
    }

    // Set foreign keys on departments (head_id) which we couldn't do earlier due to chicken-and-egg
    // Check if constraint exists, if not add it
    try {
        $pdo->exec("ALTER TABLE departments ADD CONSTRAINT fk_dept_head FOREIGN KEY (head_id) REFERENCES employees(id) ON DELETE SET NULL");
        log_step("Department head foreign key constraint added", true);
    } catch (Exception $e) {
        // Likely already exists, silent ignore
    }

    // Check if already seeded by counting employees
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees");
    $employeeCount = $stmt->fetchColumn();

    if ($employeeCount == 0) {
        log_step("Database is empty. Seeding mock data...", true);

        // Seed Departments
        $depts = ['IT Department', 'Operations', 'Marketing', 'HR Department'];
        $deptIds = [];
        $stmt = $pdo->prepare("INSERT INTO departments (name, status) VALUES (?, 'Active')");
        foreach ($depts as $d) {
            $stmt->execute([$d]);
            $deptIds[$d] = $pdo->lastInsertId();
        }
        log_step("Seeded departments", true);

        // Seed Categories
        $categories = [
            ['Electronics', json_encode(['warranty_months' => 24, 'brand_required' => true])],
            ['Furniture', json_encode(['material_required' => true])],
            ['Vehicles', json_encode(['license_plate_required' => true, 'next_service_required' => true])],
            ['Office Supplies', json_encode(['reorder_level_required' => true])]
        ];
        $catIds = [];
        $stmt = $pdo->prepare("INSERT INTO categories (name, custom_fields) VALUES (?, ?)");
        foreach ($categories as $cat) {
            $stmt->execute($cat);
            $catIds[$cat[0]] = $pdo->lastInsertId();
        }
        log_step("Seeded asset categories", true);

        // Seed Employees
        $employees = [
            ['Admin User', 'admin@assetflow.com', 'admin', $deptIds['Operations']],
            ['Vikram Malhotra', 'vikram@assetflow.com', 'asset_manager', $deptIds['Operations']],
            ['Priya Sharma', 'priya@assetflow.com', 'dept_head', $deptIds['IT Department']],
            ['Rajesh Kumar', 'rajesh@assetflow.com', 'dept_head', $deptIds['Operations']],
            ['Amit Singh', 'amit@assetflow.com', 'dept_head', $deptIds['HR Department']],
            ['Sneha Patel', 'sneha@assetflow.com', 'employee', $deptIds['Marketing']],
            ['Sunil Verma', 'sunil@assetflow.com', 'employee', $deptIds['IT Department']],
            ['Neha Gupta', 'neha@assetflow.com', 'employee', $deptIds['IT Department']]
        ];
        $empIds = [];
        $stmt = $pdo->prepare("INSERT INTO employees (name, email, password, department_id, role, status) VALUES (?, ?, ?, ?, ?, 'Active')");
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        foreach ($employees as $emp) {
            $stmt->execute([$emp[0], $emp[1], $hashedPassword, $emp[3], $emp[2]]);
            $empIds[$emp[0]] = $pdo->lastInsertId();
        }
        log_step("Seeded employees", true);

        // Update Departments with Heads
        $pdo->prepare("UPDATE departments SET head_id = ? WHERE id = ?")->execute([$empIds['Priya Sharma'], $deptIds['IT Department']]);
        $pdo->prepare("UPDATE departments SET head_id = ? WHERE id = ?")->execute([$empIds['Rajesh Kumar'], $deptIds['Operations']]);
        $pdo->prepare("UPDATE departments SET head_id = ? WHERE id = ?")->execute([$empIds['Amit Singh'], $deptIds['HR Department']]);
        log_step("Assigned department heads", true);

        // Seed Assets
        $assets = [
            ['MacBook Pro 16', $catIds['Electronics'], 'AF-0001', 'SN-MBP-9812', '2025-01-15', 2500.00, 'Good', 'Bangalore Office', 0, 'Allocated'],
            ['Dell XPS 15', $catIds['Electronics'], 'AF-0002', 'SN-DELL-4412', '2025-05-10', 1800.00, 'New', 'Mumbai Office', 0, 'Available'],
            ['Conference Table A', $catIds['Furniture'], 'AF-0003', 'SN-TAB-001', '2024-08-20', 1200.00, 'Good', 'Bangalore Office', 1, 'Available'],
            ['Tesla Model 3', $catIds['Vehicles'], 'AF-0004', 'SN-TESLA-889', '2024-11-05', 45000.00, 'Good', 'San Francisco Office', 1, 'Available'],
            ['Ergonomic Chair', $catIds['Furniture'], 'AF-0005', 'SN-CHR-771', '2025-03-12', 350.00, 'Damaged', 'Bangalore Office', 0, 'Under Maintenance'],
            ['iPad Air', $catIds['Electronics'], 'AF-0006', 'SN-IPAD-332', '2025-06-01', 600.00, 'Good', 'Delhi Office', 0, 'Allocated']
        ];
        $assetIds = [];
        $stmt = $pdo->prepare("INSERT INTO assets (name, category_id, tag, serial_number, acquisition_date, acquisition_cost, condition_state, location, is_shared, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($assets as $asset) {
            $stmt->execute($asset);
            $assetIds[$asset[2]] = $pdo->lastInsertId();
        }
        log_step("Seeded assets", true);

        // Seed Allocations
        // MacBook Pro allocated to Priya Sharma (active)
        $pdo->prepare("INSERT INTO allocations (asset_id, employee_id, allocated_by, expected_return_date, status) VALUES (?, ?, ?, '2026-12-31', 'Active')")
            ->execute([$assetIds['AF-0001'], $empIds['Priya Sharma'], $empIds['Vikram Malhotra']]);
        
        // iPad Air allocated to Sunil Verma (overdue!)
        $pdo->prepare("INSERT INTO allocations (asset_id, employee_id, allocated_by, expected_return_date, status) VALUES (?, ?, ?, '2026-07-10', 'Overdue')")
            ->execute([$assetIds['AF-0006'], $empIds['Sunil Verma'], $empIds['Vikram Malhotra']]);
        log_step("Seeded active and overdue allocations", true);

        // Seed Bookings
        // Sneha booked Conference Table A today
        $todayStr = date('Y-m-d');
        $pdo->prepare("INSERT INTO bookings (asset_id, employee_id, booking_date, start_time, end_time, status) VALUES (?, ?, ?, '09:00:00', '10:30:00', 'Upcoming')")
            ->execute([$assetIds['AF-0003'], $empIds['Sneha Patel'], $todayStr]);
        log_step("Seeded resource bookings", true);

        // Seed Maintenance Requests
        // Ergonomic Chair under maintenance
        $pdo->prepare("INSERT INTO maintenance_requests (asset_id, reported_by, description, priority, status, assigned_technician, notes) VALUES (?, ?, 'Gas lift cylinder leaking, sinks to lowest height', 'High', 'Technician Assigned', 'Technician Bob', 'Assigned Bob to perform cylinder replacement')")
            ->execute([$assetIds['AF-0005'], $empIds['Neha Gupta']]);
        log_step("Seeded maintenance logs", true);

        // Seed Audit Cycles
        $pdo->prepare("INSERT INTO audit_cycles (name, department_id, location, start_date, end_date, status, created_by) VALUES ('Q3 IT Equipment Audit', ?, 'Bangalore Office', '2026-07-01', '2026-07-31', 'Active', ?)")
            ->execute([$deptIds['IT Department'], $empIds['Admin User']]);
        $auditId = $pdo->lastInsertId();
        
        // Associate auditor
        $pdo->prepare("INSERT INTO audit_auditors (audit_cycle_id, employee_id) VALUES (?, ?)")
            ->execute([$auditId, $empIds['Vikram Malhotra']]);

        // Add IT assets to audit items
        $pdo->prepare("INSERT INTO audit_items (audit_cycle_id, asset_id, status) VALUES (?, ?, 'Pending')")
            ->execute([$auditId, $assetIds['AF-0001']]);
        $pdo->prepare("INSERT INTO audit_items (audit_cycle_id, asset_id, status) VALUES (?, ?, 'Pending')")
            ->execute([$auditId, $assetIds['AF-0006']]);
        log_step("Seeded active audit cycles and scope", true);

        // Log actions
        $logs = [
            [$empIds['Admin User'], 'System Initialized', 'Database setup completed and seeded with mock data.'],
            [$empIds['Vikram Malhotra'], 'Asset Registered', 'Registered MacBook Pro 16 (AF-0001)'],
            [$empIds['Vikram Malhotra'], 'Asset Allocated', 'Allocated AF-0001 to Priya Sharma'],
            [$empIds['Vikram Malhotra'], 'Asset Allocated', 'Allocated AF-0006 to Sunil Verma'],
            [$empIds['Neha Gupta'], 'Maintenance Requested', 'Reported issue with Ergonomic Chair (AF-0005)'],
            [$empIds['Admin User'], 'Audit Cycle Created', 'Created Q3 IT Equipment Audit (ID: ' . $auditId . ')']
        ];
        $stmt = $pdo->prepare("INSERT INTO activity_logs (employee_id, action, details) VALUES (?, ?, ?)");
        foreach ($logs as $log) {
            $stmt->execute($log);
        }

        // Add notifications
        $notifications = [
            [$empIds['Priya Sharma'], 'New Asset Allocated', 'MacBook Pro 16 (AF-0001) has been allocated to you.', 'info'],
            [$empIds['Sunil Verma'], 'Overdue Return Warning', 'The iPad Air (AF-0006) allocation is overdue! Please return it or contact the Asset Manager.', 'warning'],
            [null, 'Audit Discrepancy Flagged', 'Sunil Verma\'s iPad Air was flagged as overdue during Q3 IT Equipment Audit.', 'danger'],
            [$empIds['Vikram Malhotra'], 'Audit Cycle Assigned', 'You have been assigned as an auditor for Q3 IT Equipment Audit.', 'info']
        ];
        $stmt = $pdo->prepare("INSERT INTO notifications (employee_id, title, message, type) VALUES (?, ?, ?, ?)");
        foreach ($notifications as $notif) {
            $stmt->execute($notif);
        }
        log_step("Seeded initial audit logs & notifications", true);
        
    } else {
        log_step("Database already initialized and contains data.", true);
    }

    if (!$is_cli) {
        echo '<div style="margin-top: 24px; text-align: center; color: var(--success); font-weight: 600;">
                ✓ System setup completed successfully!
              </div>';
        echo '<a href="./" class="btn">Launch AssetFlow Dashboard</a>';
        echo '</div></body></html>';
    } else {
        echo "\nSetup completed successfully!\n";
    }

} catch (PDOException $e) {
    log_step("Setup execution failed", false, $e->getMessage());
    if (!$is_cli) {
        echo '</div></body></html>';
    }
}
?>

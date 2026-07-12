<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    try {
        $db = getDBConnection();
        $stmtLog = $db->prepare("INSERT INTO activity_logs (employee_id, action, details) VALUES (?, ?, ?)");
        $stmtLog->execute([$_SESSION['user_id'], 'User Logout', 'Logged out of the system.']);
    } catch(Exception $e) {}
}

session_destroy();
header('Location: login.php');
exit;
?>

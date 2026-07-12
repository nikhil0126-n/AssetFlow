<?php
require_once 'header.php';

global $user_role, $db, $user_id;

// Guard: Admin only
if ($user_role !== 'admin') {
    header('Location: org_setup.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    try {
        // Log activity before deleting to record name
        $stmtName = $db->prepare("SELECT name FROM departments WHERE id = ?");
        $stmtName->execute([$id]);
        $deptName = $stmtName->fetchColumn();

        if ($deptName) {
            $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$id]);

            log_activity($db, $user_id, 'Delete Department', "Deleted department: $deptName");
            create_notification($db, null, 'Department Deleted', "Department '$deptName' was deleted.", 'info');

            header('Location: org_setup.php?tab=tab-departments&msg=Department+deleted+successfully.&type=success');
            exit;
        } else {
            header('Location: org_setup.php?tab=tab-departments&msg=Department+not+found.&type=error');
            exit;
        }
    } catch (PDOException $e) {
        // Handle foreign key constraint violation gracefully
        if ($e->getCode() == '23000') {
            header('Location: org_setup.php?tab=tab-departments&msg=Cannot+delete+department+because+it+is+referenced+by+other+records.&type=error');
        } else {
            header('Location: org_setup.php?tab=tab-departments&msg=Database+error:+' . urlencode($e->getMessage()) . '&type=error');
        }
        exit;
    }
} else {
    header('Location: org_setup.php?tab=tab-departments&msg=Invalid+department+ID.&type=error');
    exit;
}
?>

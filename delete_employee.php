    try {
        // Log activity before deleting to record name
        $stmtName = $db->prepare("SELECT name FROM employees WHERE id = ?");
        $stmtName->execute([$id]);
        $empName = $stmtName->fetchColumn();

        if ($empName) {
            $stmt = $db->prepare("DELETE FROM employees WHERE id = ?");
            $stmt->execute([$id]);

            log_activity($db, $user_id, 'Delete Employee', "Deleted employee: $empName");
            create_notification($db, null, 'Employee Deleted', "Employee '$empName' account was deleted.", 'info');

            header('Location: org_setup.php?tab=tab-directory&msg=Employee+deleted+successfully.&type=success');
            exit;
        } else {
            header('Location: org_setup.php?tab=tab-directory&msg=Employee+not+found.&type=error');
            exit;
        }
    } catch (PDOException $e) {
        // Handle foreign key constraint violation gracefully
        if ($e->getCode() == '23000') {
            header('Location: org_setup.php?tab=tab-directory&msg=Cannot+delete+employee+because+they+are+referenced+by+other+records.&type=error');
        } else {
            header('Location: org_setup.php?tab=tab-directory&msg=Database+error:+' . urlencode($e->getMessage()) . '&type=error');
        }
        exit;
    }
} else {
    header('Location: org_setup.php?tab=tab-directory&msg=Invalid+employee+ID.&type=error');
    exit;
}
?>
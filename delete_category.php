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
        $stmtName = $db->prepare("SELECT name FROM categories WHERE id = ?");
        $stmtName->execute([$id]);
        $catName = $stmtName->fetchColumn();

        if ($catName) {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);

            log_activity($db, $user_id, 'Delete Category', "Deleted category: $catName");
            create_notification($db, null, 'Category Deleted', "Category '$catName' was deleted.", 'info');

            header('Location: org_setup.php?tab=tab-categories&msg=Category+deleted+successfully.&type=success');
            exit;
        } else {
            header('Location: org_setup.php?tab=tab-categories&msg=Category+not+found.&type=error');
            exit;
        }
    } catch (PDOException $e) {
        // Handle foreign key constraint violation gracefully
        if ($e->getCode() == '23000') {
            header('Location: org_setup.php?tab=tab-categories&msg=Cannot+delete+category+because+it+is+referenced+by+other+records.&type=error');
        } else {
            header('Location: org_setup.php?tab=tab-categories&msg=Database+error:+' . urlencode($e->getMessage()) . '&type=error');
        }
        exit;
    }
} else {
    header('Location: org_setup.php?tab=tab-categories&msg=Invalid+category+ID.&type=error');
    exit;
}
?>

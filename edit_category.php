<?php
require_once 'header.php';

// Guard: Admin only
if ($user_role !== 'admin') {
    header('Location: org_setup.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

// Fetch Category Record
$stmtCat = $db->prepare("SELECT * FROM categories WHERE id = ?");
$stmtCat->execute([$id]);
$cat = $stmtCat->fetch();

if (!$cat) {
    header('Location: org_setup.php?tab=tab-categories&msg=Category+not+found.&type=error');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $attr_names = $_POST['attr_name'] ?? [];
    $attr_types = $_POST['attr_type'] ?? [];

    if ($name === '') {
        $error = 'Category Name is required.';
    } else {
        try {
            $custom_fields = [];
            for ($i = 0; $i < count($attr_names); $i++) {
                $n = trim($attr_names[$i]);
                $t = $attr_types[$i] ?? 'text';
                if ($n !== '') {
                    $custom_fields[] = ['name' => $n, 'type' => $t];
                }
            }

            $stmt = $db->prepare("UPDATE categories SET name = ?, custom_fields = ? WHERE id = ?");
            $stmt->execute([$name, json_encode($custom_fields), $id]);

            log_activity($db, $user_id, 'Edit Category', "Updated asset category: $name");
            
            header('Location: org_setup.php?tab=tab-categories&msg=Category+updated+successfully.&type=success');
            exit;
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Format fields list for JS loader
$jsFields = '[]';
try {
    $decoded = json_decode($cat['custom_fields'] ?? '[]', true);
    if (is_array($decoded)) {
        $reformatted = [];
        foreach ($decoded as $key => $val) {
            if (is_array($val) && isset($val['name'])) {
                $reformatted[] = $val;
            } else {
                $reformatted[] = ['name' => $key, 'type' => 'text'];
            }
        }
        $jsFields = json_encode($reformatted);
    }
} catch(Exception $e){}
?>

<section class="app-view">
    <div class="card-glow" style="max-width: 650px; margin: 40px auto; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 32px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; border-bottom:1px solid var(--border-color); padding-bottom:12px;">
            <h3 style="margin:0; font-family: var(--font-outfit); font-size:1.3rem;">Edit Category</h3>
            <a href="org_setup.php?tab=tab-categories" class="text-link" style="font-size:0.9rem;">← Back to list</a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.15); color: #f87171; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 16px;">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="edit_category.php?id=<?php echo $id; ?>" method="POST">
            <div class="form-group" style="margin-bottom: 24px;">
                <label for="cat-name" style="display:block; margin-bottom:6px; font-weight:600; font-size:0.9rem;">Category Name</label>
                <input type="text" name="name" id="cat-name" required value="<?php echo htmlspecialchars($cat['name']); ?>" class="form-control" style="width:100%;">
            </div>

            <div style="border-top:1px solid var(--border-color); padding-top:20px; margin-bottom:24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h4 style="margin:0; font-family: var(--font-outfit); font-size:1rem;">Dynamic Specifications Attributes</h4>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addAttributeRow()">+ Add Field</button>
                </div>

                <div id="attributes-list" style="display:flex; flex-direction:column; gap:12px; margin-bottom:16px;">
                    <!-- Appended dynamically -->
                </div>
            </div>

            <div style="display:flex; gap:12px; justify-content:flex-end; border-top:1px solid var(--border-color); padding-top:20px;">
                <a href="org_setup.php?tab=tab-categories" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</section>

<script>
function addAttributeRow(name = '', type = 'text') {
    const list = document.getElementById('attributes-list');
    const row = document.createElement('div');
    row.className = 'cat-attribute-row';
    row.style.display = 'flex';
    row.style.gap = '10px';
    row.style.alignItems = 'center';
    row.innerHTML = `
        <input type="text" name="attr_name[]" class="form-control" placeholder="Field Name (e.g. Warranty)" value="${name}" required style="flex-grow:1;">
        <select name="attr_type[]" class="form-control" style="width:130px;">
            <option value="text" ${type === 'text' ? 'selected' : ''}>Text</option>
            <option value="number" ${type === 'number' ? 'selected' : ''}>Number</option>
            <option value="date" ${type === 'date' ? 'selected' : ''}>Date</option>
        </select>
        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.cat-attribute-row').remove()" style="height:38px;">Remove</button>
    `;
    list.appendChild(row);
}

// Load existing fields
document.addEventListener('DOMContentLoaded', () => {
    const fields = <?php echo $jsFields; ?>;
    if (fields.length === 0) {
        addAttributeRow();
    } else {
        fields.forEach(f => addAttributeRow(f.name, f.type));
    }
});
</script>
<?php require_once 'footer.php'; ?>

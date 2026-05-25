<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'update') {
        $name  = trim($_POST['name'] ?? '');
        $icon  = trim($_POST['icon'] ?? 'bi-tag');
        $color = trim($_POST['color'] ?? '#64748B');
        if ($name) {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO expense_categories (user_id, name, icon, color) VALUES (?,?,?,?)");
                $stmt->execute([$userId, $name, $icon, $color]);
                flash('success', 'Category added');
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE expense_categories SET name=?, icon=?, color=? WHERE id=? AND user_id=?");
                $stmt->execute([$name, $icon, $color, $id, $userId]);
                flash('success', 'Category updated');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id=? AND user_id=?");
        $stmt->execute([$id, $userId]);
        flash('success', 'Category deleted');
    }
    header('Location: categories.php'); exit;
}

$stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM expenses e WHERE e.category_id=c.id) used FROM expense_categories c WHERE c.user_id=? ORDER BY c.name");
$stmt->execute([$userId]);
$cats = $stmt->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE id=? AND user_id=?");
    $stmt->execute([(int)$_GET['edit'], $userId]);
    $edit = $stmt->fetch();
}

$icons = ['bi-cup-hot','bi-cart','bi-fuel-pump','bi-house','bi-lightning','bi-droplet','bi-phone','bi-bus-front','bi-tools','bi-heart-pulse','bi-book','bi-gift','bi-bag','bi-currency-exchange','bi-tag'];
$colors = ['#DC2626','#EA580C','#F59E0B','#16A34A','#0EA5E9','#2563EB','#7C3AED','#DB2777','#475569','#0F766E'];

$pageTitle = 'Categories';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Expense Categories'; $topbarBack = 'index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

    <div class="section-title"><h3><?= $edit?'Edit':'New' ?> Category</h3></div>
    <div class="list-card" style="padding:14px;">
        <form method="post">
            <input type="hidden" name="action" value="<?= $edit?'update':'add' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div class="form-group">
                <label class="form-label">Name *</label>
                <input class="form-control" name="name" value="<?= e($edit['name']??'') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Icon</label>
                <select class="form-select" name="icon">
                    <?php foreach ($icons as $ic): ?>
                        <option value="<?= $ic ?>" <?= ($edit['icon']??'')===$ic?'selected':'' ?>><?= $ic ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Color</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($colors as $col): ?>
                        <label style="cursor:pointer;">
                            <input type="radio" name="color" value="<?= $col ?>" <?= ($edit['color']??'#64748B')===$col?'checked':'' ?> style="display:none;" class="color-radio">
                            <span style="display:inline-block;width:30px;height:30px;border-radius:50%;background:<?= $col ?>;border:3px solid #fff;box-shadow:0 0 0 1px var(--border);"></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="btn btn-primary btn-block"><?= $edit?'Update':'Add' ?></button>
            <?php if ($edit): ?><a href="categories.php" class="btn btn-light btn-block mt-2">Cancel</a><?php endif; ?>
        </form>
    </div>

    <div class="section-title mt-4"><h3>All Categories</h3></div>
    <div class="list-card">
        <?php foreach ($cats as $c): ?>
            <div class="list-item">
                <div class="li-ico" style="background:<?= e($c['color']) ?>;color:#fff;"><i class="bi <?= e($c['icon']) ?>"></i></div>
                <div class="li-body">
                    <div class="li-title"><?= e($c['name']) ?></div>
                    <div class="li-sub"><?= (int)$c['used'] ?> entr<?= (int)$c['used']===1?'y':'ies' ?></div>
                </div>
                <div class="li-right d-flex gap-1">
                    <a href="?edit=<?= $c['id'] ?>" class="btn btn-light btn-sm"><i class="bi bi-pencil"></i></a>
                    <form method="post" data-confirm="Delete this category? Entries become uncategorized.">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button class="btn btn-light btn-sm" style="color:var(--danger)"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.querySelectorAll('.color-radio').forEach(r => {
    const update = () => document.querySelectorAll('.color-radio').forEach(x => x.nextElementSibling.style.borderColor = x.checked ? '#0F766E' : '#fff');
    r.addEventListener('change', update); update();
});
</script>

<div style="height:30px"></div>
<?php $active='add'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

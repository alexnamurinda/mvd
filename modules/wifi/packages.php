<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$bizId = defaultBusinessId($pdo);
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bizId) {
    if ($action === 'add' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $duration = (int)($_POST['duration_days'] ?? 0);
        $speed = (int)($_POST['speed_mbps'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($name && $duration > 0 && $price >= 0) {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO wifi_packages (business_id, name, duration_days, speed_mbps, price, description, is_active) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$bizId, $name, $duration, $speed, $price, $desc, $is_active]);
                logActivity($pdo, uid(), 'Added package', $name, 'bi-box-seam');
                flash('success', 'Package added');
            } else {
                $pid = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE wifi_packages SET name=?, duration_days=?, speed_mbps=?, price=?, description=?, is_active=? WHERE id=? AND business_id=?");
                $stmt->execute([$name, $duration, $speed, $price, $desc, $is_active, $pid, $bizId]);
                flash('success', 'Package updated');
            }
        }
    } elseif ($action === 'delete') {
        $pid = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM wifi_packages WHERE id=? AND business_id=?");
        $stmt->execute([$pid, $bizId]);
        flash('success', 'Package deleted');
    }
    header('Location: packages.php'); exit;
}

$packages = [];
if ($bizId) {
    $stmt = $pdo->prepare("SELECT * FROM wifi_packages WHERE business_id=? ORDER BY duration_days ASC, price ASC");
    $stmt->execute([$bizId]);
    $packages = $stmt->fetchAll();
}

// Pre-fill edit?
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM wifi_packages WHERE id=? AND business_id=?");
    $stmt->execute([(int)$_GET['edit'], $bizId]);
    $edit = $stmt->fetch();
}

$pageTitle = 'WiFi Packages';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Packages'; $topbarBack = 'index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="section-title"><h3><?= $edit ? 'Edit Package' : 'Add New Package' ?></h3></div>
    <div class="list-card" style="padding:14px;">
        <form method="post">
            <input type="hidden" name="action" value="<?= $edit?'update':'add' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div class="form-group">
                <label class="form-label">Package Name *</label>
                <input class="form-control" name="name" placeholder="e.g. Daily 24hrs" value="<?= e($edit['name']??'') ?>" required>
            </div>
            <div class="row g-2">
                <div class="col-4 form-group">
                    <label class="form-label">Days *</label>
                    <input class="form-control" name="duration_days" type="number" min="1" value="<?= e($edit['duration_days']??'') ?>" required>
                </div>
                <div class="col-4 form-group">
                    <label class="form-label">Mbps</label>
                    <input class="form-control" name="speed_mbps" type="number" min="0" value="<?= e($edit['speed_mbps']??'') ?>">
                </div>
                <div class="col-4 form-group">
                    <label class="form-label">Price *</label>
                    <input class="form-control" name="price" type="number" step="0.01" min="0" value="<?= e($edit['price']??'') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <input class="form-control" name="description" value="<?= e($edit['description']??'') ?>">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_active" id="isAct" <?= (!$edit || $edit['is_active'])?'checked':'' ?>>
                <label class="form-check-label" for="isAct">Active &amp; available for sale</label>
            </div>
            <button class="btn btn-primary btn-block"><i class="bi bi-check2"></i> <?= $edit?'Update':'Add' ?> Package</button>
            <?php if ($edit): ?>
                <a href="packages.php" class="btn btn-light btn-block mt-2">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="section-title mt-4"><h3>All Packages (<?= count($packages) ?>)</h3></div>
    <div class="list-card">
        <?php if (empty($packages)): ?>
            <div class="empty-state"><div class="icon-circle"><i class="bi bi-box-seam"></i></div>No packages yet.</div>
        <?php else: foreach ($packages as $p): ?>
            <div class="list-item">
                <div class="li-ico" style="background:linear-gradient(135deg,#0F766E,#14B8A6);color:#fff;"><i class="bi bi-box-seam"></i></div>
                <div class="li-body">
                    <div class="li-title"><?= e($p['name']) ?> <?php if(!$p['is_active']): ?><span class="pill pill-suspended">inactive</span><?php endif; ?></div>
                    <div class="li-sub"><?= $p['duration_days'] ?> day<?= $p['duration_days']>1?'s':'' ?> · <?= $p['speed_mbps']?:'—' ?> Mbps</div>
                </div>
                <div class="li-right">
                    <div class="li-amount in"><?= money($p['price']) ?></div>
                    <div class="d-flex gap-1 mt-1">
                        <a href="?edit=<?= $p['id'] ?>" class="btn btn-light btn-sm"><i class="bi bi-pencil"></i></a>
                        <form method="post" style="display:inline" data-confirm="Delete this package?">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn btn-light btn-sm" style="color:var(--danger);"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div style="height:30px"></div>
<?php $active='wifi'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

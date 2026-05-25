<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$bizId = defaultBusinessId($pdo);
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM wifi_customers WHERE id=? AND business_id=?");
$stmt->execute([$id, $bizId]);
$cust = $stmt->fetch();
if (!$cust) { flash('error', 'Customer not found'); header('Location: customers.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    if ($full_name === '') $errors[] = 'Name is required';
    if ($phone === '')     $errors[] = 'Phone is required';

    if (!$errors) {
        $stmt = $pdo->prepare("UPDATE wifi_customers SET full_name=?, phone=?, alt_phone=?, email=?, location=?, house_no=?, device_mac=?, pppoe_username=?, notes=?, status=? WHERE id=? AND business_id=?");
        $stmt->execute([
            $full_name, $phone,
            trim($_POST['alt_phone'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['location'] ?? ''),
            trim($_POST['house_no'] ?? ''),
            trim($_POST['device_mac'] ?? ''),
            trim($_POST['pppoe_username'] ?? ''),
            trim($_POST['notes'] ?? ''),
            $_POST['status'] ?? 'active',
            $id, $bizId
        ]);
        logActivity($pdo, uid(), 'Updated customer', $full_name, 'bi-person-gear');
        flash('success', 'Customer updated');
        header('Location: customer_view.php?id=' . $id); exit;
    }
}

$pageTitle = 'Edit Customer';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Edit Customer';
$topbarBack = 'customer_view.php?id=' . $id;
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <?php if ($errors): ?>
        <div class="alert alert-danger"><?= e(implode('. ', $errors)) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input class="form-control" name="full_name" value="<?= e($cust['full_name']) ?>" required>
        </div>
        <div class="row g-2">
            <div class="col-6 form-group">
                <label class="form-label">Phone *</label>
                <input class="form-control" name="phone" value="<?= e($cust['phone']) ?>" required>
            </div>
            <div class="col-6 form-group">
                <label class="form-label">Alt Phone</label>
                <input class="form-control" name="alt_phone" value="<?= e($cust['alt_phone']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-control" name="email" type="email" value="<?= e($cust['email']) ?>">
        </div>
        <div class="row g-2">
            <div class="col-7 form-group">
                <label class="form-label">Location / Zone</label>
                <input class="form-control" name="location" value="<?= e($cust['location']) ?>">
            </div>
            <div class="col-5 form-group">
                <label class="form-label">House No.</label>
                <input class="form-control" name="house_no" value="<?= e($cust['house_no']) ?>">
            </div>
        </div>
        <div class="row g-2">
            <div class="col-6 form-group">
                <label class="form-label">Device MAC</label>
                <input class="form-control" name="device_mac" value="<?= e($cust['device_mac']) ?>">
            </div>
            <div class="col-6 form-group">
                <label class="form-label">PPPoE Username</label>
                <input class="form-control" name="pppoe_username" value="<?= e($cust['pppoe_username']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <?php foreach (['active'=>'Active','suspended'=>'Suspended','expired'=>'Expired'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= $cust['status']===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="3"><?= e($cust['notes']) ?></textarea>
        </div>
        <button class="btn btn-primary btn-lg btn-block"><i class="bi bi-check2"></i> Save Changes</button>
        <a href="customer_view.php?id=<?= $id ?>" class="btn btn-light btn-block mt-2">Cancel</a>
    </form>
</div>

<div style="height:30px"></div>
<?php $active='wifi'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

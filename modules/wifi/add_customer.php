<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$bizId = defaultBusinessId($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $phone= trim($_POST['phone'] ?? '');
    $alt  = trim($_POST['alt_phone'] ?? '');
    $email= trim($_POST['email'] ?? '');
    $loc  = trim($_POST['location'] ?? '');
    $house= trim($_POST['house_no'] ?? '');
    $mac  = trim($_POST['device_mac'] ?? '');
    $ppp  = trim($_POST['pppoe_username'] ?? '');
    $note = trim($_POST['notes'] ?? '');

    if (!$name || !$phone) {
        $error = 'Name and phone are required.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO wifi_customers (business_id, full_name, phone, alt_phone, email, location, house_no, device_mac, pppoe_username, notes) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$bizId, $name, $phone, $alt, $email, $loc, $house, $mac, $ppp, $note]);
        $cid = (int)$pdo->lastInsertId();
        logActivity($pdo, uid(), 'Added customer', $name, 'bi-person-plus');

        // Optional: immediately create subscription
        if (!empty($_POST['create_sub']) && !empty($_POST['package_id'])) {
            $pkg = $pdo->prepare("SELECT * FROM wifi_packages WHERE id = ? AND business_id = ?");
            $pkg->execute([$_POST['package_id'], $bizId]);
            $pkg = $pkg->fetch();
            if ($pkg) {
                $start = date('Y-m-d');
                $expiry = date('Y-m-d', strtotime("+{$pkg['duration_days']} days"));
                $pdo->prepare("INSERT INTO wifi_subscriptions (customer_id, package_id, start_date, expiry_date) VALUES (?,?,?,?)")
                    ->execute([$cid, $pkg['id'], $start, $expiry]);
            }
        }

        flash('success', 'Customer added successfully.');
        header('Location: customer_view.php?id=' . $cid);
        exit;
    }
}

// Get packages for dropdown
$packages = [];
if ($bizId) {
    $stmt = $pdo->prepare("SELECT * FROM wifi_packages WHERE business_id=? AND is_active=1 ORDER BY price ASC");
    $stmt->execute([$bizId]);
    $packages = $stmt->fetchAll();
}

$pageTitle = 'Add Customer';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'New Customer';
$topbarBack = 'customers.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<?php if ($error): ?>
<div class="alert alert-danger mt-3"><i class="bi bi-exclamation-circle"></i><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="px-page mt-3">
    <div class="list-card" style="padding:16px;">
        <div class="form-group">
            <label class="form-label">Full Name *</label>
            <div class="input-icon"><i class="bi bi-person"></i>
                <input type="text" name="full_name" required class="form-control" placeholder="e.g. John Mukasa" value="<?= e($_POST['full_name'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Phone *</label>
            <div class="input-icon"><i class="bi bi-telephone"></i>
                <input type="tel" name="phone" required class="form-control" placeholder="+256 7xx xxx xxx" value="<?= e($_POST['phone'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Alt. Phone</label>
            <div class="input-icon"><i class="bi bi-telephone-plus"></i>
                <input type="tel" name="alt_phone" class="form-control" value="<?= e($_POST['alt_phone'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Email</label>
            <div class="input-icon"><i class="bi bi-envelope"></i>
                <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="list-card" style="padding:16px;">
        <h3 style="font-size:.92rem;margin-bottom:12px;">Location &amp; Device</h3>
        <div class="form-group">
            <label class="form-label">Location / Area</label>
            <div class="input-icon"><i class="bi bi-geo-alt"></i>
                <input type="text" name="location" class="form-control" placeholder="e.g. Kireka, Banda Zone" value="<?= e($_POST['location'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">House / Unit No.</label>
            <input type="text" name="house_no" class="form-control" placeholder="Block B / House 12" value="<?= e($_POST['house_no'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Device MAC (optional)</label>
            <input type="text" name="device_mac" class="form-control" placeholder="AA:BB:CC:DD:EE:FF" value="<?= e($_POST['device_mac'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">PPPoE Username (optional)</label>
            <input type="text" name="pppoe_username" class="form-control" value="<?= e($_POST['pppoe_username'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" placeholder="Anything you want to remember about this customer..."><?= e($_POST['notes'] ?? '') ?></textarea>
        </div>
    </div>

    <?php if ($packages): ?>
    <div class="list-card" style="padding:16px;">
        <div class="form-check d-flex align-items-center gap-2 mb-3">
            <input type="checkbox" id="createSub" name="create_sub" value="1" class="form-check-input" style="margin-top:0;">
            <label for="createSub" class="form-label mb-0">Activate package now</label>
        </div>
        <div class="form-group">
            <label class="form-label">Choose Package</label>
            <select name="package_id" class="form-select">
                <?php foreach ($packages as $p): ?>
                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> — <?= money($p['price']) ?> · <?= $p['duration_days'] ?> day(s)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>

    <div class="px-page mb-3">
        <button type="submit" class="btn btn-primary btn-block btn-lg">
            <i class="bi bi-check-lg"></i> Save Customer
        </button>
    </div>
</form>

<div style="height:14px"></div>
<?php $active='wifi'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

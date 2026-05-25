<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$bizId = defaultBusinessId($pdo);
$id    = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM wifi_customers WHERE id=? AND business_id=?");
$stmt->execute([$id, $bizId]);
$c = $stmt->fetch();
if (!$c) { header('Location: customers.php'); exit; }

// Active subscription
$stmt = $pdo->prepare("SELECT s.*, p.name AS package_name, p.duration_days, p.price FROM wifi_subscriptions s JOIN wifi_packages p ON p.id=s.package_id WHERE s.customer_id=? AND s.status='active' ORDER BY s.expiry_date DESC LIMIT 1");
$stmt->execute([$id]); $sub = $stmt->fetch();

// All subscriptions history
$stmt = $pdo->prepare("SELECT s.*, p.name AS package_name FROM wifi_subscriptions s JOIN wifi_packages p ON p.id=s.package_id WHERE s.customer_id=? ORDER BY s.start_date DESC LIMIT 10");
$stmt->execute([$id]); $subs = $stmt->fetchAll();

// Payments
$stmt = $pdo->prepare("SELECT * FROM wifi_payments WHERE customer_id=? ORDER BY paid_on DESC, created_at DESC");
$stmt->execute([$id]); $pays = $stmt->fetchAll();

$totalPaid = array_sum(array_column($pays, 'amount'));
$daysLeft  = $sub ? floor((strtotime($sub['expiry_date']) - strtotime(date('Y-m-d')))/86400) : null;

$pageTitle = $c['full_name'];
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Customer';
$topbarBack = 'customers.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<?php if ($f = flash('success')): ?>
<div class="alert alert-success auto-dismiss mt-3"><i class="bi bi-check-circle-fill"></i><?= e($f) ?></div>
<?php endif; ?>

<div class="detail-strip">
    <div class="d-flex align-items-center gap-3">
        <div style="width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;">
            <?= e(strtoupper(substr($c['full_name'],0,1))) ?>
        </div>
        <div>
            <h2 style="color:#fff;margin:0;font-size:1.25rem;"><?= e($c['full_name']) ?></h2>
            <div class="sub"><i class="bi bi-telephone"></i> <?= e($c['phone']) ?></div>
            <?php if ($c['location']): ?>
            <div class="sub"><i class="bi bi-geo-alt"></i> <?= e($c['location']) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Status card -->
<div class="balance-card">
    <?php if ($sub):
        $isExpired = $daysLeft < 0; $isExpiring = $daysLeft >= 0 && $daysLeft <=3;
    ?>
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="label">Active Plan</div>
                <div class="amount" style="font-size:1.2rem;"><?= e($sub['package_name']) ?></div>
                <div class="text-muted" style="font-size:.8rem;">Expires <?= friendlyDate($sub['expiry_date']) ?></div>
            </div>
            <span class="pill <?= $isExpired?'pill-expired':($isExpiring?'pill-suspended':'pill-active') ?>">
                <?= $isExpired ? abs($daysLeft).'d overdue' : $daysLeft.' day'.($daysLeft===1?'':'s').' left' ?>
            </span>
        </div>
    <?php else: ?>
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="label">No active plan</div>
                <div class="text-muted" style="font-size:.85rem;">Activate one by recording a payment.</div>
            </div>
            <span class="pill pill-expired">INACTIVE</span>
        </div>
    <?php endif; ?>

    <div class="mini-stats">
        <div class="stat">
            <div class="ico in"><i class="bi bi-cash"></i></div>
            <div><div class="lbl">Total Paid</div><div class="val"><?= money($totalPaid) ?></div></div>
        </div>
        <div class="stat">
            <div class="ico" style="background:#FEF3C7;color:#92400E;"><i class="bi bi-list-ol"></i></div>
            <div><div class="lbl">Payments</div><div class="val"><?= count($pays) ?></div></div>
        </div>
    </div>
</div>

<!-- Action buttons -->
<div class="px-page d-flex gap-2 mb-3">
    <a href="record_payment.php?customer=<?= $c['id'] ?>" class="btn btn-primary" style="flex:1;"><i class="bi bi-cash-coin"></i> New Payment</a>
    <a href="tel:<?= e($c['phone']) ?>" class="btn btn-light" style="flex:0 0 auto;"><i class="bi bi-telephone-fill"></i></a>
    <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$c['phone']) ?>" target="_blank" class="btn btn-light" style="flex:0 0 auto;"><i class="bi bi-whatsapp" style="color:#25D366;"></i></a>
</div>

<!-- Payment history -->
<div class="section-title">
    <h3>Payment History</h3>
    <?php if ($pays): ?><a href="record_payment.php?customer=<?= $c['id'] ?>">+ Add</a><?php endif; ?>
</div>
<div class="list-card">
    <?php if (empty($pays)): ?>
        <div class="empty-state">
            <div class="icon-circle"><i class="bi bi-receipt"></i></div>
            <div>No payments yet for this customer.</div>
        </div>
    <?php else: foreach ($pays as $p): ?>
        <a href="receipt.php?id=<?= $p['id'] ?>" class="list-item">
            <div class="li-ico bg-green-soft" style="color:#fff;"><i class="bi bi-check-lg"></i></div>
            <div class="li-body">
                <div class="li-title"><?= money($p['amount']) ?></div>
                <div class="li-sub"><?= friendlyDate($p['paid_on']) ?> · <?= ucfirst(str_replace('_',' ',$p['method'])) ?></div>
            </div>
            <div class="li-right">
                <?php if ($p['receipt_no']): ?>
                <div class="li-time"><i class="bi bi-receipt"></i> <?= e($p['receipt_no']) ?></div>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; endif; ?>
</div>

<!-- Subscription history -->
<?php if ($subs): ?>
<div class="section-title">
    <h3>Subscription History</h3>
</div>
<div class="list-card">
    <?php foreach ($subs as $s):
        $expired = strtotime($s['expiry_date']) < strtotime(date('Y-m-d'));
    ?>
        <div class="list-item">
            <div class="li-ico" style="<?= $expired?'background:#FEE2E2;color:#991B1B;':'' ?>"><i class="bi bi-box-seam"></i></div>
            <div class="li-body">
                <div class="li-title"><?= e($s['package_name']) ?></div>
                <div class="li-sub"><?= friendlyDate($s['start_date']) ?> → <?= friendlyDate($s['expiry_date']) ?></div>
            </div>
            <div class="li-right">
                <span class="pill <?= $expired?'pill-expired':'pill-active' ?>"><?= $expired?'EXPIRED':'ACTIVE' ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($c['notes']): ?>
<div class="section-title"><h3>Notes</h3></div>
<div class="list-card" style="padding:14px;">
    <div style="white-space:pre-wrap;font-size:.9rem;"><?= e($c['notes']) ?></div>
</div>
<?php endif; ?>

<div class="px-page mb-3">
    <a href="edit_customer.php?id=<?= $c['id'] ?>" class="btn btn-light btn-block"><i class="bi bi-pencil"></i> Edit Customer</a>
</div>

<div style="height:14px"></div>
<?php $active='wifi'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

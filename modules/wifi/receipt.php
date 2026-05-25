<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$bizId = defaultBusinessId($pdo);
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT p.*, c.full_name, c.phone, c.location, s.expiry_date, pk.name AS package_name, pk.duration_days
                       FROM wifi_payments p
                       JOIN wifi_customers c ON c.id = p.customer_id
                       LEFT JOIN wifi_subscriptions s ON s.id = p.subscription_id
                       LEFT JOIN wifi_packages pk ON pk.id = s.package_id
                       WHERE p.id = ? AND c.business_id = ?");
$stmt->execute([$id, $bizId]); $p = $stmt->fetch();
if (!$p) { header('Location: index.php'); exit; }

// Business info
$stmt = $pdo->prepare("SELECT b.name, b.phone, b.address, u.full_name AS owner, u.receipt_footer FROM businesses b JOIN users u ON u.id=b.user_id WHERE b.id=?");
$stmt->execute([$bizId]); $biz = $stmt->fetch();

$pageTitle = 'Receipt ' . $p['receipt_no'];
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Receipt';
$topbarBack = 'index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-3 no-print d-flex gap-2 mb-2">
    <button onclick="printDoc()" class="btn btn-primary" style="flex:1;"><i class="bi bi-printer"></i> Print</button>
    <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$p['phone']) ?>?text=<?= urlencode('Receipt '.$p['receipt_no'].': '.money($p['amount']).' received. Thank you!') ?>" target="_blank" class="btn btn-success" style="flex:1;"><i class="bi bi-whatsapp"></i> Send</a>
</div>

<div class="receipt-paper">
    <div class="biz">
        <h2><?= e($biz['name'] ?? APP_NAME) ?></h2>
        <?php if (!empty($biz['phone'])): ?><div><?= e($biz['phone']) ?></div><?php endif; ?>
        <?php if (!empty($biz['address'])): ?><div><?= e($biz['address']) ?></div><?php endif; ?>
    </div>
    <hr>
    <div class="row"><span>Receipt #</span><strong><?= e($p['receipt_no']) ?></strong></div>
    <div class="row"><span>Date</span><strong><?= date('M j, Y', strtotime($p['paid_on'])) ?></strong></div>
    <div class="row"><span>Time</span><strong><?= date('H:i', strtotime($p['created_at'])) ?></strong></div>
    <hr>
    <div class="row"><span>Customer</span><strong><?= e($p['full_name']) ?></strong></div>
    <div class="row"><span>Phone</span><strong><?= e($p['phone']) ?></strong></div>
    <?php if ($p['location']): ?>
    <div class="row"><span>Location</span><strong><?= e($p['location']) ?></strong></div>
    <?php endif; ?>
    <hr>
    <?php if ($p['package_name']): ?>
    <div class="row"><span>Service</span><strong><?= e($p['package_name']) ?></strong></div>
    <div class="row"><span>Days</span><strong><?= $p['duration_days'] ?></strong></div>
    <?php if ($p['expiry_date']): ?>
    <div class="row"><span>Valid Until</span><strong><?= date('M j, Y', strtotime($p['expiry_date'])) ?></strong></div>
    <?php endif; ?>
    <?php else: ?>
    <div class="row"><span>Service</span><strong>WiFi Payment</strong></div>
    <?php endif; ?>
    <div class="row"><span>Method</span><strong><?= ucwords(str_replace('_',' ',$p['method'])) ?></strong></div>
    <?php if ($p['reference']): ?>
    <div class="row"><span>Reference</span><strong><?= e($p['reference']) ?></strong></div>
    <?php endif; ?>
    <hr>
    <div class="row total"><span>TOTAL PAID</span><strong><?= money($p['amount']) ?></strong></div>
    <hr>
    <?php if ($p['notes']): ?>
    <div style="font-size:11px;"><em><?= e($p['notes']) ?></em></div>
    <?php endif; ?>
    <div class="foot">
        Received by: <?= e($p['received_by']) ?><br>
        <?= e($biz['receipt_footer'] ?? 'Thank you for your business!') ?>
    </div>
</div>

<div style="height:14px"></div>
<?php $active='wifi'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

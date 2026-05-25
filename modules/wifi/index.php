<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$bizId = defaultBusinessId($pdo);
$userId = uid();

// Stats
$stats = ['active'=>0,'expired'=>0,'today_revenue'=>0,'month_revenue'=>0,'expiring'=>0,'unpaid_voucher_value'=>0];
if ($bizId) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.customer_id) FROM wifi_subscriptions s JOIN wifi_customers c ON c.id=s.customer_id WHERE c.business_id=? AND s.status='active' AND s.expiry_date>=CURDATE()");
    $stmt->execute([$bizId]); $stats['active'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.customer_id) FROM wifi_subscriptions s JOIN wifi_customers c ON c.id=s.customer_id WHERE c.business_id=? AND s.expiry_date<CURDATE()");
    $stmt->execute([$bizId]); $stats['expired'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.customer_id) FROM wifi_subscriptions s JOIN wifi_customers c ON c.id=s.customer_id WHERE c.business_id=? AND s.status='active' AND s.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)");
    $stmt->execute([$bizId]); $stats['expiring'] = (int)$stmt->fetchColumn();

    $stats['today_revenue'] = sumQuery($pdo, "SELECT COALESCE(SUM(p.amount),0) FROM wifi_payments p JOIN wifi_customers c ON c.id=p.customer_id WHERE c.business_id=? AND p.paid_on=?", [$bizId, date('Y-m-d')]);
    $stats['month_revenue'] = sumQuery($pdo, "SELECT COALESCE(SUM(p.amount),0) FROM wifi_payments p JOIN wifi_customers c ON c.id=p.customer_id WHERE c.business_id=? AND MONTH(p.paid_on)=MONTH(CURDATE()) AND YEAR(p.paid_on)=YEAR(CURDATE())", [$bizId]);
}

// Recent payments (last 5)
$payments = [];
if ($bizId) {
    $stmt = $pdo->prepare("SELECT p.*, c.full_name, c.phone FROM wifi_payments p JOIN wifi_customers c ON c.id=p.customer_id WHERE c.business_id=? ORDER BY p.created_at DESC LIMIT 5");
    $stmt->execute([$bizId]);
    $payments = $stmt->fetchAll();
}

$pageTitle = 'WiFi Business';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';

$topbarTitle = 'WiFi Business';
$topbarGradient = true;
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="detail-strip">
    <h2 style="color:#fff;margin-bottom:2px;">Internet Business</h2>
    <div class="sub">Manage subscribers, payments &amp; reminders</div>
    <div class="d-flex gap-2 mt-3 flex-wrap">
        <span class="chip" style="background:rgba(255,255,255,.2);"><i class="bi bi-people"></i> <?= $stats['active'] ?> active</span>
        <span class="chip" style="background:rgba(255,255,255,.2);"><i class="bi bi-clock"></i> <?= $stats['expiring'] ?> expiring</span>
        <span class="chip" style="background:rgba(255,255,255,.2);"><i class="bi bi-x-circle"></i> <?= $stats['expired'] ?> expired</span>
    </div>
</div>

<!-- Revenue snapshot -->
<div class="balance-card">
    <div class="d-flex justify-content-between">
        <div>
            <div class="label">Revenue This Month</div>
            <div class="amount"><?= money($stats['month_revenue']) ?></div>
        </div>
        <div class="text-end">
            <div class="label">Today</div>
            <div style="font-weight:700;color:var(--primary);"><?= money($stats['today_revenue']) ?></div>
        </div>
    </div>
</div>

<!-- Sub-navigation tiles -->
<div class="action-grid" style="grid-template-columns: repeat(4,1fr);">
    <a href="customers.php" class="action-tile">
        <div class="tile-ico bg-blue-soft"><i class="bi bi-people"></i></div>
        <div class="tile-label">Customers</div>
    </a>
    <a href="record_payment.php" class="action-tile">
        <div class="tile-ico bg-green-soft"><i class="bi bi-cash-coin"></i></div>
        <div class="tile-label">Record<br>Payment</div>
    </a>
    <a href="packages.php" class="action-tile">
        <div class="tile-ico bg-purple-soft"><i class="bi bi-box-seam"></i></div>
        <div class="tile-label">Packages</div>
    </a>
    <a href="vouchers.php" class="action-tile">
        <div class="tile-ico bg-amber-soft"><i class="bi bi-ticket-perforated"></i></div>
        <div class="tile-label">Vouchers</div>
    </a>
    <a href="reminders.php" class="action-tile">
        <div class="tile-ico bg-orange-soft"><i class="bi bi-bell"></i></div>
        <div class="tile-label">Expiry<br>Alerts</div>
    </a>
    <a href="add_customer.php" class="action-tile">
        <div class="tile-ico bg-primary-soft"><i class="bi bi-person-plus"></i></div>
        <div class="tile-label">New<br>Customer</div>
    </a>
    <a href="payments.php" class="action-tile">
        <div class="tile-ico bg-indigo-soft"><i class="bi bi-list-check"></i></div>
        <div class="tile-label">All<br>Payments</div>
    </a>
    <a href="<?= BASE_URL ?>/modules/reports/index.php?biz=wifi" class="action-tile">
        <div class="tile-ico bg-pink-soft"><i class="bi bi-graph-up"></i></div>
        <div class="tile-label">Reports</div>
    </a>
</div>

<!-- Recent payments -->
<div class="section-title">
    <h3>Recent Payments</h3>
    <a href="payments.php">View all</a>
</div>
<div class="list-card">
    <?php if (empty($payments)): ?>
        <div class="empty-state">
            <div class="icon-circle"><i class="bi bi-cash-stack"></i></div>
            <div>No payments yet.</div>
            <a href="record_payment.php" class="btn btn-primary btn-sm mt-3">Record First Payment</a>
        </div>
    <?php else: foreach ($payments as $p): ?>
        <a href="receipt.php?id=<?= $p['id'] ?>" class="list-item">
            <div class="li-ico"><i class="bi bi-cash-coin"></i></div>
            <div class="li-body">
                <div class="li-title"><?= e($p['full_name']) ?></div>
                <div class="li-sub"><?= e($p['phone']) ?> · <?= friendlyDate($p['paid_on']) ?></div>
            </div>
            <div class="li-right">
                <div class="li-amount in"><?= money($p['amount']) ?></div>
                <div class="li-time"><?= ucfirst(str_replace('_',' ',$p['method'])) ?></div>
            </div>
        </a>
    <?php endforeach; endif; ?>
</div>

<a href="record_payment.php" class="fab" title="Record payment"><i class="bi bi-plus-lg"></i></a>

<div style="height:14px"></div>
<?php $active='wifi'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

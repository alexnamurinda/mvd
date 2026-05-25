<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$bizId = defaultBusinessId($pdo);
$filter = $_GET['filter'] ?? 'all';

$where = "WHERE c.business_id = ?"; $params = [$bizId];
if ($filter === 'active') {
    $where .= " AND EXISTS (SELECT 1 FROM wifi_subscriptions s WHERE s.customer_id=c.id AND s.status='active' AND s.expiry_date>=CURDATE())";
} elseif ($filter === 'expiring') {
    $where .= " AND EXISTS (SELECT 1 FROM wifi_subscriptions s WHERE s.customer_id=c.id AND s.status='active' AND s.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY))";
} elseif ($filter === 'expired') {
    $where .= " AND NOT EXISTS (SELECT 1 FROM wifi_subscriptions s WHERE s.customer_id=c.id AND s.status='active' AND s.expiry_date>=CURDATE())";
}

$sql = "SELECT c.*,
        (SELECT MAX(s.expiry_date) FROM wifi_subscriptions s WHERE s.customer_id=c.id AND s.status='active') AS expiry,
        (SELECT p.name FROM wifi_subscriptions s JOIN wifi_packages p ON p.id=s.package_id WHERE s.customer_id=c.id AND s.status='active' ORDER BY s.expiry_date DESC LIMIT 1) AS package_name
        FROM wifi_customers c $where ORDER BY c.full_name ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$customers = $stmt->fetchAll();

$pageTitle = 'Customers';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';

$topbarTitle = 'Customers';
$topbarBack = BASE_URL.'/modules/wifi/index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<?php if ($f = flash('success')): ?>
<div class="alert alert-success auto-dismiss mt-3"><i class="bi bi-check-circle-fill"></i><?= e($f) ?></div>
<?php endif; ?>

<div class="px-page mt-3">
    <div class="search-bar" style="margin-left:0;margin-right:0;">
        <i class="bi bi-search"></i>
        <input type="text" placeholder="Search by name or phone..." data-search-target="#custList">
    </div>
</div>

<div class="tab-bar">
    <a href="?filter=all"      class="<?= $filter==='all'?'active':'' ?>">All</a>
    <a href="?filter=active"   class="<?= $filter==='active'?'active':'' ?>">Active</a>
    <a href="?filter=expiring" class="<?= $filter==='expiring'?'active':'' ?>">Expiring</a>
    <a href="?filter=expired"  class="<?= $filter==='expired'?'active':'' ?>">Expired</a>
</div>

<div class="list-card" id="custList">
    <?php if (empty($customers)): ?>
        <div class="empty-state">
            <div class="icon-circle"><i class="bi bi-people"></i></div>
            <div>No customers found.</div>
            <a href="add_customer.php" class="btn btn-primary btn-sm mt-3">Add First Customer</a>
        </div>
    <?php else: foreach ($customers as $c):
        $isExpired = !$c['expiry'] || strtotime($c['expiry']) < strtotime(date('Y-m-d'));
        $daysLeft  = $c['expiry'] ? (strtotime($c['expiry']) - strtotime(date('Y-m-d')))/86400 : null;
        $statusPill = $isExpired ? 'pill-expired' : ($daysLeft<=3 ? 'pill-suspended' : 'pill-active');
        $statusText = $isExpired ? 'EXPIRED' : ($daysLeft<=3 ? 'EXPIRING' : 'ACTIVE');
        $initial = strtoupper(substr($c['full_name'],0,1));
    ?>
        <a href="customer_view.php?id=<?= $c['id'] ?>" class="list-item" data-search-row="<?= e($c['full_name'].' '.$c['phone'].' '.$c['location']) ?>">
            <div class="li-ico bg-primary-soft" style="color:#fff;font-weight:700;"><?= e($initial) ?></div>
            <div class="li-body">
                <div class="li-title"><?= e($c['full_name']) ?></div>
                <div class="li-sub"><i class="bi bi-telephone"></i> <?= e($c['phone']) ?>
                <?php if ($c['location']): ?> · <i class="bi bi-geo-alt"></i> <?= e($c['location']) ?><?php endif; ?>
                </div>
            </div>
            <div class="li-right">
                <span class="pill <?= $statusPill ?>"><?= $statusText ?></span>
                <?php if ($c['expiry']): ?>
                <div class="li-time mt-1"><?= friendlyDate($c['expiry']) ?></div>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; endif; ?>
</div>

<a href="add_customer.php" class="fab"><i class="bi bi-plus-lg"></i></a>

<div style="height:14px"></div>
<?php $active='wifi'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$bizId = defaultBusinessId($pdo);

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$method = $_GET['method'] ?? '';

$payments = [];
$total = 0;
if ($bizId) {
    $sql = "SELECT p.*, c.full_name, c.phone, pkg.name pkg_name
        FROM wifi_payments p
        JOIN wifi_customers c ON c.id=p.customer_id
        LEFT JOIN wifi_packages pkg ON pkg.id=p.package_id
        WHERE c.business_id=? AND p.paid_on BETWEEN ? AND ?";
    $params = [$bizId, $from, $to];
    if ($method !== '') { $sql .= " AND p.method=?"; $params[] = $method; }
    $sql .= " ORDER BY p.paid_on DESC, p.id DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $payments = $stmt->fetchAll();
    foreach ($payments as $p) $total += (float)$p['amount'];
}

$methods = ['cash','mtn_momo','airtel_money','bank','card','other'];

$pageTitle = 'All Payments';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Payments'; $topbarBack = 'index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <div class="balance-card">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="label">Total in range</div>
                <div class="amount"><?= money($total) ?></div>
            </div>
            <div class="text-end">
                <div class="label">Payments</div>
                <div style="font-weight:700;color:var(--primary);font-size:1.2rem;"><?= count($payments) ?></div>
            </div>
        </div>
    </div>

    <form method="get" class="row g-2 mb-2">
        <div class="col-5"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
        <div class="col-5"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
        <div class="col-2 d-flex align-items-end"><button class="btn btn-primary btn-block"><i class="bi bi-funnel"></i></button></div>
        <div class="col-12">
            <select name="method" class="form-select" onchange="this.form.submit()">
                <option value="">All methods</option>
                <?php foreach ($methods as $m): ?>
                    <option value="<?= $m ?>" <?= $method===$m?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$m)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <div class="search-bar">
        <i class="bi bi-search"></i>
        <input type="text" placeholder="Search customer or receipt…" data-search-target="#paysList">
    </div>

    <div class="list-card" id="paysList">
        <?php if (empty($payments)): ?>
            <div class="empty-state"><div class="icon-circle"><i class="bi bi-cash-stack"></i></div>No payments in this range.</div>
        <?php else: foreach ($payments as $p): ?>
            <a href="receipt.php?id=<?= $p['id'] ?>" class="list-item" data-search-row>
                <div class="li-ico" style="background:linear-gradient(135deg,#16A34A,#22C55E);color:#fff;"><i class="bi bi-cash-coin"></i></div>
                <div class="li-body">
                    <div class="li-title"><?= e($p['full_name']) ?></div>
                    <div class="li-sub"><?= e($p['receipt_no']) ?> · <?= friendlyDate($p['paid_on']) ?> · <?= e($p['pkg_name'] ?? '—') ?></div>
                </div>
                <div class="li-right">
                    <div class="li-amount in"><?= money($p['amount']) ?></div>
                    <div class="li-time"><?= ucfirst(str_replace('_',' ',$p['method'])) ?></div>
                </div>
            </a>
        <?php endforeach; endif; ?>
    </div>
</div>

<div style="height:30px"></div>
<?php $active='wifi'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

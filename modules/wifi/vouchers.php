<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$bizId = defaultBusinessId($pdo);
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bizId) {
    if ($action === 'generate') {
        $qty = max(1, min(200, (int)($_POST['qty'] ?? 0)));
        $pkg_id = (int)($_POST['package_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM wifi_packages WHERE id=? AND business_id=?");
        $stmt->execute([$pkg_id, $bizId]);
        $pkg = $stmt->fetch();
        if ($pkg) {
            $ins = $pdo->prepare("INSERT INTO wifi_vouchers (business_id, package_id, code, price, status, created_at) VALUES (?,?,?,?,'unused',NOW())");
            $made = 0;
            for ($i=0; $i<$qty; $i++) {
                $tries = 0;
                while ($tries < 5) {
                    try {
                        $ins->execute([$bizId, $pkg_id, generateVoucherCode(8), $pkg['price']]);
                        $made++; break;
                    } catch (PDOException $ex) { $tries++; }
                }
            }
            logActivity($pdo, uid(), 'Generated vouchers', $made.' x '.$pkg['name'], 'bi-ticket-perforated');
            flash('success', "$made voucher(s) generated");
        }
    } elseif ($action === 'sell') {
        $vid = (int)($_POST['id'] ?? 0);
        $buyer = trim($_POST['buyer_phone'] ?? '');
        $stmt = $pdo->prepare("UPDATE wifi_vouchers SET status='sold', sold_at=NOW(), buyer_phone=? WHERE id=? AND business_id=? AND status='unused'");
        $stmt->execute([$buyer, $vid, $bizId]);
        flash('success', 'Voucher marked as sold');
    } elseif ($action === 'use') {
        $vid = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE wifi_vouchers SET status='used', used_at=NOW() WHERE id=? AND business_id=?");
        $stmt->execute([$vid, $bizId]);
        flash('success', 'Voucher marked as used');
    } elseif ($action === 'delete') {
        $vid = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM wifi_vouchers WHERE id=? AND business_id=? AND status IN ('unused','expired')");
        $stmt->execute([$vid, $bizId]);
        flash('success', 'Voucher removed');
    }
    header('Location: vouchers.php' . (isset($_GET['filter']) ? '?filter='.urlencode($_GET['filter']) : '')); exit;
}

$filter = $_GET['filter'] ?? 'unused';
$validFilters = ['unused','sold','used','expired','all'];
if (!in_array($filter,$validFilters,true)) $filter = 'unused';

$packages = [];
$vouchers = [];
$counts = ['unused'=>0,'sold'=>0,'used'=>0,'expired'=>0];
if ($bizId) {
    $stmt = $pdo->prepare("SELECT * FROM wifi_packages WHERE business_id=? AND is_active=1 ORDER BY price ASC");
    $stmt->execute([$bizId]);
    $packages = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT status, COUNT(*) c FROM wifi_vouchers WHERE business_id=? GROUP BY status");
    $stmt->execute([$bizId]);
    foreach ($stmt as $r) $counts[$r['status']] = (int)$r['c'];

    $sql = "SELECT v.*, p.name pkg_name FROM wifi_vouchers v LEFT JOIN wifi_packages p ON p.id=v.package_id WHERE v.business_id=?";
    $params = [$bizId];
    if ($filter !== 'all') { $sql .= " AND v.status=?"; $params[] = $filter; }
    $sql .= " ORDER BY v.created_at DESC LIMIT 200";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $vouchers = $stmt->fetchAll();
}

$pageTitle = 'Vouchers';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'WiFi Vouchers'; $topbarBack = 'index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endif; ?>

    <!-- Quick counts -->
    <div class="row g-2 mb-3">
        <div class="col-3"><div class="list-card text-center" style="padding:10px;"><div style="font-size:1.2rem;font-weight:800;color:var(--primary)"><?= $counts['unused'] ?></div><div class="text-soft" style="font-size:.7rem;">Available</div></div></div>
        <div class="col-3"><div class="list-card text-center" style="padding:10px;"><div style="font-size:1.2rem;font-weight:800;color:var(--info)"><?= $counts['sold'] ?></div><div class="text-soft" style="font-size:.7rem;">Sold</div></div></div>
        <div class="col-3"><div class="list-card text-center" style="padding:10px;"><div style="font-size:1.2rem;font-weight:800;color:var(--success)"><?= $counts['used'] ?></div><div class="text-soft" style="font-size:.7rem;">Used</div></div></div>
        <div class="col-3"><div class="list-card text-center" style="padding:10px;"><div style="font-size:1.2rem;font-weight:800;color:var(--text-muted)"><?= $counts['expired'] ?></div><div class="text-soft" style="font-size:.7rem;">Expired</div></div></div>
    </div>

    <!-- Generator -->
    <div class="section-title"><h3>Generate Vouchers</h3></div>
    <div class="list-card" style="padding:14px;">
        <?php if (empty($packages)): ?>
            <div class="text-muted">Create a package first. <a href="packages.php">Manage packages →</a></div>
        <?php else: ?>
        <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="generate">
            <div class="col-7">
                <label class="form-label">Package</label>
                <select class="form-select" name="package_id" required>
                    <?php foreach ($packages as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> — <?= money($p['price']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-3">
                <label class="form-label">Qty</label>
                <input class="form-control" type="number" name="qty" min="1" max="200" value="10" required>
            </div>
            <div class="col-2"><button class="btn btn-primary btn-block"><i class="bi bi-plus-lg"></i></button></div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Filter tabs -->
    <div class="tab-bar mt-3">
        <?php foreach (['unused'=>'Available','sold'=>'Sold','used'=>'Used','expired'=>'Expired','all'=>'All'] as $k=>$lbl): ?>
            <a href="?filter=<?= $k ?>" class="<?= $filter===$k?'active':'' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
    </div>

    <div class="search-bar">
        <i class="bi bi-search"></i>
        <input type="text" placeholder="Search code or phone…" data-search-target="#vouchersList">
    </div>

    <div class="list-card" id="vouchersList">
        <?php if (empty($vouchers)): ?>
            <div class="empty-state"><div class="icon-circle"><i class="bi bi-ticket-perforated"></i></div>No vouchers in this view.</div>
        <?php else: foreach ($vouchers as $v): ?>
            <div class="list-item" data-search-row>
                <div class="li-ico" style="background:linear-gradient(135deg,#F59E0B,#FBBF24);color:#fff;"><i class="bi bi-ticket-perforated"></i></div>
                <div class="li-body">
                    <div class="li-title" style="font-family:'Courier New',monospace;letter-spacing:1px;"><?= e($v['code']) ?></div>
                    <div class="li-sub"><?= e($v['pkg_name'] ?? 'unknown pkg') ?> · <?= e($v['buyer_phone'] ?? friendlyDate($v['created_at'])) ?></div>
                </div>
                <div class="li-right">
                    <div class="li-amount"><?= money($v['price']) ?></div>
                    <span class="pill pill-<?= $v['status']==='unused'?'pending':($v['status']==='sold'?'active':($v['status']==='used'?'done':'expired')) ?>"><?= e($v['status']) ?></span>
                </div>
            </div>
            <?php if ($v['status']==='unused' || $v['status']==='sold'): ?>
            <div style="padding:0 14px 10px 60px;display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($v['status']==='unused'): ?>
                    <form method="post" class="d-flex gap-1">
                        <input type="hidden" name="action" value="sell">
                        <input type="hidden" name="id" value="<?= $v['id'] ?>">
                        <input class="form-control form-control-sm" name="buyer_phone" placeholder="Buyer phone (optional)" style="max-width:140px;font-size:.8rem;">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-cart"></i> Sell</button>
                    </form>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="action" value="use">
                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
                    <button class="btn btn-light btn-sm">Mark used</button>
                </form>
                <form method="post" data-confirm="Delete this voucher?">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
                    <button class="btn btn-light btn-sm" style="color:var(--danger)"><i class="bi bi-trash"></i></button>
                </form>
            </div>
            <?php endif; ?>
        <?php endforeach; endif; ?>
    </div>
</div>

<div style="height:30px"></div>
<?php $active='wifi'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

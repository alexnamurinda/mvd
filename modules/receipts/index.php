<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();
$bizId  = defaultBusinessId($pdo);

$q = trim($_GET['q'] ?? '');
$tab = $_GET['tab'] ?? 'all';

$wifi = [];
if (in_array($tab, ['all','wifi'], true) && $bizId) {
    $sql = "SELECT p.id, p.receipt_no, p.amount, p.paid_on date_col, p.method, c.full_name name, c.phone phone, 'wifi' src
            FROM wifi_payments p JOIN wifi_customers c ON c.id=p.customer_id
            WHERE c.business_id=?";
    $params = [$bizId];
    if ($q !== '') { $sql .= " AND (p.receipt_no LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ?)"; $like = "%$q%"; array_push($params, $like, $like, $like); }
    $sql .= " ORDER BY p.paid_on DESC, p.id DESC LIMIT 200";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $wifi = $stmt->fetchAll();
}

$exps = [];
if (in_array($tab, ['all','expense'], true)) {
    $sql = "SELECT e.id, e.receipt_no, e.amount, e.expense_date date_col, e.method, e.title name, e.vendor phone, 'expense' src
            FROM expenses e WHERE e.user_id=? AND e.receipt_no IS NOT NULL";
    $params = [$userId];
    if ($q !== '') { $sql .= " AND (e.receipt_no LIKE ? OR e.title LIKE ? OR e.vendor LIKE ?)"; $like = "%$q%"; array_push($params, $like, $like, $like); }
    $sql .= " ORDER BY e.expense_date DESC, e.id DESC LIMIT 200";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $exps = $stmt->fetchAll();
}

$all = array_merge($wifi, $exps);
usort($all, fn($a,$b)=>strcmp($b['date_col'],$a['date_col']));

$pageTitle = 'Receipts';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Receipts'; $topbarBack = BASE_URL.'/dashboard.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <div class="tab-bar">
        <a href="?tab=all"     class="<?= $tab==='all'?'active':'' ?>">All</a>
        <a href="?tab=wifi"    class="<?= $tab==='wifi'?'active':'' ?>">WiFi (<?= count($wifi) ?>)</a>
        <a href="?tab=expense" class="<?= $tab==='expense'?'active':'' ?>">Expenses (<?= count($exps) ?>)</a>
    </div>

    <form method="get" class="search-bar">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <i class="bi bi-search"></i>
        <input type="text" name="q" placeholder="Search receipt no, customer, vendor…" value="<?= e($q) ?>">
    </form>

    <div class="list-card">
        <?php if (empty($all)): ?>
            <div class="empty-state"><div class="icon-circle"><i class="bi bi-receipt"></i></div>No receipts found.</div>
        <?php else: foreach ($all as $r):
            $url = $r['src']==='wifi' ? (BASE_URL.'/modules/wifi/receipt.php?id='.$r['id']) : (BASE_URL.'/modules/expenses/receipt.php?id='.$r['id']);
            $col = $r['src']==='wifi' ? '#16A34A' : '#DC2626';
        ?>
            <a href="<?= e($url) ?>" class="list-item">
                <div class="li-ico" style="background:linear-gradient(135deg,<?= $col ?>,<?= $col ?>);color:#fff;"><i class="bi <?= $r['src']==='wifi'?'bi-wifi':'bi-receipt' ?>"></i></div>
                <div class="li-body">
                    <div class="li-title"><?= e($r['name']) ?></div>
                    <div class="li-sub"><?= e($r['receipt_no']) ?> · <?= friendlyDate($r['date_col']) ?></div>
                </div>
                <div class="li-right">
                    <div class="li-amount <?= $r['src']==='wifi'?'in':'out' ?>"><?= $r['src']==='expense'?'−':'' ?><?= money($r['amount']) ?></div>
                    <div class="li-time"><?= ucfirst(str_replace('_',' ',$r['method'])) ?></div>
                </div>
            </a>
        <?php endforeach; endif; ?>
    </div>
</div>

<div style="height:30px"></div>
<?php $active='home'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

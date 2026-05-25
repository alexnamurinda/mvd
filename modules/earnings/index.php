<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$src  = (int)($_GET['source_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM income_sources WHERE user_id=? ORDER BY name");
$stmt->execute([$userId]);
$sources = $stmt->fetchAll();

$sql = "SELECT i.*, s.name src_name, s.color src_color FROM incomes i
        LEFT JOIN income_sources s ON s.id=i.source_id
        WHERE i.user_id=? AND i.income_date BETWEEN ? AND ?";
$params = [$userId, $from, $to];
if ($src) { $sql .= " AND i.source_id=?"; $params[] = $src; }
$sql .= " ORDER BY i.income_date DESC, i.id DESC LIMIT 500";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$incomes = $stmt->fetchAll();

$rangeTotal = 0; foreach ($incomes as $r) $rangeTotal += (float)$r['amount'];
$monthTotal = sumQuery($pdo, "SELECT COALESCE(SUM(amount),0) FROM incomes WHERE user_id=? AND MONTH(income_date)=MONTH(CURDATE()) AND YEAR(income_date)=YEAR(CURDATE())", [$userId]);
$wifiMonth  = sumQuery($pdo, "SELECT COALESCE(SUM(p.amount),0) FROM wifi_payments p JOIN wifi_customers c ON c.id=p.customer_id JOIN businesses b ON b.id=c.business_id WHERE b.user_id=? AND MONTH(p.paid_on)=MONTH(CURDATE()) AND YEAR(p.paid_on)=YEAR(CURDATE())", [$userId]);
$monthExpense = sumQuery($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())", [$userId]);
$net = ($monthTotal + $wifiMonth) - $monthExpense;

// Breakdown
$breakdown = [];
foreach ($incomes as $i) {
    $k = $i['src_name'] ?: 'Other';
    $breakdown[$k] = ($breakdown[$k] ?? 0) + (float)$i['amount'];
}
arsort($breakdown);

$pageTitle = 'Earnings';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Earnings'; $topbarBack = BASE_URL.'/dashboard.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <div class="balance-card">
        <div class="label">Net Balance (this month)</div>
        <div class="amount" style="color:<?= $net>=0?'var(--success)':'var(--danger)' ?>;"><?= money($net) ?></div>
        <div class="mini-stats">
            <div class="stat"><span class="lbl">Income</span><span class="val" style="color:var(--success);">+<?= money($monthTotal + $wifiMonth) ?></span></div>
            <div class="stat"><span class="lbl">Expenses</span><span class="val" style="color:var(--danger);">−<?= money($monthExpense) ?></span></div>
        </div>
    </div>

    <div class="row g-2 mb-2">
        <div class="col-6"><div class="list-card" style="padding:12px;"><div class="text-soft" style="font-size:.75rem;">Other Income (mo)</div><div style="font-weight:800;color:var(--primary);"><?= money($monthTotal) ?></div></div></div>
        <div class="col-6"><div class="list-card" style="padding:12px;"><div class="text-soft" style="font-size:.75rem;">WiFi Revenue (mo)</div><div style="font-weight:800;color:var(--primary);"><?= money($wifiMonth) ?></div></div></div>
    </div>

    <form method="get" class="row g-2 mb-2">
        <div class="col-5"><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
        <div class="col-5"><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
        <div class="col-2"><button class="btn btn-primary btn-block"><i class="bi bi-funnel"></i></button></div>
        <div class="col-12">
            <select name="source_id" class="form-select" onchange="this.form.submit()">
                <option value="0">All sources</option>
                <?php foreach ($sources as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $src===(int)$s['id']?'selected':'' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($breakdown): ?>
    <div class="section-title"><h3>By Source</h3></div>
    <div class="list-card" style="padding:14px;">
        <?php $maxV = max($breakdown); foreach (array_slice($breakdown,0,5,true) as $sn=>$amt): $pct = $maxV?($amt/$maxV*100):0; ?>
            <div style="margin-bottom:10px;">
                <div class="d-flex justify-content-between" style="font-size:.85rem;"><span><?= e($sn) ?></span><strong><?= money($amt) ?></strong></div>
                <div style="height:6px;background:var(--border-light);border-radius:99px;overflow:hidden;margin-top:4px;"><div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--success),#22C55E);"></div></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="section-title"><h3>Records — Range total <span style="color:var(--success);">+<?= money($rangeTotal) ?></span></h3><a href="add.php">+ Add</a></div>
    <div class="list-card">
        <?php if (empty($incomes)): ?>
            <div class="empty-state"><div class="icon-circle"><i class="bi bi-cash-coin"></i></div>No income recorded.<a href="add.php" class="btn btn-primary btn-sm mt-3">Log First Income</a></div>
        <?php else: foreach ($incomes as $r): ?>
            <div class="list-item">
                <div class="li-ico" style="background:<?= e($r['src_color'] ?: '#16A34A') ?>;color:#fff;"><i class="bi bi-cash-coin"></i></div>
                <div class="li-body">
                    <div class="li-title"><?= e($r['title']) ?></div>
                    <div class="li-sub"><?= e($r['src_name'] ?: 'Other') ?> · <?= friendlyDate($r['income_date']) ?></div>
                </div>
                <div class="li-right">
                    <div class="li-amount in">+<?= money($r['amount']) ?></div>
                    <div class="li-time"><?= ucfirst(str_replace('_',' ',$r['method'])) ?></div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<a href="add.php" class="fab" title="Log earning"><i class="bi bi-plus-lg"></i></a>
<div style="height:30px"></div>
<?php $active='home'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

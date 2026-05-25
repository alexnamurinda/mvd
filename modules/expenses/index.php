<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$cat  = (int)($_GET['category_id'] ?? 0);

// Categories
$stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE user_id=? ORDER BY name");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

$sql = "SELECT e.*, c.name cat_name, c.icon cat_icon, c.color cat_color FROM expenses e
        LEFT JOIN expense_categories c ON c.id=e.category_id
        WHERE e.user_id=? AND e.expense_date BETWEEN ? AND ?";
$params = [$userId, $from, $to];
if ($cat) { $sql .= " AND e.category_id=?"; $params[] = $cat; }
$sql .= " ORDER BY e.expense_date DESC, e.id DESC LIMIT 500";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$expenses = $stmt->fetchAll();

$totalRange = 0; foreach ($expenses as $r) $totalRange += (float)$r['amount'];
$totalToday = sumQuery($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND expense_date=?", [$userId, date('Y-m-d')]);
$totalMonth = sumQuery($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())", [$userId]);

// Top category breakdown for range
$breakdown = [];
if (!empty($expenses)) {
    foreach ($expenses as $e) {
        $k = $e['cat_name'] ?: 'Uncategorized';
        $breakdown[$k] = ($breakdown[$k] ?? 0) + (float)$e['amount'];
    }
    arsort($breakdown);
}

$pageTitle = 'Expenses';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Expenses'; $topbarBack = BASE_URL.'/dashboard.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <div class="balance-card">
        <div class="label">Spent <?= date('M Y') ?></div>
        <div class="amount" style="color:var(--danger);"><?= money($totalMonth) ?></div>
        <div class="mini-stats">
            <div class="stat"><span class="lbl">Today</span><span class="val"><?= money($totalToday) ?></span></div>
            <div class="stat"><span class="lbl">In range</span><span class="val"><?= money($totalRange) ?></span></div>
        </div>
    </div>

    <form method="get" class="row g-2 mb-2">
        <div class="col-5"><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
        <div class="col-5"><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
        <div class="col-2"><button class="btn btn-primary btn-block"><i class="bi bi-funnel"></i></button></div>
        <div class="col-12">
            <select name="category_id" class="form-select" onchange="this.form.submit()">
                <option value="0">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $cat===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($breakdown): ?>
    <div class="section-title"><h3>By Category</h3></div>
    <div class="list-card" style="padding:14px;">
        <?php $maxV = max($breakdown); foreach (array_slice($breakdown,0,5,true) as $cn=>$amt): $pct = $maxV?($amt/$maxV*100):0; ?>
            <div style="margin-bottom:10px;">
                <div class="d-flex justify-content-between" style="font-size:.85rem;">
                    <span><?= e($cn) ?></span>
                    <strong><?= money($amt) ?></strong>
                </div>
                <div style="height:6px;background:var(--border-light);border-radius:99px;overflow:hidden;margin-top:4px;">
                    <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--primary),var(--primary-light));"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="section-title"><h3>Records (<?= count($expenses) ?>)</h3><a href="add.php">+ Add</a></div>
    <div class="search-bar"><i class="bi bi-search"></i><input type="text" placeholder="Search title or notes…" data-search-target="#expList"></div>
    <div class="list-card" id="expList">
        <?php if (empty($expenses)): ?>
            <div class="empty-state"><div class="icon-circle"><i class="bi bi-receipt"></i></div>No expenses yet.<a href="add.php" class="btn btn-primary btn-sm mt-3">Log First Expense</a></div>
        <?php else: foreach ($expenses as $r): ?>
            <a href="receipt.php?id=<?= $r['id'] ?>" class="list-item" data-search-row>
                <div class="li-ico" style="background:<?= e($r['cat_color'] ?: '#64748B') ?>;color:#fff;"><i class="bi <?= e($r['cat_icon'] ?: 'bi-receipt') ?>"></i></div>
                <div class="li-body">
                    <div class="li-title"><?= e($r['title']) ?></div>
                    <div class="li-sub"><?= e($r['cat_name'] ?: 'Uncategorized') ?> · <?= friendlyDate($r['expense_date']) ?></div>
                </div>
                <div class="li-right">
                    <div class="li-amount out">−<?= money($r['amount']) ?></div>
                    <div class="li-time"><?= ucfirst(str_replace('_',' ',$r['method'])) ?></div>
                </div>
            </a>
        <?php endforeach; endif; ?>
    </div>
    <a href="categories.php" class="btn btn-light btn-block mt-3"><i class="bi bi-tags"></i> Manage Categories</a>
</div>

<a href="add.php" class="fab" title="Add expense"><i class="bi bi-plus-lg"></i></a>
<div style="height:30px"></div>
<?php $active='add'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

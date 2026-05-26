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

// Recurring due-soon count (for in-page banner; topbar handles the badge separately)
$recurringSoonCount = getRecurringDueSoon($pdo, $userId, 7);

$pageTitle = 'Expenses';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Expenses'; $topbarBack = BASE_URL.'/dashboard.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <?php if ($recurringSoonCount > 0): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" style="border-radius:14px;">
        <i class="bi bi-arrow-repeat" style="font-size:1.1rem;flex-shrink:0;"></i>
        <span><strong><?= $recurringSoonCount ?> recurring expense<?= $recurringSoonCount > 1 ? 's' : '' ?></strong> due within the next 7 days.</span>
    </div>
    <?php endif; ?>

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
        <?php else: foreach ($expenses as $r):
            // Build recurring countdown chip
            $recurChip = '';
            if ($r['is_recurring'] && $r['recurring_period']) {
                $next = nextRecurringDate($r['expense_date'], $r['recurring_period']);
                if ($next) {
                    $today    = new DateTime(date('Y-m-d'));
                    $diff     = (int)$today->diff($next)->days;
                    $inverted = (int)$today->diff($next)->invert;
                    $daysUntil = $inverted ? -$diff : $diff;

                    if ($daysUntil <= 0) {
                        $label = $daysUntil === 0 ? '↻ Due today!' : '↻ ' . abs($daysUntil) . 'd overdue';
                        $cls   = 'urgent';
                    } elseif ($daysUntil === 1) {
                        $label = '↻ Due tomorrow';
                        $cls   = 'urgent';
                    } elseif ($daysUntil <= 7) {
                        $label = '↻ Due in ' . $daysUntil . ' days';
                        $cls   = 'soon';
                    } else {
                        $label = '↻ Due ' . $next->format('M j');
                        $cls   = '';
                    }
                    $recurChip = '<span class="recurring-chip ' . $cls . '">' . e($label) . '</span>';
                }
            }
        ?>
            <a href="receipt.php?id=<?= $r['id'] ?>" class="list-item" data-search-row>
                <div class="li-ico" style="background:<?= e($r['cat_color'] ?: '#64748B') ?>;color:#fff;"><i class="bi <?= e($r['cat_icon'] ?: 'bi-receipt') ?>"></i></div>
                <div class="li-body">
                    <div class="li-title"><?= e($r['title']) ?></div>
                    <div class="li-sub"><?= e($r['cat_name'] ?: 'Uncategorized') ?> · <?= friendlyDate($r['expense_date']) ?></div>
                    <?= $recurChip ?>
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

<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();
$bizId  = defaultBusinessId($pdo);

// 6-month trend
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-$i months"));
}
$labels = array_map(fn($m)=>date('M', strtotime($m.'-01')), $months);

$incomeSeries = $expenseSeries = $wifiSeries = [];
foreach ($months as $m) {
    [$y, $mo] = explode('-', $m);
    $incomeSeries[]  = (float)sumQuery($pdo, "SELECT COALESCE(SUM(amount),0) FROM incomes WHERE user_id=? AND YEAR(income_date)=? AND MONTH(income_date)=?", [$userId, $y, $mo]);
    $expenseSeries[] = (float)sumQuery($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND YEAR(expense_date)=? AND MONTH(expense_date)=?", [$userId, $y, $mo]);
    $wifiSeries[]    = $bizId ? (float)sumQuery($pdo, "SELECT COALESCE(SUM(p.amount),0) FROM wifi_payments p JOIN wifi_customers c ON c.id=p.customer_id WHERE c.business_id=? AND YEAR(p.paid_on)=? AND MONTH(p.paid_on)=?", [$bizId, $y, $mo]) : 0;
}

// Profit numbers for current month
$totalIncome  = end($incomeSeries) + end($wifiSeries);
$totalExpense = end($expenseSeries);
$profit = $totalIncome - $totalExpense;
$margin = $totalIncome > 0 ? ($profit / $totalIncome) * 100 : 0;

// Top expense categories (this month)
$stmt = $pdo->prepare("SELECT COALESCE(c.name,'Uncategorized') name, c.color, SUM(e.amount) total
    FROM expenses e LEFT JOIN expense_categories c ON c.id=e.category_id
    WHERE e.user_id=? AND MONTH(e.expense_date)=MONTH(CURDATE()) AND YEAR(e.expense_date)=YEAR(CURDATE())
    GROUP BY c.id, c.name, c.color ORDER BY total DESC LIMIT 6");
$stmt->execute([$userId]);
$topCats = $stmt->fetchAll();

// Top WiFi customers by lifetime payment
$topCust = [];
if ($bizId) {
    $stmt = $pdo->prepare("SELECT c.full_name, c.phone, SUM(p.amount) total, COUNT(p.id) pays
        FROM wifi_payments p JOIN wifi_customers c ON c.id=p.customer_id
        WHERE c.business_id=? GROUP BY c.id ORDER BY total DESC LIMIT 6");
    $stmt->execute([$bizId]);
    $topCust = $stmt->fetchAll();
}

// Payment methods breakdown
$stmt = $pdo->prepare("SELECT method, SUM(amount) total FROM (
    SELECT method, amount FROM incomes WHERE user_id=? AND MONTH(income_date)=MONTH(CURDATE()) AND YEAR(income_date)=YEAR(CURDATE())
    UNION ALL
    SELECT p.method, p.amount FROM wifi_payments p JOIN wifi_customers c ON c.id=p.customer_id WHERE c.business_id=? AND MONTH(p.paid_on)=MONTH(CURDATE()) AND YEAR(p.paid_on)=YEAR(CURDATE())
) t GROUP BY method ORDER BY total DESC");
$stmt->execute([$userId, $bizId ?: 0]);
$byMethod = $stmt->fetchAll();

$pageTitle = 'Reports';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Reports & Insights'; $topbarGradient = true;
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <div class="balance-card">
        <div class="label">Profit / Loss · <?= date('M Y') ?></div>
        <div class="amount" style="color:<?= $profit>=0?'var(--success)':'var(--danger)' ?>;"><?= money($profit) ?></div>
        <div class="mini-stats">
            <div class="stat"><span class="lbl">Income</span><span class="val" style="color:var(--success)">+<?= money($totalIncome) ?></span></div>
            <div class="stat"><span class="lbl">Expense</span><span class="val" style="color:var(--danger)">−<?= money($totalExpense) ?></span></div>
        </div>
        <div class="text-soft mt-2" style="font-size:.78rem;">Margin: <strong style="color:<?= $profit>=0?'var(--success)':'var(--danger)' ?>;"><?= number_format($margin,1) ?>%</strong></div>
    </div>

    <div class="section-title"><h3>6-Month Trend</h3></div>
    <div class="chart-card"><canvas id="trendChart" height="160"></canvas></div>

    <div class="section-title mt-3"><h3>Top Expense Categories</h3></div>
    <div class="list-card" style="padding:14px;">
        <?php if (empty($topCats)): ?>
            <div class="text-muted">No expenses this month.</div>
        <?php else: $maxV = max(array_column($topCats,'total')); foreach ($topCats as $c): $pct = $maxV?($c['total']/$maxV*100):0; ?>
            <div style="margin-bottom:10px;">
                <div class="d-flex justify-content-between" style="font-size:.85rem;">
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= e($c['color'] ?: '#64748B') ?>;"></span> <?= e($c['name']) ?></span>
                    <strong><?= money($c['total']) ?></strong>
                </div>
                <div style="height:6px;background:var(--border-light);border-radius:99px;overflow:hidden;margin-top:4px;"><div style="height:100%;width:<?= $pct ?>%;background:<?= e($c['color'] ?: '#64748B') ?>;"></div></div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <?php if ($topCust): ?>
    <div class="section-title mt-3"><h3>Top WiFi Customers</h3></div>
    <div class="list-card">
        <?php foreach ($topCust as $cu): ?>
            <div class="list-item">
                <div class="li-ico" style="background:linear-gradient(135deg,#0F766E,#14B8A6);color:#fff;"><?= strtoupper(substr($cu['full_name'],0,1)) ?></div>
                <div class="li-body">
                    <div class="li-title"><?= e($cu['full_name']) ?></div>
                    <div class="li-sub"><?= e($cu['phone']) ?> · <?= (int)$cu['pays'] ?> payment<?= (int)$cu['pays']===1?'':'s' ?></div>
                </div>
                <div class="li-right"><div class="li-amount in"><?= money($cu['total']) ?></div></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($byMethod): ?>
    <div class="section-title mt-3"><h3>Income by Method (mo)</h3></div>
    <div class="chart-card"><canvas id="methodChart" height="180"></canvas></div>
    <?php endif; ?>

</div>

<script>
const ctx = document.getElementById('trendChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [
            { label: 'WiFi', data: <?= json_encode($wifiSeries) ?>, backgroundColor: '#14B8A6', stack: 'in' },
            { label: 'Other Income', data: <?= json_encode($incomeSeries) ?>, backgroundColor: '#16A34A', stack: 'in' },
            { label: 'Expenses', data: <?= json_encode(array_map(fn($v)=>-$v,$expenseSeries)) ?>, backgroundColor: '#DC2626', stack: 'out' }
        ]
    },
    options: { responsive:true, plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:10}}}}, scales:{x:{stacked:true,grid:{display:false}}, y:{stacked:true,beginAtZero:true,ticks:{font:{size:9}}}} }
});

<?php if ($byMethod): ?>
new Chart(document.getElementById('methodChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($r)=>ucfirst(str_replace('_',' ',$r['method'])), $byMethod)) ?>,
        datasets: [{ data: <?= json_encode(array_column($byMethod,'total')) ?>, backgroundColor: ['#0F766E','#F59E0B','#2563EB','#7C3AED','#DB2777','#64748B'] }]
    },
    options: { responsive:true, plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:10}}}} }
});
<?php endif; ?>
</script>

<div style="height:30px"></div>
<?php $active='reports'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$userId = uid();
$userName = $_SESSION['full_name'];
$firstName = explode(' ', $userName)[0];
$bizName  = $_SESSION['business_name'];
$initial  = strtoupper(substr($userName, 0, 1));

// Greeting time
$h = (int)date('G');
$greet = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');

// === Stats ===
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

// This month income & expenses
$incomeMonth = sumQuery($pdo,
    "SELECT COALESCE(SUM(amount),0) FROM incomes WHERE user_id = ? AND income_date BETWEEN ? AND ?",
    [$userId, $monthStart, $monthEnd]);
$expenseMonth = sumQuery($pdo,
    "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?",
    [$userId, $monthStart, $monthEnd]);

// Add WiFi payments to income
$wifiMonth = sumQuery($pdo,
    "SELECT COALESCE(SUM(p.amount),0) FROM wifi_payments p
     JOIN wifi_customers c ON c.id = p.customer_id
     JOIN businesses b ON b.id = c.business_id
     WHERE b.user_id = ? AND p.paid_on BETWEEN ? AND ?",
    [$userId, $monthStart, $monthEnd]);
$totalIncomeMonth = $incomeMonth + $wifiMonth;
$netMonth = $totalIncomeMonth - $expenseMonth;

// Today
$incomeToday = sumQuery($pdo, "SELECT COALESCE(SUM(amount),0) FROM incomes WHERE user_id=? AND income_date=?", [$userId, $today]) +
               sumQuery($pdo, "SELECT COALESCE(SUM(p.amount),0) FROM wifi_payments p JOIN wifi_customers c ON c.id=p.customer_id JOIN businesses b ON b.id=c.business_id WHERE b.user_id=? AND p.paid_on=?", [$userId, $today]);
$expenseToday = sumQuery($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND expense_date=?", [$userId, $today]);

// WiFi key numbers
$bizId = defaultBusinessId($pdo);
$activeCustomers = 0; $expiringSoon = 0; $totalCustomers = 0;
if ($bizId) {
    $totalCustomers = (int)$pdo->prepare("SELECT COUNT(*) FROM wifi_customers WHERE business_id=?")->execute([$bizId]) ? null : 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wifi_customers WHERE business_id=?");
    $stmt->execute([$bizId]); $totalCustomers = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.customer_id) FROM wifi_subscriptions s JOIN wifi_customers c ON c.id=s.customer_id WHERE c.business_id=? AND s.status='active' AND s.expiry_date>=CURDATE()");
    $stmt->execute([$bizId]); $activeCustomers = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.customer_id) FROM wifi_subscriptions s JOIN wifi_customers c ON c.id=s.customer_id WHERE c.business_id=? AND s.status='active' AND s.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)");
    $stmt->execute([$bizId]); $expiringSoon = (int)$stmt->fetchColumn();
}

// Pending tasks today
$pendingTasks = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id=? AND status='pending' AND (due_date IS NULL OR due_date<=CURDATE())");
$stmt->execute([$userId]); $pendingTasks = (int)$stmt->fetchColumn();

// Recent activity (combine WiFi payments + expenses + incomes), last 6
$recentSql = "
(SELECT 'income' AS kind, i.title AS title, i.amount AS amount, i.income_date AS dt, i.created_at AS ts, src.name AS sub
 FROM incomes i LEFT JOIN income_sources src ON src.id = i.source_id
 WHERE i.user_id = :u
 ORDER BY i.created_at DESC LIMIT 6)
UNION ALL
(SELECT 'expense' AS kind, e.title, -e.amount, e.expense_date, e.created_at, ec.name
 FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.category_id
 WHERE e.user_id = :u2
 ORDER BY e.created_at DESC LIMIT 6)
UNION ALL
(SELECT 'wifi' AS kind, CONCAT('Payment from ', c.full_name), p.amount, p.paid_on, p.created_at, 'WiFi'
 FROM wifi_payments p JOIN wifi_customers c ON c.id = p.customer_id JOIN businesses b ON b.id=c.business_id
 WHERE b.user_id = :u3
 ORDER BY p.created_at DESC LIMIT 6)
ORDER BY ts DESC LIMIT 8";
$stmt = $pdo->prepare($recentSql);
$stmt->execute(['u'=>$userId,'u2'=>$userId,'u3'=>$userId]);
$recent = $stmt->fetchAll();

// Quick 7-day cashflow for tiny chart
$cashflow = [];
for ($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $ins  = sumQuery($pdo, "SELECT COALESCE(SUM(amount),0) FROM incomes WHERE user_id=? AND income_date=?", [$userId, $d])
          + sumQuery($pdo, "SELECT COALESCE(SUM(p.amount),0) FROM wifi_payments p JOIN wifi_customers c ON c.id=p.customer_id JOIN businesses b ON b.id=c.business_id WHERE b.user_id=? AND p.paid_on=?", [$userId, $d]);
    $outs = sumQuery($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND expense_date=?", [$userId, $d]);
    $cashflow[] = ['d'=>date('D', strtotime($d)), 'in'=>$ins, 'out'=>$outs];
}

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<?php include __DIR__ . '/includes/offcanvas.php'; ?>

<!-- Top bar with menu icon -->
<header class="app-header gradient" style="background:transparent;border:none;position:absolute;width:100%;max-width:480px;z-index:5;">
    <button class="icon-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#mainMenu">
        <i class="bi bi-list"></i>
    </button>
    <div class="d-flex align-items-center gap-1">
        <a href="<?= BASE_URL ?>/modules/notes/index.php" class="icon-btn"><i class="bi bi-journal-text"></i></a>
        <a href="<?= BASE_URL ?>/modules/planner/index.php" class="icon-btn">
            <i class="bi bi-bell"></i>
            <?php if ($pendingTasks > 0): ?><span class="badge-dot"></span><?php endif; ?>
        </a>
    </div>
</header>

<!-- Hero greeting -->
<div class="hero-greeting" style="padding-top: 70px;">
    <div class="greeting-row">
        <div>
            <span class="chip"><i class="bi bi-sun"></i> <?= e($greet) ?></span>
            <div class="user-name mt-2"><?= e($firstName) ?> 👋</div>
            <div class="business-name"><?= e($bizName) ?></div>
        </div>
        <div class="avatar"><?= e($initial) ?></div>
    </div>
</div>

<!-- Balance card -->
<div class="balance-card">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <div class="label">Net this month</div>
            <div class="amount" style="color: <?= $netMonth>=0 ? 'var(--success)' : 'var(--danger)' ?>">
                <?= money($netMonth) ?>
            </div>
            <div class="text-muted" style="font-size:.78rem;"><?= date('F Y') ?></div>
        </div>
        <span class="delta <?= $netMonth>=0 ? '' : 'down' ?>">
            <i class="bi <?= $netMonth>=0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' ?>"></i>
            <?= $totalIncomeMonth > 0 ? round(($netMonth/$totalIncomeMonth)*100) : 0 ?>%
        </span>
    </div>
    <div class="mini-stats">
        <div class="stat">
            <div class="ico in"><i class="bi bi-arrow-down-left"></i></div>
            <div>
                <div class="lbl">Income (month)</div>
                <div class="val"><?= money($totalIncomeMonth) ?></div>
            </div>
        </div>
        <div class="stat">
            <div class="ico out"><i class="bi bi-arrow-up-right"></i></div>
            <div>
                <div class="lbl">Expenses (month)</div>
                <div class="val"><?= money($expenseMonth) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Quick actions -->
<div class="section-title">
    <h3>Quick Actions</h3>
</div>

<div class="action-grid">
    <a href="<?= BASE_URL ?>/modules/wifi/record_payment.php" class="action-tile">
        <div class="tile-ico bg-primary-soft"><i class="bi bi-wifi"></i></div>
        <div class="tile-label">Record<br>WiFi Pay</div>
    </a>
    <a href="<?= BASE_URL ?>/modules/wifi/add_customer.php" class="action-tile">
        <div class="tile-ico bg-blue-soft"><i class="bi bi-person-plus"></i></div>
        <div class="tile-label">Add<br>Customer</div>
    </a>
    <a href="<?= BASE_URL ?>/modules/expenses/add.php" class="action-tile">
        <div class="tile-ico bg-red-soft"><i class="bi bi-wallet2"></i></div>
        <div class="tile-label">Log<br>Expense</div>
    </a>
    <a href="<?= BASE_URL ?>/modules/earnings/add.php" class="action-tile">
        <div class="tile-ico bg-green-soft"><i class="bi bi-cash-coin"></i></div>
        <div class="tile-label">Log<br>Income</div>
    </a>

    <a href="<?= BASE_URL ?>/modules/wifi/vouchers.php" class="action-tile">
        <div class="tile-ico bg-purple-soft"><i class="bi bi-ticket-perforated"></i></div>
        <div class="tile-label">Vouchers</div>
    </a>
    <a href="<?= BASE_URL ?>/modules/wifi/reminders.php" class="action-tile">
        <div class="tile-ico bg-orange-soft"><i class="bi bi-bell"></i></div>
        <div class="tile-label">Expiry<br>Alerts</div>
        <?php if ($expiringSoon > 0): ?>
            <span class="pill pill-urgent" style="position:absolute;margin-top:4px;font-size:.6rem;"><?= $expiringSoon ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/modules/planner/index.php" class="action-tile">
        <div class="tile-ico bg-indigo-soft"><i class="bi bi-calendar-check"></i></div>
        <div class="tile-label">Daily<br>Plan</div>
    </a>
    <a href="<?= BASE_URL ?>/modules/goals/index.php" class="action-tile">
        <div class="tile-ico bg-pink-soft"><i class="bi bi-flag"></i></div>
        <div class="tile-label">Goals</div>
    </a>

    <a href="<?= BASE_URL ?>/modules/receipts/index.php" class="action-tile">
        <div class="tile-ico bg-amber-soft"><i class="bi bi-receipt"></i></div>
        <div class="tile-label">Receipts</div>
    </a>
    <a href="<?= BASE_URL ?>/modules/contacts/index.php" class="action-tile">
        <div class="tile-ico bg-slate-soft"><i class="bi bi-person-rolodex"></i></div>
        <div class="tile-label">Contacts</div>
    </a>
    <a href="<?= BASE_URL ?>/modules/notes/index.php" class="action-tile">
        <div class="tile-ico bg-blue-soft"><i class="bi bi-journal-text"></i></div>
        <div class="tile-label">Notes</div>
    </a>
    <a href="<?= BASE_URL ?>/modules/reports/index.php" class="action-tile">
        <div class="tile-ico bg-green-soft"><i class="bi bi-bar-chart"></i></div>
        <div class="tile-label">Reports</div>
    </a>
</div>

<!-- WiFi quick stats -->
<?php if ($bizId): ?>
<div class="section-title">
    <h3>WiFi Business at a Glance</h3>
    <a href="<?= BASE_URL ?>/modules/wifi/index.php">Open</a>
</div>
<div class="list-card" style="padding: 14px;">
    <div class="row text-center g-2">
        <div class="col-4">
            <div style="font-size:1.4rem;font-weight:800;color:var(--primary);"><?= $activeCustomers ?></div>
            <div style="font-size:.72rem;color:var(--text-muted);">Active</div>
        </div>
        <div class="col-4" style="border-left:1px solid var(--border-light);border-right:1px solid var(--border-light);">
            <div style="font-size:1.4rem;font-weight:800;color:var(--accent);"><?= $expiringSoon ?></div>
            <div style="font-size:.72rem;color:var(--text-muted);">Expiring 3d</div>
        </div>
        <div class="col-4">
            <div style="font-size:1.4rem;font-weight:800;color:var(--text);"><?= $totalCustomers ?></div>
            <div style="font-size:.72rem;color:var(--text-muted);">Total</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 7-day cashflow chart -->
<div class="section-title">
    <h3>7-Day Cashflow</h3>
    <a href="<?= BASE_URL ?>/modules/reports/index.php">Details</a>
</div>
<div class="chart-card">
    <canvas id="cashflowChart" height="160"></canvas>
</div>

<!-- Recent activity -->
<div class="section-title">
    <h3>Recent Activity</h3>
    <a href="<?= BASE_URL ?>/modules/reports/index.php">All</a>
</div>

<div class="list-card">
    <?php if (empty($recent)): ?>
        <div class="empty-state">
            <div class="icon-circle"><i class="bi bi-inbox"></i></div>
            <div>Nothing yet — start by recording your first transaction.</div>
        </div>
    <?php else: foreach ($recent as $r):
        $isIn  = $r['amount'] > 0;
        $icon  = $r['kind'] === 'wifi' ? 'bi-wifi' : ($isIn ? 'bi-arrow-down-circle' : 'bi-arrow-up-circle');
        $iconClass = $isIn ? 'bg-primary-soft' : 'bg-red-soft';
    ?>
        <div class="list-item">
            <div class="li-ico <?= $iconClass ?>" style="color:#fff"><i class="bi <?= $icon ?>"></i></div>
            <div class="li-body">
                <div class="li-title"><?= e($r['title']) ?></div>
                <div class="li-sub"><?= e($r['sub'] ?? '') ?> · <?= friendlyDate($r['dt']) ?></div>
            </div>
            <div class="li-right">
                <div class="li-amount <?= $isIn?'in':'out' ?>">
                    <?= ($isIn?'+':'-') . money(abs($r['amount'])) ?>
                </div>
                <div class="li-time"><?= timeAgo($r['ts']) ?></div>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<div style="height:16px"></div>

<?php $active='home'; include __DIR__ . '/includes/bottomnav.php'; ?>

<script>
const cashflowData = <?= json_encode($cashflow) ?>;
const ctx = document.getElementById('cashflowChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: cashflowData.map(d => d.d),
        datasets: [
            { label: 'In',  data: cashflowData.map(d => d.in),  backgroundColor: '#0F766E', borderRadius: 6, barPercentage: 0.55 },
            { label: 'Out', data: cashflowData.map(d => d.out), backgroundColor: '#F87171', borderRadius: 6, barPercentage: 0.55 }
        ]
    },
    options: {
        plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, font: {size: 11 } } } },
        scales: {
            x: { grid: { display: false } },
            y: { grid: { color: '#F1F5F9' }, ticks: { font: { size: 10 } } }
        }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

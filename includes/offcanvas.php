<?php
$userName = $_SESSION['full_name'] ?? 'User';
$bizName  = $_SESSION['business_name'] ?? 'My Business';
$initial  = strtoupper(substr($userName, 0, 1));
?>
<div class="offcanvas offcanvas-start" tabindex="-1" id="mainMenu" style="max-width: 320px;">
    <div class="offcanvas-header flex-column align-items-start" style="padding: 22px 18px;">
        <div class="d-flex align-items-center gap-3 w-100">
            <div style="width:54px;height:54px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.3rem;border:2px solid rgba(255,255,255,.3)">
                <?= e($initial) ?>
            </div>
            <div>
                <div style="font-weight:700;font-size:1.05rem;"><?= e($userName) ?></div>
                <div style="font-size:.78rem;opacity:.85;"><?= e($bizName) ?></div>
            </div>
        </div>
    </div>
    <div class="offcanvas-body py-2">
        <a href="<?= BASE_URL ?>/dashboard.php" class="menu-link"><i class="bi bi-house-door"></i> Dashboard</a>
        <a href="<?= BASE_URL ?>/modules/wifi/index.php" class="menu-link"><i class="bi bi-wifi"></i> WiFi Business</a>
        <a href="<?= BASE_URL ?>/modules/wifi/customers.php" class="menu-link"><i class="bi bi-people"></i> Customers</a>
        <a href="<?= BASE_URL ?>/modules/wifi/vouchers.php" class="menu-link"><i class="bi bi-ticket-perforated"></i> Vouchers</a>
        <a href="<?= BASE_URL ?>/modules/expenses/index.php" class="menu-link"><i class="bi bi-wallet2"></i> Expenses</a>
        <a href="<?= BASE_URL ?>/modules/earnings/index.php" class="menu-link"><i class="bi bi-graph-up-arrow"></i> Earnings</a>
        <a href="<?= BASE_URL ?>/modules/receipts/index.php" class="menu-link"><i class="bi bi-receipt"></i> Receipts</a>
        <a href="<?= BASE_URL ?>/modules/planner/index.php" class="menu-link"><i class="bi bi-calendar-check"></i> Daily Planner</a>
        <a href="<?= BASE_URL ?>/modules/goals/index.php" class="menu-link"><i class="bi bi-flag"></i> Goals &amp; Future Plans</a>
        <a href="<?= BASE_URL ?>/modules/contacts/index.php" class="menu-link"><i class="bi bi-person-rolodex"></i> Contacts</a>
        <a href="<?= BASE_URL ?>/modules/notes/index.php" class="menu-link"><i class="bi bi-journal-text"></i> Notes</a>
        <a href="<?= BASE_URL ?>/modules/reports/index.php" class="menu-link"><i class="bi bi-bar-chart"></i> Reports &amp; Analytics</a>
        <hr style="border-color: var(--border-light); margin: 10px 4px;">
        <a href="<?= BASE_URL ?>/modules/settings/index.php" class="menu-link"><i class="bi bi-gear"></i> Settings</a>
        <a href="<?= BASE_URL ?>/logout.php" class="menu-link" style="color:#DC2626;"><i class="bi bi-box-arrow-right" style="color:#DC2626"></i> Logout</a>
    </div>
</div>

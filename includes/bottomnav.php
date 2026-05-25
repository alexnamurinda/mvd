<?php
$active = $active ?? 'home';
?>
<nav class="bottom-nav">
    <a href="<?= BASE_URL ?>/dashboard.php"               class="<?= $active==='home'?'active':'' ?>">
        <i class="bi <?= $active==='home'?'bi-house-fill':'bi-house' ?>"></i> Home
    </a>
    <a href="<?= BASE_URL ?>/modules/wifi/index.php"      class="<?= $active==='wifi'?'active':'' ?>">
        <i class="bi <?= $active==='wifi'?'bi-wifi':'bi-wifi' ?>"></i> WiFi
    </a>
    <a href="<?= BASE_URL ?>/modules/expenses/add.php"    class="<?= $active==='add'?'active':'' ?>" style="position: relative;">
        <span style="display:inline-flex;width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));align-items:center;justify-content:center;color:#fff;margin-top:-22px;box-shadow:0 6px 16px rgba(15,118,110,.35)">
            <i class="bi bi-plus-lg" style="font-size:1.4rem;margin:0;"></i>
        </span>
    </a>
    <a href="<?= BASE_URL ?>/modules/planner/index.php"   class="<?= $active==='plan'?'active':'' ?>">
        <i class="bi <?= $active==='plan'?'bi-calendar-check-fill':'bi-calendar-check' ?>"></i> Plan
    </a>
    <a href="<?= BASE_URL ?>/modules/reports/index.php"   class="<?= $active==='reports'?'active':'' ?>">
        <i class="bi <?= $active==='reports'?'bi-bar-chart-fill':'bi-bar-chart' ?>"></i> Reports
    </a>
</nav>

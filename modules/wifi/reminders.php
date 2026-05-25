<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$bizId = defaultBusinessId($pdo);

$days = (int)($_GET['days'] ?? 3); // expiring within X days
$tab  = $_GET['tab'] ?? 'expiring'; // expiring | expired

$rows = [];
if ($bizId) {
    if ($tab === 'expired') {
        $stmt = $pdo->prepare("SELECT c.id, c.full_name, c.phone, s.expiry_date, s.package_id, p.name pkg_name, p.price, DATEDIFF(CURDATE(), s.expiry_date) days_gone
            FROM wifi_subscriptions s
            JOIN wifi_customers c ON c.id=s.customer_id
            LEFT JOIN wifi_packages p ON p.id=s.package_id
            WHERE c.business_id=? AND s.expiry_date < CURDATE()
            ORDER BY s.expiry_date DESC LIMIT 200");
        $stmt->execute([$bizId]);
    } else {
        $stmt = $pdo->prepare("SELECT c.id, c.full_name, c.phone, s.expiry_date, s.package_id, p.name pkg_name, p.price, DATEDIFF(s.expiry_date, CURDATE()) days_left
            FROM wifi_subscriptions s
            JOIN wifi_customers c ON c.id=s.customer_id
            LEFT JOIN wifi_packages p ON p.id=s.package_id
            WHERE c.business_id=? AND s.status='active' AND s.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY s.expiry_date ASC LIMIT 200");
        $stmt->execute([$bizId, $days]);
    }
    $rows = $stmt->fetchAll();
}

$bizName = '';
$stmt = $pdo->prepare("SELECT name FROM businesses WHERE id=?");
$stmt->execute([$bizId]); $bizName = (string)($stmt->fetchColumn() ?: 'Our WiFi');

$pageTitle = 'Expiry Reminders';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Reminders'; $topbarBack = 'index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <div class="tab-bar">
        <a href="?tab=expiring&days=<?= $days ?>" class="<?= $tab==='expiring'?'active':'' ?>">Expiring</a>
        <a href="?tab=expired" class="<?= $tab==='expired'?'active':'' ?>">Expired</a>
    </div>

    <?php if ($tab==='expiring'): ?>
        <div class="d-flex gap-2 mb-3" style="flex-wrap:wrap;">
            <?php foreach ([1,3,7,14] as $d): ?>
                <a href="?tab=expiring&days=<?= $d ?>" class="btn btn-sm <?= $days===$d?'btn-primary':'btn-light' ?>">Next <?= $d ?> day<?= $d>1?'s':'' ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="list-card">
        <?php if (empty($rows)): ?>
            <div class="empty-state"><div class="icon-circle"><i class="bi bi-check2-circle" style="color:var(--success)"></i></div>
                <?= $tab==='expired' ? 'No expired subscribers. 🎉' : 'No customers expiring in this window.' ?>
            </div>
        <?php else: foreach ($rows as $r):
            $msg = $tab==='expired'
                ? "Hi " . ($r['full_name']) . ", your $bizName subscription expired " . (int)$r['days_gone'] . " day(s) ago. Renew today to stay connected. Thanks!"
                : "Hi " . ($r['full_name']) . ", your $bizName subscription expires on " . date('D M j', strtotime($r['expiry_date'])) . " (" . (int)$r['days_left'] . " day(s) left). Renew early to avoid disconnection.";
            $waPhone = preg_replace('/\D/', '', $r['phone']);
            // assume Uganda numbers if leading 0
            if (strlen($waPhone)===10 && $waPhone[0]==='0') $waPhone = '256' . substr($waPhone,1);
            $waUrl = 'https://wa.me/' . $waPhone . '?text=' . rawurlencode($msg);
            $smsUrl = 'sms:' . $r['phone'] . '?body=' . rawurlencode($msg);
        ?>
            <div class="list-item">
                <div class="li-ico" style="background:linear-gradient(135deg,#EA580C,#F97316);color:#fff;"><?= strtoupper(substr($r['full_name'],0,1)) ?></div>
                <div class="li-body">
                    <div class="li-title"><?= e($r['full_name']) ?></div>
                    <div class="li-sub">
                        <?= e($r['phone']) ?> ·
                        <?= $tab==='expired'
                            ? '<span style="color:var(--danger);font-weight:600;">'.((int)$r['days_gone']).'d ago</span>'
                            : '<span style="color:var(--accent-dark);font-weight:600;">'.((int)$r['days_left']).'d left</span>' ?>
                    </div>
                </div>
                <div class="li-right">
                    <a href="<?= e($waUrl) ?>" target="_blank" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i></a>
                    <a href="<?= e($smsUrl) ?>" class="btn btn-light btn-sm mt-1"><i class="bi bi-chat-dots"></i></a>
                </div>
            </div>
            <div style="padding:0 14px 12px 60px;">
                <a href="<?= BASE_URL ?>/modules/wifi/record_payment.php?customer_id=<?= $r['id'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-cash-coin"></i> Record Renewal</a>
                <a href="<?= BASE_URL ?>/modules/wifi/customer_view.php?id=<?= $r['id'] ?>" class="btn btn-light btn-sm">View</a>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div style="height:30px"></div>
<?php $active='wifi'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

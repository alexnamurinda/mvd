<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();

// Load user + business
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$bizId = defaultBusinessId($pdo);
$biz = null;
if ($bizId) {
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id=?");
    $stmt->execute([$bizId]);
    $biz = $stmt->fetch();
}

// Income sources
$stmt = $pdo->prepare("SELECT * FROM income_sources WHERE user_id=? ORDER BY name");
$stmt->execute([$userId]);
$sources = $stmt->fetchAll();

$action = $_POST['action'] ?? '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'profile') {
        $name  = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '') { $errors[] = 'Name is required'; }
        if ($email === '') { $errors[] = 'Email is required'; }
        if (!$errors) {
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?");
            $stmt->execute([$name, $email, $phone, $userId]);
            $_SESSION['full_name'] = $name;
            $_SESSION['email'] = $email;
            flash('success', 'Profile updated');
        }
    } elseif ($action === 'business') {
        $bizName = trim($_POST['business_name'] ?? '');
        $currency = trim($_POST['currency'] ?? 'UGX');
        $footer  = trim($_POST['receipt_footer'] ?? '');
        $bizType = $_POST['biz_type'] ?? 'wifi';
        if ($bizName) {
            $stmt = $pdo->prepare("UPDATE users SET business_name=?, currency=?, receipt_footer=? WHERE id=?");
            $stmt->execute([$bizName, $currency, $footer, $userId]);
            $_SESSION['business_name'] = $bizName;
            $_SESSION['currency'] = $currency;
            if ($bizId) {
                $stmt = $pdo->prepare("UPDATE businesses SET name=?, type=? WHERE id=?");
                $stmt->execute([$bizName, $bizType, $bizId]);
            }
            flash('success', 'Business settings saved');
        }
    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new1    = $_POST['new_password'] ?? '';
        $new2    = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        } elseif (strlen($new1) < 6) {
            $errors[] = 'New password must be at least 6 characters';
        } elseif ($new1 !== $new2) {
            $errors[] = 'New passwords do not match';
        }
        if (!$errors) {
            $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->execute([password_hash($new1, PASSWORD_DEFAULT), $userId]);
            flash('success', 'Password changed');
        }
    } elseif ($action === 'add_source') {
        $sName = trim($_POST['name'] ?? '');
        $sColor = trim($_POST['color'] ?? '#16A34A');
        if ($sName) {
            $stmt = $pdo->prepare("INSERT INTO income_sources (user_id, name, color) VALUES (?,?,?)");
            $stmt->execute([$userId, $sName, $sColor]);
            flash('success', 'Income source added');
        }
    } elseif ($action === 'delete_source') {
        $sid = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM income_sources WHERE id=? AND user_id=?");
        $stmt->execute([$sid, $userId]);
        flash('success', 'Source removed');
    }
    if (!$errors) { header('Location: index.php'); exit; }
}

// Reload user after updates
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$currencies = ['UGX','KES','TZS','RWF','USD','GBP','EUR','ZAR','NGN'];

$pageTitle = 'Settings';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Settings'; $topbarBack = BASE_URL.'/dashboard.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><?= e(implode('. ', $errors)) ?></div><?php endif; ?>

    <!-- Avatar -->
    <div class="text-center mb-3">
        <div style="display:inline-flex;width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-light));align-items:center;justify-content:center;color:#fff;font-size:1.8rem;font-weight:800;">
            <?= strtoupper(substr($user['full_name'],0,1)) ?>
        </div>
        <div style="font-weight:700;margin-top:6px;"><?= e($user['full_name']) ?></div>
        <div class="text-muted" style="font-size:.82rem;"><?= e($user['email']) ?></div>
    </div>

    <!-- Profile -->
    <div class="section-title"><h3><i class="bi bi-person"></i> Profile</h3></div>
    <div class="list-card" style="padding:14px;">
        <form method="post">
            <input type="hidden" name="action" value="profile">
            <div class="form-group"><label class="form-label">Full Name *</label><input class="form-control" name="full_name" value="<?= e($user['full_name']) ?>" required></div>
            <div class="row g-2">
                <div class="col-7 form-group"><label class="form-label">Email *</label><input class="form-control" name="email" type="email" value="<?= e($user['email']) ?>" required></div>
                <div class="col-5 form-group"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($user['phone']) ?>"></div>
            </div>
            <button class="btn btn-primary btn-block">Save Profile</button>
        </form>
    </div>

    <!-- Business -->
    <div class="section-title mt-4"><h3><i class="bi bi-building"></i> Business</h3></div>
    <div class="list-card" style="padding:14px;">
        <form method="post">
            <input type="hidden" name="action" value="business">
            <div class="form-group"><label class="form-label">Business Name</label><input class="form-control" name="business_name" value="<?= e($user['business_name']) ?>"></div>
            <div class="row g-2">
                <div class="col-5 form-group">
                    <label class="form-label">Currency</label>
                    <select class="form-select" name="currency">
                        <?php foreach ($currencies as $c): ?><option value="<?= $c ?>" <?= ($user['currency']??'UGX')===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-7 form-group">
                    <label class="form-label">Business Type</label>
                    <select class="form-select" name="biz_type">
                        <?php foreach (['wifi'=>'WiFi / Internet','retail'=>'Retail','services'=>'Services','rental'=>'Rental','other'=>'Other'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($biz['type']??'wifi')===$k?'selected':'' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group"><label class="form-label">Receipt Footer Text</label><input class="form-control" name="receipt_footer" value="<?= e($user['receipt_footer']) ?>" placeholder="e.g. Thank you for your business!"></div>
            <button class="btn btn-primary btn-block">Save Business</button>
        </form>
    </div>

    <!-- Income Sources -->
    <div class="section-title mt-4"><h3><i class="bi bi-cash-coin"></i> Income Sources</h3></div>
    <div class="list-card" style="padding:14px;">
        <form method="post" class="row g-2 mb-3">
            <input type="hidden" name="action" value="add_source">
            <div class="col-6"><input class="form-control" name="name" placeholder="Source name" required></div>
            <div class="col-3"><input class="form-control" name="color" type="color" value="#16A34A"></div>
            <div class="col-3"><button class="btn btn-primary btn-block">+</button></div>
        </form>
        <?php foreach ($sources as $s): ?>
            <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--border-light);">
                <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= e($s['color']) ?>;"></span> <?= e($s['name']) ?></span>
                <form method="post" data-confirm="Remove source?"><input type="hidden" name="action" value="delete_source"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-light btn-sm" style="color:var(--danger)"><i class="bi bi-trash"></i></button></form>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Password -->
    <div class="section-title mt-4"><h3><i class="bi bi-shield-lock"></i> Change Password</h3></div>
    <div class="list-card" style="padding:14px;">
        <form method="post">
            <input type="hidden" name="action" value="password">
            <div class="form-group"><label class="form-label">Current Password</label><input class="form-control" name="current_password" type="password" required></div>
            <div class="form-group"><label class="form-label">New Password (min 6)</label><input class="form-control" name="new_password" type="password" required minlength="6"></div>
            <div class="form-group"><label class="form-label">Confirm Password</label><input class="form-control" name="confirm_password" type="password" required></div>
            <button class="btn btn-primary btn-block">Change Password</button>
        </form>
    </div>

    <!-- Danger Zone -->
    <div class="section-title mt-4"><h3 style="color:var(--danger)"><i class="bi bi-exclamation-triangle"></i> Account</h3></div>
    <div class="list-card" style="padding:14px;">
        <div class="text-muted" style="font-size:.85rem;">Logged in as <strong><?= e($user['email']) ?></strong></div>
        <div class="text-soft mt-1" style="font-size:.78rem;">Role: <?= e($user['role'] ?? 'admin') ?> · Joined <?= date('M Y', strtotime($user['created_at'])) ?></div>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-danger btn-block mt-3"><i class="bi bi-box-arrow-right"></i> Log Out</a>
    </div>
</div>

<div style="height:30px"></div>
<?php $active='home'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

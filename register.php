<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $biz       = trim($_POST['business_name'] ?? 'My Business');
    $pwd       = $_POST['password'] ?? '';
    $pwd2      = $_POST['password2'] ?? '';

    if (!$name || !$email || !$pwd) {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($pwd) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pwd !== $pwd2) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, business_name) VALUES (?, ?, ?, ?, ?)")
                ->execute([$name, $email, $phone, $hash, $biz]);
            $userId = (int)$pdo->lastInsertId();

            // create default business + categories
            $pdo->prepare("INSERT INTO businesses (user_id, name, type, description) VALUES (?, ?, 'wifi', ?)")
                ->execute([$userId, $biz, 'Primary business']);

            $defaultCats = [
                ['Power/Electricity','bi-lightning-charge','#F59E0B'],
                ['Internet/Bandwidth','bi-router','#0EA5E9'],
                ['Transport','bi-bus-front','#8B5CF6'],
                ['Food/Meals','bi-cup-hot','#EF4444'],
                ['Salaries','bi-people','#10B981'],
                ['Rent','bi-house','#6366F1'],
                ['Equipment','bi-tools','#64748B'],
                ['Airtime/Data','bi-phone','#EC4899'],
                ['Marketing','bi-megaphone','#F97316'],
                ['Other','bi-three-dots','#71717A'],
            ];
            $s = $pdo->prepare("INSERT INTO expense_categories (user_id, name, icon, color) VALUES (?,?,?,?)");
            foreach ($defaultCats as $c) $s->execute([$userId, $c[0], $c[1], $c[2]]);

            $defaultSources = [
                ['WiFi Subscriptions','bi-wifi','#0F766E'],
                ['WiFi Vouchers','bi-ticket-perforated','#0EA5E9'],
                ['Installation Fees','bi-tools','#8B5CF6'],
                ['Salary/Office Job','bi-briefcase','#16A34A'],
                ['Side Hustles','bi-cash-stack','#F59E0B'],
                ['Other','bi-three-dots','#71717A'],
            ];
            $s = $pdo->prepare("INSERT INTO income_sources (user_id, name, icon, color) VALUES (?,?,?,?)");
            foreach ($defaultSources as $c) $s->execute([$userId, $c[0], $c[1], $c[2]]);

            $success = 'Account created. You can now sign in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#0F766E">
<title>Register · <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="logo"><i class="bi bi-briefcase-fill"></i></div>
            <h1>Create Account</h1>
            <p>Take charge of your businesses</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><?= e($error) ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><?= e($success) ?> <a href="index.php" style="margin-left:6px;font-weight:700;">Sign In</a></div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="post">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <div class="input-icon"><i class="bi bi-person"></i>
                    <input type="text" name="full_name" required class="form-control" value="<?= e($_POST['full_name'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <div class="input-icon"><i class="bi bi-envelope"></i>
                    <input type="email" name="email" required class="form-control" value="<?= e($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Phone (optional)</label>
                <div class="input-icon"><i class="bi bi-telephone"></i>
                    <input type="tel" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Business Name</label>
                <div class="input-icon"><i class="bi bi-shop"></i>
                    <input type="text" name="business_name" class="form-control" placeholder="e.g. SkyNet WiFi" value="<?= e($_POST['business_name'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-icon"><i class="bi bi-lock"></i>
                    <input type="password" name="password" required minlength="6" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <div class="input-icon"><i class="bi bi-shield-lock"></i>
                    <input type="password" name="password2" required minlength="6" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg mt-2">Create Account</button>
        </form>
        <?php endif; ?>

        <div class="text-center mt-3" style="font-size:.88rem;color:var(--text-muted)">
            Already have an account? <a href="index.php" style="font-weight:600;">Sign In</a>
        </div>
    </div>
</div>
</body>
</html>

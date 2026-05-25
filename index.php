<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pwd   = $_POST['password'] ?? '';

    if (!$email || !$pwd) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pwd, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Account suspended. Contact admin.';
        } else {
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['full_name']     = $user['full_name'];
            $_SESSION['email']         = $user['email'];
            $_SESSION['business_name'] = $user['business_name'];
            $_SESSION['currency']      = $user['currency'];
            $_SESSION['role']          = $user['role'];

            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            logActivity($pdo, $user['id'], 'Logged in', '', 'bi-box-arrow-in-right');

            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
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
<title>Sign In · <?= APP_NAME ?></title>
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
            <h1><?= APP_NAME ?></h1>
            <p><?= APP_TAGLINE ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-icon">
                    <i class="bi bi-envelope"></i>
                    <input type="email" name="email" required class="form-control" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input type="password" name="password" required class="form-control" placeholder="••••••••">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg mt-2">
                Sign In <i class="bi bi-arrow-right"></i>
            </button>
        </form>

        <div class="text-center mt-3" style="font-size:.88rem;color:var(--text-muted)">
            New here? <a href="register.php" style="font-weight:600;">Create account</a>
        </div>

        <div class="text-center mt-4" style="font-size:.75rem;color:var(--text-soft)">
            Demo: <code>demo@businesspro.app</code> / <code>demo1234</code>
        </div>
    </div>
</div>
</body>
</html>

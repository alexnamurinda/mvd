<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();

$stmt = $pdo->prepare("SELECT * FROM income_sources WHERE user_id=? ORDER BY name");
$stmt->execute([$userId]);
$sources = $stmt->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title  = trim($_POST['title'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $srcId  = (int)($_POST['source_id'] ?? 0) ?: null;
    $date   = $_POST['income_date'] ?? date('Y-m-d');
    $method = $_POST['method'] ?? 'cash';
    $notes  = trim($_POST['notes'] ?? '');

    if ($title === '') $errors[] = 'Title is required';
    if ($amount <= 0)  $errors[] = 'Amount must be greater than zero';

    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO incomes (user_id, source_id, title, amount, method, notes, income_date) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$userId, $srcId, $title, $amount, $method, $notes, $date]);
        logActivity($pdo, $userId, 'Logged income', $title.' '.money($amount), 'bi-cash-coin');
        flash('success', 'Income recorded');
        header('Location: index.php'); exit;
    }
}

$pageTitle = 'Log Income';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Log Income'; $topbarBack = 'index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <?php if ($errors): ?><div class="alert alert-danger"><?= e(implode('. ', $errors)) ?></div><?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label class="form-label">Title *</label>
            <input class="form-control" name="title" placeholder="e.g. June salary, Consulting fee" required>
        </div>
        <div class="row g-2">
            <div class="col-6 form-group">
                <label class="form-label">Amount *</label>
                <input class="form-control" name="amount" type="number" step="0.01" min="0.01" required style="font-size:1.2rem;font-weight:700;color:var(--success);">
            </div>
            <div class="col-6 form-group">
                <label class="form-label">Date</label>
                <input class="form-control" name="income_date" type="date" value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Source</label>
            <select class="form-select" name="source_id">
                <option value="0">— Other —</option>
                <?php foreach ($sources as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Method</label>
            <select class="form-select" name="method">
                <option value="cash">Cash</option>
                <option value="mtn_momo">MTN MoMo</option>
                <option value="airtel_money">Airtel Money</option>
                <option value="bank">Bank</option>
                <option value="card">Card</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="2"></textarea>
        </div>
        <button class="btn btn-primary btn-lg btn-block"><i class="bi bi-check2"></i> Save Income</button>
        <a href="index.php" class="btn btn-light btn-block mt-2">Cancel</a>
    </form>
</div>

<div style="height:30px"></div>
<?php $active='home'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

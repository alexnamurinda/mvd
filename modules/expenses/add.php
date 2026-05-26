<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();
$bizId  = defaultBusinessId($pdo);

$stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE user_id=? ORDER BY name");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $amount   = (float)($_POST['amount'] ?? 0);
    $catId    = (int)($_POST['category_id'] ?? 0) ?: null;
    $date     = $_POST['expense_date'] ?? date('Y-m-d');
    $method   = $_POST['method'] ?? 'cash';
    $vendor      = trim($_POST['vendor'] ?? '');
    $vendorPhone = trim($_POST['vendor_phone'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');
    $genReceipt = isset($_POST['generate_receipt']);
    $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;
    $recurringPeriod = $_POST['recurring_period'] ?? null;

    if ($title === '')   $errors[] = 'Title is required';
    if ($amount <= 0)    $errors[] = 'Amount must be greater than zero';

    if (!$errors) {
        $receiptNo = $genReceipt ? generateReceiptNo('EXP') : null;
        $stmt = $pdo->prepare("INSERT INTO expenses (user_id, business_id, category_id, title, amount, method, vendor, vendor_phone, notes, expense_date, receipt_no, is_recurring, recurring_period) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$userId, $bizId ?: null, $catId, $title, $amount, $method, $vendor, $vendorPhone ?: null, $notes, $date, $receiptNo, $isRecurring, $isRecurring ? $recurringPeriod : null]);
        $newId = (int)$pdo->lastInsertId();
        logActivity($pdo, $userId, 'Logged expense', $title.' '.money($amount), 'bi-receipt');
        flash('success', 'Expense recorded');
        if ($genReceipt) {
            header('Location: receipt.php?id=' . $newId); exit;
        }
        header('Location: index.php'); exit;
    }
}

$pageTitle = 'Add Expense';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Log Expense'; $topbarBack = 'index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <?php if ($errors): ?><div class="alert alert-danger"><?= e(implode('. ', $errors)) ?></div><?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label class="form-label">What for? *</label>
            <input class="form-control" name="title" placeholder="e.g. Diesel for generator" required>
        </div>
        <div class="row g-2">
            <div class="col-6 form-group">
                <label class="form-label">Amount *</label>
                <input class="form-control" name="amount" type="number" step="0.01" min="0.01" placeholder="0" required style="font-size:1.2rem;font-weight:700;color:var(--danger);">
            </div>
            <div class="col-6 form-group">
                <label class="form-label">Date</label>
                <input class="form-control" name="expense_date" type="date" value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Category</label>
            <select class="form-select" name="category_id">
                <option value="0">— Uncategorized —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Payment Method</label>
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
            <label class="form-label">Vendor / Paid to</label>
            <input class="form-control" name="vendor" id="vendorName" placeholder="e.g. Total fuel station">
        </div>
        <div class="form-group">
            <label class="form-label">Vendor Phone</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                <input class="form-control" name="vendor_phone" id="vendorPhone" type="tel" placeholder="+256 700 000 000">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="Optional details..."></textarea>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="generate_receipt" id="gr" checked>
            <label class="form-check-label" for="gr"><i class="bi bi-receipt"></i> Generate printable receipt</label>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="is_recurring" id="ir" onchange="toggleRecurring()">
            <label class="form-check-label" for="ir"><i class="bi bi-arrow-repeat"></i> This is recurring</label>
        </div>
        <div class="form-group" id="rp" style="display:none;">
            <label class="form-label">Frequency</label>
            <select class="form-select" name="recurring_period" id="recurPeriod" onchange="updateRecurPreview()">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly" selected>Monthly</option>
                <option value="yearly">Yearly</option>
            </select>
            <div id="recurPreview" style="font-size:.78rem;color:var(--primary);margin-top:5px;font-weight:600;"></div>
        </div>
        <button class="btn btn-primary btn-lg btn-block"><i class="bi bi-check2"></i> Save Expense</button>
        <a href="index.php" class="btn btn-light btn-block mt-2">Cancel</a>
    </form>
</div>

<div style="height:30px"></div>
<?php $active='add'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script>
function toggleRecurring() {
    var on = document.getElementById('ir').checked;
    document.getElementById('rp').style.display = on ? 'block' : 'none';
    if (on) updateRecurPreview();
}
function updateRecurPreview() {
    var dateVal  = document.querySelector('[name="expense_date"]').value;
    var period   = document.getElementById('recurPeriod').value;
    var preview  = document.getElementById('recurPreview');
    if (!dateVal) { preview.textContent = ''; return; }

    var base  = new Date(dateVal + 'T00:00:00');
    var today = new Date(); today.setHours(0,0,0,0);

    var max = 3660, i = 0;
    while (base <= today && i++ < max) {
        if      (period === 'daily')   base.setDate(base.getDate() + 1);
        else if (period === 'weekly')  base.setDate(base.getDate() + 7);
        else if (period === 'monthly') base.setMonth(base.getMonth() + 1);
        else if (period === 'yearly')  base.setFullYear(base.getFullYear() + 1);
        else break;
    }

    var diff  = Math.round((base - today) / 86400000);
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var ds    = months[base.getMonth()] + ' ' + base.getDate() + ', ' + base.getFullYear();
    var when  = diff === 0 ? 'today' : diff === 1 ? 'tomorrow' : 'in ' + diff + ' days';
    preview.textContent = '↻ Next due: ' + ds + ' (' + when + ')';
}
document.querySelector('[name="expense_date"]').addEventListener('change', function() {
    if (document.getElementById('ir').checked) updateRecurPreview();
});
</script>

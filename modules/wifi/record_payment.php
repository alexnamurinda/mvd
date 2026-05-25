<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$bizId = defaultBusinessId($pdo);
$error = '';

$customerId = (int)($_GET['customer'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid    = (int)($_POST['customer_id'] ?? 0);
    $pid    = (int)($_POST['package_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['method'] ?? 'cash';
    $ref    = trim($_POST['reference'] ?? '');
    $when   = $_POST['paid_on'] ?? date('Y-m-d');
    $notes  = trim($_POST['notes'] ?? '');
    $receivedBy = $_SESSION['full_name'];

    if (!$cid || $amount <= 0) {
        $error = 'Please select a customer and enter the amount.';
    } else {
        // Verify customer belongs to user
        $stmt = $pdo->prepare("SELECT c.id FROM wifi_customers c WHERE c.id=? AND c.business_id=?");
        $stmt->execute([$cid, $bizId]);
        if (!$stmt->fetch()) {
            $error = 'Invalid customer.';
        } else {
            $pdo->beginTransaction();

            // Subscription: extend existing active or create new
            $subId = null;
            if ($pid) {
                $pkg = $pdo->prepare("SELECT * FROM wifi_packages WHERE id=? AND business_id=?");
                $pkg->execute([$pid, $bizId]); $pkg = $pkg->fetch();
                if ($pkg) {
                    // Existing active sub?
                    $act = $pdo->prepare("SELECT * FROM wifi_subscriptions WHERE customer_id=? AND status='active' AND expiry_date>=CURDATE() ORDER BY expiry_date DESC LIMIT 1");
                    $act->execute([$cid]); $existing = $act->fetch();

                    if ($existing && $existing['package_id'] == $pkg['id']) {
                        // Extend
                        $newExp = date('Y-m-d', strtotime("+{$pkg['duration_days']} days", strtotime($existing['expiry_date'])));
                        $pdo->prepare("UPDATE wifi_subscriptions SET expiry_date=? WHERE id=?")->execute([$newExp, $existing['id']]);
                        $subId = $existing['id'];
                    } else {
                        // Expire old and create new
                        if ($existing) $pdo->prepare("UPDATE wifi_subscriptions SET status='expired' WHERE id=?")->execute([$existing['id']]);
                        $start = max($when, date('Y-m-d'));
                        $expiry = date('Y-m-d', strtotime("+{$pkg['duration_days']} days", strtotime($start)));
                        $ins = $pdo->prepare("INSERT INTO wifi_subscriptions (customer_id, package_id, start_date, expiry_date) VALUES (?,?,?,?)");
                        $ins->execute([$cid, $pkg['id'], $start, $expiry]);
                        $subId = (int)$pdo->lastInsertId();
                    }
                }
            }

            // Insert payment
            $rcpt = generateReceiptNo('WIFI');
            $stmt = $pdo->prepare("INSERT INTO wifi_payments (customer_id, subscription_id, amount, method, reference, paid_on, received_by, notes, receipt_no) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$cid, $subId, $amount, $method, $ref, $when, $receivedBy, $notes, $rcpt]);
            $payId = (int)$pdo->lastInsertId();

            $custName = $pdo->prepare("SELECT full_name FROM wifi_customers WHERE id=?");
            $custName->execute([$cid]);
            logActivity($pdo, uid(), 'WiFi payment recorded', $custName->fetchColumn().' — '.money($amount), 'bi-cash-coin');

            $pdo->commit();

            flash('success', 'Payment recorded. Receipt #' . $rcpt);
            header('Location: receipt.php?id=' . $payId);
            exit;
        }
    }
}

// Customers list
$customers = [];
if ($bizId) {
    $stmt = $pdo->prepare("SELECT id, full_name, phone FROM wifi_customers WHERE business_id=? ORDER BY full_name ASC");
    $stmt->execute([$bizId]); $customers = $stmt->fetchAll();
}
// Packages
$packages = [];
if ($bizId) {
    $stmt = $pdo->prepare("SELECT * FROM wifi_packages WHERE business_id=? AND is_active=1 ORDER BY price ASC");
    $stmt->execute([$bizId]); $packages = $stmt->fetchAll();
}

$pageTitle = 'Record Payment';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Record WiFi Payment';
$topbarBack = 'index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<?php if ($error): ?>
<div class="alert alert-danger mt-3"><i class="bi bi-exclamation-circle"></i><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="mt-3">
    <div class="list-card" style="padding:16px;">
        <div class="form-group">
            <label class="form-label">Customer *</label>
            <select name="customer_id" required class="form-select">
                <option value="">-- Select customer --</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $customerId==$c['id']?'selected':'' ?>>
                    <?= e($c['full_name']) ?> · <?= e($c['phone']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($customers)): ?>
            <div class="text-muted mt-1" style="font-size:.78rem;">No customers yet. <a href="add_customer.php">Add one first</a>.</div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label">Package (optional — to activate/extend)</label>
            <select name="package_id" id="pkgSelect" class="form-select">
                <option value="">-- No package (cash payment only) --</option>
                <?php foreach ($packages as $p): ?>
                <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>"><?= e($p['name']) ?> — <?= money($p['price']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Amount Paid *</label>
            <div class="input-icon"><i class="bi bi-cash"></i>
                <input type="number" name="amount" id="amount" required min="0" step="100" class="form-control" placeholder="0" data-money>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Payment Method</label>
            <select name="method" class="form-select">
                <option value="cash">Cash</option>
                <option value="mobile_money">Mobile Money</option>
                <option value="mtn_momo">MTN MoMo</option>
                <option value="airtel_money">Airtel Money</option>
                <option value="bank">Bank Transfer</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Reference (Txn ID, MoMo code, etc.)</label>
            <input type="text" name="reference" class="form-control" placeholder="Optional">
        </div>

        <div class="form-group">
            <label class="form-label">Date Paid</label>
            <input type="date" name="paid_on" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" placeholder="Optional..."></textarea>
        </div>
    </div>

    <div class="px-page mb-3">
        <button type="submit" class="btn btn-primary btn-block btn-lg">
            <i class="bi bi-check-lg"></i> Save &amp; Generate Receipt
        </button>
    </div>
</form>

<script>
// Auto-fill amount when package selected
document.getElementById('pkgSelect').addEventListener('change', function() {
    var price = this.options[this.selectedIndex].getAttribute('data-price');
    if (price) document.getElementById('amount').value = price;
});
</script>

<div style="height:14px"></div>
<?php $active='wifi'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

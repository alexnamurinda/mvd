<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();
$bizId  = defaultBusinessId($pdo);

$stmt = $pdo->prepare("SELECT * FROM income_sources WHERE user_id=? ORDER BY name");
$stmt->execute([$userId]);
$sources = $stmt->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title         = trim($_POST['title']          ?? '');
    $amount        = (float)($_POST['amount']       ?? 0);
    $srcId         = (int)($_POST['source_id']      ?? 0) ?: null;
    $date          = $_POST['income_date']           ?? date('Y-m-d');
    $method        = $_POST['method']                ?? 'cash';
    $notes         = trim($_POST['notes']            ?? '');
    $genReceipt    = isset($_POST['generate_receipt']);

    // Sender
    $senderName    = trim($_POST['sender_name']    ?? '');
    $senderPhone   = trim($_POST['sender_phone']   ?? '');
    $senderAddress = trim($_POST['sender_address'] ?? '');

    // Goal / savings plan
    $hasPlan      = isset($_POST['has_plan']);
    $goalTitle    = trim($_POST['goal_title']    ?? '');
    $goalTarget   = (float)($_POST['goal_target']  ?? 0);
    $goalDeadline = trim($_POST['goal_deadline']  ?? '');
    $goalCategory = trim($_POST['goal_category']  ?? 'business');
    $goalDesc     = trim($_POST['goal_desc']      ?? '');

    if ($title === '') $errors[] = 'Title is required';
    if ($amount <= 0)  $errors[] = 'Amount must be greater than zero';
    if ($hasPlan && $goalTitle === '') $errors[] = 'Plan title is required when linking a savings plan';
    if ($hasPlan && $goalTarget <= 0) $errors[] = 'Plan target amount is required';

    // File upload validation
    $attachmentPath = null;
    $hasFile = !empty($_FILES['attachment']['name']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($hasFile) {
        $allowed = ['jpg','jpeg','png','gif','pdf'];
        $ext     = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Attachment must be JPG, PNG, GIF, or PDF';
        } elseif ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Attachment must be under 5 MB';
        } elseif ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed — please try again';
        }
    }

    if (!$errors) {
        // Save file
        if ($hasFile && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $ext       = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $uploadDir = __DIR__ . '/../../uploads/income_attachments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $safeName       = $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $safeName);
            $attachmentPath = 'uploads/income_attachments/' . $safeName;
        }

        $receiptNo = $genReceipt ? generateReceiptNo('INC') : null;

        $stmt = $pdo->prepare("INSERT INTO incomes
            (user_id, business_id, source_id, title, amount, method, notes, income_date,
             sender_name, sender_phone, sender_address, receipt_no, attachment)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $userId, $bizId ?: null, $srcId, $title, $amount, $method, $notes, $date,
            $senderName ?: null, $senderPhone ?: null, $senderAddress ?: null,
            $receiptNo, $attachmentPath,
        ]);
        $newId = (int)$pdo->lastInsertId();

        // Auto-create savings goal
        if ($hasPlan && $goalTitle && $goalTarget > 0) {
            $gStmt = $pdo->prepare("INSERT INTO goals
                (user_id, title, description, category, target_amount, current_amount, deadline, status)
                VALUES (?,?,?,?,?,?,?,'active')");
            $gStmt->execute([
                $userId, $goalTitle, $goalDesc,
                $goalCategory ?: 'business', $goalTarget, $amount,
                $goalDeadline ?: null,
            ]);
            logActivity($pdo, $userId, 'Created plan from income', $goalTitle, 'bi-bullseye');
        }

        logActivity($pdo, $userId, 'Logged income', $title . ' ' . money($amount), 'bi-cash-coin');
        flash('success', 'Income recorded' . ($hasPlan ? ' & savings plan created' : ''));

        if ($genReceipt) {
            header('Location: receipt.php?id=' . $newId); exit;
        }
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
    <?php if ($errors): ?>
        <div class="alert alert-danger"><?= e(implode('. ', $errors)) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">

        <!-- Core fields -->
        <div class="form-group">
            <label class="form-label">Title / Description *</label>
            <input class="form-control" name="title" placeholder="e.g. June salary, Consulting fee" required>
        </div>
        <div class="row g-2">
            <div class="col-6 form-group">
                <label class="form-label">Amount *</label>
                <input class="form-control" name="amount" type="number" step="0.01" min="0.01" required
                       style="font-size:1.2rem;font-weight:700;color:var(--success);">
            </div>
            <div class="col-6 form-group">
                <label class="form-label">Date</label>
                <input class="form-control" name="income_date" type="date" value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Income Source</label>
            <select class="form-select" name="source_id">
                <option value="0">— Other —</option>
                <?php foreach ($sources as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Payment Method</label>
            <select class="form-select" name="method">
                <option value="cash">Cash</option>
                <option value="mtn_momo">MTN MoMo</option>
                <option value="airtel_money">Airtel Money</option>
                <option value="bank">Bank Transfer</option>
                <option value="card">Card</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="Optional remarks…"></textarea>
        </div>

        <!-- Sender details -->
        <div class="section-divider">
            <span><i class="bi bi-person-lines-fill"></i> Sender Details
                <small class="text-muted fw-normal">(optional — shown on receipt)</small>
            </span>
        </div>
        <div class="form-group">
            <label class="form-label">Sender Name</label>
            <input class="form-control" name="sender_name" placeholder="e.g. John Mukasa">
        </div>
        <div class="row g-2">
            <div class="col-6 form-group">
                <label class="form-label">Sender Phone</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                    <input class="form-control" name="sender_phone" type="tel" placeholder="+256 7xx">
                </div>
            </div>
            <div class="col-6 form-group">
                <label class="form-label">Address / Location</label>
                <input class="form-control" name="sender_address" placeholder="Kampala">
            </div>
        </div>

        <!-- Proof attachment -->
        <div class="section-divider">
            <span><i class="bi bi-paperclip"></i> Proof / Attachment
                <small class="text-muted fw-normal">(PDF or image ≤ 5 MB)</small>
            </span>
        </div>
        <div class="form-group">
            <label class="form-label">Attach File</label>
            <input class="form-control" type="file" name="attachment" accept=".jpg,.jpeg,.png,.gif,.pdf">
            <div style="font-size:.73rem;color:var(--text-muted);margin-top:4px;">
                Transfer confirmation, cheque image, or any proof of payment.
            </div>
        </div>

        <!-- Receipt checkbox -->
        <div class="form-check mb-2 mt-1">
            <input class="form-check-input" type="checkbox" name="generate_receipt" id="gr" checked>
            <label class="form-check-label" for="gr">
                <i class="bi bi-receipt"></i> Generate acknowledgement receipt
            </label>
        </div>

        <!-- Savings plan / goal -->
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="has_plan" id="hp"
                   onchange="document.getElementById('planSection').style.display=this.checked?'block':'none'">
            <label class="form-check-label" for="hp">
                <i class="bi bi-bullseye"></i> This money has a savings plan / goal
            </label>
        </div>

        <div id="planSection" style="display:none;background:var(--primary-50);border-radius:14px;padding:14px;margin-bottom:14px;border:1.5px solid var(--primary-100);">
            <div style="font-size:.85rem;font-weight:700;color:var(--primary);margin-bottom:12px;">
                <i class="bi bi-bullseye"></i> New Savings Plan
            </div>
            <div class="form-group">
                <label class="form-label">Plan Title *</label>
                <input class="form-control" name="goal_title" placeholder="e.g. Buy new router, Office rent fund">
            </div>
            <div class="row g-2">
                <div class="col-6 form-group">
                    <label class="form-label">Target Amount *</label>
                    <input class="form-control" name="goal_target" type="number" step="0.01" min="0.01" placeholder="0">
                </div>
                <div class="col-6 form-group">
                    <label class="form-label">Deadline</label>
                    <input class="form-control" name="goal_deadline" type="date">
                </div>
            </div>
            <div class="row g-2">
                <div class="col-6 form-group">
                    <label class="form-label">Category</label>
                    <input class="form-control" name="goal_category" value="business" placeholder="business">
                </div>
                <div class="col-6 form-group">
                    <label class="form-label">Description</label>
                    <input class="form-control" name="goal_desc" placeholder="Optional notes">
                </div>
            </div>
            <div style="font-size:.73rem;color:var(--primary);margin-top:2px;">
                <i class="bi bi-info-circle"></i>
                The income amount will be recorded as the plan's starting contribution.
            </div>
        </div>

        <button class="btn btn-primary btn-lg btn-block"><i class="bi bi-check2"></i> Save Income</button>
        <a href="index.php" class="btn btn-light btn-block mt-2">Cancel</a>
    </form>
</div>

<div style="height:30px"></div>
<?php $active='home'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

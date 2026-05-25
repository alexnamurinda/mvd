<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT e.*, c.name cat_name, b.name biz_name, u.full_name user_name, u.business_name as user_biz_name, u.receipt_footer
    FROM expenses e
    LEFT JOIN expense_categories c ON c.id=e.category_id
    LEFT JOIN businesses b ON b.id=e.business_id
    JOIN users u ON u.id=e.user_id
    WHERE e.id=? AND e.user_id=?");
$stmt->execute([$id, $userId]);
$exp = $stmt->fetch();
if (!$exp) { flash('error','Expense not found'); header('Location: index.php'); exit; }

// If no receipt_no yet, generate one now (lazy)
if (empty($exp['receipt_no'])) {
    $rn = generateReceiptNo('EXP');
    $stmt = $pdo->prepare("UPDATE expenses SET receipt_no=? WHERE id=? AND user_id=?");
    $stmt->execute([$rn, $id, $userId]);
    $exp['receipt_no'] = $rn;
}

$bizName = $exp['biz_name'] ?: ($exp['user_biz_name'] ?: $exp['user_name']);
$footerTxt = $exp['receipt_footer'] ?: 'Thank you!';

$pageTitle = 'Expense Receipt';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Receipt'; $topbarBack = 'index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page no-print">
    <div class="d-flex gap-2 mb-3">
        <button onclick="window.print()" class="btn btn-primary btn-block"><i class="bi bi-printer"></i> Print Receipt</button>
    </div>
</div>

<div class="receipt-paper">
    <div style="text-align:center;">
        <div style="font-weight:800;font-size:1rem;letter-spacing:.5px;"><?= e(strtoupper($bizName)) ?></div>
        <div style="font-size:.78rem;">EXPENSE / VOUCHER</div>
    </div>

    <div class="dashed"></div>

    <div class="row-line"><span>Receipt #</span><strong><?= e($exp['receipt_no']) ?></strong></div>
    <div class="row-line"><span>Date</span><span><?= date('D, M j Y', strtotime($exp['expense_date'])) ?></span></div>
    <div class="row-line"><span>Logged</span><span><?= date('M j, H:i', strtotime($exp['created_at'])) ?></span></div>

    <div class="dashed"></div>
    <div style="margin:8px 0;">
        <div style="font-weight:700;"><?= e($exp['title']) ?></div>
        <?php if ($exp['cat_name']): ?><div style="font-size:.78rem;color:#666;">Category: <?= e($exp['cat_name']) ?></div><?php endif; ?>
        <?php if ($exp['vendor']): ?><div style="font-size:.78rem;color:#666;">Paid to: <?= e($exp['vendor']) ?></div><?php endif; ?>
    </div>
    <div class="dashed"></div>

    <div class="row-line"><span>Method</span><span><?= e(ucfirst(str_replace('_',' ',$exp['method']))) ?></span></div>
    <div class="row-line" style="font-size:1.15rem;font-weight:800;margin-top:6px;">
        <span>TOTAL</span><span><?= money($exp['amount']) ?></span>
    </div>

    <?php if ($exp['notes']): ?>
        <div class="dashed"></div>
        <div style="font-size:.78rem;"><strong>Notes:</strong> <?= e($exp['notes']) ?></div>
    <?php endif; ?>

    <div class="dashed"></div>
    <div style="text-align:center;font-size:.78rem;">
        <div>Authorized by</div>
        <div style="margin-top:18px;border-top:1px dashed #999;width:60%;margin-left:auto;margin-right:auto;padding-top:4px;">
            <?= e($exp['user_name']) ?>
        </div>
        <div style="margin-top:14px;"><?= e($footerTxt) ?></div>
    </div>
</div>

<div style="height:30px"></div>
<?php $active='add'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

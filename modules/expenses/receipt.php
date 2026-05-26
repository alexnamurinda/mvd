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

// Lazy receipt number generation
if (empty($exp['receipt_no'])) {
    $rn = generateReceiptNo('EXP');
    $stmt = $pdo->prepare("UPDATE expenses SET receipt_no=? WHERE id=? AND user_id=?");
    $stmt->execute([$rn, $id, $userId]);
    $exp['receipt_no'] = $rn;
}

$bizName    = $exp['biz_name'] ?: ($exp['user_biz_name'] ?: $exp['user_name']);
$footerTxt  = $exp['receipt_footer'] ?: 'Thank you!';
$vendorPhone = $exp['vendor_phone'] ?? '';

// Build WhatsApp message (computed server-side for clean JSON encoding)
$waMessage = implode("\n", array_filter([
    strtoupper($bizName),
    'EXPENSE RECEIPT',
    '---',
    'Receipt: ' . $exp['receipt_no'],
    'Date: '    . date('D, M j Y', strtotime($exp['expense_date'])),
    '---',
    $exp['title'],
    $exp['cat_name'] ? 'Category: ' . $exp['cat_name'] : '',
    $exp['vendor']   ? 'Vendor: '   . $exp['vendor']   : '',
    '---',
    'Method: ' . ucfirst(str_replace('_', ' ', $exp['method'])),
    'TOTAL: '  . money($exp['amount']),
    $exp['notes'] ? 'Notes: ' . $exp['notes'] : '',
    '---',
    $footerTxt,
]));

$pageTitle = 'Expense Receipt';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Receipt'; $topbarBack = 'index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page no-print">
    <div class="d-flex gap-2 mb-3">
        <button onclick="window.print()" class="btn btn-primary flex-fill"><i class="bi bi-printer"></i> Print Receipt</button>
        <button type="button" class="btn btn-success flex-fill" data-bs-toggle="modal" data-bs-target="#waModal">
            <i class="bi bi-whatsapp"></i> Share
        </button>
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
        <?php if ($exp['cat_name']): ?>
            <div style="font-size:.78rem;color:#666;">Category: <?= e($exp['cat_name']) ?></div>
        <?php endif; ?>
        <?php if ($exp['vendor']): ?>
            <div style="font-size:.78rem;color:#666;">Paid to: <?= e($exp['vendor']) ?></div>
        <?php endif; ?>
        <?php if ($vendorPhone): ?>
            <div style="font-size:.78rem;color:#666;"><i class="bi bi-telephone" style="font-size:.7rem;"></i> <?= e($vendorPhone) ?></div>
        <?php endif; ?>
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

<!-- WhatsApp Share Modal -->
<div class="modal fade" id="waModal" tabindex="-1" aria-labelledby="waModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px;overflow:hidden;">
            <div class="modal-header border-0 pb-1" style="background:#25D366;color:#fff;">
                <h6 class="modal-title fw-bold" id="waModalLabel"><i class="bi bi-whatsapp"></i> Share Receipt via WhatsApp</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <p style="font-size:.82rem;color:var(--text-muted);">The receipt details will be sent as a WhatsApp message. Enter the recipient's number below:</p>
                <div class="form-group">
                    <label class="form-label">WhatsApp Number</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-whatsapp" style="color:#25D366;"></i></span>
                        <input class="form-control" id="waNumber" type="tel"
                               value="<?= e($vendorPhone) ?>"
                               placeholder="+256 700 000 000">
                    </div>
                    <?php if ($vendorPhone): ?>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:4px;">Pre-filled from vendor contact: <?= e($exp['vendor']) ?></div>
                    <?php endif; ?>
                </div>
                <div style="background:var(--bg);border-radius:10px;padding:10px;font-size:.75rem;color:var(--text-muted);margin-top:4px;">
                    <strong>Message preview:</strong><br>
                    <?= nl2br(e(substr($waMessage, 0, 180))) ?>…
                </div>
            </div>
            <div class="modal-footer border-0 pt-1">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="sendWhatsApp()">
                    <i class="bi bi-send"></i> Open WhatsApp
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var waMsg = <?= json_encode($waMessage) ?>;
function sendWhatsApp() {
    var raw   = document.getElementById('waNumber').value.trim();
    var phone = raw.replace(/[\s\-\(\)\+]/g, '');
    if (!phone) { alert('Please enter a WhatsApp number'); return; }
    // Convert Uganda local 0xx to international 256xx
    if (phone.charAt(0) === '0' && phone.length <= 10) phone = '256' + phone.slice(1);
    window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(waMsg), '_blank');
}
</script>

<div style="height:30px"></div>
<?php $active='add'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

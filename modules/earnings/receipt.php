<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT i.*, s.name src_name, s.color src_color,
        b.name biz_name, u.full_name user_name, u.business_name user_biz_name, u.receipt_footer
    FROM incomes i
    LEFT JOIN income_sources s ON s.id = i.source_id
    LEFT JOIN businesses b     ON b.id = i.business_id
    JOIN users u               ON u.id = i.user_id
    WHERE i.id = ? AND i.user_id = ?");
$stmt->execute([$id, $userId]);
$inc = $stmt->fetch();
if (!$inc) { flash('error', 'Income record not found'); header('Location: index.php'); exit; }

// Lazy receipt number generation
if (empty($inc['receipt_no'])) {
    $rn = generateReceiptNo('INC');
    $pdo->prepare("UPDATE incomes SET receipt_no=? WHERE id=? AND user_id=?")
        ->execute([$rn, $id, $userId]);
    $inc['receipt_no'] = $rn;
}

$bizName    = $inc['biz_name'] ?: ($inc['user_biz_name'] ?: $inc['user_name']);
$footerTxt  = $inc['receipt_footer'] ?: 'Thank you!';
$senderPhone = $inc['sender_phone'] ?? '';

// Attachment info
$attachment     = $inc['attachment'] ?? '';
$attachmentFull = $attachment ? BASE_URL . '/' . $attachment : '';
$attachExt      = $attachment ? strtolower(pathinfo($attachment, PATHINFO_EXTENSION)) : '';
$isImage        = in_array($attachExt, ['jpg','jpeg','png','gif']);
$isPdf          = $attachExt === 'pdf';

// WhatsApp message
$waLines = [
    strtoupper($bizName),
    'INCOME RECEIPT',
    str_repeat('-', 20),
    'Receipt: '  . $inc['receipt_no'],
    'Date: '     . date('D, M j Y', strtotime($inc['income_date'])),
    str_repeat('-', 20),
    $inc['title'],
    $inc['src_name'] ? 'Source: '  . $inc['src_name'] : '',
    str_repeat('-', 20),
    'Method: '   . ucfirst(str_replace('_', ' ', $inc['method'])),
    'AMOUNT: '   . money($inc['amount']),
];
if ($inc['sender_name'])    $waLines[] = 'From: '    . $inc['sender_name'];
if ($inc['sender_phone'])   $waLines[] = 'Contact: ' . $inc['sender_phone'];
if ($inc['sender_address']) $waLines[] = 'Address: ' . $inc['sender_address'];
if ($inc['notes'])          $waLines[] = 'Notes: '   . $inc['notes'];
$waLines[] = str_repeat('-', 20);
$waLines[] = $footerTxt;
$waMessage = implode("\n", array_filter($waLines));

$pageTitle = 'Income Receipt';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Receipt'; $topbarBack = 'index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<!-- Action buttons (hidden on print) -->
<div class="px-page mt-page no-print">
    <div class="d-flex gap-2 mb-3">
        <button onclick="window.print()" class="btn btn-primary flex-fill">
            <i class="bi bi-printer"></i> Print / Save PDF
        </button>
        <button type="button" class="btn btn-success flex-fill" data-bs-toggle="modal" data-bs-target="#waModal">
            <i class="bi bi-whatsapp"></i> Share
        </button>
    </div>
</div>

<!-- Receipt paper -->
<div class="receipt-paper">
    <div style="text-align:center;">
        <div style="font-weight:800;font-size:1rem;letter-spacing:.5px;"><?= e(strtoupper($bizName)) ?></div>
        <div style="font-size:.78rem;">INCOME RECEIPT / ACKNOWLEDGEMENT</div>
    </div>

    <div class="dashed"></div>

    <div class="row-line"><span>Receipt #</span><strong><?= e($inc['receipt_no']) ?></strong></div>
    <div class="row-line"><span>Date</span><span><?= date('D, M j Y', strtotime($inc['income_date'])) ?></span></div>
    <div class="row-line"><span>Logged</span><span><?= date('M j, H:i', strtotime($inc['created_at'])) ?></span></div>

    <div class="dashed"></div>

    <div style="margin:8px 0;">
        <div style="font-weight:700;"><?= e($inc['title']) ?></div>
        <?php if ($inc['src_name']): ?>
            <div style="font-size:.78rem;color:#666;">Source: <?= e($inc['src_name']) ?></div>
        <?php endif; ?>
    </div>

    <!-- Sender details (only if at least one is filled) -->
    <?php if ($inc['sender_name'] || $inc['sender_phone'] || $inc['sender_address']): ?>
    <div class="dashed"></div>
    <div style="margin:6px 0;">
        <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#888;margin-bottom:3px;">Received From</div>
        <?php if ($inc['sender_name']):    ?><div style="font-weight:600;font-size:.82rem;"><?= e($inc['sender_name']) ?></div><?php endif; ?>
        <?php if ($inc['sender_phone']):   ?><div style="font-size:.78rem;color:#555;"><i class="bi bi-telephone" style="font-size:.7rem;"></i> <?= e($inc['sender_phone']) ?></div><?php endif; ?>
        <?php if ($inc['sender_address']): ?><div style="font-size:.78rem;color:#555;"><i class="bi bi-geo-alt" style="font-size:.7rem;"></i> <?= e($inc['sender_address']) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="dashed"></div>

    <div class="row-line"><span>Method</span><span><?= e(ucfirst(str_replace('_', ' ', $inc['method']))) ?></span></div>
    <div class="row-line" style="font-size:1.15rem;font-weight:800;margin-top:6px;">
        <span>AMOUNT RECEIVED</span><span style="color:#166534;"><?= money($inc['amount']) ?></span>
    </div>

    <?php if ($inc['notes']): ?>
        <div class="dashed"></div>
        <div style="font-size:.78rem;"><strong>Notes:</strong> <?= e($inc['notes']) ?></div>
    <?php endif; ?>

    <div class="dashed"></div>
    <div style="text-align:center;font-size:.78rem;">
        <div>Received by</div>
        <div style="margin-top:18px;border-top:1px dashed #999;width:60%;margin-left:auto;margin-right:auto;padding-top:4px;">
            <?= e($inc['user_name']) ?>
        </div>
        <div style="margin-top:14px;"><?= e($footerTxt) ?></div>
    </div>
</div>

<!-- Attachment viewer (non-printable) -->
<?php if ($attachmentFull): ?>
<div class="px-page no-print" style="margin-top:18px;margin-bottom:6px;">
    <div class="list-card" style="padding:14px;">
        <div style="font-size:.82rem;font-weight:700;margin-bottom:10px;color:var(--text-muted);">
            <i class="bi bi-paperclip"></i> Attached Proof
        </div>
        <?php if ($isImage): ?>
            <img src="<?= e($attachmentFull) ?>" alt="Attachment"
                 style="width:100%;max-height:360px;object-fit:contain;border-radius:10px;background:#f0f0f0;">
        <?php elseif ($isPdf): ?>
            <iframe src="<?= e($attachmentFull) ?>" style="width:100%;height:360px;border:none;border-radius:10px;"></iframe>
        <?php endif; ?>
        <a href="<?= e($attachmentFull) ?>" target="_blank" class="btn btn-light btn-sm btn-block mt-2">
            <i class="bi bi-box-arrow-up-right"></i> Open in New Tab
        </a>
    </div>
</div>
<?php endif; ?>

<!-- WhatsApp share modal -->
<div class="modal fade" id="waModal" tabindex="-1" aria-labelledby="waModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px;overflow:hidden;">
            <div class="modal-header border-0 pb-1" style="background:#25D366;color:#fff;">
                <h6 class="modal-title fw-bold" id="waModalLabel">
                    <i class="bi bi-whatsapp"></i> Share Receipt via WhatsApp
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <p style="font-size:.82rem;color:var(--text-muted);">
                    Send the receipt details to the sender or any other number:
                </p>
                <div class="form-group">
                    <label class="form-label">WhatsApp Number</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-whatsapp" style="color:#25D366;"></i></span>
                        <input class="form-control" id="waNumber" type="tel"
                               value="<?= e($senderPhone) ?>"
                               placeholder="+256 700 000 000">
                    </div>
                    <?php if ($senderPhone): ?>
                        <div style="font-size:.73rem;color:var(--text-muted);margin-top:4px;">
                            Pre-filled from sender contact<?= $inc['sender_name'] ? ': ' . e($inc['sender_name']) : '' ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="background:var(--bg);border-radius:10px;padding:10px;font-size:.73rem;color:var(--text-muted);white-space:pre-wrap;max-height:120px;overflow:hidden;"><?= e(substr($waMessage, 0, 220)) ?>…</div>
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
    if (phone.charAt(0) === '0' && phone.length <= 10) phone = '256' + phone.slice(1);
    window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(waMsg), '_blank');
}
</script>

<div style="height:30px"></div>
<?php $active='home'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

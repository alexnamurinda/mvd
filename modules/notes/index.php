<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'update') {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $color   = trim($_POST['color'] ?? '#FEF3C7');
        if ($title || $content) {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, content, color) VALUES (?,?,?,?)");
                $stmt->execute([$userId, $title, $content, $color]);
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE notes SET title=?, content=?, color=? WHERE id=? AND user_id=?");
                $stmt->execute([$title, $content, $color, $id, $userId]);
            }
            flash('success', 'Note saved');
        }
    } elseif ($action === 'pin') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE notes SET is_pinned = NOT is_pinned WHERE id=? AND user_id=?");
        $stmt->execute([$id, $userId]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id=? AND user_id=?");
        $stmt->execute([$id, $userId]);
        flash('success','Note deleted');
    }
    header('Location: index.php'); exit;
}

$stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id=? ORDER BY is_pinned DESC, updated_at DESC, id DESC");
$stmt->execute([$userId]);
$notes = $stmt->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE id=? AND user_id=?");
    $stmt->execute([(int)$_GET['edit'], $userId]);
    $edit = $stmt->fetch();
}

$noteColors = ['#FEF3C7','#FECACA','#BFDBFE','#BBF7D0','#DDD6FE','#FBCFE8','#FED7AA','#E5E7EB'];

$pageTitle = 'Notes';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Notes'; $topbarBack = BASE_URL.'/dashboard.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

    <div class="section-title"><h3><?= $edit?'Edit Note':'New Note' ?></h3></div>
    <div class="list-card" style="padding:14px;">
        <form method="post">
            <input type="hidden" name="action" value="<?= $edit?'update':'add' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div class="form-group">
                <input class="form-control" name="title" placeholder="Title (optional)" value="<?= e($edit['title']??'') ?>">
            </div>
            <div class="form-group">
                <textarea class="form-control" name="content" rows="4" placeholder="Write your note..."><?= e($edit['content']??'') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Color</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php $defCol = $edit['color'] ?? '#FEF3C7'; foreach ($noteColors as $col): ?>
                        <label style="cursor:pointer;"><input type="radio" name="color" value="<?= $col ?>" <?= $defCol===$col?'checked':'' ?> style="display:none;" class="note-color-radio"><span style="display:inline-block;width:28px;height:28px;border-radius:8px;background:<?= $col ?>;border:3px solid #fff;box-shadow:0 0 0 2px <?= $col ?>;"></span></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="btn btn-primary btn-block"><?= $edit?'Update':'Save' ?> Note</button>
            <?php if ($edit): ?><a href="index.php" class="btn btn-light btn-block mt-2">Cancel</a><?php endif; ?>
        </form>
    </div>

    <div class="section-title mt-4"><h3>All Notes (<?= count($notes) ?>)</h3></div>
    <div class="row g-2">
        <?php if (empty($notes)): ?>
            <div class="col-12"><div class="list-card"><div class="empty-state"><div class="icon-circle"><i class="bi bi-journal-text"></i></div>No notes yet.</div></div></div>
        <?php else: foreach ($notes as $n): ?>
            <div class="col-6">
                <div style="background:<?= e($n['color']) ?>;border-radius:14px;padding:12px;min-height:120px;position:relative;box-shadow:var(--shadow-sm);">
                    <?php if ($n['is_pinned']): ?><div style="position:absolute;top:8px;right:8px;color:var(--danger);"><i class="bi bi-pin-fill"></i></div><?php endif; ?>
                    <?php if ($n['title']): ?><div style="font-weight:700;font-size:.95rem;margin-bottom:4px;padding-right:20px;"><?= e($n['title']) ?></div><?php endif; ?>
                    <div style="font-size:.82rem;white-space:pre-wrap;color:#1F2937;"><?= e($n['content']) ?></div>
                    <div class="d-flex justify-content-between mt-2" style="border-top:1px dashed rgba(0,0,0,.1);padding-top:6px;">
                        <span style="font-size:.7rem;color:#374151;"><?= timeAgo($n['updated_at']) ?></span>
                        <div class="d-flex gap-1">
                            <form method="post" style="display:inline;"><input type="hidden" name="action" value="pin"><input type="hidden" name="id" value="<?= $n['id'] ?>"><button class="btn btn-light btn-sm" style="padding:2px 6px;font-size:.7rem;"><i class="bi bi-pin"></i></button></form>
                            <a href="?edit=<?= $n['id'] ?>" class="btn btn-light btn-sm" style="padding:2px 6px;font-size:.7rem;"><i class="bi bi-pencil"></i></a>
                            <form method="post" data-confirm="Delete note?" style="display:inline;"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $n['id'] ?>"><button class="btn btn-light btn-sm" style="padding:2px 6px;font-size:.7rem;color:var(--danger)"><i class="bi bi-trash"></i></button></form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.note-color-radio').forEach(r => {
    const update = () => document.querySelectorAll('.note-color-radio').forEach(x => x.nextElementSibling.style.borderColor = x.checked ? '#0F766E' : '#fff');
    r.addEventListener('change', update); update();
});
</script>

<div style="height:30px"></div>
<?php $active='home'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

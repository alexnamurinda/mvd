<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'update') {
        $title = trim($_POST['title'] ?? '');
        $target = (float)($_POST['target_amount'] ?? 0);
        $current = (float)($_POST['current_amount'] ?? 0);
        $deadline = $_POST['deadline'] ?? null;
        $desc = trim($_POST['description'] ?? '');
        $cat  = trim($_POST['category'] ?? '');
        if ($title && $target > 0) {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO goals (user_id, title, description, category, target_amount, current_amount, deadline, status) VALUES (?,?,?,?,?,?,?, 'active')");
                $stmt->execute([$userId, $title, $desc, $cat, $target, $current, $deadline ?: null]);
                logActivity($pdo, $userId, 'Created goal', $title, 'bi-bullseye');
                flash('success', 'Goal created');
            } else {
                $gid = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE goals SET title=?, description=?, category=?, target_amount=?, current_amount=?, deadline=? WHERE id=? AND user_id=?");
                $stmt->execute([$title, $desc, $cat, $target, $current, $deadline ?: null, $gid, $userId]);
                flash('success', 'Goal updated');
            }
        }
    } elseif ($action === 'contribute') {
        $gid = (int)($_POST['id'] ?? 0);
        $amt = (float)($_POST['amount'] ?? 0);
        if ($amt > 0) {
            $stmt = $pdo->prepare("UPDATE goals SET current_amount = current_amount + ?, status = IF(current_amount + ? >= target_amount, 'completed', status), completed_at = IF(current_amount + ? >= target_amount AND completed_at IS NULL, NOW(), completed_at) WHERE id=? AND user_id=?");
            $stmt->execute([$amt, $amt, $amt, $gid, $userId]);
            flash('success', 'Contribution added: '.money($amt));
        }
    } elseif ($action === 'delete') {
        $gid = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM goals WHERE id=? AND user_id=?");
        $stmt->execute([$gid, $userId]);
        flash('success', 'Goal deleted');
    }
    header('Location: index.php'); exit;
}

$stmt = $pdo->prepare("SELECT * FROM goals WHERE user_id=? ORDER BY status='active' DESC, deadline IS NULL, deadline ASC, id DESC");
$stmt->execute([$userId]);
$goals = $stmt->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM goals WHERE id=? AND user_id=?");
    $stmt->execute([(int)$_GET['edit'], $userId]);
    $edit = $stmt->fetch();
}

$pageTitle = 'Goals';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Future Goals'; $topbarBack = BASE_URL.'/dashboard.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

    <div class="section-title"><h3><?= $edit?'Edit Goal':'New Goal' ?></h3></div>
    <div class="list-card" style="padding:14px;">
        <form method="post">
            <input type="hidden" name="action" value="<?= $edit?'update':'add' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div class="form-group">
                <label class="form-label">Goal Title *</label>
                <input class="form-control" name="title" placeholder="e.g. New router, buy plot of land" value="<?= e($edit['title']??'') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="2"><?= e($edit['description']??'') ?></textarea>
            </div>
            <div class="row g-2">
                <div class="col-6 form-group">
                    <label class="form-label">Target Amount *</label>
                    <input class="form-control" name="target_amount" type="number" step="0.01" min="0.01" value="<?= e($edit['target_amount']??'') ?>" required>
                </div>
                <div class="col-6 form-group">
                    <label class="form-label">Already Saved</label>
                    <input class="form-control" name="current_amount" type="number" step="0.01" min="0" value="<?= e($edit['current_amount']??'0') ?>">
                </div>
            </div>
            <div class="row g-2">
                <div class="col-7 form-group">
                    <label class="form-label">Deadline</label>
                    <input class="form-control" name="deadline" type="date" value="<?= e($edit['deadline']??'') ?>">
                </div>
                <div class="col-5 form-group">
                    <label class="form-label">Category</label>
                    <input class="form-control" name="category" placeholder="business" value="<?= e($edit['category']??'') ?>">
                </div>
            </div>
            <button class="btn btn-primary btn-block"><?= $edit?'Update':'Create' ?> Goal</button>
            <?php if ($edit): ?><a href="index.php" class="btn btn-light btn-block mt-2">Cancel</a><?php endif; ?>
        </form>
    </div>

    <div class="section-title mt-4"><h3>Active Goals (<?= count(array_filter($goals, fn($g)=>$g['status']==='active')) ?>)</h3></div>
    <?php if (empty($goals)): ?>
        <div class="list-card"><div class="empty-state"><div class="icon-circle"><i class="bi bi-bullseye"></i></div>Set your first goal above. Big or small.</div></div>
    <?php else: foreach ($goals as $g):
        $pct = $g['target_amount']>0 ? min(100, ($g['current_amount']/$g['target_amount'])*100) : 0;
        $done = $g['status']==='completed' || $pct >= 100;
        $daysLeft = $g['deadline'] ? (int)round((strtotime($g['deadline']) - time())/86400) : null;
    ?>
        <div class="list-card mb-3" style="padding:14px;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-weight:700;font-size:1.05rem;"><?= e($g['title']) ?> <?php if ($done): ?><span class="pill pill-done">done</span><?php endif; ?></div>
                    <?php if ($g['category']): ?><div class="text-soft" style="font-size:.75rem;"><?= e($g['category']) ?></div><?php endif; ?>
                </div>
                <div class="d-flex gap-1">
                    <a href="?edit=<?= $g['id'] ?>" class="btn btn-light btn-sm"><i class="bi bi-pencil"></i></a>
                    <form method="post" data-confirm="Delete this goal?"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="btn btn-light btn-sm" style="color:var(--danger)"><i class="bi bi-trash"></i></button></form>
                </div>
            </div>
            <?php if ($g['description']): ?><div class="text-muted mt-1" style="font-size:.85rem;"><?= e($g['description']) ?></div><?php endif; ?>
            <div class="d-flex justify-content-between mt-3" style="font-size:.85rem;">
                <span><?= money($g['current_amount']) ?> / <strong><?= money($g['target_amount']) ?></strong></span>
                <span style="font-weight:700;color:var(--primary);"><?= number_format($pct,0) ?>%</span>
            </div>
            <div style="height:10px;background:var(--border-light);border-radius:99px;overflow:hidden;margin-top:6px;">
                <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--primary),var(--primary-light));transition:width .3s;"></div>
            </div>
            <?php if ($daysLeft !== null): ?>
                <div class="mt-2" style="font-size:.78rem;color:<?= $daysLeft < 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;">
                    <i class="bi bi-calendar"></i>
                    <?= $daysLeft < 0 ? abs($daysLeft).' day(s) overdue' : ($daysLeft.' day(s) left — '.date('D M j', strtotime($g['deadline']))) ?>
                </div>
            <?php endif; ?>
            <?php if (!$done): ?>
            <form method="post" class="d-flex gap-1 mt-3">
                <input type="hidden" name="action" value="contribute">
                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                <input class="form-control form-control-sm" name="amount" type="number" step="0.01" min="0.01" placeholder="Add amount...">
                <button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Save</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>

<div style="height:30px"></div>
<?php $active='plan'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

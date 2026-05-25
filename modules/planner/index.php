<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();

// Quick action handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($act === 'toggle') {
        $stmt = $pdo->prepare("UPDATE tasks SET status = IF(status='done','pending','done'), completed_at = IF(status='done', NULL, NOW()) WHERE id=? AND user_id=?");
        $stmt->execute([$id, $userId]);
    } elseif ($act === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id=? AND user_id=?");
        $stmt->execute([$id, $userId]);
    } elseif ($act === 'snooze') {
        $stmt = $pdo->prepare("UPDATE tasks SET due_date = DATE_ADD(COALESCE(due_date,CURDATE()), INTERVAL 1 DAY) WHERE id=? AND user_id=?");
        $stmt->execute([$id, $userId]);
    }
    header('Location: index.php'); exit;
}

$today = date('Y-m-d');

$tabs = [
    'overdue'  => "due_date IS NOT NULL AND due_date < CURDATE() AND status='pending'",
    'today'    => "(due_date = CURDATE() OR (due_date IS NULL AND status='pending'))",
    'upcoming' => "due_date IS NOT NULL AND due_date > CURDATE() AND status='pending'",
    'done'     => "status='done'",
];
$tab = $_GET['tab'] ?? 'today';
if (!isset($tabs[$tab])) $tab = 'today';

$counts = [];
foreach ($tabs as $k=>$cond) {
    $counts[$k] = (int)sumQuery($pdo, "SELECT COUNT(*) FROM tasks WHERE user_id=? AND $cond", [$userId]);
}

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id=? AND " . $tabs[$tab] . " ORDER BY priority='urgent' DESC, priority='high' DESC, due_date ASC, due_time ASC, id DESC");
$stmt->execute([$userId]);
$tasks = $stmt->fetchAll();

$pageTitle = 'Planner';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Planner';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <div class="balance-card">
        <div class="d-flex justify-content-between">
            <div>
                <div class="label">Today's Focus</div>
                <div class="amount" style="font-size:1.6rem;"><?= $counts['today'] ?> <small style="font-size:.9rem;font-weight:500;">task<?= $counts['today']===1?'':'s' ?></small></div>
            </div>
            <div class="text-end">
                <div class="label">Done</div>
                <div style="font-weight:700;color:var(--success);"><?= $counts['done'] ?></div>
            </div>
        </div>
    </div>

    <div class="action-grid" style="grid-template-columns:repeat(4,1fr);">
        <a href="add_task.php" class="action-tile"><div class="tile-ico bg-primary-soft"><i class="bi bi-plus-lg"></i></div><div class="tile-label">New Task</div></a>
        <a href="<?= BASE_URL ?>/modules/goals/index.php" class="action-tile"><div class="tile-ico bg-amber-soft"><i class="bi bi-bullseye"></i></div><div class="tile-label">Goals</div></a>
        <a href="<?= BASE_URL ?>/modules/notes/index.php" class="action-tile"><div class="tile-ico bg-purple-soft"><i class="bi bi-journal-text"></i></div><div class="tile-label">Notes</div></a>
        <a href="?tab=upcoming" class="action-tile"><div class="tile-ico bg-blue-soft"><i class="bi bi-calendar-week"></i></div><div class="tile-label">Upcoming</div></a>
    </div>

    <div class="tab-bar">
        <a href="?tab=overdue"  class="<?= $tab==='overdue'?'active':'' ?>">Overdue <?= $counts['overdue']?'·'.$counts['overdue']:'' ?></a>
        <a href="?tab=today"    class="<?= $tab==='today'?'active':'' ?>">Today <?= $counts['today']?'·'.$counts['today']:'' ?></a>
        <a href="?tab=upcoming" class="<?= $tab==='upcoming'?'active':'' ?>">Upcoming <?= $counts['upcoming']?'·'.$counts['upcoming']:'' ?></a>
        <a href="?tab=done"     class="<?= $tab==='done'?'active':'' ?>">Done</a>
    </div>

    <div class="list-card">
        <?php if (empty($tasks)): ?>
            <div class="empty-state">
                <div class="icon-circle"><i class="bi bi-check2-circle"></i></div>
                <?= $tab==='done' ? 'Nothing completed yet.' : 'Nothing here. Plan something!' ?>
                <a href="add_task.php" class="btn btn-primary btn-sm mt-3">+ New Task</a>
            </div>
        <?php else: foreach ($tasks as $t):
            $done = $t['status']==='done';
            $overdue = !$done && $t['due_date'] && $t['due_date'] < $today;
        ?>
            <div class="list-item" style="<?= $done?'opacity:.6;':'' ?>">
                <form method="post" style="display:inline-block;margin-right:6px;">
                    <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button class="btn btn-light btn-sm" style="border-radius:50%;padding:6px 9px;<?= $done?'background:var(--success);color:#fff;border-color:var(--success);':'' ?>"><i class="bi <?= $done?'bi-check-lg':'bi-circle' ?>"></i></button>
                </form>
                <div class="li-body">
                    <div class="li-title" style="<?= $done?'text-decoration:line-through;':'' ?>"><?= e($t['title']) ?></div>
                    <div class="li-sub">
                        <?= $t['due_date'] ? friendlyDate($t['due_date']) : 'No date' ?>
                        <?= $t['due_time'] ? ' · '.date('H:i', strtotime($t['due_time'])) : '' ?>
                        <?php if ($overdue): ?> · <span style="color:var(--danger);font-weight:700;">OVERDUE</span><?php endif; ?>
                    </div>
                </div>
                <div class="li-right">
                    <span class="pill pill-<?= e($t['priority']) ?>"><?= e($t['priority']) ?></span>
                    <div class="d-flex gap-1 mt-1">
                        <?php if (!$done): ?>
                        <form method="post"><input type="hidden" name="action" value="snooze"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="btn btn-light btn-sm" title="Snooze 1 day"><i class="bi bi-arrow-clockwise"></i></button></form>
                        <?php endif; ?>
                        <form method="post" data-confirm="Delete task?"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="btn btn-light btn-sm" style="color:var(--danger)"><i class="bi bi-trash"></i></button></form>
                    </div>
                </div>
            </div>
            <?php if (!empty($t['description'])): ?>
                <div style="padding:0 14px 12px 60px;color:var(--text-muted);font-size:.85rem;"><?= e($t['description']) ?></div>
            <?php endif; ?>
        <?php endforeach; endif; ?>
    </div>
</div>

<a href="add_task.php" class="fab" title="New task"><i class="bi bi-plus-lg"></i></a>
<div style="height:30px"></div>
<?php $active='plan'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

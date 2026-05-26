<?php
/**
 * Reusable top bar. Set these before including:
 *   $topbarTitle (string), $topbarBack (URL or '' to hide), $topbarGradient (bool)
 */
$topbarTitle    = $topbarTitle    ?? APP_NAME;
$topbarBack     = $topbarBack     ?? '';
$topbarGradient = $topbarGradient ?? false;
?>
<header class="app-header <?= $topbarGradient ? 'gradient' : '' ?>">
    <div class="d-flex align-items-center gap-2">
        <?php if ($topbarBack !== ''): ?>
            <a href="<?= e($topbarBack) ?>" class="icon-btn"><i class="bi bi-arrow-left"></i></a>
        <?php else: ?>
            <button class="icon-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#mainMenu">
                <i class="bi bi-list"></i>
            </button>
        <?php endif; ?>
        <div class="title"><?= e($topbarTitle) ?></div>
    </div>
    <div class="d-flex align-items-center gap-1">
        <a href="<?= BASE_URL ?>/modules/notes/index.php" class="icon-btn" title="Notes"><i class="bi bi-journal-text"></i></a>
        <a href="<?= BASE_URL ?>/modules/planner/index.php" class="icon-btn" title="Tasks &amp; Reminders">
            <i class="bi bi-bell"></i>
            <?php
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'pending' AND (due_date IS NULL OR due_date <= CURDATE())");
            $stmt->execute([uid()]);
            $pendingTasks   = (int)$stmt->fetchColumn();
            $recurringSoon  = getRecurringDueSoon($pdo, uid(), 7);
            $notifTotal     = $pendingTasks + $recurringSoon;
            if ($notifTotal > 0):
                $badge = $notifTotal > 99 ? '99+' : $notifTotal;
            ?>
                <span class="badge-count"><?= $badge ?></span>
            <?php endif; ?>
        </a>
    </div>
</header>

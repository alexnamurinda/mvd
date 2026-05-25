<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = $_POST['priority'] ?? 'medium';
    $due_date    = $_POST['due_date'] ?? null;
    $due_time    = $_POST['due_time'] ?? null;
    $reminder    = !empty($_POST['reminder_at']) ? $_POST['reminder_at'] : null;
    $category    = trim($_POST['category'] ?? '');

    if ($title === '') $errors[] = 'Title is required';
    if (!in_array($priority, ['low','medium','high','urgent'], true)) $priority = 'medium';

    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, priority, status, due_date, due_time, reminder_at, category) VALUES (?,?,?,?,'pending',?,?,?,?)");
        $stmt->execute([$userId, $title, $description, $priority, $due_date ?: null, $due_time ?: null, $reminder ?: null, $category ?: null]);
        logActivity($pdo, $userId, 'Added task', $title, 'bi-check2-square');
        flash('success', 'Task added');
        header('Location: index.php'); exit;
    }
}

$pageTitle = 'New Task';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'New Task'; $topbarBack = 'index.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <?php if ($errors): ?><div class="alert alert-danger"><?= e(implode('. ', $errors)) ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label class="form-label">What needs doing? *</label>
            <input class="form-control" name="title" placeholder="e.g. Visit Bugolobi customer" required>
        </div>
        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="3"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Priority</label>
            <select class="form-select" name="priority">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </select>
        </div>
        <div class="row g-2">
            <div class="col-6 form-group">
                <label class="form-label">Due Date</label>
                <input class="form-control" name="due_date" type="date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6 form-group">
                <label class="form-label">Due Time</label>
                <input class="form-control" name="due_time" type="time">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Reminder At (optional)</label>
            <input class="form-control" name="reminder_at" type="datetime-local">
        </div>
        <div class="form-group">
            <label class="form-label">Category / Tag</label>
            <input class="form-control" name="category" placeholder="e.g. WiFi, Office, Personal">
        </div>
        <button class="btn btn-primary btn-lg btn-block"><i class="bi bi-check2"></i> Add Task</button>
        <a href="index.php" class="btn btn-light btn-block mt-2">Cancel</a>
    </form>
</div>

<div style="height:30px"></div>
<?php $active='plan'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

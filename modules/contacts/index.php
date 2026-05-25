<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();
$userId = uid();
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'update') {
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name && $phone) {
            $data = [
                $name, $phone,
                trim($_POST['email'] ?? ''),
                trim($_POST['address'] ?? ''),
                trim($_POST['company'] ?? ''),
                $_POST['type'] ?? 'other',
                trim($_POST['notes'] ?? ''),
            ];
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO contacts (user_id, full_name, phone, email, address, company, type, notes) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute(array_merge([$userId], $data));
                flash('success','Contact added');
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE contacts SET full_name=?, phone=?, email=?, address=?, company=?, type=?, notes=? WHERE id=? AND user_id=?");
                $stmt->execute(array_merge($data, [$id, $userId]));
                flash('success','Contact updated');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE id=? AND user_id=?");
        $stmt->execute([$id, $userId]);
        flash('success','Contact removed');
    }
    header('Location: index.php' . (isset($_GET['type']) ? '?type='.urlencode($_GET['type']) : '')); exit;
}

$type = $_GET['type'] ?? 'all';
$sql = "SELECT * FROM contacts WHERE user_id=?";
$params = [$userId];
if ($type !== 'all') { $sql .= " AND type=?"; $params[] = $type; }
$sql .= " ORDER BY full_name ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$contacts = $stmt->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id=? AND user_id=?");
    $stmt->execute([(int)$_GET['edit'], $userId]);
    $edit = $stmt->fetch();
}

$types = ['customer'=>'Customer','supplier'=>'Supplier','partner'=>'Partner','staff'=>'Staff','other'=>'Other'];

$pageTitle = 'Contacts';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/offcanvas.php';
$topbarTitle = 'Contacts'; $topbarBack = BASE_URL.'/dashboard.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="px-page mt-page">
    <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

    <div class="section-title"><h3><?= $edit?'Edit':'New' ?> Contact</h3></div>
    <div class="list-card" style="padding:14px;">
        <form method="post">
            <input type="hidden" name="action" value="<?= $edit?'update':'add' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
            <div class="row g-2">
                <div class="col-7 form-group"><label class="form-label">Name *</label><input class="form-control" name="full_name" value="<?= e($edit['full_name']??'') ?>" required></div>
                <div class="col-5 form-group"><label class="form-label">Type</label>
                    <select class="form-select" name="type">
                        <?php foreach ($types as $k=>$v): ?><option value="<?= $k ?>" <?= ($edit['type']??'other')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-6 form-group"><label class="form-label">Phone *</label><input class="form-control" name="phone" value="<?= e($edit['phone']??'') ?>" required></div>
                <div class="col-6 form-group"><label class="form-label">Email</label><input class="form-control" name="email" type="email" value="<?= e($edit['email']??'') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Company</label><input class="form-control" name="company" value="<?= e($edit['company']??'') ?>"></div>
            <div class="form-group"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= e($edit['address']??'') ?>"></div>
            <div class="form-group"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($edit['notes']??'') ?></textarea></div>
            <button class="btn btn-primary btn-block"><?= $edit?'Update':'Add' ?> Contact</button>
            <?php if ($edit): ?><a href="index.php" class="btn btn-light btn-block mt-2">Cancel</a><?php endif; ?>
        </form>
    </div>

    <div class="tab-bar mt-3">
        <a href="?type=all" class="<?= $type==='all'?'active':'' ?>">All</a>
        <?php foreach ($types as $k=>$v): ?>
            <a href="?type=<?= $k ?>" class="<?= $type===$k?'active':'' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>

    <div class="search-bar"><i class="bi bi-search"></i><input type="text" placeholder="Search contacts…" data-search-target="#ctList"></div>

    <div class="list-card" id="ctList">
        <?php if (empty($contacts)): ?>
            <div class="empty-state"><div class="icon-circle"><i class="bi bi-people"></i></div>No contacts yet.</div>
        <?php else: foreach ($contacts as $c):
            $waPhone = preg_replace('/\D/', '', $c['phone']);
            if (strlen($waPhone)===10 && $waPhone[0]==='0') $waPhone = '256' . substr($waPhone,1);
        ?>
            <div class="list-item" data-search-row>
                <div class="li-ico" style="background:linear-gradient(135deg,#2563EB,#3B82F6);color:#fff;"><?= strtoupper(substr($c['full_name'],0,1)) ?></div>
                <div class="li-body">
                    <div class="li-title"><?= e($c['full_name']) ?> <span class="pill pill-pending" style="font-size:.65rem;"><?= e($c['type']) ?></span></div>
                    <div class="li-sub"><?= e($c['phone']) ?><?= $c['company']?' · '.e($c['company']):'' ?></div>
                </div>
                <div class="li-right">
                    <div class="d-flex gap-1">
                        <a href="tel:<?= e($c['phone']) ?>" class="btn btn-light btn-sm"><i class="bi bi-telephone"></i></a>
                        <a href="https://wa.me/<?= e($waPhone) ?>" target="_blank" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i></a>
                    </div>
                    <div class="d-flex gap-1 mt-1">
                        <a href="?edit=<?= $c['id'] ?>" class="btn btn-light btn-sm"><i class="bi bi-pencil"></i></a>
                        <form method="post" data-confirm="Delete contact?"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-light btn-sm" style="color:var(--danger)"><i class="bi bi-trash"></i></button></form>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div style="height:30px"></div>
<?php $active='home'; include __DIR__ . '/../../includes/bottomnav.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

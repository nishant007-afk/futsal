<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$nid = (int)($_GET['id'] ?? 0);
$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['delete_notification'])) {
        $stmt = $conn->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $nid, $uid);
        $stmt->execute();
        set_flash('success', 'Notification deleted.');
        redirect('pages/notifications.php');
    }
}

$stmt = $conn->prepare('SELECT * FROM notifications WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $nid, $uid);
$stmt->execute();
$n = $stmt->get_result()->fetch_assoc();

if (!$n) {
    set_flash('info', 'That notification could not be found.');
    redirect('pages/notifications.php');
}

if ((int)$n['is_read'] === 0) {
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $nid, $uid);
    $stmt->execute();
    $n['is_read'] = 1;
}

$nav = $conn->prepare('SELECT id, title, icon FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC');
$nav->bind_param('i', $uid);
$nav->execute();
$all = $nav->get_result()->fetch_all(MYSQLI_ASSOC);
$prevN = null;
$nextN = null;
foreach ($all as $i => $row) {
    if ((int)$row['id'] === $nid) {
        $nextN = $all[$i - 1] ?? null;
        $prevN = $all[$i + 1] ?? null;
        break;
    }
}

$page_title = $n['title'];
require __DIR__ . '/../includes/header.php';
?>

<div class="nd-wrap reveal">
    <nav class="breadcrumb">
        <a href="<?php echo base_url('pages/notifications.php'); ?>">Notifications</a> &nbsp;/&nbsp;
        <span><?php echo e($n['title']); ?></span>
    </nav>

    <?php if ($prevN || $nextN): ?>
        <div class="nd-nav">
            <?php if ($nextN): ?>
                <a href="<?php echo base_url('pages/notification_details.php?id=' . (int)$nextN['id']); ?>" class="nd-nav-btn" title="<?php echo e($nextN['title']); ?>">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Newer</span>
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <span class="nd-nav-count"><?php echo count($all); ?> total</span>
            <?php if ($prevN): ?>
                <a href="<?php echo base_url('pages/notification_details.php?id=' . (int)$prevN['id']); ?>" class="nd-nav-btn" title="<?php echo e($prevN['title']); ?>">
                    <span>Older</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="nd-card">
        <div class="nd-top">
            <div class="mbooking-thumb notif-thumb c-<?php echo notification_icon_color($n['icon']); ?>">
                <i class="fa-solid <?php echo e($n['icon']); ?>"></i>
            </div>
            <div>
                <span class="eyebrow">Notification</span>
                <h1><?php echo e($n['title']); ?></h1>
                <p class="muted" style="font-size:13px;"><i class="fa-regular fa-clock"></i> <?php echo e(date('M j, Y g:i A', strtotime($n['created_at']))); ?> &middot; <?php echo e(notification_time($n['created_at'])); ?></p>
            </div>
        </div>

        <?php if ($n['body'] !== ''): ?>
            <div class="nd-body">
                <p><?php echo e($n['body']); ?></p>
            </div>
        <?php endif; ?>

        <div class="nd-actions">
            <?php if ($n['link']): ?>
                <a href="<?php echo base_url($n['link']); ?>" class="btn btn-primary"><i class="fa-solid fa-arrow-right"></i> Go to related page</a>
            <?php endif; ?>
            <form method="post" action="" style="display:inline;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="delete_notification" value="1">
                <button type="submit" class="btn btn-danger" data-confirm="Delete this notification?"><i class="fa-solid fa-trash-can"></i> Delete</button>
            </form>
            <a href="<?php echo base_url('pages/notifications.php'); ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> All notifications</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

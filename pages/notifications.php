<?php
require_once __DIR__ . '/../config/db.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['mark_read'])) {
        mark_notifications_read((int)$_SESSION['user_id']);
        set_flash('success', 'All notifications marked as read.');
        redirect('pages/notifications.php');
    }
    if (isset($_POST['delete_all'])) {
        $stmt = $conn->prepare('DELETE FROM notifications WHERE user_id = ?');
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        set_flash('success', 'All notifications cleared.');
        redirect('pages/notifications.php');
    }
    if (isset($_POST['delete_notification'])) {
        $nid = (int)$_POST['delete_notification'];
        $stmt = $conn->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $nid, $_SESSION['user_id']);
        $stmt->execute();
        set_flash('success', 'Notification deleted.');
        redirect('pages/notifications.php');
    }
}

$filter = $_GET['filter'] ?? 'all';
$notifications = user_notifications((int)$_SESSION['user_id'], 100);
if ($filter === 'unread') {
    $notifications = array_values(array_filter($notifications, fn($n) => (int)$n['is_read'] === 0));
} elseif ($filter === 'read') {
    $notifications = array_values(array_filter($notifications, fn($n) => (int)$n['is_read'] === 1));
}
$unreadTotal = unread_notification_count((int)$_SESSION['user_id']);

$page_title = 'Notifications';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h2><i class="fa-solid fa-bell"></i> Notifications</h2>
    <div class="actions">
        <?php if ($notifications): ?>
            <form method="post" action="">
                <?php echo csrf_field(); ?>
                <button type="submit" name="mark_read" value="1" class="btn btn-outline btn-sm"><i class="fa-solid fa-check-double"></i> Mark all as read</button>
            </form>
            <form method="post" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="delete_all" value="1">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete all notifications?"><i class="fa-solid fa-trash-can"></i> Clear all</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<p class="muted" style="margin-bottom:14px;">Updates about your bookings, payments and cancellations.</p>

<div class="notif-tabs reveal">
    <a href="<?php echo base_url('pages/notifications.php'); ?>" class="notif-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
    <a href="<?php echo base_url('pages/notifications.php?filter=unread'); ?>" class="notif-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">Unread <?php echo $unreadTotal > 0 ? '<span class="notif-tab-count">' . $unreadTotal . '</span>' : ''; ?></a>
    <a href="<?php echo base_url('pages/notifications.php?filter=read'); ?>" class="notif-tab <?php echo $filter === 'read' ? 'active' : ''; ?>">Read</a>
</div>

<?php if (!$notifications): ?>
    <div class="empty reveal">
        <span class="big"><i class="fa-regular fa-bell"></i></span>
        <?php echo $filter === 'all' ? "You're all caught up." : 'No notifications in this view.'; ?>
        <p style="margin-top:8px;">When something happens with your bookings, updates land here.</p>
    </div>
<?php else: ?>
    <div class="mbookings reveal">
        <?php foreach ($notifications as $n): ?>
            <div class="mbooking <?php echo $n['is_read'] ? '' : 'notif-unread'; ?>">
                <div class="mbooking-thumb notif-thumb c-<?php echo notification_icon_color($n['icon']); ?>">
                    <i class="fa-solid <?php echo e($n['icon']); ?>"></i>
                </div>
                <div class="mbooking-main">
                    <div class="mbooking-head">
                        <h3><?php echo e($n['title']); ?></h3>
                        <span class="mbooking-status">
                            <?php if (!$n['is_read']): ?>
                                <span class="badge badge-confirmed">New</span>
                            <?php endif; ?>
                            <span class="muted" style="font-size:12px;"><i class="fa-regular fa-clock"></i> <?php echo e(date('M j, Y g:i A', strtotime($n['created_at']))); ?> &middot; <?php echo e(notification_time($n['created_at'])); ?></span>
                        </span>
                    </div>
                    <?php if ($n['body'] !== ''): ?>
                        <div class="mbooking-meta">
                            <span><i class="fa-solid fa-circle-info"></i> <?php echo e($n['body']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mbooking-side">
                    <a href="<?php echo base_url('pages/notification_details.php?id=' . (int)$n['id']); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-arrow-right"></i> View</a>
                    <form method="post" action="" style="display:inline;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="delete_notification" value="<?php echo (int)$n['id']; ?>">
                        <button type="submit" class="btn btn-outline btn-sm" data-confirm="Delete this notification?" title="Delete"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

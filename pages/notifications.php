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
$allNotifications = user_notifications((int)$_SESSION['user_id'], 100);
$notifications = $allNotifications;
if ($filter === 'unread') {
    $notifications = array_values(array_filter($notifications, fn($n) => (int)$n['is_read'] === 0));
} elseif ($filter === 'read') {
    $notifications = array_values(array_filter($notifications, fn($n) => (int)$n['is_read'] === 1));
}
$unreadTotal = unread_notification_count((int)$_SESSION['user_id']);

// Group by recency: Today / Yesterday / Earlier
$todayStart = strtotime(date('Y-m-d'));
$yesterdayStart = strtotime('-1 day', $todayStart);
$groups = ['today' => [], 'yesterday' => [], 'earlier' => []];
foreach ($notifications as $n) {
    $ts = strtotime((string)$n['created_at']);
    if ($ts >= $todayStart) {
        $groups['today'][] = $n;
    } elseif ($ts >= $yesterdayStart) {
        $groups['yesterday'][] = $n;
    } else {
        $groups['earlier'][] = $n;
    }
}

$bookingRefs = [];
$linkedIds = [];
foreach ($notifications as $n) {
    if (preg_match('/booking_details\.php\?id=(\d+)/', (string)$n['link'], $m)) {
        $linkedIds[(int)$m[1]] = true;
    }
}
if ($linkedIds) {
    $ids = array_map('intval', array_keys($linkedIds));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT id, booking_ref FROM bookings WHERE id IN ($ph)");
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $bookingRefs[(int)$row['id']] = $row['booking_ref'];
    }
}

$page_title = 'Notifications';
require __DIR__ . '/../includes/header.php';
?>

<div class="notif-page-head reveal">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>Notifications</h1>
    </div>
</div>
<div class="notif-actions reveal mb-18">
    <?php if ($allNotifications): ?>
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

<div class="notif-tabs reveal">
    <a href="<?php echo base_url('pages/notifications.php'); ?>" class="notif-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
    <a href="<?php echo base_url('pages/notifications.php?filter=unread'); ?>" class="notif-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">Unread <?php echo $unreadTotal > 0 ? '<span class="notif-tab-count">' . $unreadTotal . '</span>' : ''; ?></a>
    <a href="<?php echo base_url('pages/notifications.php?filter=read'); ?>" class="notif-tab <?php echo $filter === 'read' ? 'active' : ''; ?>">Read</a>
</div>

<?php if (!$notifications): ?>
    <?php empty_state('fa-regular fa-bell', $filter === 'all' ? "You're all caught up" : 'Nothing in this view', 'When something happens with your bookings, updates land here.', grounds_list_url(), 'Browse courts'); ?>
<?php else: ?>
    <div class="notif-list reveal">
        <?php foreach ($groups as $groupKey => $groupItems): ?>
            <?php if (!$groupItems) continue; ?>
            <div class="notif-group-head">
                <?php echo $groupKey === 'today' ? 'Today' : ($groupKey === 'yesterday' ? 'Yesterday' : 'Earlier'); ?>
            </div>
            <?php foreach ($groupItems as $n): ?>
                <div class="notif-item <?php echo $n['is_read'] ? '' : 'notif-unread'; ?>">
                    <div class="notif-thumb c-<?php echo notification_icon_color($n['icon']); ?>">
                        <i class="fa-solid <?php echo e($n['icon']); ?>"></i>
                    </div>
                    <div class="notif-body">
                        <h3><?php echo e($n['title']); ?>
                            <?php if (!$n['is_read']): ?>
                                <span class="notif-badge unread">New</span>
                            <?php endif; ?>
                        </h3>
                        <div class="notif-meta"><i class="fa-regular fa-clock"></i> <?php echo e(notification_time($n['created_at'])); ?></div>
                        <?php if (preg_match('/booking_details\.php\?id=(\d+)/', (string)$n['link'], $bm) && isset($bookingRefs[(int)$bm[1]])): ?>
                            <div class="notif-ref"><i class="fa-solid fa-receipt"></i> Booking <?php echo e($bookingRefs[(int)$bm[1]]); ?></div>
                        <?php endif; ?>
                        <?php if ($n['body'] !== ''): ?>
                            <div class="notif-desc"><?php echo e($n['body']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="notif-side">
                        <a href="<?php echo base_url('pages/notification_details.php?id=' . (int)$n['id'] . '&view=1'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-arrow-right"></i> View</a>
                        <form method="post" action="" class="d-inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="delete_notification" value="<?php echo (int)$n['id']; ?>">
                            <button type="submit" class="btn-icon" data-confirm="Delete this notification?" title="Delete" aria-label="Delete notification"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

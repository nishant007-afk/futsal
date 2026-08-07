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

$relatedBooking = null;
if (preg_match('/booking_details\.php\?id=(\d+)/', (string)$n['link'], $m)) {
    $stmt = $conn->prepare(
        'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.status, b.total_price, b.payment_status,
                g.name AS ground_name, g.location
         FROM bookings b
         JOIN grounds g ON g.id = b.ground_id
         WHERE b.id = ?'
    );
    $relId = (int)$m[1];
    $stmt->bind_param('i', $relId);
    $stmt->execute();
    $relatedBooking = $stmt->get_result()->fetch_assoc();
}

if ((int)$n['is_read'] === 0) {
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $nid, $uid);
    $stmt->execute();
    $n['is_read'] = 1;
}

// If notification has a link and this is a direct click (not from View all), redirect to it
if (!empty($n['link']) && !isset($_GET['view'])) {
    redirect($n['link']);
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

        <?php if ($relatedBooking): ?>
            <div class="nd-booking">
                <div class="nd-booking-head">
                    <div class="mbooking-thumb"><i class="fa-solid fa-calendar-check"></i></div>
                    <div>
                        <span class="eyebrow">Related booking</span>
                        <h3><?php echo e($relatedBooking['ground_name']); ?></h3>
                        <p class="muted" style="font-size:13px;"><i class="fa-solid fa-receipt"></i> <?php echo e($relatedBooking['booking_ref']); ?> &middot; <i class="fa-solid fa-location-dot"></i> <?php echo e($relatedBooking['location']); ?></p>
                    </div>
                </div>
                <dl class="bd-list">
                    <div><dt>Date</dt><dd><?php echo e(date('D, M j, Y', strtotime($relatedBooking['booking_date']))); ?></dd></div>
                    <div><dt>Time</dt><dd><?php echo e(substr($relatedBooking['start_time'], 0, 5)); ?> - <?php echo e(substr($relatedBooking['end_time'], 0, 5)); ?></dd></div>
                    <div><dt>Status</dt><dd><span class="badge badge-<?php echo e($relatedBooking['status']); ?>"><?php echo ucfirst(e($relatedBooking['status'])); ?></span> <span class="badge badge-<?php echo $relatedBooking['payment_status'] === 'paid' ? 'paid' : ($relatedBooking['payment_status'] === 'partial' ? 'partial' : 'unpaid'); ?>"><?php echo ucfirst(e($relatedBooking['payment_status'])); ?></span></dd></div>
                </dl>
                <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$relatedBooking['id']); ?>" class="btn btn-primary btn-block"><i class="fa-solid fa-eye"></i> View this booking</a>
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

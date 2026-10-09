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
        $statement = $conn->prepare(
            'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.status, b.total_price, b.payment_status,
                    g.name AS ground_name, g.location
             FROM bookings b
             JOIN grounds g ON g.id = b.ground_id
             WHERE b.id = ? AND b.user_id = ?'
        );
        $relId = (int)$m[1];
        $statement->bind_param('ii', $relId, $uid);
        $statement->execute();
        $relatedBooking = $statement->get_result()->fetch_assoc();
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

$page_title = !empty($n['title']) ? $n['title'] : 'Notification Details';
require __DIR__ . '/../includes/header.php';
?>

<div class="nd-wrap reveal">
    <div class="nd-top-bar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <div class="title-back-row">
            <a href="<?php echo base_url('pages/notifications.php'); ?>" class="page-back-arrow" data-back aria-label="Back to all notifications"><i class="fa-solid fa-arrow-left"></i></a>
            <span style="font-size:15px; font-weight:700; color:var(--ink);">Notification Details</span>
        </div>
        <?php if ($prevN || $nextN): ?>
            <div class="nd-quick-pager" style="display:flex; align-items:center; gap:8px;">
                <?php if ($nextN): ?>
                    <a href="<?php echo base_url('pages/notification_details.php?id=' . (int)$nextN['id'] . '&view=1'); ?>" class="btn btn-outline btn-sm" title="<?php echo e($nextN['title']); ?>"><i class="fa-solid fa-chevron-left"></i> Newer</a>
                <?php endif; ?>
                <?php if ($prevN): ?>
                    <a href="<?php echo base_url('pages/notification_details.php?id=' . (int)$prevN['id'] . '&view=1'); ?>" class="btn btn-outline btn-sm" title="<?php echo e($prevN['title']); ?>">Older <i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="nd-card">
        <div class="nd-header-row">
            <div class="nd-icon-box notif-thumb c-<?php echo notification_icon_color($n['icon']); ?>">
                <i class="fa-solid <?php echo e($n['icon']); ?>"></i>
            </div>
            <div class="nd-header-meta">
                <h1 class="nd-heading"><?php echo e($n['title']); ?></h1>
                <div class="nd-timestamp">
                    <span><i class="fa-regular fa-clock"></i> <?php echo e(date('l, F j, Y \a\t g:i A', strtotime($n['created_at']))); ?></span>
                    <span class="nd-dot">&middot;</span>
                    <span class="nd-timeago"><?php echo e(notification_time($n['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <?php if ($n['body'] !== ''): ?>
            <div class="nd-message-box">
                <p><?php echo nl2br(e($n['body'])); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($relatedBooking): ?>
            <div class="nd-booking-card">
                <div class="nd-booking-title-bar">
                    <div class="nd-booking-icon">
                        <i class="fa-solid fa-futbol"></i>
                    </div>
                    <div class="nd-booking-title-info">
                        <h3><?php echo e($relatedBooking['ground_name']); ?></h3>
                        <p class="muted text-sm"><i class="fa-solid fa-location-dot"></i> <?php echo e($relatedBooking['location']); ?> &middot; <span class="nbc-ref">#<?php echo e($relatedBooking['booking_ref']); ?></span></p>
                    </div>
                </div>

                <div class="nd-booking-grid">
                    <div class="nd-grid-item">
                        <span class="nd-label">Match Date</span>
                        <strong class="nd-val"><i class="fa-regular fa-calendar"></i> <?php echo date('D, M j, Y', strtotime($relatedBooking['booking_date'])); ?></strong>
                    </div>
                    <div class="nd-grid-item">
                        <span class="nd-label">Time Slot</span>
                        <strong class="nd-val"><i class="fa-regular fa-clock"></i> <?php echo substr($relatedBooking['start_time'], 0, 5); ?> - <?php echo substr($relatedBooking['end_time'], 0, 5); ?></strong>
                    </div>
                    <div class="nd-grid-item">
                        <span class="nd-label">Booking Status</span>
                        <div><span class="badge badge-<?php echo e($relatedBooking['status']); ?>"><?php echo ucfirst(e($relatedBooking['status'])); ?></span></div>
                    </div>
                    <div class="nd-grid-item">
                        <span class="nd-label">Payment Status</span>
                        <div><span class="badge badge-<?php echo $relatedBooking['payment_status'] === 'paid' ? 'paid' : ($relatedBooking['payment_status'] === 'partial' ? 'partial' : 'unpaid'); ?>"><?php echo ucfirst(e($relatedBooking['payment_status'])); ?></span></div>
                    </div>
                </div>

                <div class="nd-booking-cta">
                    <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$relatedBooking['id']); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open Booking Details</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="nd-footer-actions">
            <?php if (!empty($n['link']) && !$relatedBooking): ?>
                <a href="<?php echo e(base_url($n['link'])); ?>" class="btn btn-primary"><i class="fa-solid fa-arrow-up-right-from-square"></i> Go to related page</a>
            <?php endif; ?>
            <a href="<?php echo base_url('pages/notifications.php'); ?>" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> All Notifications</a>
            <?php
            $shortTitle = (mb_strlen($n['title']) > 50) ? (mb_substr($n['title'], 0, 47) . '…') : $n['title'];
            ?>
            <form method="post" action="" class="d-inline" novalidate style="margin-left:auto;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="delete_notification" value="1">
                <button type="submit" class="btn btn-danger" data-confirm="Delete &ldquo;<?php echo e($shortTitle); ?>&rdquo;?" data-confirm-title="Delete notification"><i class="fa-solid fa-trash-can"></i> Delete</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

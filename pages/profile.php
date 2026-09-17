<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();
$errors = [];

$uid = (int)$_SESSION['user_id'];
$statStmt = $conn->prepare("SELECT COUNT(*) c FROM bookings WHERE user_id = ? AND status != 'cancelled'");
$statStmt->bind_param('i', $uid);
$statStmt->execute();
$totalBookings = (int)$statStmt->get_result()->fetch_assoc()['c'];
$statStmt->close();
$statStmt = $conn->prepare("SELECT COUNT(*) c FROM bookings WHERE user_id = ? AND status != 'cancelled' AND booking_date >= CURDATE()");
$statStmt->bind_param('i', $uid);
$statStmt->execute();
$upcomingBookings = (int)$statStmt->get_result()->fetch_assoc()['c'];
$statStmt->close();
$statStmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid), 0) s FROM bookings WHERE user_id = ? AND status != 'cancelled'");
$statStmt->bind_param('i', $uid);
$statStmt->execute();
$totalSpent = (float)$statStmt->get_result()->fetch_assoc()['s'];
$statStmt->close();
$statStmt = $conn->prepare("SELECT COUNT(*) c FROM favorites WHERE user_id = ?");
$statStmt->bind_param('i', $uid);
$statStmt->execute();
$favoriteCount = (int)$statStmt->get_result()->fetch_assoc()['c'];
$statStmt->close();

$maxBytes = 2 * 1024 * 1024;
$avatarDir = __DIR__ . '/../uploads/avatars/';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
    verify_csrf();
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors['avatar'] = 'Choose a photo to upload first.';
    } elseif ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $errors['avatar'] = 'The upload failed. Please try again.';
    } else {
        $file = $_FILES['avatar'];
        $size = $file['size'];
        $tmp = $file['tmp_name'];
        $detected = getimagesize($tmp);
        $allowed = ['image/jpeg' => true, 'image/png' => true, 'image/webp' => true, 'image/gif' => true];
        if (!$detected || !isset($allowed[$detected['mime']])) {
            $errors['avatar'] = 'That file isn\'t a supported image. Use JPG, PNG, WebP or GIF.';
        } elseif (($detected[0] * $detected[1]) > 16000000 || $detected[0] > 6000 || $detected[1] > 6000) {
            $errors['avatar'] = 'Those image dimensions are too large. Use a smaller photo.';
        } elseif ($size > $maxBytes) {
            $errors['avatar'] = 'That photo is too big. It must be 2MB or smaller.';
        } else {
            $filename = 'user_' . (int)$_SESSION['user_id'] . '_' . bin2hex(random_bytes(8)) . '.webp';
            $dest = $avatarDir . $filename;
            if (convert_image_to_webp($tmp, $dest, 85, 512)) {
                if (!empty($user['avatar'])) {
                    $old = $avatarDir . basename($user['avatar']);
                    if (is_file($old)) unlink($old);
                }
                $stmt = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ?');
                $stmt->bind_param('si', $filename, $_SESSION['user_id']);
                if ($stmt->execute()) { set_flash('success', 'Profile photo updated.'); redirect('pages/profile.php'); }
                else { unlink($dest); $errors[] = 'Could not save the photo.'; }
            } else { $errors[] = 'Could not save the upload.'; }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_avatar') {
    verify_csrf();
    if (!empty($user['avatar'])) {
        $old = $avatarDir . basename($user['avatar']);
        if (is_file($old)) unlink($old);
        $stmt = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ?');
        $empty = '';
        $stmt->bind_param('si', $empty, $_SESSION['user_id']);
        if ($stmt->execute()) { set_flash('success', 'Profile photo removed.'); redirect('pages/profile.php'); }
    }
    redirect('pages/profile.php');
}

$today = date('Y-m-d');
$upStmt = $conn->prepare(
    'SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status, g.name AS ground_name, g.location
     FROM bookings b JOIN grounds g ON g.id = b.ground_id
     WHERE b.user_id = ? AND b.booking_date >= ? AND b.status != \'cancelled\'
     ORDER BY b.booking_date ASC, b.start_time ASC LIMIT 18'
);
$upStmt->bind_param('is', $_SESSION['user_id'], $today);
$upStmt->execute();
$upcoming = $upStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$upStmt->close();

$pastStmt = $conn->prepare(
    'SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status, g.name AS ground_name, g.location
     FROM bookings b JOIN grounds g ON g.id = b.ground_id
     WHERE b.user_id = ? AND b.status != \'cancelled\'
     ORDER BY b.booking_date DESC, b.start_time DESC LIMIT 18'
);
$pastStmt->bind_param('i', $_SESSION['user_id']);
$pastStmt->execute();
$past = $pastStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pastStmt->close();

$page_title = 'My Profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="pfl">
    <!-- Header card -->
    <div class="pfl-card pfl-header">
        <div class="pfl-avatar-wrap">
            <label for="avatar" class="pfl-avatar" aria-label="Change profile photo">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?php echo base_url('uploads/avatars/' . rawurlencode($user['avatar'])); ?>" alt="<?php echo e($user['name']); ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <span class="pfl-avatar-letter"><?php echo e(strtoupper(substr($user['name'], 0, 1))); ?></span>
                <?php endif; ?>
                <span class="pfl-avatar-edit"><i class="fa-solid fa-camera"></i></span>
            </label>
        </div>
        <h1 class="pfl-name"><?php echo e($user['name']); ?></h1>
        <p class="pfl-meta"><?php echo e($user['email']); ?></p>
        <span class="pfl-role-badge"><?php echo e(ucfirst($user['role'])); ?></span>

        <div class="pfl-stats">
            <div class="pfl-stat">
                <span class="pfl-stat-num"><?php echo $totalBookings; ?></span>
                <span class="pfl-stat-label">Bookings</span>
            </div>
            <div class="pfl-stat-divider"></div>
            <div class="pfl-stat">
                <span class="pfl-stat-num"><?php echo $upcomingBookings; ?></span>
                <span class="pfl-stat-label">Upcoming</span>
            </div>
            <div class="pfl-stat-divider"></div>
            <div class="pfl-stat">
                <span class="pfl-stat-num"><?php echo $favoriteCount; ?></span>
                <span class="pfl-stat-label">Saved</span>
            </div>
        </div>
    </div>

    <form method="post" action="" enctype="multipart/form-data" class="sr-only" id="avatarForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="upload_avatar">
        <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
    </form>

    <?php if (!empty($user['avatar'])): ?>
    <form method="post" action="" class="sr-only" id="removeAvatarForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="remove_avatar">
    </form>
    <?php endif; ?>

    <!-- Quick links -->
    <div class="pfl-card pfl-links">
        <a href="<?php echo base_url('pages/settings_account.php'); ?>" class="pfl-link">
            <span class="pfl-link-icon"><i class="fa-solid fa-user-pen"></i></span>
            <span class="pfl-link-text">Edit profile</span>
            <i class="fa-solid fa-chevron-right pfl-link-chevr"></i>
        </a>
        <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="pfl-link">
            <span class="pfl-link-icon"><i class="fa-solid fa-calendar-check"></i></span>
            <span class="pfl-link-text">My bookings</span>
            <i class="fa-solid fa-chevron-right pfl-link-chevr"></i>
        </a>
        <a href="<?php echo base_url('pages/favorites.php'); ?>" class="pfl-link">
            <span class="pfl-link-icon"><i class="fa-solid fa-heart"></i></span>
            <span class="pfl-link-text">Saved courts</span>
            <i class="fa-solid fa-chevron-right pfl-link-chevr"></i>
        </a>
        <a href="<?php echo base_url('pages/settings.php'); ?>" class="pfl-link">
            <span class="pfl-link-icon"><i class="fa-solid fa-gear"></i></span>
            <span class="pfl-link-text">Settings</span>
            <i class="fa-solid fa-chevron-right pfl-link-chevr"></i>
        </a>
    </div>

    <!-- Booking history -->
    <div class="pfl-card">
        <div class="pfl-tabs" role="tablist" aria-label="Booking history">
            <button type="button" class="pfl-tab active" data-tab="upcoming" role="tab" aria-selected="true" aria-controls="pane-upcoming">
                Upcoming
            </button>
            <button type="button" class="pfl-tab" data-tab="past" role="tab" aria-selected="false" aria-controls="pane-past">
                Past
            </button>
        </div>

        <!-- Upcoming list -->
        <div class="pfl-tab-pane active" id="pane-upcoming" role="tabpanel">
            <?php if ($upcoming): ?>
                <div class="pfl-booking-list">
                    <?php foreach ($upcoming as $b): ?>
                        <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="pfl-booking-item">
                            <div class="pfl-booking-date">
                                <span class="pfl-booking-day"><?php echo (int)date('d', strtotime($b['booking_date'])); ?></span>
                                <span class="pfl-booking-month"><?php echo e(strtoupper(date('M', strtotime($b['booking_date'])))); ?></span>
                            </div>
                            <div class="pfl-booking-info">
                                <span class="pfl-booking-name"><?php echo e($b['ground_name']); ?></span>
                                <span class="pfl-booking-time"><?php echo e(substr($b['start_time'], 0, 5) . ' - ' . substr($b['end_time'], 0, 5)); ?></span>
                            </div>
                            <i class="fa-solid fa-chevron-right pfl-booking-chevr"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="pfl-empty">
                    <i class="fa-regular fa-calendar-xmark"></i>
                    <p>No upcoming bookings</p>
                    <a href="<?php echo base_url('pages/courts.php'); ?>" class="btn btn-primary btn-sm">Find a court</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Past list -->
        <div class="pfl-tab-pane" id="pane-past" role="tabpanel">
            <?php if ($past): ?>
                <div class="pfl-booking-list">
                    <?php foreach ($past as $b): ?>
                        <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="pfl-booking-item">
                            <div class="pfl-booking-date">
                                <span class="pfl-booking-day"><?php echo (int)date('d', strtotime($b['booking_date'])); ?></span>
                                <span class="pfl-booking-month"><?php echo e(strtoupper(date('M', strtotime($b['booking_date'])))); ?></span>
                            </div>
                            <div class="pfl-booking-info">
                                <span class="pfl-booking-name"><?php echo e($b['ground_name']); ?></span>
                                <span class="pfl-booking-time"><?php echo e(substr($b['start_time'], 0, 5) . ' - ' . substr($b['end_time'], 0, 5)); ?></span>
                            </div>
                            <i class="fa-solid fa-chevron-right pfl-booking-chevr"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="pfl-empty">
                    <i class="fa-regular fa-clock"></i>
                    <p>No past bookings yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.pfl-tab').forEach(function(btn){
    btn.addEventListener('click', function(){
        document.querySelectorAll('.pfl-tab').forEach(function(t){
            t.classList.remove('active');
            t.setAttribute('aria-selected', 'false');
        });
        document.querySelectorAll('.pfl-tab-pane').forEach(function(p){ p.classList.remove('active'); });
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');
        document.getElementById('pane-' + btn.dataset.tab).classList.add('active');
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

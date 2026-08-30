<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();
$errors = [];

$totalBookings = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE user_id = " . (int)$_SESSION['user_id'] . " AND status != 'cancelled'")->fetch_assoc()['c'];
$upcomingBookings = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE user_id = " . (int)$_SESSION['user_id'] . " AND status != 'cancelled' AND booking_date >= CURDATE()")->fetch_assoc()['c'];
$totalSpent = (float)$conn->query("SELECT COALESCE(SUM(amount_paid), 0) s FROM bookings WHERE user_id = " . (int)$_SESSION['user_id'] . " AND status != 'cancelled'")->fetch_assoc()['s'];
$favoriteCount = (int)$conn->query("SELECT COUNT(*) c FROM favorites WHERE user_id = " . (int)$_SESSION['user_id'])->fetch_assoc()['c'];

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
$upcoming = $conn->query(
    'SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status, g.name AS ground_name, g.location
     FROM bookings b JOIN grounds g ON g.id = b.ground_id
     WHERE b.user_id = ' . (int)$_SESSION['user_id'] . " AND b.booking_date >= '$today' AND b.status != 'cancelled'
     ORDER BY b.booking_date ASC, b.start_time ASC LIMIT 18"
)->fetch_all(MYSQLI_ASSOC);

$past = $conn->query(
    'SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status, g.name AS ground_name, g.location
     FROM bookings b JOIN grounds g ON g.id = b.ground_id
     WHERE b.user_id = ' . (int)$_SESSION['user_id'] . " AND b.status != 'cancelled'
     ORDER BY b.booking_date DESC, b.start_time DESC LIMIT 18"
)->fetch_all(MYSQLI_ASSOC);

$page_title = 'My Profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="prof-wrap">
<!-- Top bar -->
<div class="prof-topbar">
    <h1><?php echo e($user['name']); ?></h1>
    <a href="<?php echo base_url('pages/settings.php'); ?>" class="prof-topbar-icon" aria-label="Settings"><i class="fa-solid fa-gear"></i></a>
</div>

<!-- Profile section -->
<div class="prof-section">
    <div class="prof-row">
        <div class="prof-avatar">
            <label for="avatar" class="prof-avatar-label">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?php echo base_url('uploads/avatars/' . rawurlencode($user['avatar'])); ?>" alt="<?php echo e($user['name']); ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <span class="prof-avatar-letter"><?php echo e(strtoupper(substr($user['name'], 0, 1))); ?></span>
                <?php endif; ?>
            </label>
        </div>
        <div class="prof-stats">
            <div class="prof-stat"><strong><?php echo $totalBookings; ?></strong><span>bookings</span></div>
            <div class="prof-stat"><strong><?php echo $upcomingBookings; ?></strong><span>upcoming</span></div>
            <div class="prof-stat"><strong><?php echo $favoriteCount; ?></strong><span>saved</span></div>
        </div>
    </div>
    <div class="prof-bio">
        <strong><?php echo e($user['name']); ?></strong>
        <span><?php echo e(ucfirst($user['role'])); ?></span>
        <span>Member since <?php echo e(date('M Y', strtotime($user['created_at']))); ?></span>
    </div>
    <div class="prof-actions">
        <a href="<?php echo base_url('pages/settings_account.php'); ?>" class="prof-btn">Edit profile</a>
        <a href="<?php echo base_url('pages/settings.php'); ?>" class="prof-btn">Settings</a>
        <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="prof-btn">Bookings</a>
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

<!-- Tabs -->
<div class="prof-tabs">
    <button class="prof-tab active" data-tab="upcoming"><i class="fa-solid fa-calendar-days"></i> Upcoming</button>
    <button class="prof-tab" data-tab="past"><i class="fa-solid fa-clock-rotate-left"></i> Past</button>
</div>

<!-- Upcoming grid -->
<div class="prof-tab-pane active" id="pane-upcoming">
    <?php if ($upcoming): ?>
        <div class="prof-grid">
            <?php foreach ($upcoming as $b): ?>
                <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="prof-grid-cell">
                    <span class="prof-grid-primary"><?php echo date('M j', strtotime($b['booking_date'])); ?></span>
                    <span class="prof-grid-secondary"><?php echo substr($b['start_time'], 0, 5); ?></span>
                    <span class="prof-grid-tertiary"><?php echo e($b['ground_name']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="prof-empty"><i class="fa-regular fa-calendar-xmark"></i><p>No upcoming bookings</p></div>
    <?php endif; ?>
</div>

<!-- Past grid -->
<div class="prof-tab-pane" id="pane-past">
    <?php if ($past): ?>
        <div class="prof-grid">
            <?php foreach ($past as $b): ?>
                <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="prof-grid-cell">
                    <span class="prof-grid-primary"><?php echo date('M j', strtotime($b['booking_date'])); ?></span>
                    <span class="prof-grid-secondary"><?php echo substr($b['start_time'], 0, 5); ?></span>
                    <span class="prof-grid-tertiary"><?php echo e($b['ground_name']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="prof-empty"><i class="fa-regular fa-clock"></i><p>No past bookings</p></div>
    <?php endif; ?>
</div>
</div><!-- /prof-wrap -->

<script>
document.querySelectorAll('.prof-tab').forEach(function(btn){
    btn.addEventListener('click', function(){
        document.querySelectorAll('.prof-tab').forEach(function(t){ t.classList.remove('active'); });
        document.querySelectorAll('.prof-tab-pane').forEach(function(p){ p.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById('pane-' + btn.dataset.tab).classList.add('active');
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

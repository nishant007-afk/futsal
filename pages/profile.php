<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();
$errors = [];

// Player stats
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
                    if (is_file($old)) {
                        unlink($old);
                    }
                }
                $stmt = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ?');
                $stmt->bind_param('si', $filename, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    set_flash('success', 'Profile photo updated.');
                    redirect('pages/profile.php');
                } else {
                    unlink($dest);
                    $errors[] = 'Could not save the photo. Please try again.';
                }
            } else {
                $errors[] = 'Could not save the upload. Please try again.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_avatar') {
    verify_csrf();
    if (!empty($user['avatar'])) {
        $old = $avatarDir . basename($user['avatar']);
        if (is_file($old)) {
            unlink($old);
        }
        $stmt = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ?');
        $empty = '';
        $stmt->bind_param('si', $empty, $_SESSION['user_id']);
        if ($stmt->execute()) {
            set_flash('success', 'Profile photo removed.');
            redirect('pages/profile.php');
        }
    }
    set_flash_error(
        'The photo couldn\'t be removed.',
        'The image file may be in use or unavailable right now.',
        'Try again, or upload a new photo instead.',
        'pages/profile.php'
    );
    redirect('pages/profile.php');
}

// Upcoming bookings for the grid
$today = date('Y-m-d');
$upcoming = $conn->query(
    'SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status, g.name AS ground_name, g.location
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     WHERE b.user_id = ' . (int)$_SESSION['user_id'] . " AND b.booking_date >= '$today' AND b.status != 'cancelled'
     ORDER BY b.booking_date ASC, b.start_time ASC LIMIT 12"
)->fetch_all(MYSQLI_ASSOC);

$page_title = 'My Profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="ig-profile">
    <!-- Profile Header -->
    <div class="ig-profile-header">
        <div class="ig-profile-avatar-wrap">
            <label for="avatar" class="ig-avatar-label" title="Change profile photo">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?php echo base_url('uploads/avatars/' . rawurlencode($user['avatar'])); ?>" alt="<?php echo e($user['name']); ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <span class="ig-avatar-letter"><?php echo e(strtoupper(substr($user['name'], 0, 1))); ?></span>
                <?php endif; ?>
                <span class="ig-avatar-edit" aria-hidden="true"><i class="fa-solid fa-camera"></i></span>
            </label>
            <?php if (!empty($user['avatar'])): ?>
            <form method="post" action="" class="sr-only" id="removeAvatarForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="remove_avatar">
            </form>
            <?php endif; ?>
        </div>

        <div class="ig-profile-info">
            <div class="ig-profile-name-row">
                <h1 class="ig-profile-username"><?php echo e($user['name']); ?></h1>
                <span class="badge badge-<?php echo e($user['role']); ?>"><?php echo e($user['role']); ?></span>
            </div>
            <p class="ig-profile-email"><?php echo e($user['email']); ?></p>
            <p class="ig-profile-member">Member since <?php echo e(date('M Y', strtotime($user['created_at']))); ?></p>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="ig-profile-stats">
        <div class="ig-stat">
            <strong><?php echo $totalBookings; ?></strong>
            <span>Bookings</span>
        </div>
        <div class="ig-stat">
            <strong><?php echo $upcomingBookings; ?></strong>
            <span>Upcoming</span>
        </div>
        <div class="ig-stat">
            <strong><?php echo format_price($totalSpent); ?></strong>
            <span>Spent</span>
        </div>
        <div class="ig-stat">
            <strong><?php echo $favoriteCount; ?></strong>
            <span>Saved</span>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="ig-profile-actions">
        <a href="<?php echo base_url('pages/settings.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-gear"></i> Settings</a>
        <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-calendar-check"></i> My Bookings</a>
    </div>

    <!-- Avatar Upload Form -->
    <form method="post" action="" enctype="multipart/form-data" class="sr-only" id="avatarForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="upload_avatar">
        <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
    </form>
</div>

<!-- Upcoming Bookings Grid -->
<?php if ($upcoming): ?>
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="section-head reveal">
            <h2 class="section-title">Upcoming bookings</h2>
        </div>
        <div class="ig-grid">
            <?php foreach ($upcoming as $b): ?>
                <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="ig-grid-item reveal">
                    <span class="ig-grid-date"><?php echo date('M j', strtotime($b['booking_date'])); ?></span>
                    <span class="ig-grid-time"><?php echo substr($b['start_time'], 0, 5); ?></span>
                    <span class="ig-grid-court"><?php echo e($b['ground_name']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

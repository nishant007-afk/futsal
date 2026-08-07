<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();
$errors = [];
$flash_success = '';

$maxBytes = 2 * 1024 * 1024;
$avatarDir = __DIR__ . '/../uploads/avatars/';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '' || strlen($name) < 2) {
        $errors['name'] = 'Please enter your full name, at least 2 characters.';
    }
    if (strlen($phone) > 20) {
        $errors['phone'] = 'Phone number is too long. Keep it under 20 characters.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?');
        $stmt->bind_param('ssi', $name, $phone, $_SESSION['user_id']);
        if ($stmt->execute()) {
            set_flash('success', 'Profile updated.');
            redirect('pages/profile.php');
        } else {
            $errors['general'] = 'Could not save your changes. Please try again.';
        }
    }
}

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
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

        if (!$detected || !isset($allowed[$detected['mime']])) {
            $errors['avatar'] = 'That file isn\'t a supported image. Use JPG, PNG, WebP or GIF.';
        } elseif ($size > $maxBytes) {
            $errors['avatar'] = 'That photo is too big. It must be 2MB or smaller.';
        } else {
            $ext = $allowed[$detected['mime']];
            $filename = 'user_' . (int)$_SESSION['user_id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = $avatarDir . $filename;

            if (move_uploaded_file($tmp, $dest)) {
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

$page_title = 'My Profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h2><i class="fa-solid fa-id-card"></i> My Profile</h2>
</div>

<?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

<div class="profile-grid">
    <div class="profile-side form-card">
        <div class="profile-avatar">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?php echo base_url('uploads/avatars/' . rawurlencode($user['avatar'])); ?>" alt="<?php echo e($user['name']); ?>" loading="lazy" decoding="async">
            <?php else: ?>
                <span><?php echo e(strtoupper(substr($user['name'], 0, 1))); ?></span>
            <?php endif; ?>
            <span class="profile-avatar-edit"><i class="fa-solid fa-camera"></i></span>
        </div>
        <h3 class="profile-name"><?php echo e($user['name']); ?></h3>
        <p class="muted profile-email"><?php echo e($user['email']); ?></p>
        <span class="badge badge-<?php echo e($user['role']); ?>"><?php echo e($user['role']); ?></span>
        <p class="form-hint" style="text-align:center;margin-top:14px;">Member since <?php echo e(date('F Y', strtotime($user['created_at']))); ?></p>

        <form method="post" action="" enctype="multipart/form-data" class="avatar-form" id="avatarForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="upload_avatar">
            <label for="avatar" class="btn btn-outline btn-block<?php echo has_error($errors, 'avatar'); ?>"><i class="fa-solid fa-upload"></i> Choose photo</label>
            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only">
            <p class="form-hint" style="text-align:center;margin-top:8px;" id="avatarStatus">JPG, PNG, WebP or GIF &middot; max 2MB</p>
            <?php field_error($errors, 'avatar'); ?>
            <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px;display:none;"><i class="fa-solid fa-camera-retro"></i> Upload photo</button>
        </form>
        <?php if (!empty($user['avatar'])): ?>
            <form method="post" action="" class="avatar-form" style="margin-top:10px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="remove_avatar">
                <button type="submit" class="btn btn-danger btn-block" data-confirm="Remove your profile photo?"><i class="fa-solid fa-trash-can"></i> Remove photo</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="form-card lg">
        <div class="form-head">
            <h2>Edit details</h2>
            <p class="muted">Keep your contact details up to date so managers can reach you.</p>
        </div>
        <form method="post" action="" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group<?php echo has_error($errors, 'name'); ?>">
                <label for="name">Full name <span class="req">*</span></label>
                <div class="input-group">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" id="name" name="name" value="<?php echo e($user['name']); ?>" autocomplete="name" required>
                </div>
                <?php field_error($errors, 'name'); ?>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-group">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" id="email" value="<?php echo e($user['email']); ?>" disabled>
                </div>
                <p class="form-hint">Email can't be changed on this site.</p>
            </div>
            <div class="form-group<?php echo has_error($errors, 'phone'); ?>">
                <label for="phone">Phone <span class="muted" style="font-weight:400;">(optional)</span></label>
                <div class="input-group">
                    <i class="fa-solid fa-phone"></i>
                    <input type="tel" id="phone" name="phone" value="<?php echo e($user['phone']); ?>" autocomplete="tel" placeholder="98xxxxxxxx">
                </div>
                <?php field_error($errors, 'phone'); ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

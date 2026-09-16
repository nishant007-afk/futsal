<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();
$errors = [];
$activeSettings = 'account';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));

    if ($name === '' || strlen($name) < 2) {
        $errors['name'] = 'Please enter your full name, at least 2 characters. Letters, numbers and special characters are all allowed.';
    }
    if (strlen($phone) > 20) {
        $errors['phone'] = 'Phone number is too long. Keep it under 20 characters.';
    }

    $emailChanged = $email !== '' && $email !== strtolower($user['email']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address, e.g. you@example.com.';
    } elseif ($emailChanged) {
        if (is_disposable_email($email)) {
            $errors['email'] = 'One-time email addresses aren\'t allowed. Please use a real email.';
        } elseif (!email_has_mx($email)) {
            $errors['email'] = 'This email domain does not accept mail. Please use a real email address.';
        } else {
            $check = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $check->bind_param('si', $email, $_SESSION['user_id']);
            $check->execute();
            if ($check->get_result()->fetch_assoc()) {
                $errors['email'] = 'An account with this email already exists. Try logging in instead.';
            }
        }
    }

    if (!$errors) {
        $stmt = $conn->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?');
        $stmt->bind_param('ssi', $name, $phone, $_SESSION['user_id']);
        if ($stmt->execute()) {
            if ($emailChanged) {
                $_SESSION['pending_email_change'] = [
                    'user_id' => (int)$_SESSION['user_id'],
                    'new_email' => $email,
                ];
                redirect('pages/change_email_otp.php');
            }
            set_flash('success', 'Profile updated.');
            redirect('pages/settings_account.php');
        } else {
            $errors['general'] = 'Could not save your changes. Please try again.';
        }
    }
}

$page_title = 'Edit Profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="stg-layout">
    <nav class="stg-sidebar" aria-label="Settings navigation">
        <div class="stg-sidebar-head">
            <a href="<?php echo base_url('pages/profile.php'); ?>" class="stg-sidebar-back" aria-label="Back to profile"><i class="fa-solid fa-arrow-left"></i></a>
            <h1>Settings</h1>
        </div>
        <a href="<?php echo base_url('pages/profile.php'); ?>" class="stg-sidebar-user">
            <span class="stg-sidebar-avatar">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?php echo base_url('uploads/avatars/' . rawurlencode($user['avatar'])); ?>" alt="" loading="lazy" decoding="async">
                <?php else: ?>
                    <?php echo e(strtoupper(substr($user['name'], 0, 1))); ?>
                <?php endif; ?>
            </span>
            <span class="stg-sidebar-user-info">
                <strong><?php echo e($user['name']); ?></strong>
                <span><?php echo e($user['email']); ?></span>
            </span>
        </a>
        <div class="stg-sidebar-section">
            <span class="stg-sidebar-label">Account</span>
            <a href="<?php echo base_url('pages/settings.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-sliders"></i> General</a>
            <a href="<?php echo base_url('pages/settings_account.php'); ?>" class="stg-sidebar-link active"><i class="fa-solid fa-user-pen"></i> Edit profile</a>
            <a href="<?php echo base_url('pages/security.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-lock"></i> Password</a>
        </div>
        <div class="stg-sidebar-section">
            <span class="stg-sidebar-label">Preferences</span>
            <a href="<?php echo base_url('pages/settings_notifications.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-bell"></i> Notifications</a>
            <a href="<?php echo base_url('pages/settings_preferences.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-palette"></i> Appearance</a>
        </div>
        <div class="stg-sidebar-section">
            <span class="stg-sidebar-label">Support</span>
            <a href="<?php echo base_url('pages/faq.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-circle-question"></i> Help & FAQ</a>
            <a href="<?php echo base_url('pages/page.php?slug=terms'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-file-lines"></i> Terms</a>
            <a href="<?php echo base_url('pages/page.php?slug=privacy'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-shield-halved"></i> Privacy</a>
        </div>
        <div class="stg-sidebar-section">
            <form method="post" action="<?php echo base_url('pages/logout.php'); ?>" class="m-0">
                <?php echo csrf_field(); ?>
                <button type="submit" class="stg-sidebar-link stg-sidebar-danger" data-confirm="Log out of your account?" data-confirm-ok="Yes, log out" data-confirm-cancel="Cancel"><i class="fa-solid fa-right-from-bracket"></i> Log out</button>
            </form>
        </div>
    </nav>

    <div class="stg-main">
        <div class="stg-main-head"><h2>Edit Profile</h2></div>
        <div class="stg-content-card">
            <div class="stg-content-section">
                <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>
                <form method="post" action="" novalidate role="form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group<?php echo has_error($errors, 'name'); ?>">
                        <div class="input-group floating">
                            <input type="text" id="name" name="name" value="<?php echo e($user['name']); ?>" autocomplete="name" placeholder=" " maxlength="100" required aria-required="true">
                            <label for="name">Full name <span class="req">*</span></label>
                        </div>
                        <p class="form-hint">Letters, numbers, spaces and special characters are all allowed.</p>
                        <?php field_error($errors, 'name'); ?>
                    </div>
                    <div class="form-group<?php echo has_error($errors, 'email'); ?>">
                        <div class="input-group floating">
                            <input type="email" id="email" name="email" value="<?php echo e($user['email']); ?>" autocomplete="email" placeholder=" " required aria-required="true" data-check-email="available" data-exclude-id="<?php echo (int)$user['id']; ?>">
                            <label for="email">Email</label>
                        </div>
                        <?php field_error($errors, 'email'); ?>
                    </div>
                    <div class="form-group<?php echo has_error($errors, 'phone'); ?>">
                        <div class="input-group floating">
                            <input type="tel" id="phone" name="phone" value="<?php echo e($user['phone']); ?>" autocomplete="tel" placeholder=" " maxlength="20">
                            <label for="phone">Phone</label>
                        </div>
                        <?php field_error($errors, 'phone'); ?>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

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
        $errors['name'] = 'Please enter your full name, at least 2 characters.';
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

<div class="page-head settings-page-head">
    <div class="ps-head-row">
        <a href="<?php echo base_url('pages/settings.php'); ?>" class="page-back-arrow" aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Edit Profile</h2>
    </div>
</div>

<div class="settings-layout">
    <?php require __DIR__ . '/../includes/views/settings_sidebar.php'; ?>

    <div class="settings-content">
        <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

        <div class="settings-card">
            <form method="post" action="" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group<?php echo has_error($errors, 'name'); ?>">
                    <div class="input-group floating">
                        <input type="text" id="name" name="name" value="<?php echo e($user['name']); ?>" autocomplete="name" placeholder=" " required>
                        <label for="name">Full name <span class="req">*</span></label>
                    </div>
                    <?php field_error($errors, 'name'); ?>
                </div>
                <div class="form-group<?php echo has_error($errors, 'email'); ?>">
                    <div class="input-group floating">
                        <input type="email" id="email" name="email" value="<?php echo e($user['email']); ?>" autocomplete="email" placeholder=" " required data-check-email="available" data-exclude-id="<?php echo (int)$user['id']; ?>">
                        <label for="email">Email</label>
                    </div>
                    <?php field_error($errors, 'email'); ?>
                </div>
                <div class="form-group<?php echo has_error($errors, 'phone'); ?>">
                    <div class="input-group floating">
                        <input type="tel" id="phone" name="phone" value="<?php echo e($user['phone']); ?>" autocomplete="tel" placeholder=" ">
                        <label for="phone">Phone</label>
                    </div>
                    <?php field_error($errors, 'phone'); ?>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

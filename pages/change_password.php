<?php
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) {
    redirect('pages/login.php');
}

$me = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($current_password === '') {
        $errors['current_password'] = 'Enter your current password.';
    }
    if ($new_password === '') {
        $errors['new_password'] = 'Enter a new password.';
    }
    if ($confirm_password === '') {
        $errors['confirm_password'] = 'Repeat your new password.';
    }

    if ($new_password !== '' && $confirm_password !== '' && $new_password !== $confirm_password) {
        $errors['confirm_password'] = 'The two passwords don\'t match. Please type the same password in both boxes.';
    }

    $pwError = $new_password !== '' ? validate_password($new_password) : null;
    if ($pwError) {
        $errors['new_password'] = $pwError;
    }

    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->bind_param('i', $me['id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($current_password !== '' && (!$user || !password_verify($current_password, $user['password']))) {
        $errors['current_password'] = 'That\'s not your current password. Try again.';
    }

    if (!$errors) {
        $_SESSION['pending_password_change'] = [
            'user_id' => (int)$me['id'],
            'hash' => password_hash($new_password, PASSWORD_BCRYPT),
        ];
        redirect('pages/change_password_otp.php');
    }
}

$page_title = 'Change Password';
?>

<div class="form-card lg" style="margin-top:44px;">
    <div class="form-head">
        <h2>Change your password</h2>
        <p class="muted">Enter your current password, choose a new one, and we'll confirm it by email.</p>
    </div>

    <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

    <form method="post" action="" novalidate>
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'current_password'); ?>">
            <label for="current_password">Current password</label>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                <button type="button" class="pw-toggle" data-target="current_password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <?php field_error($errors, 'current_password'); ?>
            <p class="form-hint" style="margin-top:8px;"><a href="<?php echo base_url('pages/forgot_password.php'); ?>">Forgot your password?</a></p>
        </div>

        <div class="form-group<?php echo has_error($errors, 'new_password'); ?>">
            <label for="new_password">New password</label>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" required>
                <button type="button" class="pw-toggle" data-target="new_password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <p class="form-hint">Your new password needs:</p>
            <ul class="pw-requirements" id="pwRequirements">
                <li data-req="length">At least 8 characters</li>
                <li data-req="letter">At least one letter</li>
                <li data-req="number">At least one number</li>
                <li data-req="special">At least one special character</li>
            </ul>
            <?php field_error($errors, 'new_password'); ?>
        </div>

        <div class="form-group<?php echo has_error($errors, 'confirm_password'); ?>">
            <label for="confirm_password">Confirm new password</label>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" placeholder="Repeat your new password" required>
                <button type="button" class="pw-toggle" data-target="confirm_password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <?php field_error($errors, 'confirm_password'); ?>
        </div>

        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-arrow-right"></i> Continue</button>
        <p class="form-foot">Need help? <a href="<?php echo base_url('pages/contact_submit.php?topic=password_issue'); ?>">Contact support</a></p>
    </form>
</div>

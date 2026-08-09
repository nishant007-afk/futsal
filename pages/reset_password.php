<?php
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect('index.php');
}

$verified = $_SESSION['reset_verified'] ?? null;
if (!$verified || !isset($verified['email'])) {
    redirect('pages/forgot_password.php');
}

$email = $verified['email'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    $pwError = validate_password($password);
    if ($pwError) {
        $errors['password'] = $pwError;
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'The two passwords don\'t match. Please type the same password in both boxes.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $user['id']);
            $stmt->execute();
            unset($_SESSION['reset_verified']);
            set_flash('success', 'Your password has been updated. You can now log in.');
            redirect('pages/login.php');
        }
    }
}

$page_title = 'Reset Password';
$page_description = 'Choose a new password for your GoalSpace account and get back to booking your next game.';
require __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <div class="form-head">
        <h2>Choose a new password</h2>
        <p class="muted">You're verified. Pick a new password for <strong><?php echo e($email); ?></strong>.</p>
    </div>
    <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>
    <form method="post" action="" novalidate>
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'password'); ?>">
            <div class="input-group floating">
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" placeholder=" " required>
                <label for="password">New password <span class="req">*</span></label>
                <button type="button" class="pw-toggle" data-target="password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <p class="form-hint">Your new password needs:</p>
            <ul class="pw-requirements" id="pwRequirements">
                <li data-req="length">At least 8 characters</li>
                <li data-req="letter">At least one letter</li>
                <li data-req="number">At least one number</li>
                <li data-req="special">At least one special character</li>
            </ul>
            <?php field_error($errors, 'password'); ?>
        </div>
        <div class="form-group<?php echo has_error($errors, 'confirm'); ?>">
            <div class="input-group floating">
                <input type="password" id="confirm" name="confirm" autocomplete="new-password" minlength="8" placeholder=" " required>
                <label for="confirm">Confirm new password <span class="req">*</span></label>
                <button type="button" class="pw-toggle" data-target="confirm" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <?php field_error($errors, 'confirm'); ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-key"></i> Update password</button>
        <p class="form-foot">Remembered it? <a href="<?php echo base_url('pages/login.php'); ?>">Log in</a></p>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
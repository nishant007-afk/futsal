<?php
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect('index.php');
}

$email = trim($_POST['email'] ?? ($_GET['email'] ?? ''));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address, e.g. you@example.com.';
    }
    $pwError = validate_password($password);
    if ($pwError) {
        $errors['password'] = $pwError;
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'The two passwords don\'t match. Please type the same password in both boxes.';
    }
    if ($code === '') {
        $errors['code'] = 'Enter the 6-digit code from your email.';
    }

    if (!$errors && verify_otp($email, 'password_reset', $code)) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $user['id']);
            $stmt->execute();
            set_flash('success', 'Your password has been updated. You can now log in.');
            redirect('pages/login.php');
        }
    } elseif (!$errors) {
        $left = otp_attempts_left($email, 'password_reset');
        $errors['code'] = $left > 0
            ? 'That code didn\'t work or it has expired. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.'
            : 'Too many wrong attempts. Request a new code.';
    }
}

$page_title = 'Reset Password';
$page_description = 'Choose a new password for your GoalSpace account and get back to booking your next game.';
require __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <div class="form-head">
        <h2>Choose a new password</h2>
        <p class="muted">Enter the code from your email, then pick a new password.</p>
    </div>
    <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>
    <form method="post" action="" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="email" value="<?php echo e($email); ?>">
        <div class="form-group<?php echo has_error($errors, 'email'); ?>">
            <label for="email">Email <span class="req">*</span></label>
            <div class="input-group">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" id="email" name="email" value="<?php echo e($email); ?>" autocomplete="email" required>
            </div>
            <?php field_error($errors, 'email'); ?>
        </div>
        <div class="form-group<?php echo has_error($errors, 'code'); ?>">
            <label for="code">Reset code <span class="req">*</span></label>
            <div class="input-group">
                <i class="fa-solid fa-shield-halved"></i>
                <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" autocomplete="one-time-code" spellcheck="false" required>
            </div>
            <?php field_error($errors, 'code'); ?>
        </div>
        <div class="form-group<?php echo has_error($errors, 'password'); ?>">
            <label for="password">New password <span class="req">*</span></label>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" required>
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
            <label for="confirm">Confirm new password <span class="req">*</span></label>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="confirm" name="confirm" autocomplete="new-password" minlength="8" placeholder="Repeat your new password" required>
                <button type="button" class="pw-toggle" data-target="confirm" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <?php field_error($errors, 'confirm'); ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-key"></i> Update password</button>
        <p class="form-foot">Remembered it? <a href="<?php echo base_url('pages/login.php'); ?>">Log in</a></p>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

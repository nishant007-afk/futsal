<?php
require_once __DIR__ . '/../config/db.php';

$me = is_logged_in() ? current_user() : null;
$email = $me ? $me['email'] : '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address, e.g. you@example.com.';
    } else {
        $stmt = $conn->prepare('SELECT id, name FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user) {
            $otp = issue_otp($email, 'password_reset');
            $email_sent = send_otp_mail($email, $otp, 'password_reset');
            $reset_code = $otp;
            $reset_email = $email;
        } else {
            set_flash('info', 'If an account exists for that email, a reset code has been sent.');
            redirect('pages/login.php');
        }
    }
}

if (isset($reset_code)) {
    $page_title = 'Reset password';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="form-card lg">
        <div class="form-head">
            <h2>Enter reset code</h2>
            <p class="muted">Enter the 6-digit code from <strong><?php echo e($reset_email); ?></strong> along with your new password.</p>
        </div>
        <div class="notice">
            <i class="fa-solid fa-envelope-circle-check"></i>
            <span><?php echo $email_sent ? 'Check your inbox (and spam folder). The code expires in 5 minutes.' : 'We couldn\'t send the email, so here\'s your code:'; ?></span>
            <?php if (!$email_sent): ?>
                <span style="display:block;margin-top:8px;font-weight:800;letter-spacing:3px;font-size:20px;color:var(--brand-700);"><?php echo e($reset_code); ?></span>
            <?php endif; ?>
        </div>
        <form method="post" action="<?php echo base_url('pages/reset_password.php'); ?>" style="margin-top:22px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="email" value="<?php echo e($reset_email); ?>">
            <div class="form-group">
                <label for="rcode">Reset code</label>
                <div class="input-group">
                    <i class="fa-solid fa-shield-halved"></i>
                    <input type="text" id="rcode" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" autocomplete="one-time-code" required>
                </div>
                <?php field_hint('The 6-digit code from the email. Numbers only, no spaces or dashes.'); ?>
            </div>
            <div class="form-group">
                <label for="rpassword">New password</label>
                <div class="input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="rpassword" name="password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" required>
                    <button type="button" class="pw-toggle" data-target="rpassword" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                </div>
                <p class="form-hint">Your new password needs:</p>
                <ul class="pw-requirements" id="pwRequirements">
                    <li data-req="length">At least 8 characters</li>
                    <li data-req="letter">At least one letter</li>
                    <li data-req="number">At least one number</li>
                    <li data-req="special">At least one special character</li>
                </ul>
            </div>
            <div class="form-group">
                <label for="rconfirm">Confirm new password</label>
                <div class="input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="rconfirm" name="confirm" autocomplete="new-password" minlength="8" placeholder="Repeat your new password" required>
                    <button type="button" class="pw-toggle" data-target="rconfirm" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                </div>
                <?php field_hint('Retype the same password you entered above.'); ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-key"></i> Reset password</button>
            <p class="form-foot" style="margin-top:20px;">Remembered it? <a href="<?php echo base_url('pages/login.php'); ?>">Log in</a></p>
        </form>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$page_title = 'Forgot Password';
require __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <div class="form-head">
        <h2>Forgot your password?</h2>
        <p class="muted">Enter the email on your account and we'll send you a 6-digit reset code.</p>
    </div>
    <?php if (!empty($errors['general'])): ?>
        <div class="toast toast-error" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?php echo e($errors['general']); ?></span>
            <button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>
    <form method="post" action="">
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'email'); ?>">
            <label for="email">Email</label>
            <div class="input-group">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" id="email" name="email" value="<?php echo e($email); ?>" placeholder="you@example.com" autocomplete="email" required>
            </div>
            <?php field_hint('We\'ll email a 6-digit reset code to this address.'); ?>
            <?php field_error($errors, 'email'); ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-paper-plane"></i> Send reset code</button>
        <p class="form-foot">Remembered your password? <a href="<?php echo base_url('pages/login.php'); ?>">Log in</a></p>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

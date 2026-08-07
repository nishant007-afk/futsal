<?php
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect('index.php');
}

$email = trim($_POST['email'] ?? ($_GET['email'] ?? ''));
$errors = [];
$demoCode = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $resend = !empty($_POST['resend']);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address, e.g. you@example.com.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND email_verified = 0');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            set_flash('info', 'This email is already verified or no pending account exists.');
            redirect('pages/login.php');
        }

        if ($resend) {
            $cooldown = otp_send_cooldown($email, 'email_verify');
            if ($cooldown > 0) {
                set_flash_error(
                    'You\'re requesting too many codes.',
                    'Wait ' . $cooldown . 's before asking for another one.',
                    'Check your inbox for the code we already sent, then try again shortly.',
                    'pages/verify.php?email=' . urlencode($email)
                );
                redirect('pages/login.php');
            }
            $otp = issue_otp($email, 'email_verify');
            $sent = send_otp_mail($email, $otp, 'email_verify');
            set_flash('success', $sent ? 'A new code has been sent to ' . $email . '.' : 'Email delivery is unavailable right now, so your code is shown below.');
            if (!$sent) {
                $demoCode = $otp;
            }
        } else {
            $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
            if ($code === '') {
                $errors['code'] = 'Enter the 6-digit code from your email.';
            } elseif (verify_otp($email, 'email_verify', $code)) {
                $stmt = $conn->prepare('UPDATE users SET email_verified = 1, verify_token = NULL WHERE id = ?');
                $stmt->bind_param('i', $user['id']);
                $stmt->execute();
                set_flash('success', 'Your email is verified! You can now log in.');
                redirect('pages/login.php');
            } else {
                $left = otp_attempts_left($email, 'email_verify');
                $errors['code'] = $left > 0
                    ? 'That code didn\'t work or it has expired. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.'
                    : 'Too many wrong attempts. Request a new code.';
            }
        }
    }
}

$page_title = 'Verify Email';
require __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <div class="form-head">
        <h2>Email verification</h2>
        <p class="muted">Enter the 6-digit code we sent to your email.</p>
    </div>

    <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

    <?php if ($demoCode): ?>
        <div class="notice" style="margin-top:14px;">
            <i class="fa-solid fa-circle-info"></i>
            <span><strong>Email couldn't be sent</strong> right now, so use this code: <strong style="letter-spacing:3px;font-size:18px;color:var(--brand-700);"><?php echo e($demoCode); ?></strong></span>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo base_url('pages/verify.php'); ?>" style="margin-top:18px;" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="email" value="<?php echo e($email); ?>">
        <input type="hidden" name="resend" value="0">
        <div class="form-group<?php echo has_error($errors, 'email'); ?>">
            <label for="email">Email <span class="req">*</span></label>
            <div class="input-group">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" id="email" name="email" value="<?php echo e($email); ?>" autocomplete="email" required>
            </div>
            <?php field_error($errors, 'email'); ?>
        </div>
        <div class="form-group<?php echo has_error($errors, 'code'); ?>">
            <label for="vcode">Verification code <span class="req">*</span></label>
            <div class="input-group">
                <i class="fa-solid fa-shield-halved"></i>
                <input type="text" id="vcode" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" autocomplete="one-time-code" spellcheck="false" required>
            </div>
            <?php field_error($errors, 'code'); ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-check"></i> Verify email</button>
        <button type="submit" name="resend" value="1" formnovalidate class="btn btn-ghost btn-block" style="margin-top:10px;"><i class="fa-solid fa-rotate-right"></i> Resend code</button>
    </form>
    <p class="form-foot"><a href="<?php echo base_url('pages/register.php'); ?>">Create a new account</a></p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

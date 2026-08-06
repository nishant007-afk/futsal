<?php
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) {
    redirect('pages/login.php');
}

$pending = $_SESSION['pending_password_change'] ?? null;
if (!$pending || !isset($pending['user_id'], $pending['hash']) || (int)$pending['user_id'] !== (int)$_SESSION['user_id']) {
    redirect('pages/change_password.php');
}

$me = current_user();
$errors = [];
$codeSent = false;
$demoCode = null;

$cooldown = otp_send_cooldown($me['email'], 'password_change');
$resendLeft = max(0, $cooldown);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $cooldown === 0) {
    $code = issue_otp($me['email'], 'password_change');
    if (send_otp_mail($me['email'], $code, 'password_change')) {
        $codeSent = true;
    } else {
        $codeSent = true;
        $demoCode = $code;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $otp = preg_replace('/\D/', '', $_POST['otp'] ?? '');

    if (!empty($_POST['resend'])) {
        $cooldown = otp_send_cooldown($me['email'], 'password_change');
        if ($cooldown > 0) {
            set_flash_error(
                'You\'re sending codes too quickly.',
                'Wait ' . $cooldown . 's before requesting another code.',
                'Check your inbox for the latest code, or try again shortly.',
                'pages/change_password_otp.php'
            );
            redirect('pages/change_password_otp.php');
        }
        $code = issue_otp($me['email'], 'password_change');
        if (send_otp_mail($me['email'], $code, 'password_change')) {
            set_flash('success', 'A new security code has been sent to your email.');
            redirect('pages/change_password_otp.php');
        } else {
            $codeSent = true;
            $demoCode = $code;
        }
    } else {
        if ($otp === '') {
            $errors['otp'] = 'Enter the 6-digit security code from your email.';
        } elseif (verify_otp($me['email'], 'password_change', $otp)) {
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $pending['hash'], $pending['user_id']);
            $stmt->execute();
            unset($_SESSION['pending_password_change']);
            set_flash('success', 'Your password has been updated.');
            redirect('index.php');
        } else {
            $left = otp_attempts_left($me['email'], 'password_change');
            $errors['otp'] = $left > 0
                ? 'That code didn\'t work or it has expired. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.'
                : 'Too many wrong attempts. Request a new code.';
        }
    }
}

$page_title = 'Confirm Password Change';
?>

<div class="form-card lg" style="margin-top:44px;">
    <div class="form-head">
        <h2>Confirm with a security code</h2>
        <p class="muted">We just sent a 6-digit security code to <strong><?php echo e($me['email']); ?></strong>. Enter it below to finish changing your password.</p>
    </div>

    <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

    <?php if ($codeSent): ?>
        <div class="notice">
            <i class="fa-solid fa-envelope-circle-check"></i>
            <span><?php echo $demoCode ? '<strong>Email couldn\'t be sent</strong> right now, so use this code: <strong style="letter-spacing:3px;font-size:18px;color:var(--brand-700);">' . e($demoCode) . '</strong>' : 'Check your inbox (and spam folder). The code expires in 5 minutes.'; ?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="" novalidate>
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'otp'); ?>">
            <label for="otp">Security code</label>
            <div class="input-group">
                <i class="fa-solid fa-shield-halved"></i>
                <input type="text" id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" autocomplete="one-time-code" spellcheck="false" required>
            </div>
            <?php field_error($errors, 'otp'); ?>
        </div>

        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-key"></i> Confirm & change password</button>
        <button type="submit" name="resend" value="1" formnovalidate class="btn btn-ghost btn-block" style="margin-top:10px;"><i class="fa-solid fa-rotate-right"></i> Resend code</button>
        <p class="form-foot"><a href="<?php echo base_url('pages/change_password.php'); ?>">Start over</a></p>
    </form>
</div>

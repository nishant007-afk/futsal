<?php
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) {
    redirect('pages/login.php');
}

$pending = $_SESSION['pending_email_change'] ?? null;
if (!$pending || !isset($pending['user_id'], $pending['new_email']) || (int)$pending['user_id'] !== (int)$_SESSION['user_id']) {
    redirect('pages/profile.php');
}

$me = current_user();
$errors = [];
$codeSent = false;
$demoCode = null;
$newEmail = $pending['new_email'];

$cooldown = otp_send_cooldown($newEmail, 'email_change');
$resendLeft = max(0, $cooldown);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $cooldown === 0) {
    $code = issue_otp($newEmail, 'email_change');
    if (send_otp_mail($newEmail, $code, 'email_change')) {
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
        $cooldown = otp_send_cooldown($newEmail, 'email_change');
        if ($cooldown > 0) {
            set_flash_error(
                'You\'re sending codes too quickly.',
                'Wait ' . format_otp_wait($cooldown) . ' before requesting another code.',
                'Check your inbox for the latest code, or try again shortly.',
                'pages/change_email_otp.php'
            );
            redirect('pages/change_email_otp.php');
        }
        $code = issue_otp($newEmail, 'email_change');
        if (send_otp_mail($newEmail, $code, 'email_change')) {
            set_flash('success', 'A new verification code has been sent to ' . $newEmail . '.');
            redirect('pages/change_email_otp.php');
        } else {
            $codeSent = true;
            $demoCode = $code;
        }
    } else {
        if ($otp === '') {
            $errors['otp'] = 'Enter the 6-digit verification code from your email.';
        } elseif (verify_otp($newEmail, 'email_change', $otp)) {
            $check = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $check->bind_param('si', $newEmail, $me['id']);
            $check->execute();
            if ($check->get_result()->fetch_assoc()) {
                unset($_SESSION['pending_email_change']);
                set_flash_error(
                    'That email is already in use.',
                    'Another account is already registered with ' . $newEmail . '.',
                    'Go back and choose a different email address.',
                    'pages/profile.php'
                );
                redirect('pages/profile.php');
            }
            $stmt = $conn->prepare('UPDATE users SET email = ? WHERE id = ?');
            $stmt->bind_param('si', $newEmail, $me['id']);
            $stmt->execute();
            unset($_SESSION['pending_email_change']);
            set_flash('success', 'Your email has been updated to ' . $newEmail . '.');
            redirect('pages/profile.php');
        } else {
            $left = otp_attempts_left($newEmail, 'email_change');
            $errors['otp'] = $left > 0
                ? 'That code didn\'t work or it has expired. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.'
                : 'Too many wrong attempts. Request a new code.';
        }
    }
}

$page_title = 'Verify Your New Email';
require __DIR__ . '/../includes/header.php';
?>

<div class="form-card lg" style="margin-top:44px;">
 <div class="form-head">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="nav-back mob-title-back" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Confirm your new email</h2>
    </div>
 </div>

    <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

    <?php if ($codeSent): ?>
        <div class="notice" style="margin-top:8px;">
            <i class="fa-solid fa-envelope-circle-check"></i>
            <span><?php echo $demoCode ? '<strong>Email couldn\'t be sent</strong> right now, so use this code: <strong style="letter-spacing:3px;font-size:18px;color:var(--brand-700);">' . e($demoCode) . '</strong>' : 'Check your inbox (and spam folder). The code expires in 5 minutes.'; ?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="" novalidate style="margin-top:10px;">
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'otp'); ?>">
            <label for="otp">Verification code <span class="req">*</span></label>
            <input type="hidden" name="otp" class="otp-source" required>
            <div class="otp-boxes" role="group" aria-label="Verification code">
                <input class="otp-box" type="tel" id="otp" inputmode="numeric" maxlength="1" pattern="[0-9]*" autocomplete="one-time-code" spellcheck="false" aria-label="First digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Second digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Third digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Fourth digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Fifth digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Sixth digit">
            </div>
            <?php field_error($errors, 'otp'); ?>
        </div>

        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-circle-check"></i> Confirm & change email</button>
        <button type="submit" name="resend" value="1" formnovalidate class="btn btn-ghost btn-block" style="margin-top:10px;"><i class="fa-solid fa-rotate-right"></i> Resend code</button>
        <p class="form-foot"><a href="<?php echo base_url('pages/profile.php'); ?>">Cancel and go back</a></p>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    });
</script>
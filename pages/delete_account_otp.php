<?php
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) {
    redirect('pages/login.php');
}

$pending = $_SESSION['pending_account_delete'] ?? null;
if (!$pending || !isset($pending['user_id'], $pending['email']) || (int)$pending['user_id'] !== (int)$_SESSION['user_id']) {
    redirect('pages/security.php');
}

$me = current_user();
$errors = [];
$codeSent = false;
$email = $pending['email'];

$cooldown = otp_send_cooldown($email, 'delete_account');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $cooldown === 0) {
    $code = issue_otp($email, 'delete_account');
    if (send_otp_mail($email, $code, 'delete_account')) {
        $codeSent = true;
    } else {
        error_log('OTP email failed for delete_account to ' . $email);
        $codeSent = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $otp = preg_replace('/\D/', '', $_POST['otp'] ?? '');

    if (!empty($_POST['resend'])) {
        $cooldown = otp_send_cooldown($email, 'delete_account');
        if ($cooldown > 0) {
            set_flash_error(
                'You\'re sending codes too quickly.',
                'Wait ' . format_otp_wait($cooldown) . ' before requesting another code.',
                'Check your inbox for the latest code, or try again shortly.',
                'pages/delete_account_otp.php'
            );
            redirect('pages/delete_account_otp.php');
        }
        $code = issue_otp($email, 'delete_account');
        if (send_otp_mail($email, $code, 'delete_account')) {
            set_flash('success', 'A new security code has been sent to your email.');
            redirect('pages/delete_account_otp.php');
        } else {
            error_log('OTP email failed for delete_account to ' . $email);
            $codeSent = true;
        }
    } else {
        if ($otp === '') {
            $errors['otp'] = 'Enter the 6-digit security code from your email.';
        } elseif (verify_otp($email, 'delete_account', $otp)) {
            if (delete_user_account((int)$me['id'], $email)) {
                session_unset();
                session_destroy();
                session_start();
                set_flash('success', 'Your account has been deleted. We\'re sorry to see you go.');
                redirect('index.php');
            }
            set_flash_error(
                'We couldn\'t delete your account.',
                'The server hit an error while removing your data.',
                'Please try again in a moment.',
                'pages/security.php'
            );
            redirect('pages/security.php');
        } else {
            $left = otp_attempts_left($email, 'delete_account');
            $errors['otp'] = $left > 0
                ? 'That code didn\'t work or it has expired. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.'
                : 'Too many wrong attempts. Request a new code.';
        }
    }
}

$page_title = 'Confirm Account Deletion';
require __DIR__ . '/../includes/header.php';
?>

<div class="form-card lg">
 <div class="form-head">
    <div class="title-back-row">
        <a href="<?php echo base_url('pages/security.php'); ?>" class="page-back-arrow" aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Confirm account deletion</h2>
    </div>
 </div>

    <div class="notice notice-danger mt-8">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span><strong>This is permanent.</strong> Your account, bookings, reviews and saved courts will be removed. This can't be undone.</span>
    </div>

    <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

    <?php if ($codeSent): ?>
        <div class="notice mt-8">
            <i class="fa-solid fa-envelope-circle-check"></i>
            <span>Check your inbox (and spam folder). The code expires in 5 minutes.</span>
        </div>
    <?php endif; ?>

    <form method="post" action="" novalidate role="form" class="mt-10">
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'otp'); ?>">
            <label for="otp">Security code <span class="req">*</span></label>
            <input type="hidden" name="otp" class="otp-source" required aria-required="true">
            <div class="otp-boxes" role="group" aria-label="Security code">
                <input class="otp-box" type="tel" id="otp" inputmode="numeric" maxlength="1" pattern="[0-9]*" autocomplete="one-time-code" spellcheck="false" aria-label="First digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Second digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Third digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Fourth digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Fifth digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Sixth digit">
            </div>
            <?php field_error($errors, 'otp'); ?>
        </div>

        <button type="submit" class="btn btn-danger btn-block"><i class="fa-solid fa-trash-can"></i> Permanently delete my account</button>
        <button type="submit" name="resend" value="1" formnovalidate class="btn btn-ghost btn-block mt-10"><i class="fa-solid fa-rotate-right"></i> Resend code</button>
        <p class="form-foot"><a href="<?php echo base_url('pages/security.php'); ?>">Cancel and keep my account</a></p>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

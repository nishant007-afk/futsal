<?php
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect('index.php');
}

$pending = $_SESSION['pending_2fa_login'] ?? $_SESSION['pending_login'] ?? null;
if (!$pending || empty($pending['email'])) {
    set_flash('info', 'Start by logging in with your password.');
    redirect('pages/login.php');
}

$email = $pending['email'];
$senderEmail = env('MAIL_FROM', 'goalspace.noreply@gmail.com');
$resendCount = (int)($pending['resend_count'] ?? 0);
$cooldown = otp_send_cooldown($email, 'login');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (rate_limit_exceeded('otp_ip', 30, 300)) {
        set_flash_error(
            'Too many verification attempts from this location.',
            'We blocked further OTP checks for a few minutes.',
            'Wait a moment, then try again with the newest code.',
            'pages/otp_verify.php'
        );
        redirect('pages/otp_verify.php');
    }
    $resend = !empty($_POST['resend']);

    if ($resend) {
        $cooldown = otp_send_cooldown($email, 'login');
        if ($cooldown > 0) {
            set_flash('info', 'Please wait ' . format_otp_wait($cooldown) . ' before requesting another code. The previous code remains active.');
            redirect('pages/otp_verify.php');
        }
        $otp = issue_otp($email, 'login');
        $sent = send_otp_mail($email, $otp, 'login');
        $newCount = $resendCount + 1;

        // Clear any previous pending 2FA/login session data
        unset($_SESSION['pending_login'], $_SESSION['pending_2fa_login']);
        $_SESSION['pending_2fa_login'] = [
            'user_id' => $pending['user_id'] ?? 0,
            'email' => $email,
            'role' => $pending['role'] ?? 'player',
            'sent' => $sent,
            'resend_count' => $newCount,
            'resent_at' => time()
        ];

        if ($sent) {
            if ($newCount === 1) {
                set_flash('success', 'A fresh code was sent to ' . $email . '. We invalidated any previous code, so please use this newest one.');
            } elseif ($newCount === 2) {
                set_flash('success', 'Second code dispatched! If you still don\'t see it, search for "' . $senderEmail . '" in your Spam, Junk, or Promotions folder.');
            } else {
                set_flash('success', 'Code #' . ($newCount + 1) . ' dispatched to ' . $email . '. Note that delivery can take up to 30 seconds depending on your email provider.');
            }
        } else {
            set_flash('error', 'We generated a new security code, but email delivery is blocked by the mail server (Brevo/SMTP). Check server configuration.');
        }
        redirect('pages/otp_verify.php');
    } else {
        $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
        if ($code === '') {
            $errors['code'] = 'Enter the 6-digit code from your email.';
        } elseif (verify_otp($email, 'login', $code)) {
            $stmt = $conn->prepare('SELECT id, name, role FROM users WHERE id = ? AND email = ?');
            $stmt->bind_param('is', $pending['user_id'], $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if (!$user) {
                unset($_SESSION['pending_login'], $_SESSION['pending_2fa_login']);
                set_flash_error(
                    'We couldn\'t finish logging you in.',
                    'Your login session expired while verifying the code.',
                    'Log in again and it should only take a minute.',
                    'pages/login.php'
                );
                redirect('pages/login.php');
            }
            unset($_SESSION['pending_2fa_login'], $_SESSION['pending_login']);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            if ($user['role'] === 'admin') {
                redirect('admin/dashboard.php');
            } elseif ($user['role'] === 'manager') {
                redirect('manager/dashboard.php');
            } else {
                redirect('index.php');
            }
        } else {
            $left = otp_attempts_left($email, 'login');
            if ($left <= 0) {
                $errors['code'] = 'Too many failed attempts. This code is locked  -  click "Resend code" below for a brand new code.';
            } elseif ($resendCount > 0) {
                $errors['code'] = 'That code didn\'t match. Since you resent the code, please make sure you\'re entering the newest one sent by ' . $senderEmail . '. (' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left)';
            } else {
                $errors['code'] = 'That code didn\'t work or it has expired. (' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left)';
            }
        }
    }
}

$page_title = 'Verification Code';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
<div class="auth-card form-card">
    <div class="auth-topline">
        <div class="title-back-row">
            <a href="<?php echo base_url('pages/login.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
            <h1>Verification code</h1>
        </div>
        <p>Enter the 6-digit code sent to <strong><?php echo e($email); ?></strong>.</p>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="auth-msg auth-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo e($errors['general']); ?></div>
    <?php endif; ?>

    <form method="post" action="" novalidate id="otpForm">
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'code'); ?>">
            <label for="code">Verification code <span class="req">*</span></label>
            <input type="hidden" name="code" class="otp-source" required aria-required="true">
            <div class="otp-boxes" role="group" aria-label="Login code">
                <input class="otp-box" type="tel" id="code" inputmode="numeric" maxlength="1" pattern="[0-9]*" autocomplete="one-time-code" spellcheck="false" aria-label="First digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Second digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Third digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Fourth digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Fifth digit">
                <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Sixth digit">
            </div>
            <?php field_error($errors, 'code'); ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-right-to-bracket"></i> Verify and log in</button>
    </form>

    <form method="post" action="" id="resendForm" class="mt-10">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="resend" value="1">
        <button type="submit" id="resendBtn" class="btn btn-ghost btn-block" <?php echo $cooldown > 0 ? 'data-cooldown="' . (int)$cooldown . '" disabled style="opacity:0.65;cursor:not-allowed;"' : ''; ?>>
            <i class="fa-solid fa-rotate-right"></i>
            <span id="resendBtnText"><?php echo $cooldown > 0 ? 'Resend available in ' . (int)$cooldown . 's' : ($resendCount > 0 ? 'Resend another code' : 'Resend code'); ?></span>
        </button>
    </form>
    <p class="auth-foot"><a href="<?php echo base_url('pages/login.php'); ?>">Use a different account</a></p>
</div>
</div>

<script>
(function() {
    var btn = document.getElementById('resendBtn');
    var text = document.getElementById('resendBtnText');
    if (!btn || !text) return;
    var cd = parseInt(btn.getAttribute('data-cooldown') || '0', 10);
    if (cd > 0) {
        btn.disabled = true;
        btn.style.opacity = '0.65';
        btn.style.cursor = 'not-allowed';
        var timer = setInterval(function() {
            cd--;
            if (cd <= 0) {
                clearInterval(timer);
                btn.disabled = false;
                btn.style.opacity = '';
                btn.style.cursor = '';
                text.textContent = 'Resend code';
                btn.removeAttribute('data-cooldown');
            } else {
                text.textContent = 'Resend available in ' + cd + 's';
            }
        }, 1000);
    }
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

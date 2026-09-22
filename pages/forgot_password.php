<?php
require_once __DIR__ . '/../config/db.php';

// If user requested to start fresh with a different email
if (isset($_GET['action']) && $_GET['action'] === 'new') {
    unset($_SESSION['pending_password_reset']);
    redirect('pages/forgot_password.php');
}

$pending = $_SESSION['pending_password_reset'] ?? null;
$senderEmail = env('MAIL_FROM', 'goalspace.noreply@gmail.com');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Honeypot: bots fill this, silently reject
    if (is_honeypot_filled()) {
        $errors['general'] = 'Request failed. Please try again.';
    }

    if (isset($_POST['resend'])) {
        $resendEmail = trim($pending['email'] ?? ($_POST['email'] ?? ''));
        if ($resendEmail === '' || !filter_var($resendEmail, FILTER_VALIDATE_EMAIL)) {
            unset($_SESSION['pending_password_reset']);
            redirect('pages/forgot_password.php');
        }

        $cooldown = otp_send_cooldown($resendEmail, 'password_reset');
        if ($cooldown > 0) {
            set_flash('info', 'Please wait ' . format_otp_wait($cooldown) . ' before requesting another reset code. Your current code is still active.');
            redirect('pages/forgot_password.php');
        }

        $otp = issue_otp($resendEmail, 'password_reset');
        $sent = send_otp_mail($resendEmail, $otp, 'password_reset');
        $newCount = ((int)($pending['resend_count'] ?? 0)) + 1;

        $_SESSION['pending_password_reset'] = [
            'email' => $resendEmail,
            'resend_count' => $newCount,
            'resent_at' => time()
        ];

        if ($sent) {
            if ($newCount === 1) {
                set_flash('success', 'A fresh code was sent to ' . $resendEmail . '. We invalidated any previous code, so please use this newest one.');
            } elseif ($newCount === 2) {
                set_flash('success', 'Second reset code dispatched! If you still don\'t see it, search for "' . $senderEmail . '" in your Spam, Junk, or Promotions folder.');
            } else {
                set_flash('success', 'Reset code #' . ($newCount + 1) . ' sent. Delivery can take up to 30 seconds depending on your email provider.');
            }
        } else {
            set_flash('error', 'New reset code generated, but email delivery encountered a server issue. Please try again shortly.');
        }
        redirect('pages/forgot_password.php');

    } elseif (isset($_POST['code'])) {
        // Step 2: verify the 6-digit reset code
        $verifyEmail = trim($pending['email'] ?? ($_POST['email'] ?? ''));
        $code = preg_replace('/\D/', '', $_POST['code'] ?? '');

        if ($verifyEmail === '' || !filter_var($verifyEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address, e.g. you@example.com.';
        }
        if ($code === '') {
            $errors['code'] = 'Enter the 6-digit code from your email.';
        }

        if (!$errors && verify_otp($verifyEmail, 'password_reset', $code)) {
            unset($_SESSION['pending_password_reset']);
            $_SESSION['reset_verified'] = ['email' => $verifyEmail, 'ts' => time()];
            redirect('pages/reset_password.php');
        } elseif (!$errors) {
            $left = otp_attempts_left($verifyEmail, 'password_reset');
            $resendCount = (int)($pending['resend_count'] ?? 0);
            if ($left <= 0) {
                $errors['code'] = 'Too many failed attempts. This code is locked — click "Resend code" below for a fresh one.';
            } elseif ($resendCount > 0) {
                $errors['code'] = 'That code didn\'t match. Since you resent the code, make sure you\'re entering the newest one from ' . $senderEmail . '. (' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left)';
            } else {
                $errors['code'] = 'That code didn\'t work or it has expired. (' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left)';
            }
        }

    } else {
        // Step 1: send a reset code to the requested email
        $inputEmail = trim($_POST['email'] ?? '');
        if (!filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address, e.g. you@example.com.';
        } else {
            $stmt = $conn->prepare('SELECT id, name FROM users WHERE email = ?');
            $stmt->bind_param('s', $inputEmail);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user) {
                $cooldown = otp_send_cooldown($inputEmail, 'password_reset');
                if ($cooldown > 0) {
                    set_flash('info', 'A reset code was already sent recently. Please wait ' . format_otp_wait($cooldown) . ' before requesting another.');
                } else {
                    $otp = issue_otp($inputEmail, 'password_reset');
                    $email_sent = send_otp_mail($inputEmail, $otp, 'password_reset');
                    if ($email_sent) {
                        set_flash('success', 'A 6-digit reset code has been sent to ' . $inputEmail . '.');
                    } else {
                        set_flash('error', 'We generated a reset code, but email delivery encountered an issue. Please check back shortly.');
                    }
                }
                $_SESSION['pending_password_reset'] = [
                    'email' => $inputEmail,
                    'resend_count' => 0,
                    'created_at' => time()
                ];
                redirect('pages/forgot_password.php');
            } else {
                set_flash('info', 'If an account exists for that email, a reset code has been sent.');
                redirect('pages/forgot_password.php');
            }
        }
    }
}

// -------------------------------------------------------------
// STEP 2: Code Verification View (when email is in pending session)
// -------------------------------------------------------------
if (!empty($pending['email'])) {
    $resetEmail = $pending['email'];
    $resendCount = (int)($pending['resend_count'] ?? 0);
    $cooldown = otp_send_cooldown($resetEmail, 'password_reset');

    $page_title = 'Enter reset code';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="auth-wrap">
    <div class="auth-card form-card">
        <a href="<?php echo base_url('pages/login.php'); ?>" class="auth-brand">
            <span class="auth-brand-mark"><i class="fa-solid fa-futbol"></i></span>
            <span class="auth-brand-name">GoalSpace</span>
        </a>
        <div class="auth-topline">
            <h1>Reset your password</h1>
            <p>Enter the 6-digit reset code sent to <strong><?php echo e($resetEmail); ?></strong> from <strong><?php echo e($senderEmail); ?></strong>.</p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="auth-msg auth-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo e($errors['general']); ?></div>
        <?php endif; ?>

        <div class="otp-hint-card">
            <div class="otp-hint-header" onclick="this.parentElement.classList.toggle('expanded')" role="button" tabindex="0">
                <i class="fa-solid fa-circle-question"></i>
                <span>Not seeing the email from <?php echo e($senderEmail); ?>?</span>
                <i class="fa-solid fa-chevron-down otp-hint-chevron"></i>
            </div>
            <div class="otp-hint-body">
                <ul>
                    <li>Check your <strong>Spam / Junk</strong> or <strong>Promotions</strong> folder.</li>
                    <li>Search your inbox for <code>from:<?php echo e($senderEmail); ?></code> or <code>GoalSpace</code>.</li>
                    <li>Mark the email as <em>Not Spam</em> so future codes land in your inbox.</li>
                    <li>Confirm that your email <strong><?php echo e($resetEmail); ?></strong> is spelled correctly.</li>
                </ul>
            </div>
        </div>

        <form method="post" action="" novalidate id="otpForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="email" value="<?php echo e($resetEmail); ?>">
            <div class="form-group<?php echo has_error($errors, 'code'); ?>">
                <label for="code">Reset code <span class="req">*</span></label>
                <input type="hidden" name="code" class="otp-source" required aria-required="true">
                <div class="otp-boxes" role="group" aria-label="Reset code">
                    <input class="otp-box" type="tel" id="code" inputmode="numeric" maxlength="1" pattern="[0-9]*" autocomplete="one-time-code" spellcheck="false" aria-label="First digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Second digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Third digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Fourth digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Fifth digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Sixth digit">
                </div>
                <?php field_error($errors, 'code'); ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-shield-halved"></i> Verify code</button>
        </form>

        <form method="post" action="" id="resendForm" class="mt-10">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="resend" value="1">
            <button type="submit" id="resendBtn" class="btn btn-ghost btn-block" <?php echo $cooldown > 0 ? 'data-cooldown="' . (int)$cooldown . '" disabled style="opacity:0.65;cursor:not-allowed;"' : ''; ?>>
                <i class="fa-solid fa-rotate-right"></i>
                <span id="resendBtnText"><?php echo $cooldown > 0 ? 'Resend available in ' . (int)$cooldown . 's' : ($resendCount > 0 ? 'Resend another code' : 'Resend code'); ?></span>
            </button>
        </form>

        <p class="auth-foot">Mistyped your email? <a href="<?php echo base_url('pages/forgot_password.php?action=new'); ?>">Use a different email</a></p>
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
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// -------------------------------------------------------------
// STEP 1: Enter Email View
// -------------------------------------------------------------
$emailVal = trim($_POST['email'] ?? '');

$page_title = 'Forgot Password';
$page_description = 'Reset your GoalSpace password safely. Enter your email and we\'ll send you a link to get back into your account.';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-card form-card">
        <a href="<?php echo base_url('pages/login.php'); ?>" class="auth-brand">
            <span class="auth-brand-mark"><i class="fa-solid fa-futbol"></i></span>
            <span class="auth-brand-name">GoalSpace</span>
        </a>
        <div class="auth-topline">
            <h1>Forgot your password?</h1>
            <p>Enter the email on your account and we'll send you a 6-digit reset code.</p>
        </div>
        <?php if (!empty($errors['general'])): ?>
            <div class="auth-msg auth-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo e($errors['general']); ?></div>
        <?php endif; ?>
        <form method="post" action="" novalidate>
            <?php echo csrf_field(); ?>
            <?php honeypot_field(); ?>
            <div class="form-group<?php echo has_error($errors, 'email'); ?>">
                <div class="input-group floating">
                    <input type="email" id="email" name="email" value="<?php echo e($emailVal); ?>" placeholder=" " autocomplete="email" required aria-required="true" data-check-email="exists">
                    <label for="email">Email <span class="req">*</span></label>
                </div>
                <?php field_error($errors, 'email'); ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-paper-plane"></i> Send reset code</button>
        </form>
        <p class="auth-foot">Remembered your password? <a href="<?php echo base_url('pages/login.php'); ?>">Log in</a></p>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

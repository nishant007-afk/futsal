<?php
require_once __DIR__ . '/../config/db.php';

$me = is_logged_in() ? current_user() : null;
$email = $me ? $me['email'] : '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');

    // Honeypot: bots fill this, silently reject
    if (is_honeypot_filled()) {
        $errors['general'] = 'Request failed. Please try again.';
    }

    if (isset($_POST['code'])) {
        // Step 2: verify the 6-digit reset code before even touching the password
        $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address, e.g. you@example.com.';
        }
        if ($code === '') {
            $errors['code'] = 'Enter the 6-digit code from your email.';
        }

        if (!$errors && verify_otp($email, 'password_reset', $code)) {
            $_SESSION['reset_verified'] = ['email' => $email, 'ts' => time()];
            redirect('pages/reset_password.php');
        } elseif (!$errors) {
            $left = otp_attempts_left($email, 'password_reset');
            $errors['code'] = $left > 0
                ? 'That code didn\'t work or it has expired. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.'
                : 'Too many wrong attempts. Request a new code.';
        }
        $reset_email = $email;
    } else {
        // Step 1: send a reset code to the requested email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address, e.g. you@example.com.';
        } else {
            $stmt = $conn->prepare('SELECT id, name FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user) {
                $cooldown = otp_send_cooldown($email, 'password_reset');
                if ($cooldown > 0) {
                    set_flash_error(
                        'You\'re requesting too many codes.',
                        'Wait ' . format_otp_wait($cooldown) . ' before asking for another one.',
                        'Check your inbox for the code we already sent, then try again shortly.',
                        'pages/forgot_password.php'
                    );
                    redirect('pages/login.php');
                }
                $otp = issue_otp($email, 'password_reset');
                $email_sent = send_otp_mail($email, $otp, 'password_reset');
                if (!$email_sent) {
                    error_log('OTP email delivery failed for purpose: password_reset');
                }
                $reset_code = null;
                $reset_email = $email;
            } else {
                set_flash('info', 'If an account exists for that email, a reset code has been sent.');
                redirect('pages/login.php');
            }
        }
    }
}

if (isset($reset_email)) {
    $page_title = 'Enter reset code';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="auth-wrap">
    <div class="auth-card form-card lg">
        <a href="<?php echo base_url('pages/login.php'); ?>" class="auth-brand">
            <span class="auth-brand-mark"><i class="fa-solid fa-futbol"></i></span>
            <span class="auth-brand-name">GoalSpace</span>
        </a>
        <div class="auth-topline">
            <h1>Enter reset code</h1>
            <p>First, confirm it's you. Enter the 6-digit code from <strong><?php echo e($reset_email); ?></strong>.</p>
        </div>
        <?php if (isset($email_sent)): ?>
<div class="auth-msg auth-success"><i class="fa-solid fa-envelope-circle-check"></i> <span><?php echo $email_sent
    ? 'Check your inbox (and spam folder). The code expires in 5 minutes.'
    : 'Email delivery is unavailable right now. Please try resending in a minute, or contact support.'; ?></span></div>
        <?php endif; ?>
        <?php if (!empty($errors['code'])): ?>
            <div class="auth-msg auth-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo e($errors['code']); ?></div>
        <?php endif; ?>
        <form method="post" action="" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="email" value="<?php echo e($reset_email); ?>">
            <div class="form-group">
                <label for="rcode">Reset code <span class="req">*</span></label>
                <input type="hidden" name="code" class="otp-source" required aria-required="true">
                <div class="otp-boxes" role="group" aria-label="Reset code">
                    <input class="otp-box" type="tel" id="rcode" inputmode="numeric" maxlength="1" pattern="[0-9]*" autocomplete="one-time-code" aria-label="First digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Second digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Third digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Fourth digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Fifth digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Sixth digit">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-shield-halved"></i> Verify code</button>
        </form>
        <p class="auth-foot">Never got it? <a href="<?php echo base_url('pages/forgot_password.php'); ?>">Request another code</a></p>
    </div>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

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
                    <input type="email" id="email" name="email" value="<?php echo e($email); ?>" placeholder=" " autocomplete="email" required aria-required="true" data-check-email="exists">
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

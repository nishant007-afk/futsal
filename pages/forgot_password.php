<?php
require_once __DIR__ . '/../config/db.php';

$me = is_logged_in() ? current_user() : null;
$email = $me ? $me['email'] : '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');

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
                $reset_code = $otp;
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
    <div class="form-card lg">
        <div class="form-head">
            <a href="<?php echo base_url('index.php'); ?>" class="btn-back-home"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>
            <h2>Enter reset code</h2>
            <p class="muted">First, confirm it's you. Enter the 6-digit code from <strong><?php echo e($reset_email); ?></strong>.</p>
        </div>
        <?php if (isset($email_sent)): ?>
            <div class="notice">
                <i class="fa-solid fa-envelope-circle-check"></i>
                <span><?php echo $email_sent ? 'Check your inbox (and spam folder). The code expires in 5 minutes.' : 'We couldn\'t send the email, so here\'s your code:'; ?></span>
                <?php if (!$email_sent && isset($reset_code)): ?>
                    <span style="display:block;margin-top:8px;font-weight:800;letter-spacing:3px;font-size:20px;color:var(--brand-700);"><?php echo e($reset_code); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($errors['code'])): ?>
            <div class="toast toast-error" role="alert">
                <div class="toast-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="toast-content"><div class="toast-msg"><?php echo e($errors['code']); ?></div></div>
                <button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
            </div>
        <?php endif; ?>
        <form method="post" action="" style="margin-top:22px;" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="email" value="<?php echo e($reset_email); ?>">
            <div class="form-group">
                <label for="rcode">Reset code <span class="req">*</span></label>
                <input type="hidden" name="code" class="otp-source" required>
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
            <p class="form-foot" style="margin-top:20px;">Never got it? <a href="<?php echo base_url('pages/forgot_password.php'); ?>">Request another code</a></p>
        </form>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$page_title = 'Forgot Password';
$page_description = 'Reset your GoalSpace password safely. Enter your email and we\'ll send you a link to get back into your account.';
require __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <div class="form-head">
        <a href="<?php echo base_url('index.php'); ?>" class="btn-back-home"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>
        <h2>Forgot your password?</h2>
        <p class="muted">Enter the email on your account and we'll send you a 6-digit reset code.</p>
    </div>
    <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>
    <form method="post" action="" novalidate>
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'email'); ?>">
            <div class="input-group floating">
                <input type="email" id="email" name="email" value="<?php echo e($email); ?>" placeholder=" " autocomplete="email" required>
                <label for="email">Email <span class="req">*</span></label>
            </div>
            <?php field_error($errors, 'email'); ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-paper-plane"></i> Send reset code</button>
        <p class="form-foot">Remembered your password? <a href="<?php echo base_url('pages/login.php'); ?>">Log in</a></p>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

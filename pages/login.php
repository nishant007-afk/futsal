<?php
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect('index.php');
}

$email = '';
$errors = [];
$lock = null;
$suspended = false;
$suspendedEmail = '';
$failuresLeft = null;
$errorTitle = 'Login failed';
$showTimeout = isset($_GET['timeout']) && $_GET['timeout'] === '1';

if (isset($_SESSION['login_lock']) && is_array($_SESSION['login_lock'])) {
    $ll = $_SESSION['login_lock'];
    $remaining = max(0, $ll['ends'] - time());
    if ($remaining > 0) {
        $lock = [
            'seconds' => $remaining,
            'ends' => date('c', $ll['ends']),
            'failures' => (int)($ll['failures'] ?? 0),
        ];
        $email = $_SESSION['login_lock_email'] ?? $email;
        $errors['general'] = 'You\'ve had too many failed attempts. Try again in ' . gmdate('i:s', $remaining) . '.';
    } else {
        unset($_SESSION['login_lock'], $_SESSION['login_lock_email']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' && $password === '') {
        $errors['email'] = 'Enter your email address.';
        $errors['password'] = 'Enter your password.';
    } elseif ($email === '') {
        $errors['email'] = 'Enter your email address.';
    } elseif ($password === '') {
        $errors['password'] = 'Enter your password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address, e.g. you@example.com.';
    } else {
        $key = login_attempt_key($email);
        $state = login_attempt_state($key);

        if ($state['suspended']) {
            $suspended = true;
            $suspendedEmail = login_attempts_email($key);
        } elseif (rate_limit_exceeded('login_ip', 20, 300)) {
            // IP-only rate limit: blocks bots cycling many different emails from one IP.
            // 20 attempts per 5 minutes per IP, regardless of email used.
            $errors['general'] = 'Too many login attempts from your location. Please wait a few minutes and try again.';
        } elseif (is_honeypot_filled()) {
            // Honeypot: bots fill this, silently reject
            $errors['general'] = 'Login failed. Please try again.';
        } else {
            $stmt = $conn->prepare('SELECT id, name, password, role, email_verified, login_count FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            $ok = $user && password_verify($password, $user['password']);

            if ($ok && (int)$user['email_verified'] === 1) {
                // Increment login count for legitimate login
                $newCount = (int)($user['login_count'] ?? 0) + 1;
                $updStmt = $conn->prepare('UPDATE users SET login_count = ? WHERE id = ?');
                $updStmt->bind_param('ii', $newCount, $user['id']);
                $updStmt->execute();

                // Clear login failures on successful password authentication
                clear_login_attempts($key);

                // --- 2FA CHECK: Admin always requires 2FA; non-admin periodic check (every 6th login: 6, 12, 18...) ---
                $requires_2fa = ($user['role'] === 'admin');
                $needs_otp_verify = false;

                if ($requires_2fa) {
                    // Admin: always require OTP verification
                    $needs_otp_verify = true;
                } elseif ($newCount > 1 && $newCount % 6 === 0) {
                    // Non-admin: OTP every 6 logins
                    $needs_otp_verify = true;
                }

                if ($needs_otp_verify) {
                    $otp = issue_otp($email, 'login');
                    $otp_sent = send_otp_mail($email, $otp, 'login');
                    if (!$otp_sent) {
                        error_log('OTP email delivery failed for purpose: login');
                    }
                    session_regenerate_id(true);
                    // Never store the OTP in session – verification uses the DB row only.
                    $_SESSION['pending_2fa_login'] = ['user_id' => (int)$user['id'], 'email' => $email, 'role' => $user['role']];
                    redirect('pages/otp_verify.php');
                }

                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $returnPath = $_SESSION['return_path'] ?? '';
                unset($_SESSION['return_path']);
                // Normalize + only honor same-site app paths (blocks open redirects).
                if (is_string($returnPath) && $returnPath !== '' && $returnPath[0] !== '/') {
                    $returnPath = '/' . ltrim($returnPath, '/');
                }
                if (is_string($returnPath) && $returnPath !== ''
                    && $returnPath[0] === '/'
                    && (!isset($returnPath[1]) || $returnPath[1] !== '/')
                    && !preg_match('#^//[^/]#', $returnPath)
                    && preg_match('#^/(pages|admin|manager|ajax|auth|index\.php)#', $returnPath)
                ) {
                    redirect(ltrim($returnPath, '/'));
                }
                if ($user['role'] === 'admin') {
                    redirect('admin/dashboard.php');
                } elseif ($user['role'] === 'manager') {
                    redirect('manager/dashboard.php');
                } else {
                    redirect('index.php');
                }
            }

            if (!$ok) {
                record_login_failure($key);
            }
            $after = login_attempt_state($key);

            if ($after['suspended']) {
                $suspended = true;
                $suspendedEmail = login_attempts_email($key);
            } elseif ($after['locked']) {
                $lock = [
                    'seconds' => $after['lock_seconds'],
                    'ends' => $after['lock_ends'],
                    'failures' => $after['failures'],
                ];
                $_SESSION['login_lock'] = ['ends' => $after['lock_ends'] ? strtotime($after['lock_ends']) : (time() + $after['lock_seconds']), 'failures' => $after['failures']];
                $_SESSION['login_lock_email'] = $email;
                $errors['general'] = 'Too many failed attempts. Try again in ' . gmdate('i:s', $after['lock_seconds']) . '.';
            } elseif (!$ok) {
                $failuresLeft = 5 - $after['failures'];
                $errorTitle = 'Incorrect password';
                $errors['general'] = 'Attempts remaining: ' . max(0, $failuresLeft);
            } else {
                $errors['general'] = 'Please verify your email before logging in. Check your inbox for the verification link.';
            }
        }
    }
}

$page_title = 'Login';
$page_description = 'Log in to your GoalSpace account to manage bookings, view open slots, and get back on the court.';
require __DIR__ . '/../includes/header.php';
?>

<div class="signup-shell">
    <section class="signup-right">
        <div class="auth-topline">
            <div class="title-back-row">
                <a href="<?php echo base_url('index.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
                <h1 class="auth-title-lg">Sign in to GoalSpace</h1>
            </div>
        </div>

    <?php if ($suspended): ?>
        <?php $cErrors = form_errors(); $cOld = form_old(); ?>
        <div class="toast toast-error" role="alert">
            <div class="toast-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="toast-content">
                <div class="toast-msg"><span>Logins are paused for this account.</span></div>
                <p class="toast-detail toast-reason"><i class="fa-solid fa-circle-question"></i> <span>Too many failed attempts for <strong><?php echo e($suspendedEmail); ?></strong>.</span></p>
                <p class="toast-detail toast-fix"><i class="fa-solid fa-lightbulb"></i> <span>Reach out to an admin below to restore access.</span></p>
            </div>
            <button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="notice mt-16">
            <i class="fa-solid fa-headset"></i>
            <span>Use the form below to reach the admin about this issue.</span>
        </div>

        <form method="post" action="<?php echo base_url('pages/contact_submit.php'); ?>" class="mt-18" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="topic" value="login_locked">
            <div class="form-group">
                <div class="input-group floating">
                    <input type="text" id="subj" name="subject" value="My account is locked after login attempts" placeholder=" " maxlength="200" required aria-required="true">
                    <label for="subj">Subject <span class="req">*</span></label>
                </div>
            </div>
            <div class="form-group<?php echo has_error($cErrors, 'email'); ?>">
                <div class="input-group floating">
                    <input type="email" id="em" name="email" value="<?php echo e(old_value($cOld, 'email', $suspendedEmail)); ?>" placeholder=" " required aria-required="true">
                    <label for="em">Your email <span class="req">*</span></label>
                </div>
                <?php field_error($cErrors, 'email'); ?>
            </div>
            <div class="form-group<?php echo has_error($cErrors, 'message'); ?>">
                <label for="msg">Message <span class="req">*</span></label>
                <textarea id="msg" name="message" rows="4" placeholder="Explain what happened so we can help you log back in." required aria-required="true"><?php echo e(old_value($cOld, 'message')); ?></textarea>
                <?php field_error($cErrors, 'message'); ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-paper-plane"></i> Send to admin</button>
        </form>
    <?php else: ?>
        <?php if ($showTimeout && empty($errors['general'])): ?>
            <div class="notice notice-strong" role="status" style="margin-bottom:16px;">
                <i class="fa-solid fa-clock"></i>
                <span>Your session expired after 30 minutes of inactivity. Sign in again to continue.</span>
            </div>
        <?php endif; ?>
        <?php if (!empty($errors['general'])): ?>
            <div hidden data-error-modal-title="<?php echo e($errorTitle); ?>" data-error-modal-msg="<?php echo e($errors['general']); ?>"></div>
        <?php endif; ?>

        <?php if ($lock): ?>
            <div class="lock-card" data-lock-ends="<?php echo e($lock['ends']); ?>" data-lock-total="<?php echo (int)$lock['seconds']; ?>" id="lockNotice">
                <div class="lock-head">
                    <strong>Too many wrong attempts, logins are paused briefly.</strong>
                </div>
                <div class="lock-timer-row">
                    <span class="lock-label">Retry in</span>
                    <span class="lock-timer" id="lockTimer"><?php echo gmdate('i:s', $lock['seconds']); ?></span>
                </div>
                <div class="lock-progress"><span id="lockBar"></span></div>
            </div>
        <?php endif; ?>

        <a href="<?php echo base_url('pages/login_google.php?intent=login'); ?>" class="btn-google">
            <?php google_svg_icon(); ?>
            Continue with Google
        </a>
        <div class="auth-divider"><span>or</span></div>

        <form method="post" action="" novalidate>
            <?php echo csrf_field(); ?>
            <?php honeypot_field(); ?>
            <div class="form-group<?php echo has_error($errors, 'email'); ?>">
                <div class="input-group floating">
                    <input type="email" id="email" name="email" value="<?php echo e($email); ?>" placeholder=" " autocomplete="email" required aria-required="true" data-check-email="exists" <?php echo $lock ? 'disabled' : ''; ?>>
                    <label for="email">Email <span class="req">*</span></label>
                </div>
                <?php field_error($errors, 'email'); ?>
            </div>
            <div class="form-group<?php echo has_error($errors, 'password'); ?>">
                <div class="input-group floating">
                    <input type="password" id="password" name="password" placeholder=" " autocomplete="current-password" required aria-required="true" <?php echo $lock ? 'disabled' : ''; ?>>
                    <label for="password">Password <span class="req">*</span></label>
                    <button type="button" class="pw-toggle" data-target="password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                </div>
                <?php field_error($errors, 'password'); ?>
            </div>
            <div class="auth-aux-row">
                <label class="check-line" for="remember_me">
                    <input type="checkbox" id="remember_me" name="remember_me" value="1" aria-label="Remember me">
                    <span class="check-box"><i class="fa-solid fa-check"></i></span>
                    <span>Remember me</span>
                </label>
                <a href="<?php echo base_url('pages/forgot_password.php'); ?>" class="auth-forgot-link">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-primary btn-block" <?php echo $lock ? 'disabled' : 'data-autogate=""'; ?>><i class="fa-solid fa-right-to-bracket"></i> Log in</button>
            <p class="form-foot">Don't have an account? <a href="<?php echo base_url('pages/register.php'); ?>"><strong>Sign up</strong></a></p>
        </form>
    <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

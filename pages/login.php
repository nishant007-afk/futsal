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
        } else {
            $stmt = $conn->prepare('SELECT id, name, password, role, email_verified, login_count FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            $ok = $user && password_verify($password, $user['password']);

            if ($ok && (int)$user['email_verified'] === 1) {
                $stmt = $conn->prepare('UPDATE users SET login_count = login_count + 1 WHERE id = ?');
                $stmt->bind_param('i', $user['id']);
                $stmt->execute();
                $newCount = (int)$user['login_count'] + 1;
                unset($_SESSION['login_lock'], $_SESSION['login_lock_email']);
                clear_login_attempts($key);
                if ($newCount % 6 === 0 && $user['role'] !== 'admin') {
                    $otp = issue_otp($email, 'login');
                    $otp_sent = send_otp_mail($email, $otp, 'login');
                    session_regenerate_id(true);
                    $_SESSION['pending_login'] = ['user_id' => (int)$user['id'], 'email' => $email, 'otp' => $otp, 'sent' => $otp_sent];
                    redirect('pages/otp_verify.php');
                }
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $returnPath = $_SESSION['return_path'] ?? '';
                unset($_SESSION['return_path']);
                set_flash('success', 'Welcome back, ' . $user['name'] . '!');
                if ($returnPath === 'pages/page.php?slug=contact') {
                    redirect($returnPath);
                }
                redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'index.php');
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
                $errors['general'] = 'The password you entered is incorrect. You have ' . max(0, $failuresLeft) . ' attempt' . (max(0, $failuresLeft) === 1 ? '' : 's') . ' left before a 1-minute pause.';
            } else {
                $errors['general'] = 'Please verify your email before logging in. Check your inbox for the verification link.';
            }
        }
    }
}

$page_title = 'Login';
require __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <div class="form-head">
        <h2 >Sign in to GoalSpace</h2>
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

        <div class="notice" style="margin-top:16px;">
            <i class="fa-solid fa-headset"></i>
            <span>Use the form below to reach the admin about this issue.</span>
        </div>

        <form method="post" action="<?php echo base_url('pages/contact_submit.php'); ?>" style="margin-top:18px;" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="topic" value="login_locked">
            <div class="form-group">
                <label for="subj">Subject</label>
                <div class="input-group">
                    <i class="fa-solid fa-heading"></i>
                    <input type="text" id="subj" name="subject" value="My account is locked after login attempts" required>
                </div>
            </div>
            <div class="form-group<?php echo has_error($cErrors, 'email'); ?>">
                <label for="em">Your email</label>
                <div class="input-group">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" id="em" name="email" value="<?php echo e(old_value($cOld, 'email', $suspendedEmail)); ?>" required>
                </div>
                <?php field_error($cErrors, 'email'); ?>
            </div>
            <div class="form-group<?php echo has_error($cErrors, 'message'); ?>">
                <label for="msg">Message</label>
                <textarea id="msg" name="message" rows="4" placeholder="Explain what happened so we can help you log back in." required><?php echo e(old_value($cOld, 'message')); ?></textarea>
                <?php field_error($cErrors, 'message'); ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-paper-plane"></i> Send to admin</button>
        </form>
    <?php else: ?>
        <?php if (!empty($errors['general'])): ?>
            <div hidden data-error-modal-title="Login failed" data-error-modal-msg="<?php echo e($errors['general']); ?>"></div>
        <?php endif; ?>

        <?php if ($lock): ?>
            <div class="lock-card" data-lock-ends="<?php echo e($lock['ends']); ?>" data-lock-total="<?php echo (int)$lock['seconds']; ?>" id="lockNotice">
                <div class="lock-head">
                    <i class="fa-solid fa-hourglass-half"></i>
                    <strong>Too many attempts</strong>
                </div>
                <p class="lock-msg">Too many wrong attempts, so we've paused logins for a minute.</p>
                <div class="lock-timer-row">
                    <span class="lock-label">Retry in</span>
                    <span class="lock-timer" id="lockTimer"><?php echo gmdate('i:s', $lock['seconds']); ?></span>
                </div>
                <div class="lock-progress"><span id="lockBar"></span></div>
            </div>
        <?php endif; ?>

        <a href="<?php echo base_url('pages/login_google.php?intent=login'); ?>" class="btn-google">
            <svg class="g-icon" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.1 18.9 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.1 5.7l6.2 5.2C36.9 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z"/></svg>
            Continue with Google
        </a>
        <div class="auth-divider"><span>or</span></div>

        <form method="post" action="" novalidate>
            <?php echo csrf_field(); ?>
            <div class="form-group<?php echo has_error($errors, 'email'); ?>">
                <label for="email">Email</label>
                <div class="input-group">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" id="email" name="email" value="<?php echo e($email); ?>" placeholder="you@example.com" autocomplete="email" required <?php echo $lock ? 'disabled' : ''; ?>>
                </div>
                <?php field_error($errors, 'email'); ?>
            </div>
            <div class="form-group<?php echo has_error($errors, 'password'); ?>">
                <label for="password">Password</label>
                <div class="input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" autocomplete="current-password" required <?php echo $lock ? 'disabled' : ''; ?>>
                    <button type="button" class="pw-toggle" data-target="password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                </div>
                <p class="form-hint" style="margin-top:8px;"><a href="<?php echo base_url('pages/forgot_password.php'); ?>">Forgot password?</a></p>
                <?php field_error($errors, 'password'); ?>
            </div>
            <label class="check-line" style="margin-bottom:18px;">
                <input type="checkbox" name="remember_me" value="1">
                <span class="check-box"><i class="fa-solid fa-check"></i></span>
                <span>Remember me</span>
            </label>
            <button type="submit" class="btn btn-primary btn-block" <?php echo $lock ? 'disabled' : 'data-autogate=""'; ?>><i class="fa-solid fa-right-to-bracket"></i> Login</button>
            <p class="form-foot">New here? <a href="<?php echo base_url('pages/register.php'); ?>">Create an account</a></p>
        </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

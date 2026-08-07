<?php
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect('index.php');
}

$pending = $_SESSION['pending_login'] ?? null;
if (!$pending || empty($pending['email'])) {
    set_flash('info', 'Start by logging in with your password.');
    redirect('pages/login.php');
}

$email = $pending['email'];
$errors = [];
$demoCode = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $resend = !empty($_POST['resend']);

    if ($resend) {
        $cooldown = otp_send_cooldown($email, 'login');
        if ($cooldown > 0) {
            set_flash_error(
                'You\'re requesting too many codes.',
                'Wait ' . $cooldown . 's before asking for another one.',
                'Check your inbox for the code we already sent, then try again shortly.',
                'pages/login.php'
            );
            redirect('pages/login.php');
        }
        $otp = issue_otp($email, 'login');
        $sent = send_otp_mail($email, $otp, 'login');
        $_SESSION['pending_login']['otp'] = $otp;
        $_SESSION['pending_login']['sent'] = $sent;
            set_flash('success', $sent ? 'A new code has been sent to ' . $email . '.' : 'Email delivery is unavailable right now, so your code is shown below.');
        if (!$sent) {
            $demoCode = $otp;
        }
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
                unset($_SESSION['pending_login']);
                set_flash_error(
                    'We couldn\'t finish logging you in.',
                    'Your login session expired while verifying the code.',
                    'Log in again and it should only take a minute.',
                    'pages/login.php'
                );
                redirect('pages/login.php');
            }
            unset($_SESSION['pending_login']);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            set_flash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'index.php');
        } else {
            $left = otp_attempts_left($email, 'login');
            $errors['code'] = $left > 0
                ? 'That code didn\'t work or it has expired. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.'
                : 'Too many wrong attempts. Request a new code.';
        }
    }
}

$page_title = 'Enter your code';
require __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <div class="form-head">
        <h2>Two-step login</h2>
        <p class="muted">Enter the 6-digit code we sent to <strong><?php echo e($email); ?></strong> to finish signing in.</p>
    </div>

    <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

    <?php if ($demoCode || (!empty($pending['otp']) && empty($pending['sent']))): ?>
        <div class="notice" style="margin-top:14px;">
            <i class="fa-solid fa-circle-info"></i>
            <span><strong>Email couldn't be sent</strong> right now, so use this code: <strong style="letter-spacing:3px;font-size:18px;color:var(--brand-700);"><?php echo e($demoCode ?? $pending['otp']); ?></strong></span>
        </div>
    <?php endif; ?>

    <form method="post" action="" style="margin-top:18px;" novalidate>
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'code'); ?>">
            <label for="code">Login code <span class="req">*</span></label>
            <div class="input-group">
                <i class="fa-solid fa-shield-halved"></i>
                <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" autocomplete="one-time-code" spellcheck="false" required>
            </div>
            <?php field_error($errors, 'code'); ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-right-to-bracket"></i> Verify and log in</button>
        <button type="submit" name="resend" value="1" formnovalidate class="btn btn-ghost btn-block" style="margin-top:10px;"><i class="fa-solid fa-rotate-right"></i> Resend code</button>
    </form>
    <p class="form-foot"><a href="<?php echo base_url('pages/login.php'); ?>">Use a different account</a></p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

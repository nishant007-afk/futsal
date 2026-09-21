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
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $resend = !empty($_POST['resend']);

    if ($resend) {
        $cooldown = otp_send_cooldown($email, 'login');
        if ($cooldown > 0) {
            set_flash_error(
                'You\'re requesting too many codes.',
                'Wait ' . format_otp_wait($cooldown) . ' before asking for another one.',
                'Check your inbox for the code we already sent, then try again shortly.',
                'pages/login.php'
            );
            redirect('pages/login.php');
        }
        $otp = issue_otp($email, 'login');
        $sent = send_otp_mail($email, $otp, 'login');
        // Clear any previous pending 2FA/login session data
        unset($_SESSION['pending_login'], $_SESSION['pending_2fa_login']);
        $_SESSION['pending_2fa_login'] = ['user_id' => $pending['user_id'] ?? $pending['user_id'] ?? 0, 'email' => $email, 'role' => $pending['role'] ?? 'player'];
        $_SESSION['pending_2fa_login']['sent'] = $sent;
            set_flash('success', $sent ? 'A new code has been sent to ' . $email . '.' : 'Email delivery is unavailable. Please try again shortly.');
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
            unset($_SESSION['pending_2fa_login']);
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
        <div class="title-back-row">
            <a href="<?php echo base_url('pages/login.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
            <h2>Two-step login</h2>
        </div>
        <p class="muted mt-12 lh-15">Enter the 6-digit code we sent to <strong><?php echo e($email); ?></strong> to finish signing in.</p>
    </div>

    <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

    <?php
    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if ($isLocal) {
        $devStmt = $conn->prepare('SELECT code FROM otps WHERE identifier = ? AND purpose = "login" AND used = 0 ORDER BY id DESC LIMIT 1');
        $devStmt->bind_param('s', $email);
        $devStmt->execute();
        $devRow = $devStmt->get_result()->fetch_assoc();
        if ($devRow) {
            echo '<div class="notice notice-info mb-14"><i class="fa-solid fa-code"></i> <span><strong>Local Development Notice:</strong> SMTP failed (Brevo auth error). Your active verification code is: <strong style="letter-spacing:3px;font-size:18px;color:var(--brand-700);">' . e($devRow['code']) . '</strong></span></div>';
        }
    }
    ?>

    <form method="post" action="" class="mt-18" novalidate>
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'code'); ?>">
            <label for="code">Login code <span class="req">*</span></label>
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
        <button type="submit" name="resend" value="1" formnovalidate class="btn btn-ghost btn-block mt-10"><i class="fa-solid fa-rotate-right"></i> Resend code</button>
    </form>
    <p class="form-foot"><a href="<?php echo base_url('pages/login.php'); ?>">Use a different account</a></p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

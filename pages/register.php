<?php
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect('index.php');
}

$name = $email = $phone = '';
$accept = false;
$email_updates = false;
$role = ($_GET['role'] ?? '') === 'manager' ? 'manager' : 'user';
$errors = [];
$reg_blocked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // IP-based rate limiting: max 3 registrations per hour per IP (DB-backed)
    if (rate_limit_exceeded('reg', 3, 3600)) {
        $reg_blocked = true;
        $errors['general'] = 'Too many registration attempts. Please try again later.';
    }

    // Honeypot check: bots fill this, humans don't
    if (is_honeypot_filled()) {
        // Silently reject bot submissions
        $errors['general'] = 'Registration failed. Please try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['user', 'manager'], true) ? $_POST['role'] : $role;
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $accept = ($_POST['accept'] ?? '') === '1';
    $email_updates = ($_POST['email_updates'] ?? '') === '1';

    if ($name === '' || strlen($name) < 2) {
        $errors['name'] = 'Please enter your full name, at least 2 characters. Letters, numbers and special characters are all allowed.';
    }
    $cleanPhone = preg_replace('/[\s\-]/', '', $phone);
    if ($cleanPhone !== '') {
        if (strlen($cleanPhone) > 20) {
            $errors['phone'] = 'Phone number is too long. Keep it under 20 characters.';
        } else {
            $pStmt = $conn->prepare('SELECT id FROM users WHERE REPLACE(REPLACE(phone, " ", ""), "-", "") = ?');
            $pStmt->bind_param('s', $cleanPhone);
            $pStmt->execute();
            if ($pStmt->get_result()->num_rows > 0) {
                $errors['phone'] = 'An account with this phone number already exists.';
            }
            $pStmt->close();
        }
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address, e.g. you@example.com.';
    } elseif (email_has_plus_alias($email)) {
        $errors['email'] = 'Email addresses with "+" aliases are not allowed. Please use your standard email.';
    } elseif (is_disposable_email($email)) {
        $errors['email'] = 'One-time email addresses aren\'t allowed. Please use a real email.';
    } elseif (!email_has_mx($email)) {
        $errors['email'] = 'This email domain does not accept mail. Please use a real email address.';
    }
    $pwError = validate_password($password);
    if ($pwError) {
        $errors['password'] = $pwError;
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'The two passwords don\'t match. Please type the same password in both boxes.';
    }
    if (!$accept) {
        $errors['terms'] = 'Please accept the Terms of Service and Privacy Policy to continue.';
    }

    if (!$errors && !$reg_blocked) {
        $stmt = $conn->prepare('SELECT id, email FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $emailExists = (bool)$userRow;
        if (!$emailExists) {
            $canon = canonical_email($email);
            $domain = strtolower(substr(strrchr($email, '@'), 1));
            if ($domain === 'gmail.com' || $domain === 'googlemail.com') {
                $gCheck = $conn->query("SELECT email FROM users WHERE email LIKE '%@gmail.com' OR email LIKE '%@googlemail.com'");
                if ($gCheck) {
                    while ($r = $gCheck->fetch_assoc()) {
                        if (canonical_email($r['email']) === $canon) {
                            $emailExists = true;
                            break;
                        }
                    }
                }
            }
        }

        if ($emailExists) {
            $errors['email'] = 'An account with this email already exists. Try logging in instead.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $conn->prepare('INSERT INTO users (name, email, phone, password, role, email_verified, verify_token, email_updates) VALUES (?, ?, ?, ?, ?, 0, NULL, ?)');
            $stmt->bind_param('sssssi', $name, $email, $cleanPhone, $hash, $role, $email_updates);
            if ($stmt->execute()) {
                $otp = issue_otp($email, 'email_verify');
                $verification_sent = send_otp_mail($email, $otp, 'email_verify');
                if (!$verification_sent) {
                    error_log('OTP email delivery failed for purpose: email_verify');
                }
                $verification_code = null;
                $verification_email = $email;
            } else {
                $errors['general'] = 'Something went wrong. Please try again.';
            }
        }
    }
}

if (isset($verification_email)) {
    session_regenerate_id(true);
    $page_title = 'Verify your email';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="form-card lg">
        <div class="form-head">
            <h1>Verify your email</h1>
            <p class="muted mt-12 lh-15">Enter the 6-digit code we sent to <strong><?php echo e($verification_email); ?></strong> to activate your account.</p>
        </div>
        <div class="notice">
            <i class="fa-solid fa-envelope-circle-check"></i>
            <span><?php echo $verification_sent ? 'Check your inbox (and spam folder). The code expires in 5 minutes.' : 'Email delivery is unavailable right now. Please open Verify Email and tap Resend in a minute.'; ?></span>
        </div>
        <form method="post" action="<?php echo base_url('pages/verify.php'); ?>" class="mt-22" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="email" value="<?php echo e($verification_email); ?>">
            <input type="hidden" name="resend" value="0">
            <div class="form-group">
                <label for="vcode">Verification code</label>
                <input type="hidden" name="code" class="otp-source" required aria-required="true">
                <div class="otp-boxes" role="group" aria-label="Verification code">
                    <input class="otp-box" type="tel" id="vcode" inputmode="numeric" maxlength="1" pattern="[0-9]*" autocomplete="one-time-code" aria-label="First digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Second digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Third digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Fourth digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Fifth digit">
                    <input class="otp-box" type="tel" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="Sixth digit">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-check"></i> Verify email</button>
            <button type="submit" name="resend" value="1" formnovalidate class="btn btn-ghost btn-block mt-10"><i class="fa-solid fa-rotate-right"></i> Resend code</button>
        </form>
        <p class="form-foot mt-20">Already verified? <a href="<?php echo base_url('pages/login.php'); ?>">Log in</a></p>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$page_title = 'Sign Up';
$page_description = 'Create a free GoalSpace account to book futsal courts near you, track your bookings, and never miss a game.';
require __DIR__ . '/../includes/header.php';

$active_auth_tab = 'register';
$regErrors = $errors;
$regName = $name;
$regPhone = $phone;
$regEmail = $email;
$regRole = $role;
$regAccept = $accept;
require __DIR__ . '/../includes/views/auth_split.php';

require __DIR__ . '/../includes/footer.php';
?>

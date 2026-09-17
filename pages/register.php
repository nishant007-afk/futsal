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
    if (strlen($phone) > 20) {
        $errors['phone'] = 'Phone number is too long. Keep it under 20 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address, e.g. you@example.com.';
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
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors['email'] = 'An account with this email already exists. Try logging in instead.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $conn->prepare('INSERT INTO users (name, email, phone, password, role, email_verified, verify_token, email_updates) VALUES (?, ?, ?, ?, ?, 0, NULL, ?)');
            $stmt->bind_param('sssssi', $name, $email, $phone, $hash, $role, $email_updates);
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
            <h2>Verify your email</h2>
            <p class="muted mt-12 lh-15">Enter the 6-digit code we sent to <strong><?php echo e($verification_email); ?></strong> to activate your account.</p>
        </div>
        <div class="notice">
            <i class="fa-solid fa-envelope-circle-check"></i>
            <span><?php echo $verification_sent ? 'Check your inbox (and spam folder). The code expires in 5 minutes.' : 'Email delivery is unavailable right now. Please open Verify Email and tap Resend in a minute.'; ?></span>
        </div>
        <form method="post" action="<?php echo base_url('pages/verify.php'); ?>" class="mt-22">
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
?>

<div class="signup-shell">
    <section class="signup-intro">
        <h1>Book a futsal court in minutes</h1>
        <p class="lead">Find a free court near you, lock in your slot in seconds and focus on the game. Managers get one dashboard to run their grounds, bookings and payments.</p>
        <button type="button" class="signup-intro-toggle" id="signupIntroToggle" aria-expanded="false" aria-controls="signupIntroDetails">
            <span class="sint-label">See what's included</span>
            <i class="fa-solid fa-chevron-down sint-chev" aria-hidden="true"></i>
        </button>
        <div class="signup-intro-details" id="signupIntroDetails">
            <ul class="benefit-list">
                <li>
                    <i class="fa-solid fa-map-location-dot b-icon" aria-hidden="true"></i>
                    <div class="benefit-copy">
                        <strong class="benefit-title">Find a free court</strong>
                        <span class="benefit-desc">Browse grounds near you and check live availability.</span>
                    </div>
                </li>
                <li>
                    <i class="fa-solid fa-bolt b-icon" aria-hidden="true"></i>
                    <div class="benefit-copy">
                        <strong class="benefit-title">Book in seconds</strong>
                        <span class="benefit-desc">Reserve your slot in a few taps, pay when it suits you.</span>
                    </div>
                </li>
                <li>
                    <i class="fa-solid fa-bell b-icon" aria-hidden="true"></i>
                    <div class="benefit-copy">
                        <strong class="benefit-title">Never miss a game</strong>
                        <span class="benefit-desc">Get instant reminders and booking updates.</span>
                    </div>
                </li>
                <li>
                    <i class="fa-solid fa-user-tie b-icon" aria-hidden="true"></i>
                    <div class="benefit-copy">
                        <strong class="benefit-title">Run a court</strong>
                        <span class="benefit-desc">Managers get a dashboard for bookings and payments.</span>
                    </div>
                </li>
            </ul>
        </div>
    </section>

    <section class="signup-right">
        <div class="auth-topline">
            <div class="title-back-row">
                <a href="<?php echo base_url('index.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
                <h2 class="auth-title-lg">Create your account</h2>
            </div>
        </div>
        <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>
        <a href="<?php echo base_url('pages/login_google.php?intent=signup'); ?>" class="btn-google" id="googleLink">
            <?php google_svg_icon(); ?>
            Continue with Google
        </a>
        <div class="auth-divider"><span>or</span></div>
        <form method="post" action="" novalidate>
            <?php echo csrf_field(); ?>
            <?php honeypot_field(); ?>
            <div class="form-row-2">
                <div class="form-group<?php echo has_error($errors, 'name'); ?>">
                    <div class="input-group floating">
                        <input type="text" id="name" name="name" value="<?php echo e($name); ?>" autocomplete="name" placeholder=" " maxlength="100" required aria-required="true">
                        <label for="name">Full name <span class="req">*</span></label>
                    </div>
                    <?php field_error($errors, 'name'); ?>
                </div>
                <div class="form-group<?php echo has_error($errors, 'phone'); ?>">
                    <div class="input-group floating">
                        <input type="tel" id="phone" name="phone" value="<?php echo e($phone); ?>" autocomplete="tel" placeholder=" " maxlength="20">
                        <label for="phone">Phone <span class="opt">(optional)</span></label>
                    </div>
                    <?php field_error($errors, 'phone'); ?>
                </div>
            </div>

            <div class="form-group<?php echo has_error($errors, 'email'); ?>">
                <div class="input-group floating">
                    <input type="email" id="email" name="email" value="<?php echo e($email); ?>" autocomplete="email" placeholder=" " required aria-required="true" data-check-email="available">
                    <label for="email">Email <span class="req">*</span></label>
                </div>
                <?php field_error($errors, 'email'); ?>
            </div>

            <div class="form-group form-group-role">
                <span id="roleLabel" class="sr-only">Account type</span>
                <div class="role-select" role="radiogroup" aria-labelledby="roleLabel">
                    <label class="role-option <?php echo $role === 'user' ? 'checked' : ''; ?>"
                           data-hint="Players book courts, track their games and cancel their own bookings.">
                        <input type="radio" name="role" value="user" <?php echo $role === 'user' ? 'checked' : ''; ?>>
                        <span class="role-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="role-text">
                            <span class="role-name">Player</span>
                            <span class="role-desc">Book courts &amp; play</span>
                        </span>
                        <span class="role-check"><i class="fa-solid fa-check"></i></span>
                    </label>
                    <label class="role-option <?php echo $role === 'manager' ? 'checked' : ''; ?>"
                           data-hint="Managers get a dashboard to list their courts, manage bookings and see what's been paid.">
                        <input type="radio" name="role" value="manager" <?php echo $role === 'manager' ? 'checked' : ''; ?>>
                        <span class="role-icon"><i class="fa-solid fa-user-tie"></i></span>
                        <span class="role-text">
                            <span class="role-name">Manager</span>
                            <span class="role-desc">Own courts &amp; slots</span>
                        </span>
                        <span class="role-check"><i class="fa-solid fa-check"></i></span>
                    </label>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group<?php echo has_error($errors, 'password'); ?>">
                    <div class="input-group floating">
                        <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" placeholder=" " required aria-required="true">
                        <label for="password">Password <span class="req">*</span></label>
                        <button type="button" class="pw-toggle" data-target="password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                    </div>
                    <?php field_error($errors, 'password'); ?>
                </div>
                <div class="form-group<?php echo has_error($errors, 'confirm'); ?>">
                    <div class="input-group floating">
                        <input type="password" id="confirm" name="confirm" autocomplete="new-password" minlength="8" placeholder=" " required aria-required="true">
                        <label for="confirm">Confirm password <span class="req">*</span></label>
                        <button type="button" class="pw-toggle" data-target="confirm" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                    </div>
                    <?php field_error($errors, 'confirm'); ?>
                </div>
            </div>
            <div class="pw-requirements-wrap">
                <?php require __DIR__ . '/../includes/views/pw_requirements.php'; ?>
            </div>

            <label class="check-line mb-6">
                <input type="checkbox" id="termsCheck" name="accept" value="1" required aria-required="true" <?php echo $accept ? 'checked' : ''; ?>>
                <span class="check-box"><i class="fa-solid fa-check"></i></span>
                <span>I accept the <a href="<?php echo base_url('pages/page.php?slug=terms'); ?>" target="_blank" rel="noopener">Terms of Service</a> and <a href="<?php echo base_url('pages/page.php?slug=privacy'); ?>" target="_blank" rel="noopener">Privacy Policy</a> <span class="req">*</span></span>
            </label>
            <?php field_error($errors, 'terms'); ?>

            <label class="check-line check-line-sub mb-14">
                <input type="checkbox" id="updatesCheck" name="email_updates" value="1" <?php echo $email_updates ? 'checked' : ''; ?>>
                <span class="check-box"><i class="fa-solid fa-check"></i></span>
                <span>Send me booking tips and court updates</span>
            </label>

            <button type="submit" class="btn btn-primary btn-block" data-autogate=""><i class="fa-solid fa-user-plus"></i> Create account</button>
            <p class="form-foot">Already have an account? <a href="<?php echo base_url('pages/login.php'); ?>"><strong>Sign in</strong></a></p>
        </form>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

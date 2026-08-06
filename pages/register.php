<?php
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect('index.php');
}

$name = $email = $phone = '';
$accept = false;
$role = ($_GET['role'] ?? '') === 'manager' ? 'manager' : 'user';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['user', 'manager'], true) ? $_POST['role'] : $role;
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $accept = ($_POST['accept'] ?? '') === '1';

    if ($name === '' || strlen($name) < 2) {
        $errors['name'] = 'Please enter your full name, at least 2 characters.';
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

    if (!$errors) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors['email'] = 'An account with this email already exists. Try logging in instead.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('INSERT INTO users (name, email, phone, password, role, email_verified, verify_token) VALUES (?, ?, ?, ?, ?, 0, NULL)');
            $stmt->bind_param('sssss', $name, $email, $phone, $hash, $role);
            if ($stmt->execute()) {
                $otp = issue_otp($email, 'email_verify');
                $verification_sent = send_otp_mail($email, $otp, 'email_verify');
                $verification_code = $otp;
                $verification_email = $email;
            } else {
                $errors['general'] = 'Something went wrong. Please try again.';
            }
        }
    }
}

if (isset($verification_code)) {
    session_regenerate_id(true);
    $page_title = 'Verify your email';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="form-card lg">
        <div class="form-head">
            <h2>Verify your email</h2>
            <p class="muted">Enter the 6-digit code we sent to <strong><?php echo e($verification_email); ?></strong> to activate your account.</p>
        </div>
        <div class="notice">
            <i class="fa-solid fa-envelope-circle-check"></i>
            <span><?php echo $verification_sent ? 'Check your inbox (and spam folder). The code expires in 5 minutes.' : 'We couldn\'t send the email, so here\'s your code:'; ?></span>
            <?php if (!$verification_sent): ?>
                <span style="display:block;margin-top:8px;font-weight:800;letter-spacing:3px;font-size:20px;color:var(--brand-700);"><?php echo e($verification_code); ?></span>
            <?php endif; ?>
        </div>
        <form method="post" action="<?php echo base_url('pages/verify.php'); ?>" style="margin-top:22px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="email" value="<?php echo e($verification_email); ?>">
            <input type="hidden" name="resend" value="0">
            <div class="form-group">
                <label for="vcode">Verification code</label>
                <div class="input-group">
                    <i class="fa-solid fa-shield-halved"></i>
                    <input type="text" id="vcode" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" autocomplete="one-time-code" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-check"></i> Verify email</button>
            <button type="submit" name="resend" value="1" formnovalidate class="btn btn-ghost btn-block" style="margin-top:10px;"><i class="fa-solid fa-rotate-right"></i> Resend code</button>
        </form>
        <p class="form-foot" style="margin-top:20px;">Already verified? <a href="<?php echo base_url('pages/login.php'); ?>">Log in</a></p>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$page_title = 'Sign Up';
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
                <li><span class="b-icon"><i class="fa-solid fa-map-location-dot"></i></span><span><strong>Find a free court</strong>Browse grounds near you and check live availability.</span></li>
                <li><span class="b-icon"><i class="fa-solid fa-bolt"></i></span><span><strong>Book in seconds</strong>Reserve your slot in a few taps, pay when it suits you.</span></li>
                <li><span class="b-icon"><i class="fa-solid fa-bell"></i></span><span><strong>Never miss a game</strong>Get instant reminders and booking updates.</span></li>
                <li><span class="b-icon"><i class="fa-solid fa-user-tie"></i></span><span><strong>Run a court</strong>Managers get a dashboard for bookings and payments.</span></li>
            </ul>
        </div>
    </section>

    <section class="signup-right">
        <div class="auth-topline">
            <a href="<?php echo base_url('pages/login.php'); ?>" class="auth-switch">Already have an account? Sign in <i class="fa-solid fa-arrow-right"></i></a>
            <h2>Sign up for GoalSpace</h2>
        </div>
        <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>
        <a href="<?php echo base_url('pages/login_google.php?intent=signup'); ?>" class="btn-google" id="googleLink">
        <svg class="g-icon" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.1 18.9 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.1 5.7l6.2 5.2C36.9 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z"/></svg>
        Continue with Google
    </a>
    <div class="auth-divider"><span>or</span></div>
    <form method="post" action="" novalidate>
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'name'); ?>">
            <label for="name">Full name</label>
            <div class="input-group">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="name" name="name" value="<?php echo e($name); ?>" autocomplete="name" placeholder="e.g. Hari Sharma" required>
            </div>
            <?php field_error($errors, 'name'); ?>
        </div>
        <div class="form-group<?php echo has_error($errors, 'email'); ?>">
            <label for="email">Email</label>
            <div class="input-group">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" id="email" name="email" value="<?php echo e($email); ?>" autocomplete="email" placeholder="you@example.com" required>
            </div>
            <?php field_error($errors, 'email'); ?>
        </div>
        <div class="form-group<?php echo has_error($errors, 'phone'); ?>">
            <label for="phone">Phone <span class="muted" style="font-weight:400;">(optional)</span></label>
            <div class="input-group">
                <i class="fa-solid fa-phone"></i>
                <input type="tel" id="phone" name="phone" value="<?php echo e($phone); ?>" autocomplete="tel" placeholder="98xxxxxxxx">
            </div>
            <?php field_error($errors, 'phone'); ?>
        </div>

        <div class="form-group">
            <label>I'm signing up as</label>
            <div class="role-select">
                <label class="role-option <?php echo $role === 'user' ? 'checked' : ''; ?>"
                       data-hint="Players book courts, track their games and cancel their own bookings.">
                    <input type="radio" name="role" value="user" <?php echo $role === 'user' ? 'checked' : ''; ?>>
                    <span class="role-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="role-text">
                        <span class="role-name">Player</span>
                        <span class="role-desc">Book courts and play</span>
                    </span>
                    <span class="role-check"><i class="fa-solid fa-check"></i></span>
                </label>
                <label class="role-option <?php echo $role === 'manager' ? 'checked' : ''; ?>"
                       data-hint="Managers get a dashboard to list their courts, manage bookings and see what's been paid.">
                    <input type="radio" name="role" value="manager" <?php echo $role === 'manager' ? 'checked' : ''; ?>>
                    <span class="role-icon"><i class="fa-solid fa-user-tie"></i></span>
                    <span class="role-text">
                        <span class="role-name">Manager</span>
                        <span class="role-desc">Own courts &amp; manage bookings</span>
                    </span>
                    <span class="role-check"><i class="fa-solid fa-check"></i></span>
                </label>
            </div>
        </div>

        <div class="form-group<?php echo has_error($errors, 'password'); ?>">
            <label for="password">Password</label>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" required>
                <button type="button" class="pw-toggle" data-target="password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <p class="form-hint">Your password needs:</p>
            <ul class="pw-requirements" id="pwRequirements">
                <li data-req="length">At least 8 characters</li>
                <li data-req="letter">At least one letter</li>
                <li data-req="number">At least one number</li>
                <li data-req="special">At least one special character</li>
            </ul>
            <?php field_error($errors, 'password'); ?>
        </div>
        <div class="form-group<?php echo has_error($errors, 'confirm'); ?>">
            <label for="confirm">Confirm password</label>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="confirm" name="confirm" autocomplete="new-password" minlength="8" placeholder="Repeat your password" required>
                <button type="button" class="pw-toggle" data-target="confirm" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <?php field_error($errors, 'confirm'); ?>
        </div>
        <label class="check-line" style="margin-bottom:18px;">
            <input type="checkbox" id="termsCheck" name="accept" value="1" required <?php echo $accept ? 'checked' : ''; ?>>
            <span class="check-box"><i class="fa-solid fa-check"></i></span>
            <span>I accept the <a href="<?php echo base_url('pages/page.php?slug=terms'); ?>">Terms of Service</a> and <a href="<?php echo base_url('pages/page.php?slug=privacy'); ?>">Privacy Policy</a></span>
        </label>
        <?php field_error($errors, 'terms'); ?>
        <button type="submit" class="btn btn-primary btn-block" data-autogate=""><i class="fa-solid fa-user-plus"></i> Create account</button>
    </form>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

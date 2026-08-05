<?php
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect('index.php');
}

$name = $email = $phone = '';
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
            <button type="submit" name="resend" value="1" class="btn btn-ghost btn-block" style="margin-top:10px;"><i class="fa-solid fa-rotate-right"></i> Resend code</button>
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

<div class="form-card lg">
    <div class="form-head">
        <!-- <a href="<?php echo base_url('index.php'); ?>" class="brand">
            <span class="brand-mark"><i class="fa-solid fa-futbol"></i></span>
            GoalSpace
        </a> -->
        <h2>Create your account</h2>
        
    </div>
    <?php if (!empty($errors['general'])): ?>
        <div class="toast toast-error" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?php echo e($errors['general']); ?></span>
            <button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>
    <form method="post" action="">
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'name'); ?>">
            <label for="name">Full name</label>
            <div class="input-group">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="name" name="name" value="<?php echo e($name); ?>" autocomplete="name" placeholder="e.g. Hari Sharma" required>
            </div>
            <?php field_hint('Use your full name so grounds and managers can recognise you.'); ?>
            <?php field_error($errors, 'name'); ?>
        </div>
        <div class="form-group<?php echo has_error($errors, 'email'); ?>">
            <label for="email">Email</label>
            <div class="input-group">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" id="email" name="email" value="<?php echo e($email); ?>" autocomplete="email" placeholder="you@example.com" required>
            </div>
            <?php field_hint('We\'ll send your login and verification codes here.'); ?>
            <?php field_error($errors, 'email'); ?>
        </div>
        <div class="form-group<?php echo has_error($errors, 'phone'); ?>">
            <label for="phone">Phone <span class="muted" style="font-weight:400;">(optional)</span></label>
            <div class="input-group">
                <i class="fa-solid fa-phone"></i>
                <input type="tel" id="phone" name="phone" value="<?php echo e($phone); ?>" autocomplete="tel" placeholder="98xxxxxxxx">
            </div>
            <?php field_hint('A 10-digit mobile number, e.g. 98xxxxxxxx. No spaces or dashes needed.'); ?>
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
            <p class="form-hint" id="roleHint"><?php echo $role === 'manager' ? 'Managers get a dashboard to list their courts, manage bookings and see what\'s been paid.' : 'Players book courts, track their games and cancel their own bookings.'; ?></p>
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
            <?php field_hint('Retype the same password you entered above.'); ?>
            <?php field_error($errors, 'confirm'); ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-user-plus"></i> Create account</button>
        <p class="form-foot">Already have an account? <a href="<?php echo base_url('pages/login.php'); ?>">Log in</a></p>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

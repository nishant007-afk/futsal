<?php
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect('index.php');
}

$pending = $_SESSION['google_pending'] ?? null;
if (!is_array($pending) || empty($pending['email']) || empty($pending['name'])) {
    unset($_SESSION['google_pending']);
    redirect('pages/register.php');
}

$gName = trim($pending['name']);
$gEmail = trim($pending['email']);
$back = in_array($pending['back'] ?? '', ['pages/login.php', 'pages/register.php'], true)
    ? $pending['back']
    : 'pages/login.php';

$role = in_array($pending['role'] ?? '', ['user', 'manager'], true) ? $pending['role'] : 'user';
$email_updates = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $role = in_array($_POST['role'] ?? '', ['user', 'manager'], true) ? $_POST['role'] : '';
    $email_updates = ($_POST['email_updates'] ?? '') === '1';
    $accept = ($_POST['accept'] ?? '') === '1';

    if ($role === '') {
        $errors['role'] = 'Please choose whether you\'re a player or a manager.';
    }
    if (!$accept) {
        $errors['terms'] = 'Please accept the Terms of Service and Privacy Policy to continue.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $gEmail);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $uid = (int)$existing['id'];
        } else {
            $dummy = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $conn->prepare('INSERT INTO users (name, email, phone, password, role, email_verified, email_updates) VALUES (?, ?, ?, ?, ?, 1, ?)');
            $phone = '';
            $stmt->bind_param('sssssi', $gName, $gEmail, $phone, $dummy, $role, $email_updates);
            if (!$stmt->execute()) {
                $errors['general'] = 'Something went wrong while creating your account. Please try again.';
            } else {
                $uid = $conn->insert_id;
                if ($role === 'manager' && !manager_subscription((int)$uid)) {
                    create_manager_subscription((int)$uid);
                }
            }
        }

        if (!isset($errors['general'])) {
            // Use the Google profile picture as the new account's avatar.
            if (!empty($pending['picture'])) {
                $avatar = save_google_avatar($pending['picture'], $uid);
                if ($avatar !== '') {
                    $stmt = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ?');
                    $stmt->bind_param('si', $avatar, $uid);
                    $stmt->execute();
                }
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $uid;
            unset($_SESSION['google_pending']);
            set_flash('success', 'Welcome to GoalSpace, ' . $gName . '! Your account is ready.');
            redirect('index.php');
        }
    }
}

$firstName = preg_split('/\s+/', trim($gName))[0] ?? $gName;
$page_title = 'Set up your account';
$page_description = 'Finish setting up your GoalSpace account after signing in with Google.';
require __DIR__ . '/../includes/header.php';
?>

<div class="form-card lg">
    <div class="form-head">
        <div class="title-back-row">
            <a href="<?php echo base_url($back); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
            <h2>Complete your account</h2>
        </div>
        <p class="muted">Hi <?php echo e($gName); ?>, choose your account type to finish setup.</p>
    </div>

    <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

    <form method="post" action="" novalidate>
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'role'); ?>">
            <label id="roleLabel">I'm using GoalSpace as</label>
            <div class="role-select" role="radiogroup" aria-labelledby="roleLabel">
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
            <?php field_error($errors, 'role'); ?>
        </div>

        <label class="check-line mb-10">
            <input type="checkbox" id="updatesCheck" name="email_updates" value="1" <?php echo $email_updates ? 'checked' : ''; ?>>
            <span class="check-box"><i class="fa-solid fa-check"></i></span>
            <span>I'd like to receive emails about new grounds, booking tips and GoalSpace updates.</span>
        </label>

        <div<?php echo has_error($errors, 'terms'); ?>>
            <label class="check-line mb-18">
                <input type="checkbox" id="termsCheck" name="accept" value="1" required aria-required="true">
                <span class="check-box"><i class="fa-solid fa-check"></i></span>
                <span>I accept the <a href="<?php echo base_url('pages/page.php?slug=terms'); ?>" target="_blank" rel="noopener">Terms of Service</a> and <a href="<?php echo base_url('pages/page.php?slug=privacy'); ?>" target="_blank" rel="noopener">Privacy Policy</a></span>
            </label>
            <?php field_error($errors, 'terms'); ?>
        </div>

        <button type="submit" class="btn btn-primary btn-block" data-autogate=""><i class="fa-solid fa-user-plus"></i> Create my account</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
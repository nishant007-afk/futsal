<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();
$activeSettings = 'security';
$pwErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    verify_csrf();
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '') {
        $pwErrors['current_password'] = 'Enter your current password.';
    }
    if ($newPassword === '') {
        $pwErrors['new_password'] = 'Enter a new password.';
    }
    if ($confirmPassword === '') {
        $pwErrors['confirm_password'] = 'Repeat your new password.';
    }
    if ($newPassword !== '' && $confirmPassword !== '' && $newPassword !== $confirmPassword) {
        $pwErrors['confirm_password'] = 'The two passwords don\'t match.';
    }
    $pwValidate = $newPassword !== '' ? validate_password($newPassword) : null;
    if ($pwValidate) {
        $pwErrors['new_password'] = $pwValidate;
    }
    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $pwRow = $stmt->get_result()->fetch_assoc();
    if ($currentPassword !== '' && (!$pwRow || !password_verify($currentPassword, $pwRow['password']))) {
        $pwErrors['current_password'] = 'That\'s not your current password.';
    }
    if ($pwRow && $newPassword !== '' && password_verify($newPassword, $pwRow['password'])) {
        $pwErrors['new_password'] = 'Your new password must be different from your current one.';
    }
    if (!$pwErrors) {
        $_SESSION['pending_password_change'] = [
            'user_id' => (int)$user['id'],
            'hash' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
        ];
        redirect('pages/change_password_otp.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    verify_csrf();
    if ($user['role'] === 'admin') {
        set_flash_error(
            'Admins can\'t delete their own account.',
            'Removing the last admin would lock everyone out.',
            'Contact another admin to downgrade your role first.',
            'pages/security.php'
        );
        redirect('pages/security.php');
    }
    $_SESSION['pending_account_delete'] = [
        'user_id' => (int)$user['id'],
        'email' => $user['email'],
    ];
    redirect('pages/delete_account_otp.php');
}

$page_title = 'Security';
require __DIR__ . '/../includes/header.php';
?>

<div class="stg-layout">
    <?php settings_sidebar('security'); ?>

    <div class="stg-main">
        <div class="stg-main-head"><h2>Password</h2></div>
        <div class="stg-content-card">
            <div class="stg-content-section">
                <h3>Change password</h3>
                <p class="stg-content-desc">Update your account password.</p>
                <form method="post" action="" novalidate role="form" id="passwordCard">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group<?php echo has_error($pwErrors, 'current_password'); ?>">
                        <div class="input-group floating">
                            <input type="password" id="pwCurrent" name="current_password" autocomplete="current-password" placeholder=" " required aria-required="true">
                            <label for="pwCurrent">Current password <span class="req">*</span></label>
                            <button type="button" class="pw-toggle" data-target="pwCurrent" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                        </div>
                        <?php field_error($pwErrors, 'current_password'); ?>
                        <p class="form-hint mt-8"><a href="<?php echo base_url('pages/forgot_password.php'); ?>">Forgot your password?</a></p>
                    </div>
                    <div class="form-group<?php echo has_error($pwErrors, 'new_password'); ?>">
                        <div class="input-group floating">
                            <input type="password" id="pwNew" name="new_password" autocomplete="new-password" minlength="8" placeholder=" " required aria-required="true">
                            <label for="pwNew">New password <span class="req">*</span></label>
                            <button type="button" class="pw-toggle" data-target="pwNew" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                        </div>
                        <p class="form-hint">Your new password needs:</p>
                        <?php require __DIR__ . '/../includes/views/pw_requirements.php'; ?>
                        <p class="form-hint pw-same-warn" id="pwSameWarn" hidden><i class="fa-solid fa-circle-exclamation"></i> That looks like your current password.</p>
                        <?php field_error($pwErrors, 'new_password'); ?>
                    </div>
                    <div class="form-group<?php echo has_error($pwErrors, 'confirm_password'); ?>">
                        <div class="input-group floating">
                            <input type="password" id="pwConfirm" name="confirm_password" autocomplete="new-password" minlength="8" placeholder=" " required aria-required="true">
                            <label for="pwConfirm">Confirm new password <span class="req">*</span></label>
                            <button type="button" class="pw-toggle" data-target="pwConfirm" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                        </div>
                        <?php field_error($pwErrors, 'confirm_password'); ?>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-key"></i> Update password</button>
                </form>
            </div>
            <div class="stg-content-section stg-content-section--danger">
                <h3>Danger zone</h3>
                <p class="stg-content-desc">Irreversible actions.</p>
                <div class="pref-row">
                    <div class="pref-row-text">
                        <strong>Delete account</strong>
                        <em>Permanently removes your account, bookings and reviews. This can't be undone.</em>
                    </div>
                </div>
                <form method="post" action="" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_account">
                    <button type="submit" class="btn btn-danger btn-block" data-confirm="Delete your account permanently?" data-confirm-ok="Yes, delete" data-confirm-cancel="No"><i class="fa-solid fa-trash-can"></i> Delete my account</button>
                </form>
                <p class="form-hint mt-12 text-xs">You'll get a security code by email to confirm.</p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
function updatePwToggleLabel(btn) {
    const isShowing = btn.getAttribute('aria-label') === 'Show password';
    btn.setAttribute('aria-label', isShowing ? 'Hide password' : 'Show password');
}
document.addEventListener('DOMContentLoaded', function () {
    const pwInput = document.getElementById('pwNew');
    const currentPw = document.getElementById('pwCurrent');
    const pwSameWarn = document.getElementById('pwSameWarn');
    if (currentPw && pwInput && pwSameWarn) {
        function checkSame() {
            pwSameWarn.hidden = !(currentPw.value !== '' && pwInput.value !== '' && currentPw.value === pwInput.value);
        }
        currentPw.addEventListener('input', checkSame);
        pwInput.addEventListener('input', checkSame);
    }
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () { updatePwToggleLabel(this); });
    });
});
</script>

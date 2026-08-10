<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();
$errors = [];
$activeTab = 'password';

if (isset($_GET['tab']) && in_array($_GET['tab'], ['password', 'notifications', 'preferences'], true)) {
    $activeTab = $_GET['tab'];
}

// ---------- Change password ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    verify_csrf();
    $activeTab = 'password';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($current_password === '') {
        $errors['current_password'] = 'Enter your current password.';
    }
    if ($new_password === '') {
        $errors['new_password'] = 'Enter a new password.';
    }
    if ($confirm_password === '') {
        $errors['confirm_password'] = 'Repeat your new password.';
    }

    if ($new_password !== '' && $confirm_password !== '' && $new_password !== $confirm_password) {
        $errors['confirm_password'] = 'The two passwords don\'t match. Please type the same password in both boxes.';
    }

    $pwError = $new_password !== '' ? validate_password($new_password) : null;
    if ($pwError) {
        $errors['new_password'] = $pwError;
    }

    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $pwUser = $stmt->get_result()->fetch_assoc();
    if ($current_password !== '' && (!$pwUser || !password_verify($current_password, $pwUser['password']))) {
        $errors['current_password'] = 'That\'s not your current password. Try again.';
    }
    if ($pwUser && $new_password !== '' && password_verify($new_password, $pwUser['password'])) {
        $errors['new_password'] = 'Your new password must be different from your current one.';
    }

    if (!$errors) {
        $_SESSION['pending_password_change'] = [
            'user_id' => (int)$user['id'],
            'hash' => password_hash($new_password, PASSWORD_BCRYPT),
        ];
        redirect('pages/change_password_otp.php?tab=password');
    }
}

// ---------- Notifications ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_notifications') {
    verify_csrf();
    $notifyBookings = isset($_POST['notify_bookings']) ? 1 : 0;
    $notifyPromo = isset($_POST['notify_promo']) ? 1 : 0;
    $notifyExpiry = isset($_POST['notify_expiry']) ? 1 : 0;
    $notifySms = isset($_POST['notify_sms']) ? 1 : 0;
    $stmt = $conn->prepare('UPDATE users SET notify_bookings = ?, notify_promo = ?, notify_expiry = ?, notify_sms = ? WHERE id = ?');
    $stmt->bind_param('iiiii', $notifyBookings, $notifyPromo, $notifyExpiry, $notifySms, $_SESSION['user_id']);
    if ($stmt->execute()) {
        set_flash('success', 'Notification preferences saved.');
    } else {
        set_flash_error(
            'Could not save your preferences.',
            'The server returned an error while saving.',
            'Please try again in a moment.',
            'pages/settings.php?tab=notifications'
        );
    }
    redirect('pages/settings.php?tab=notifications');
}

$page_title = 'Settings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head settings-page-head">
    <div>
        <h2>Settings</h2>
        <p class="muted" style="font-size:13px;margin-top:4px;">Manage your account, notifications and preferences.</p>
    </div>
</div>

<?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

<!-- desktop tabs -->
<nav class="settings-nav settings-nav-tabs" role="tablist" id="settingsNav" aria-label="Settings sections">
    <button type="button" class="settings-nav-btn<?php echo $activeTab === 'password' ? ' active' : ''; ?>" data-tab="password" id="tab-password" role="tab" aria-selected="<?php echo $activeTab === 'password' ? 'true' : 'false'; ?>"><i class="fa-solid fa-user-gear"></i> Account</button>
    <button type="button" class="settings-nav-btn<?php echo $activeTab === 'notifications' ? ' active' : ''; ?>" data-tab="notifications" id="tab-notifications" role="tab" aria-selected="<?php echo $activeTab === 'notifications' ? 'true' : 'false'; ?>"><i class="fa-solid fa-bell"></i> Notifications</button>
    <button type="button" class="settings-nav-btn<?php echo $activeTab === 'preferences' ? ' active' : ''; ?>" data-tab="preferences" id="tab-preferences" role="tab" aria-selected="<?php echo $activeTab === 'preferences' ? 'true' : 'false'; ?>"><i class="fa-solid fa-sliders"></i> Preferences</button>
</nav>

<!-- mobile dedicated-page list -->
<nav class="settings-nav settings-nav-links" aria-label="Settings sections">
    <a href="<?php echo base_url('pages/settings_account.php'); ?>"><i class="fa-solid fa-user-gear"></i> Account <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a>
    <a href="<?php echo base_url('pages/settings_notifications.php'); ?>"><i class="fa-solid fa-bell"></i> Notifications <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a>
    <a href="<?php echo base_url('pages/settings_preferences.php'); ?>"><i class="fa-solid fa-sliders"></i> Preferences <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a>
</nav>

<!-- desktop content cards (tabs) -->
<div class="settings-panels-desktop">
    <section class="settings-panel<?php echo $activeTab === 'password' ? '' : ' d-none'; ?>" id="panel-password" role="tabpanel" aria-labelledby="tab-password">
        <div class="form-card chg-pass settings-narrow">
            <div class="form-head"><h2>Change your password</h2></div>
            <form method="post" action="" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="change_password">
                <div class="form-group<?php echo has_error($errors, 'current_password'); ?>">
                    <div class="input-group floating">
                        <input type="password" id="current_password" name="current_password" autocomplete="current-password" placeholder=" " required>
                        <label for="current_password">Current password <span class="req">*</span></label>
                        <button type="button" class="pw-toggle" data-target="current_password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                    </div>
                    <?php field_error($errors, 'current_password'); ?>
                    <p class="form-hint" style="margin-top:8px;"><a href="<?php echo base_url('pages/forgot_password.php'); ?>">Forgot your password?</a></p>
                </div>
                <div class="form-group<?php echo has_error($errors, 'new_password'); ?>">
                    <div class="input-group floating">
                        <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" placeholder=" " required>
                        <label for="new_password">New password <span class="req">*</span></label>
                        <button type="button" class="pw-toggle" data-target="new_password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                    </div>
                    <p class="form-hint">Your new password needs:</p>
                    <ul class="pw-requirements" id="pwRequirements">
                        <li data-req="length">At least 8 characters</li>
                        <li data-req="letter">At least one letter</li>
                        <li data-req="number">At least one number</li>
                        <li data-req="special">At least one special character</li>
                    </ul>
                    <p class="form-hint pw-same-warn" id="pwSameWarn" hidden><i class="fa-solid fa-circle-exclamation"></i> That looks like your current password. Choose something new.</p>
                    <?php field_error($errors, 'new_password'); ?>
                </div>
                <div class="form-group<?php echo has_error($errors, 'confirm_password'); ?>">
                    <div class="input-group floating">
                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" placeholder=" " required>
                        <label for="confirm_password">Confirm new password <span class="req">*</span></label>
                        <button type="button" class="pw-toggle" data-target="confirm_password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                    </div>
                    <?php field_error($errors, 'confirm_password'); ?>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-key"></i> Update password</button>
            </form>
        </div>
    </section>
    <section class="settings-panel<?php echo $activeTab === 'notifications' ? '' : ' d-none'; ?>" id="panel-notifications" role="tabpanel" aria-labelledby="tab-notifications">
        <div class="form-card settings-narrow">
            <div class="form-head"><h2>Notifications</h2></div>
            <form method="post" action="" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_notifications">
                <ul class="notify-options<?php echo $activeTab === 'notifications' ? '' : ''; ?>">
                    <li class="opt-row"><input type="checkbox" id="notify_bookings" name="notify_bookings" <?php echo (int)($user['notify_bookings'] ?? 1) ? 'checked' : ''; ?>> <label for="notify_bookings">Booking updates</label></li>
                    <li class="opt-row"><input type="checkbox" id="notify_promo" name="notify_promo" <?php echo (int)($user['notify_promo'] ?? 1) ? 'checked' : ''; ?>> <label for="notify_promo">Promotional emails</label></li>
                    <li class="opt-row"><input type="checkbox" id="notify_expiry" name="notify_expiry" <?php echo (int)($user['notify_expiry'] ?? 1) ? 'checked' : ''; ?>> <label for="notify_expiry">Expiry reminders</label></li>
                    <li class="opt-row"><input type="checkbox" id="notify_sms" name="notify_sms" <?php echo (int)($user['notify_sms'] ?? 0) ? 'checked' : ''; ?>> <label for="notify_sms">SMS notifications</label></li>
                </ul>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-floppy-disk"></i> Save preferences</button>
            </form>
        </div>
    </section>
    <section class="settings-panel<?php echo $activeTab === 'preferences' ? '' : ' d-none'; ?>" id="panel-preferences" role="tabpanel" aria-labelledby="tab-preferences">
        <div class="form-card settings-narrow">
            <div class="form-head"><h2>Appearance</h2></div>
            <div class="theme-options" id="themeOptions" role="radiogroup" aria-label="Colour theme">
                <button type="button" class="theme-option" data-theme="system" role="radio" aria-checked="false"><i class="fa-solid fa-circle-half-stroke"></i><span><strong>System</strong><em>Follow your device</em></span></button>
                <button type="button" class="theme-option" data-theme="light" role="radio" aria-checked="false"><i class="fa-solid fa-sun"></i><span><strong>Light</strong><em>Bright and clean</em></span></button>
                <button type="button" class="theme-option" data-theme="dark" role="radio" aria-checked="false"><i class="fa-solid fa-moon"></i><span><strong>Dark</strong><em>Easy on the eyes</em></span></button>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var nav = document.getElementById('settingsNav');
    var tabs = nav ? Array.prototype.slice.call(nav.querySelectorAll('.settings-nav-btn')) : [];
    var panels = {};
    tabs.forEach(function (tab) {
        panels[tab.getAttribute('data-tab')] = document.getElementById('panel-' + tab.getAttribute('data-tab'));
    });
    function showTab(name) {
        tabs.forEach(function (t) {
            var isActive = t.getAttribute('data-tab') === name;
            t.classList.toggle('active', isActive);
            t.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        Object.keys(panels).forEach(function (key) {
            if (panels[key]) { panels[key].classList.toggle('d-none', key !== name); }
        });
    }
    if (nav) {
        tabs.forEach(function (tab) { tab.addEventListener('click', function () { showTab(tab.getAttribute('data-tab')); }); });
        var active = nav.querySelector('.settings-nav-btn.active');
        if (active) { showTab(active.getAttribute('data-tab')); }
    }
    var pwInput = document.getElementById('new_password');
    var pwReqs = document.getElementById('pwRequirements');
    if (pwInput && pwReqs) {
        function checkRequirements() {
            var v = pwInput.value;
            pwReqs.querySelector('[data-req="length"]').classList.toggle('met', v.length >= 8);
            pwReqs.querySelector('[data-req="letter"]').classList.toggle('met', /[A-Za-z]/.test(v));
            pwReqs.querySelector('[data-req="number"]').classList.toggle('met', /[0-9]/.test(v));
            pwReqs.querySelector('[data-req="special"]').classList.toggle('met', /[^A-Za-z0-9]/.test(v));
        }
        pwInput.addEventListener('input', checkRequirements); checkRequirements();
    }
    var currentPw = document.getElementById('current_password');
    var pwSameWarn = document.getElementById('pwSameWarn');
    if (currentPw && pwInput && pwSameWarn) {
        function checkSame() { pwSameWarn.hidden = !(currentPw.value !== '' && pwInput.value !== '' && currentPw.value === pwInput.value); }
        currentPw.addEventListener('input', checkSame); pwInput.addEventListener('input', checkSame);
    }
    var THEME_KEY = 'goalspace-theme';
    function systemPrefersDark() { return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches; }
    function savedTheme() { try { return localStorage.getItem(THEME_KEY); } catch (e) { return null; } }
    function activeTheme() { var s = savedTheme(); if (s) return s; return systemPrefersDark() ? 'dark' : 'light'; }
    var themeOptions = document.getElementById('themeOptions');
    if (themeOptions) {
        function syncThemeOptions() {
            var s = savedTheme() || 'system';
            Array.prototype.forEach.call(themeOptions.querySelectorAll('.theme-option'), function (opt) {
                var isActive = opt.getAttribute('data-theme') === s;
                opt.classList.toggle('active', isActive);
                opt.setAttribute('aria-checked', isActive ? 'true' : 'false');
            });
            var theme = activeTheme();
            document.documentElement.setAttribute('data-theme', theme);
            var themeToggle = document.getElementById('themeToggle');
            var themeIcon = document.getElementById('themeIcon');
            if (themeToggle) { themeToggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false'); }
            if (themeIcon) { themeIcon.className = 'fa-solid ' + (theme === 'dark' ? 'fa-sun' : 'fa-moon'); }
        }
        themeOptions.addEventListener('click', function (e) {
            var opt = e.target.closest('.theme-option');
            if (!opt) { return; }
            var selected = opt.getAttribute('data-theme');
            try { if (selected === 'system') { localStorage.removeItem(THEME_KEY); } else { localStorage.setItem(THEME_KEY, selected); } } catch (err) { /* ignore */ }
            syncThemeOptions();
        });
        syncThemeOptions();
    }
});
</script>

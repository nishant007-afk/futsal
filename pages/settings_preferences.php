<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$page_title = 'Preferences';
require __DIR__ . '/../includes/header.php';
?>

<div class="stg-layout">
    <nav class="stg-sidebar" aria-label="Settings navigation">
        <div class="stg-sidebar-head">
            <a href="<?php echo base_url('pages/profile.php'); ?>" class="stg-sidebar-back" aria-label="Back to profile"><i class="fa-solid fa-arrow-left"></i></a>
            <h1>Settings</h1>
        </div>
        <a href="<?php echo base_url('pages/profile.php'); ?>" class="stg-sidebar-user">
            <span class="stg-sidebar-avatar">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?php echo base_url('uploads/avatars/' . rawurlencode($user['avatar'])); ?>" alt="" loading="lazy" decoding="async">
                <?php else: ?>
                    <?php echo e(strtoupper(substr($user['name'], 0, 1))); ?>
                <?php endif; ?>
            </span>
            <span class="stg-sidebar-user-info">
                <strong><?php echo e($user['name']); ?></strong>
                <span><?php echo e($user['email']); ?></span>
            </span>
        </a>
        <div class="stg-sidebar-section">
            <span class="stg-sidebar-label">Account</span>
            <a href="<?php echo base_url('pages/settings.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-sliders"></i> General</a>
            <a href="<?php echo base_url('pages/settings_account.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-user-pen"></i> Edit profile</a>
            <a href="<?php echo base_url('pages/security.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-lock"></i> Password</a>
        </div>
        <div class="stg-sidebar-section">
            <span class="stg-sidebar-label">Preferences</span>
            <a href="<?php echo base_url('pages/settings_notifications.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-bell"></i> Notifications</a>
            <a href="<?php echo base_url('pages/settings_preferences.php'); ?>" class="stg-sidebar-link active"><i class="fa-solid fa-palette"></i> Appearance</a>
        </div>
        <div class="stg-sidebar-section">
            <span class="stg-sidebar-label">Support</span>
            <a href="<?php echo base_url('pages/faq.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-circle-question"></i> Help & FAQ</a>
            <a href="<?php echo base_url('pages/page.php?slug=terms'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-file-lines"></i> Terms</a>
            <a href="<?php echo base_url('pages/page.php?slug=privacy'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-shield-halved"></i> Privacy</a>
        </div>
        <div class="stg-sidebar-section">
            <form method="post" action="<?php echo base_url('pages/logout.php'); ?>" class="m-0">
                <?php echo csrf_field(); ?>
                <button type="submit" class="stg-sidebar-link stg-sidebar-danger" data-confirm="Log out of your account?" data-confirm-ok="Yes, log out" data-confirm-cancel="Cancel"><i class="fa-solid fa-right-from-bracket"></i> Log out</button>
            </form>
        </div>
    </nav>

    <div class="stg-main">
        <div class="stg-main-head"><h2>Appearance</h2></div>
        <div class="stg-content-card">
            <div class="stg-content-section">
                <h3>Theme</h3>
                <p class="stg-content-desc">Choose your preferred colour theme.</p>
                <div class="theme-options" id="themeOptions" role="radiogroup" aria-label="Colour theme">
                    <button type="button" class="theme-option" data-theme="system" role="radio" aria-checked="false">
                        <i class="fa-solid fa-circle-half-stroke"></i>
                        <span><strong>System</strong><em>Follow your device</em></span>
                    </button>
                    <button type="button" class="theme-option" data-theme="light" role="radio" aria-checked="false">
                        <i class="fa-solid fa-sun"></i>
                        <span><strong>Light</strong><em>Bright and clean</em></span>
                    </button>
                    <button type="button" class="theme-option" data-theme="dark" role="radio" aria-checked="false">
                        <i class="fa-solid fa-moon"></i>
                        <span><strong>Dark</strong><em>Easy on the eyes</em></span>
                    </button>
                </div>
                <p class="form-hint theme-hint"><i class="fa-solid fa-circle-info"></i> You can also flip the theme from the <strong>profile menu</strong> in the top-right corner.</p>
            </div>
            <div class="stg-content-section">
                <h3>Location</h3>
                <p class="stg-content-desc">Manage how your location is used.</p>
                <div class="pref-row">
                    <div class="pref-row-text">
                        <strong>Use my location</strong>
                        <em>Show courts near you and sort by distance</em>
                    </div>
                    <label class="switch">
                        <input type="checkbox" id="nearmeToggle">
                        <span class="switch-track" aria-hidden="true"></span>
                    </label>
                </div>
                <p class="form-hint theme-hint"><i class="fa-solid fa-shield-halved"></i> Your exact position is only used in your browser to find nearby courts. It is never stored or shared.</p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function () {
    var THEME_KEY = 'goalspace-theme';
    function systemPrefersDark() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    function savedTheme() {
        try { return localStorage.getItem(THEME_KEY); } catch (e) { return null; }
    }
    function activeTheme() {
        var s = savedTheme();
        if (s === 'dark') { return 'dark'; }
        if (s === 'light') { return 'light'; }
        return systemPrefersDark() ? 'dark' : 'light';
    }
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
            try { localStorage.setItem(THEME_KEY, selected); } catch (err) {}
            syncThemeOptions();
        });
        syncThemeOptions();
    }
})();
</script>

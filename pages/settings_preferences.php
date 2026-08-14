<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$page_title = 'Preferences';
require __DIR__ . '/../includes/header.php';
?>

    <div class="page-head settings-page-head">
        <div class="ps-head-row">
            <a href="<?php echo base_url('pages/settings.php'); ?>" class="nav-back mob-title-back" aria-label="Back to settings"><i class="fa-solid fa-arrow-left"></i></a>
            <h2>Appearance</h2>
        </div>
    </div>

    <div class="settings-card settings-narrow">
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
        if (s) { return s; }
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
            try {
                if (selected === 'system') { localStorage.removeItem(THEME_KEY); }
                else { localStorage.setItem(THEME_KEY, selected); }
            } catch (err) { /* ignore */ }
            syncThemeOptions();
        });
        syncThemeOptions();
    }
})();
</script>

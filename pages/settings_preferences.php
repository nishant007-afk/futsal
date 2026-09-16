<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();
$currentPage = 'appearance';

$page_title = 'Preferences';
require __DIR__ . '/../includes/header.php';
?>

<div class="stg-layout">
    <?php settings_sidebar('appearance'); ?>

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

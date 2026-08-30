    </main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a href="<?php echo base_url('index.php'); ?>" class="brand">
                <span class="brand-mark"><i class="fa-solid fa-futbol"></i></span>
                GoalSpace
            </a>
            <p>Find a free court near you, book your slot, and get on with the game.</p>
        </div>

        <div class="footer-col">
            <h4>Players</h4>
            <a href="<?php echo base_url('pages/how_to_use.php'); ?>">How to use</a>
            <a href="<?php echo grounds_list_url(); ?>">Browse grounds</a>
            <a href="<?php echo base_url('pages/my_bookings.php'); ?>">My bookings</a>
            <a href="<?php echo base_url('pages/register.php'); ?>">Become a member</a>
        </div>

        <div class="footer-col">
            <h4>For owners</h4>
            <a href="<?php echo base_url('pages/how_to_use.php#managers'); ?>">Manager guide</a>
            <a href="<?php echo base_url('pages/register.php?role=manager'); ?>">Become a manager</a>
            <a href="<?php echo base_url('pages/page.php?slug=help#for-managers'); ?>">Manager help</a>
            <a href="<?php echo base_url('pages/page.php?slug=terms#for-managers'); ?>">Manager terms</a>
        </div>

        <div class="footer-col">
            <h4>Company</h4>
            <a href="<?php echo base_url('pages/page.php?slug=about'); ?>">About us</a>
            <a href="<?php echo base_url('pages/page.php?slug=contact'); ?>">Contact</a>
            <a href="<?php echo base_url('pages/page.php?slug=terms'); ?>">Terms of service</a>
            <a href="<?php echo base_url('pages/page.php?slug=privacy'); ?>">Privacy policy</a>
            <a href="<?php echo base_url('pages/faq.php'); ?>">FAQ</a>
        </div>

        <div class="footer-col footer-contact">
            <h4>Contact</h4>
            <span><i class="fa-solid fa-envelope"></i> hello@goalspace.com</span>
            <span><i class="fa-solid fa-phone"></i> +977 9800 000 000</span>
            <span><i class="fa-solid fa-location-dot"></i> Kathmandu, Nepal</span>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container footer-bottom-inner">
            <p>&copy; <?php echo date('Y'); ?> GoalSpace. All rights reserved.</p>
            <p>
                <a href="<?php echo base_url('pages/page.php?slug=privacy'); ?>">Privacy</a> &middot;
                <a href="<?php echo base_url('pages/page.php?slug=terms'); ?>">Terms</a> &middot;
                <a href="<?php echo base_url('pages/page.php?slug=help'); ?>">Help</a> &middot;
                <a href="<?php echo base_url('pages/faq.php'); ?>">FAQ</a>
            </p>
        </div>
    </div>
<button type="button" id="backToTop" aria-label="Back to top" title="Back to top"><i class="fa-solid fa-angles-up"></i></button>
</footer>

<!-- Cookie consent -->
<div class="cookie-banner" id="cookieBanner" role="dialog" aria-label="Cookie consent">
    <i class="fa-solid fa-cookie-bite cookie-icon"></i>
    <div class="cookie-text">
        <strong>Cookie notice</strong>
        <p>We use cookies to keep things secure and make the site work properly. Learn more in our <a href="<?php echo base_url('pages/page.php?slug=privacy'); ?>">Privacy Policy</a>.</p>
    </div>
    <div class="cookie-actions">
        <button type="button" class="btn btn-ghost btn-sm" id="cookieDecline">Not now</button>
        <button type="button" class="btn btn-primary btn-sm" id="cookieAccept">Got it</button>
    </div>
</div>

<?php
// Command palette: role-aware quick links (court search is handled in core.js).
$paletteCommands = [
    ['label' => 'Home', 'hint' => 'Back to the homepage', 'url' => base_url('index.php'), 'icon' => 'fa-house'],
    ['label' => 'Browse courts', 'hint' => 'All courts, filters and prices', 'url' => grounds_list_url(), 'icon' => 'fa-futbol'],
];
if ($site_user) {
    if ($site_user['role'] === 'user') {
        $paletteCommands[] = ['label' => 'My bookings', 'hint' => 'Upcoming and past games', 'url' => base_url('pages/my_bookings.php'), 'icon' => 'fa-calendar-check'];
        $paletteCommands[] = ['label' => 'Saved courts', 'hint' => 'Courts you\'ve favourited', 'url' => base_url('pages/favorites.php'), 'icon' => 'fa-heart'];
    } elseif ($site_user['role'] === 'manager') {
        $paletteCommands[] = ['label' => 'Manager dashboard', 'hint' => 'Overview of your grounds', 'url' => base_url('manager/dashboard.php'), 'icon' => 'fa-gauge-high'];
        $paletteCommands[] = ['label' => 'My grounds', 'hint' => 'Manage your courts and hours', 'url' => base_url('manager/grounds.php'), 'icon' => 'fa-store'];
        $paletteCommands[] = ['label' => 'Manager bookings', 'hint' => 'All bookings on your courts', 'url' => base_url('manager/bookings.php'), 'icon' => 'fa-calendar-check'];
    } elseif ($site_user['role'] === 'admin') {
        $paletteCommands[] = ['label' => 'Admin dashboard', 'hint' => 'Platform overview', 'url' => base_url('admin/dashboard.php'), 'icon' => 'fa-gauge-high'];
        $paletteCommands[] = ['label' => 'Manage users', 'hint' => 'Roles and accounts', 'url' => base_url('admin/users.php'), 'icon' => 'fa-users'];
        $paletteCommands[] = ['label' => 'Manage grounds', 'hint' => 'All courts on the platform', 'url' => base_url('admin/grounds.php'), 'icon' => 'fa-store'];
        $paletteCommands[] = ['label' => 'Settlements', 'hint' => 'Manager payouts', 'url' => base_url('admin/settlements.php'), 'icon' => 'fa-sack-dollar'];
    }
    $paletteCommands[] = ['label' => 'Notifications', 'hint' => 'Your updates and alerts', 'url' => base_url('pages/notifications.php'), 'icon' => 'fa-bell'];
    $paletteCommands[] = ['label' => 'Settings', 'hint' => 'Account, preferences and security', 'url' => base_url('pages/settings.php'), 'icon' => 'fa-gear'];
    $paletteCommands[] = ['label' => 'My profile', 'hint' => 'Edit your details and photo', 'url' => base_url('pages/profile.php'), 'icon' => 'fa-user'];
    $paletteCommands[] = ['label' => 'Log out', 'hint' => 'End your session', 'url' => base_url('pages/logout.php?csrf=' . csrf_token()), 'icon' => 'fa-right-from-bracket'];
} else {
    $paletteCommands[] = ['label' => 'Log in', 'hint' => 'Sign in to your account', 'url' => base_url('pages/login.php'), 'icon' => 'fa-right-to-bracket'];
    $paletteCommands[] = ['label' => 'Create account', 'hint' => 'Join as a player', 'url' => base_url('pages/register.php'), 'icon' => 'fa-user-plus'];
    $paletteCommands[] = ['label' => 'Become a manager', 'hint' => 'List your court on GoalSpace', 'url' => base_url('pages/register.php?role=manager'), 'icon' => 'fa-store'];
}
?>

<!-- Command palette (Ctrl/Cmd + K) -->
<div class="palette-backdrop" id="paletteBackdrop" hidden>
    <div class="palette" role="dialog" aria-modal="true" aria-label="Quick navigation">
        <div class="palette-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="text" id="paletteInput" placeholder="Search courts or jump to a page..." autocomplete="off" spellcheck="false" role="combobox" aria-expanded="false" aria-controls="paletteBody">
            <span class="palette-kbd">esc</span>
        </div>
        <div class="palette-body" id="paletteBody"></div>
        <div class="palette-foot">
            <span><i class="fa-solid fa-arrow-up"></i><i class="fa-solid fa-arrow-down"></i> navigate</span>
            <span><i class="fa-solid fa-arrow-right"></i> open</span>
            <span><i class="fa-solid fa-magnifying-glass"></i> live court search</span>
        </div>
    </div>
</div>
<script>window.GS_COMMANDS = <?php echo json_encode($paletteCommands); ?>;</script>

<script src="<?php echo base_url('assets/js/core.js?v=82'); ?>" defer></script>
<?php
// Code-split bundles: only load the JS a page/role actually needs.
$pageModules = [];
if (in_array($active, ['ground.php', 'book.php', 'payment.php', 'reschedule.php'], true)) {
    $pageModules[] = 'booking';
}
if (in_array($active, [
    'login.php', 'register.php', 'forgot_password.php', 'reset_password.php',
    'verify.php', 'otp_verify.php', 'profile.php',
    'change_email_otp.php', 'change_password_otp.php', 'google_setup.php',
    'settings.php', 'settings_notifications.php',
    'settings_preferences.php', 'security.php'
], true)) {
    $pageModules[] = 'auth';
}
if ($site_user && in_array($site_user['role'], ['manager', 'admin'], true)) {
    $pageModules[] = 'manager';
}
foreach ($pageModules as $module) {
    echo '<script src="' . base_url('assets/js/modules/' . $module . '.js?v=2') . '" defer></script>' . "\n";
}
?>
</body>
</html>

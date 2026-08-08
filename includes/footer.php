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
            <a href="<?php echo grounds_list_url(); ?>">Browse grounds</a>
            <a href="<?php echo base_url('pages/my_bookings.php'); ?>">My bookings</a>
            <a href="<?php echo base_url('pages/register.php'); ?>">Create account</a>
            <a href="<?php echo base_url('pages/login.php'); ?>">Log in</a>
        </div>

        <div class="footer-col">
            <h4>For owners</h4>
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
                <a href="<?php echo base_url('pages/page.php?slug=help'); ?>">Help</a>
            </p>
        </div>
    </div>
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

<script src="<?php echo base_url('assets/js/core.js?v=60'); ?>" defer></script>
<?php
// Code-split bundles: only load the JS a page/role actually needs.
$pageModules = [];
if (in_array($active, ['ground.php', 'book.php', 'payment.php', 'reschedule.php'], true)) {
    $pageModules[] = 'booking';
}
if (in_array($active, [
    'login.php', 'register.php', 'forgot_password.php', 'reset_password.php',
    'verify.php', 'otp_verify.php', 'change_password.php', 'profile.php',
    'change_email_otp.php', 'change_password_otp.php'
], true)) {
    $pageModules[] = 'auth';
}
if ($site_user && in_array($site_user['role'], ['manager', 'admin'], true)) {
    $pageModules[] = 'manager';
}
foreach ($pageModules as $module) {
    echo '<script src="' . base_url('assets/js/modules/' . $module . '.js?v=1') . '" defer></script>' . "\n";
}
?>
</body>
</html>

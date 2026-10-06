    </main>

<?php
$active = basename($_SERVER['SCRIPT_NAME']);
?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a href="<?php echo base_url('index.php'); ?>" class="brand" aria-label="GoalSpace home">
                <span class="brand-mark"><i class="fa-solid fa-futbol"></i></span>
                <span class="brand-name">GoalSpace</span>
            </a>
            <p>Find a free court near you, book your slot, and get on with the game.</p>
        </div>

        <div class="footer-col">
            <h4>Players</h4>
            <a href="<?php echo grounds_list_url(); ?>">Browse courts</a>
            <a href="<?php echo base_url('pages/my_bookings.php'); ?>">My bookings</a>
            <a href="<?php echo base_url('pages/register.php'); ?>">Create an account</a>
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
            <a href="<?php echo base_url('pages/faq.php'); ?>">FAQ</a>
        </div>

        <div class="footer-col footer-contact">
            <h4>Contact</h4>
            <span aria-label="Email"><i class="fa-solid fa-envelope"></i> <a href="mailto:hello@goalspace.com">hello@goalspace.com</a></span>
            <span aria-label="Phone"><i class="fa-solid fa-phone"></i> <a href="tel:+9779800000000">+977 9800 000 000</a></span>
            <span aria-label="GMB"><i class="fa-brands fa-google"></i> <a href="https://www.google.com/business/" target="_blank" rel="noopener">Google My Business</a></span>
            <span aria-label="Location"><i class="fa-solid fa-location-dot"></i> Kathmandu, Nepal</span>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container footer-bottom-inner">
            <p>&copy; <?php echo date('Y'); ?> GoalSpace. All rights reserved.</p>
        </div>
    </div>
</footer>

<?php if (!is_logged_in()): ?>
<!-- Cookie consent for guests only -->
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
<?php endif; ?>


<script src="<?php echo base_url('assets/js/core.js?v=' . filemtime(__DIR__ . '/../assets/js/core.js')); ?>" defer></script>
    <script src="<?php echo base_url('assets/js/modules/ui.js?v=' . filemtime(__DIR__ . '/../assets/js/modules/ui.js')); ?>" defer></script>
<script>
(function() {
    function showError(input, message) {
        var group = input.closest('.form-group');
        if (!group) return;
        group.classList.add('has-error');
        var errEl = group.querySelector('.field-error');
        if (!errEl) {
            errEl = document.createElement('p');
            errEl.className = 'field-error';
            errEl.setAttribute('role', 'alert');
            errEl.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i>';
            group.appendChild(errEl);
        }
        errEl.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + message;
        input.addEventListener('input', function() {
            group.classList.remove('has-error');
            if (errEl && errEl.parentNode) errEl.parentNode.removeChild(errEl);
        }, { once: true });
    }

    function clearErrors(form) {
        form.querySelectorAll('.form-group.has-error').forEach(function(g) {
            g.classList.remove('has-error');
        });
        form.querySelectorAll('.field-error').forEach(function(e) {
            if (e.parentNode) e.parentNode.removeChild(e);
        });
    }

    function validateForm(form) {
        clearErrors(form);
        var valid = true;
        var firstError = null;

        form.querySelectorAll('input[required], textarea[required], select[required]').forEach(function(input) {
            if (input.type === 'checkbox') {
                if (!input.checked) {
                    var msg = 'This field is required.';
                    if (input.id === 'regTerms' || input.id === 'termsCheck') {
                        msg = 'Please accept the Terms of Service and Privacy Policy to continue.';
                    }
                    showError(input, msg);
                    valid = false;
                    if (!firstError) firstError = input;
                }
            } else if (input.type === 'radio') {
                var name = input.name;
                if (!form.querySelector('input[name="' + name + '"]:checked')) {
                    showError(input, 'Please choose an option.');
                    valid = false;
                    if (!firstError) firstError = input;
                }
            } else if (input.type === 'hidden' && input.classList.contains('otp-source')) {
                if (!input.value || input.value.trim() === '') {
                    var group = input.closest('.form-group');
                    if (group) {
                        group.classList.add('has-error');
                        var errEl = group.querySelector('.field-error');
                        if (!errEl) {
                            errEl = document.createElement('p');
                            errEl.className = 'field-error';
                            errEl.setAttribute('role', 'alert');
                            group.appendChild(errEl);
                        }
                        errEl.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Enter the 6-digit code from your email.';
                        valid = false;
                        if (!firstError) firstError = input;
                    }
                }
            } else if (input.type === 'email') {
                var val = input.value.trim();
                if (val === '') {
                    showError(input, 'Enter your email address.');
                    valid = false;
                    if (!firstError) firstError = input;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                    showError(input, 'Enter a valid email address, e.g. you@example.com.');
                    valid = false;
                    if (!firstError) firstError = input;
                }
            } else if (input.type === 'password') {
                if (input.value === '') {
                    var label = input.closest('.form-group');
                    var labelText = label ? label.querySelector('label') : null;
                    var fieldLabel = labelText ? labelText.textContent.trim() : 'Password';
                    showError(input, 'Enter ' + fieldLabel.toLowerCase() + '.');
                    valid = false;
                    if (!firstError) firstError = input;
                }
            } else {
                if (input.value.trim() === '') {
                    var label2 = input.closest('.form-group');
                    var labelText2 = label2 ? label2.querySelector('label') : null;
                    // KEPT (removed per request): this only looked for a visible <label>, so any field
                    // whose label is hidden fell back to "This field" in the error message. Fields that
                    // rely on a placeholder now expose aria-label instead, so that is checked too.
                    var ariaLabel2 = input.getAttribute('aria-label');
                    var fieldLabel2 = labelText2 ? labelText2.textContent.trim()
                        : (ariaLabel2 ? ariaLabel2.trim() : 'This field');
                    showError(input, 'Enter ' + fieldLabel2.toLowerCase() + '.');
                    valid = false;
                    if (!firstError) firstError = input;
                }
            }
        });

        if (!valid && firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (firstError.focus) firstError.focus();
        }

        return valid;
    }

    document.querySelectorAll('form[novalidate]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
})();

/* Shared live filtering: any form.courts-search with data-ajax-results swaps
   that container's markup via fetch (no page reload). Typing in text inputs is
   debounced; selects/dates apply immediately. Links inside the container marked
   data-ajax-link (pagination, view tabs) also swap in place. */
(function() {
    function boxFor(el) {
        var id = el && el.getAttribute && el.getAttribute('data-ajax-results');
        return id ? document.getElementById(id) : null;
    }
    function swap(box, url) {
        if (!box) return;
        var id = box.id;
        window.history.replaceState(null, '', url);
        box.setAttribute('aria-busy', 'true');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.getElementById(id);
                if (fresh) { box.innerHTML = fresh.innerHTML; }
            })
            .catch(function () { window.location.href = url; })
            .then(function () { box.removeAttribute('aria-busy'); });
    }
    function applyFilters(form) {
        var box = boxFor(form);
        if (!box) return;
        var params = new URLSearchParams(new FormData(form));
        swap(box, form.action + '?' + params.toString());
    }
    var debounceTimer;
    document.addEventListener('input', function (e) {
        var t = e.target;
        if (!t || !t.closest || t.type !== 'text') return;
        var form = t.closest('form.courts-search');
        if (!form || !boxFor(form)) return;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () { applyFilters(form); }, 350);
    });
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        var form = t.closest('form.courts-search');
        if (!form || !boxFor(form)) return;
        applyFilters(form);
    });
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        var link = t.closest('[data-ajax-link]');
        if (!link) return;
        var box = document.getElementById(link.getAttribute('data-ajax-link'));
        if (!box) return;
        e.preventDefault();
        swap(box, link.href);
    });
})();
</script>
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
    // Slide-over booking viewer, used by the manager and admin bookings tables.
    $pageModules[] = 'booking_drawer';
}
foreach ($pageModules as $module) {
        echo '<script src="' . base_url('assets/js/modules/' . $module . '.js?v=' . filemtime(__DIR__ . '/../assets/js/modules/' . $module . '.js')) . '" defer></script>' . "\n";
}
?>
</body>
</html>

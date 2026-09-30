<?php
// Unified Split Screen Auth View (Desktop alternating split-card, mobile overlapping sheet)
$active_mode = $active_auth_tab ?? 'login';
$suspended = $suspended ?? false;
$suspendedEmail = $suspendedEmail ?? '';
$showTimeout = $showTimeout ?? false;
$loginErrors = $loginErrors ?? ($errors ?? []);
$loginEmail = $loginEmail ?? ($email ?? '');
$lock = $lock ?? null;

$regErrors = $regErrors ?? ($errors ?? []);
$regName = $name ?? '';
$regPhone = $phone ?? '';
$regEmail = $email ?? '';
$regRole = $role ?? 'user';
$regAccept = $accept ?? false;
?>

<div class="auth-page-shell">
    <div class="auth-split-card <?php echo $active_mode === 'register' ? 'is-register' : 'is-login'; ?>" id="authSplitCard">
        
        <!-- 1. UNIFIED MOODY FUTSAL MEDIA (Desktop left/right alternate, mobile top banner) -->
        <div class="auth-card-media" id="authMediaSide">
            <div class="auth-media-bg"></div>
            <div class="auth-media-overlay" aria-hidden="true"></div>
            
            <!-- Mobile Navigation Overlay (Visible only on mobile <= 900px) -->
            <div class="auth-mobile-header">
                <a href="<?php echo base_url('index.php'); ?>" class="auth-mobile-back" aria-label="Back to home" title="Back to GoalSpace home">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                </a>
                <span class="auth-mobile-brand">GoalSpace</span>
            </div>
        </div>

        <!-- 2. AUTH FORM CARD AREA -->
        <div class="auth-card-content">
            
            <!-- Brand Identity & Navigation Header (Both desktop & mobile sheet) -->
            <div class="auth-nav-bar">
                <a href="<?php echo base_url('index.php'); ?>" class="auth-brand" aria-label="GoalSpace home">
                    <span class="brand-mark"><i class="fa-solid fa-futbol"></i></span>
                    <span class="brand-name">GoalSpace</span>
                </a>
                <a href="<?php echo base_url('index.php'); ?>" class="auth-back-link">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Back to Home</span>
                </a>
            </div>

            <div class="auth-panels-wrapper">
                
                <!-- LOGIN PANEL (Form Left on desktop, or top sheet on mobile) -->
                <div class="auth-panel auth-panel-login <?php echo $active_mode === 'login' ? 'active-panel' : ''; ?>" id="panelLogin">
                    <div class="auth-panel-body">
                        <?php if ($showTimeout && !$suspended && empty($loginErrors['general'])): ?>
                            <div class="notice notice-info auth-session-banner" role="status">
                                <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                <span><strong>Your session has timed out.</strong> Please sign in to continue.</span>
                            </div>
                        <?php endif; ?>

                        <div class="auth-form-head">
                            <h1 class="auth-title">Welcome back</h1>
                        </div>

                        <?php if ($suspended): ?>
                            <div class="notice notice-error mb-14" role="alert">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <span>Logins paused for <strong><?php echo e($suspendedEmail); ?></strong>. Contact admin to restore access.</span>
                            </div>
                            <form method="post" action="<?php echo base_url('pages/contact_submit.php'); ?>" novalidate>
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="topic" value="login_locked">
                                <input type="hidden" name="email" value="<?php echo e($suspendedEmail); ?>">
                                <div class="form-group">
                                    <div class="input-group floating">
                                        <input type="text" id="subj" name="subject" value="Account locked after login attempts" placeholder=" " required>
                                        <label for="subj">Subject</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="msg">Message</label>
                                    <textarea id="msg" name="message" rows="3" class="form-textarea" placeholder="Explain what happened..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-paper-plane"></i> Send to admin</button>
                            </form>
                        <?php else: ?>
                            <?php if (!empty($loginErrors['general'])): ?>
                                <div class="notice notice-error mb-12" role="alert">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                    <span><?php echo e($loginErrors['general']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($lock): ?>
                                <div class="lock-card mb-12" data-lock-ends="<?php echo e($lock['ends']); ?>" data-lock-total="<?php echo (int)$lock['seconds']; ?>" id="lockNotice">
                                    <div class="lock-head"><strong>Too many wrong attempts. Retry in <?php echo gmdate('i:s', $lock['seconds']); ?>.</strong></div>
                                </div>
                            <?php endif; ?>

                            <!-- Third-Party Auth: Continue with Google -->
                            <a href="<?php echo base_url('pages/login_google.php?intent=login'); ?>" class="btn-google-auth">
                                <?php google_svg_icon(); ?>
                                <span>Continue with Google</span>
                            </a>
                            
                            <div class="auth-divider"><span>or sign in with email</span></div>

                            <form method="post" action="<?php echo base_url('pages/login.php'); ?>" class="auth-fields" novalidate id="loginForm">
                                <?php echo csrf_field(); ?>
                                <?php honeypot_field(); ?>

                                <div class="form-group<?php echo has_error($loginErrors, 'email'); ?>">
                                    <div class="input-group floating">
                                        <input type="email" id="loginEmail" name="email" value="<?php echo e($loginEmail); ?>" placeholder=" " autocomplete="email" required aria-required="true" <?php echo $lock ? 'disabled' : ''; ?>>
                                        <label for="loginEmail">Email <span class="req">*</span></label>
                                    </div>
                                    <?php field_error($loginErrors, 'email'); ?>
                                </div>

                                <div class="form-group<?php echo has_error($loginErrors, 'password'); ?>">
                                    <div class="input-group floating">
                                        <input type="password" id="loginPassword" name="password" placeholder=" " autocomplete="current-password" required aria-required="true" <?php echo $lock ? 'disabled' : ''; ?>>
                                        <label for="loginPassword">Password <span class="req">*</span></label>
                                        <button type="button" class="pw-toggle" data-target="loginPassword" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                                    </div>
                                    <?php field_error($loginErrors, 'password'); ?>
                                </div>

                                <div class="auth-aux-row">
                                    <label class="check-line" for="remember_me">
                                        <input type="checkbox" id="remember_me" name="remember_me" value="1" aria-label="Remember me">
                                        <span class="check-box"><i class="fa-solid fa-check"></i></span>
                                        <span>Remember this device</span>
                                    </label>
                                    <a href="<?php echo base_url('pages/forgot_password.php'); ?>" class="auth-forgot-link">Forgot password?</a>
                                </div>

                                <button type="submit" class="btn btn-primary btn-block auth-submit-btn" <?php echo $lock ? 'disabled' : 'data-autogate=""'; ?>>
                                    <i class="fa-solid fa-right-to-bracket"></i>
                                    <span>Log in</span>
                                </button>
                                
                                <p class="auth-switch-prompt">
                                    Don't have an account? 
                                    <button type="button" class="auth-mode-switch" id="toRegisterBtn" data-target-mode="register">
                                        <strong>Create account</strong>
                                    </button>
                                </p>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- REGISTER PANEL (Form Right on desktop, or top sheet on mobile) -->
                <div class="auth-panel auth-panel-register <?php echo $active_mode === 'register' ? 'active-panel' : ''; ?>" id="panelRegister">
                    <div class="auth-panel-body">
                        <div class="auth-form-head">
                            <h1 class="auth-title">Create your account</h1>
                        </div>

                        <?php if (!empty($regErrors['general'])): ?>
                            <div class="notice notice-error mb-12" role="alert">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <span><?php echo e($regErrors['general']); ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?php echo base_url('pages/register.php'); ?>" class="auth-fields" novalidate id="registerForm">
                            <?php echo csrf_field(); ?>
                            <?php honeypot_field(); ?>

                            <!-- Role Switcher Hierarchy: Directly beneath main header -->
                            <div class="role-segmented-box">
                                <div class="role-segmented-toggle" role="radiogroup" aria-label="Account Type">
                                    <label class="role-seg-btn <?php echo $regRole === 'user' ? 'active' : ''; ?>">
                                        <input type="radio" name="role" value="user" <?php echo $regRole === 'user' ? 'checked' : ''; ?>>
                                        <i class="fa-solid fa-futbol"></i>
                                        <span>Player</span>
                                    </label>
                                    <label class="role-seg-btn <?php echo $regRole === 'manager' ? 'active' : ''; ?>">
                                        <input type="radio" name="role" value="manager" <?php echo $regRole === 'manager' ? 'checked' : ''; ?>>
                                        <i class="fa-solid fa-building-user"></i>
                                        <span>Venue Manager</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Third-Party Auth: Continue with Google -->
                            <a href="<?php echo base_url('pages/login_google.php?intent=signup'); ?>" class="btn-google-auth">
                                <?php google_svg_icon(); ?>
                                <span>Continue with Google</span>
                            </a>

                            <div class="auth-divider"><span>or fill details</span></div>

                            <div class="form-row-2 form-row-name-phone">
                                <div class="form-group<?php echo has_error($regErrors, 'name'); ?>">
                                    <div class="input-group floating">
                                        <input type="text" id="regName" name="name" value="<?php echo e($regName); ?>" autocomplete="name" placeholder=" " maxlength="100" required aria-required="true">
                                        <label for="regName">Full name <span class="req">*</span></label>
                                    </div>
                                    <?php field_error($regErrors, 'name'); ?>
                                </div>
                                <div class="form-group<?php echo has_error($regErrors, 'phone'); ?>">
                                    <div class="input-group floating">
                                        <input type="tel" id="regPhone" name="phone" value="<?php echo e($regPhone); ?>" autocomplete="tel" placeholder=" " maxlength="15">
                                        <label for="regPhone">Phone <span class="opt">(optional)</span></label>
                                    </div>
                                    <?php field_error($regErrors, 'phone'); ?>
                                </div>
                            </div>

                            <div class="form-group<?php echo has_error($regErrors, 'email'); ?>">
                                <div class="input-group floating">
                                    <input type="email" id="regEmail" name="email" value="<?php echo e($regEmail); ?>" autocomplete="email" placeholder=" " required aria-required="true" data-check-email="available">
                                    <label for="regEmail">Email <span class="req">*</span></label>
                                </div>
                                <?php field_error($regErrors, 'email'); ?>
                            </div>

                                <div class="form-row-2 form-row-passwords">
                                    <div class="form-group<?php echo has_error($regErrors, 'password'); ?>">
                                        <div class="input-group floating">
                                            <input type="password" id="regPassword" name="password" autocomplete="new-password" minlength="8" placeholder=" " required aria-required="true">
                                            <label for="regPassword">Password <span class="req">*</span></label>
                                            <button type="button" class="pw-toggle" data-target="regPassword" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                                        </div>
                                        <?php field_error($regErrors, 'password'); ?>
                                    </div>
                                    <div class="form-group<?php echo has_error($regErrors, 'confirm'); ?>">
                                        <div class="input-group floating">
                                            <input type="password" id="regConfirm" name="confirm" autocomplete="new-password" minlength="8" placeholder=" " required aria-required="true">
                                            <label for="regConfirm">Confirm password <span class="req">*</span></label>
                                            <button type="button" class="pw-toggle" data-target="regConfirm" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                                        </div>
                                        <?php field_error($regErrors, 'confirm'); ?>
                                    </div>
                                </div>

                                <div class="pw-requirements-wrap">

                                <?php require __DIR__ . '/pw_requirements.php'; ?>
                            </div>

                            <label class="check-line compact-check" for="regTerms">
                                <input type="checkbox" id="regTerms" name="accept" value="1" aria-label="Accept terms" required aria-required="true" <?php echo $regAccept ? 'checked' : ''; ?>>
                                <span class="check-box"><i class="fa-solid fa-check"></i></span>
                                <span>I agree to the <a href="<?php echo base_url('pages/page.php?slug=terms'); ?>" target="_blank" rel="noopener">Terms of Service</a> and <a href="<?php echo base_url('pages/page.php?slug=privacy'); ?>" target="_blank" rel="noopener">Privacy Policy</a> <span class="req">*</span></span>
                            </label>
                            <?php field_error($regErrors, 'terms'); ?>

                            <button type="submit" class="btn btn-primary btn-block auth-submit-btn" data-autogate="">
                                <i class="fa-solid fa-user-plus"></i>
                                <span>Create account</span>
                            </button>
                            
                            <p class="auth-switch-prompt">
                                Already have an account? 
                                <button type="button" class="auth-mode-switch" id="toLoginBtn" data-target-mode="login">
                                    <strong>Sign in</strong>
                                </button>
                            </p>
                        </form>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var card = document.getElementById('authSplitCard');
    if (!card) return;

    function setAuthMode(mode, pushUrl) {
        var loginPanel = document.getElementById('panelLogin');
        var regPanel = document.getElementById('panelRegister');

        if (mode === 'register') {
            card.classList.remove('is-login');
            card.classList.add('is-register');
            if (loginPanel) loginPanel.classList.remove('active-panel');
            if (regPanel) regPanel.classList.add('active-panel');
            document.title = 'Sign Up | GoalSpace';
            if (pushUrl) {
                history.pushState({ mode: 'register' }, '', 'register.php');
            }
        } else {
            card.classList.remove('is-register');
            card.classList.add('is-login');
            if (regPanel) regPanel.classList.remove('active-panel');
            if (loginPanel) loginPanel.classList.add('active-panel');
            document.title = 'Login | GoalSpace';
            if (pushUrl) {
                history.pushState({ mode: 'login' }, '', 'login.php');
            }
        }

        // Smooth scroll to top of card on mobile
        if (window.innerWidth <= 900) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    var toRegisterBtn = document.getElementById('toRegisterBtn');
    if (toRegisterBtn) {
        toRegisterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            setAuthMode('register', true);
        });
    }

    var toLoginBtn = document.getElementById('toLoginBtn');
    if (toLoginBtn) {
        toLoginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            setAuthMode('login', true);
        });
    }

    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.mode) {
            setAuthMode(e.state.mode, false);
        } else {
            var path = window.location.pathname;
            if (path.indexOf('register.php') !== -1) {
                setAuthMode('register', false);
            } else {
                setAuthMode('login', false);
            }
        }
    });

    // Role switcher segment logic
    var roleOpts = card.querySelectorAll('.role-seg-btn');
    roleOpts.forEach(function(opt) {
        opt.addEventListener('click', function() {
            roleOpts.forEach(function(o) { o.classList.remove('active'); });
            opt.classList.add('active');
            var radio = opt.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }
        });
    });

    // Keep the focused field visible above the on-screen keyboard (mobile only).
    var focusScrolled = false;
    document.addEventListener('focusin', function (ev) {
        var t = ev.target;
        if (!t || !t.closest || !t.closest('.auth-card-content')) return;
        if (t.tagName !== 'INPUT' && t.tagName !== 'TEXTAREA') return;
        if (window.innerWidth > 900) return;
        clearTimeout(focusScrolled);
        focusScrolled = setTimeout(function () {
            var r = t.getBoundingClientRect();
            var vh = window.innerHeight;
            if (r.top < 72 || r.bottom > vh - 96) {
                t.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 280);
    });

    // Password requirements meter live validation
    var regPw = document.getElementById('regPassword');
    var pwReqs = document.getElementById('pwRequirements');
    if (regPw && pwReqs) {
        function checkPwMeter() {
            var v = regPw.value || '';
            if (v.length > 0 || document.activeElement === regPw) {
                pwReqs.classList.add('open');
            } else {
                pwReqs.classList.remove('open');
            }
            var checks = {
                length: v.length >= 8,
                special: /[^A-Za-z0-9]/.test(v),
                upper: /[A-Z]/.test(v),
                number: /[0-9]/.test(v)
            };
            Object.keys(checks).forEach(function(key) {
                var seg = pwReqs.querySelector('.pw-meter-seg[data-req="' + key + '"]');
                var lbl = pwReqs.querySelector('.pw-meter-labels span[data-req="' + key + '"]');
                if (seg) seg.classList.toggle('met', checks[key]);
                if (lbl) lbl.classList.toggle('met', checks[key]);
            });
        }
        regPw.addEventListener('focus', function() {
            pwReqs.classList.add('open');
            checkPwMeter();
        });
        regPw.addEventListener('input', checkPwMeter);
        regPw.addEventListener('blur', function() {
            if (!regPw.value) {
                pwReqs.classList.remove('open');
            }
        });
        if (regPw.value) {
            checkPwMeter();
        }
    }



    // Floating label zero-delay sync
    function syncAuthFloatingLabels() {
        var inputs = card.querySelectorAll('.auth-fields .input-group.floating input, .input-group.floating input');
        for (var i = 0; i < inputs.length; i++) {
            var el = inputs[i];
            var isAuto = false;
            try {
                isAuto = el.matches(':-webkit-autofill') || el.matches(':autofill');
            } catch (e) {}
            var on = (el.value && el.value.trim() !== '') || el === document.activeElement || isAuto;
            if (el.classList.contains('has-value') !== on) {
                el.classList.toggle('has-value', on);
            }
        }
        if (typeof window.autosync === 'function') {
            window.autosync();
        }
    }

    syncAuthFloatingLabels();
    var authRafCount = 0;
    function authRafLoop() {
        syncAuthFloatingLabels();
        if (++authRafCount < 90) {
            requestAnimationFrame(authRafLoop);
        }
    }
    requestAnimationFrame(authRafLoop);

    document.addEventListener('animationstart', function(e) {
        if (e.animationName === 'autofill') syncAuthFloatingLabels();
    }, true);
    document.addEventListener('input', syncAuthFloatingLabels, true);
    document.addEventListener('change', syncAuthFloatingLabels, true);
    window.addEventListener('pageshow', syncAuthFloatingLabels);
});

// Run immediate inline check as soon as auth markup finishes parsing
(function() {
    function preSyncLabels() {
        var inputs = document.querySelectorAll('.auth-fields .input-group.floating input');
        for (var i = 0; i < inputs.length; i++) {
            var el = inputs[i];
            var isAuto = false;
            try {
                isAuto = el.matches(':-webkit-autofill') || el.matches(':autofill');
            } catch (e) {}
            if ((el.value && el.value.trim() !== '') || isAuto) {
                el.classList.add('has-value');
            }
        }
    }
    preSyncLabels();
    var f = 0;
    function preLoop() {
        preSyncLabels();
        if (++f < 60) requestAnimationFrame(preLoop);
    }
    requestAnimationFrame(preLoop);
})();
</script>

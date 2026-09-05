<?php
if (!isset($conn)) {
    require_once __DIR__ . '/../config/db.php';
}
$site_user = is_logged_in() ? current_user() : null;
$flash = get_flash();
$active = basename($_SERVER['SCRIPT_NAME']);
$activeSection = '';
if (in_array($active, ['ground.php', 'courts.php', 'book.php', 'payment.php', 'receipt.php', 'confirmation.php', 'ics.php'], true)) {
    $activeSection = 'grounds';
} elseif (in_array($active, ['my_bookings.php', 'notifications.php'], true)) {
    $activeSection = 'my_bookings';
} elseif (in_array($active, ['profile.php'], true)) {
    $activeSection = 'profile';
}

$skeletonType = 'generic';
if ($active === 'index.php') {
    $skeletonType = 'home';
} elseif ($active === 'courts.php') {
    $skeletonType = 'list';
} elseif ($active === 'ground.php') {
    $skeletonType = 'ground';
} elseif ($active === 'my_bookings.php') {
    $skeletonType = 'bookings';
} elseif ($active === 'booking_details.php') {
    $skeletonType = 'booking-detail';
} elseif ($active === 'payment.php') {
    $skeletonType = 'payment';
} elseif ($active === 'profile.php') {
    $skeletonType = 'profile';
} elseif ($active === 'favorites.php') {
    $skeletonType = 'favorites';
} elseif ($active === 'map.php') {
    $skeletonType = 'map';
} elseif ($active === 'book.php') {
    $skeletonType = 'book';
} elseif ($active === 'notifications.php' || $active === 'notification_details.php') {
    $skeletonType = 'notifications';
} elseif (in_array($active, ['grounds.php'], true) && $site_user && in_array($site_user['role'], ['manager', 'admin'], true)) {
    $skeletonType = 'manage-grounds';
} elseif (in_array($active, ['grounds.php', 'users.php', 'settlements.php', 'contact_messages.php', 'bookings.php', 'promos.php'], true)) {
    $skeletonType = 'list';
} elseif (in_array($active, ['dashboard.php'], true)) {
    $skeletonType = 'dashboard';
} elseif (in_array($active, ['login.php', 'register.php', 'forgot_password.php', 'reset_password.php', 'verify.php', 'otp_verify.php', 'google_setup.php', 'login_google.php'], true)) {
    $skeletonType = 'auth';
} elseif ($active === 'page.php' || $active === 'how_to_use.php') {
    $skeletonType = 'page';
} elseif ($active === 'contact_submit.php') {
    $skeletonType = 'contact-form';
} elseif ($active === 'confirmation.php') {
    $skeletonType = 'confirmation';
} elseif (in_array($active, ['receipt.php', 'receipt_pdf.php'], true)) {
    $skeletonType = 'receipt';
} elseif ($active === 'reschedule.php') {
    $skeletonType = 'reschedule';
} elseif ($active === 'logout.php') {
    $skeletonType = 'logout';
} elseif (in_array($active, ['settings.php', 'settings_notifications.php', 'settings_preferences.php', 'security.php'], true)) {
    $skeletonType = 'settings';
} elseif (in_array($active, ['change_email_otp.php', 'change_password_otp.php'], true)) {
    $skeletonType = 'otp';
} elseif (in_array($active, ['pages.php', 'edit_page.php'], true) && $site_user && $site_user['role'] === 'admin') {
    $skeletonType = 'admin-pages';
} elseif ($active === 'notify_policy.php') {
    $skeletonType = 'admin-notify';
}
$role_label = $site_user ? ucfirst($site_user['role']) : '';
$body_role = $site_user ? $site_user['role'] : 'guest';
$body_classes = [$body_role];
if ($active === 'index.php' && !$site_user) {
    $body_classes[] = 'landing';
}
if ($active === 'login.php') {
    $body_classes[] = 'login-page';
}
if (in_array($active, ['login.php', 'register.php', 'forgot_password.php', 'reset_password.php', 'verify.php', 'otp_verify.php', 'google_setup.php', 'change_email_otp.php', 'change_password_otp.php', 'delete_account_otp.php'], true)) {
    $body_classes[] = 'auth-page';
}
if ($active === 'page.php' && ($_GET['slug'] ?? '') === 'contact') {
    $body_classes[] = 'contact-page';
}
if (in_array($active, ['settings.php', 'settings_notifications.php', 'settings_preferences.php', 'security.php'], true)) {
    $body_classes[] = 'settings-page';
}
$body_class = implode(' ', $body_classes);

// Back button (mobile-first): injected top-left on form/detail pages. Desktop
// only where there's no persistent nav.
$scriptDir = basename(dirname($_SERVER['SCRIPT_NAME']));
$pageBack = false;
$pageBackUrl = base_url('index.php');
$backPagesPanel = [];
$backPagesManager = [];
$backPagesAdmin = [];
if (($scriptDir === 'pages' && in_array($active, $backPagesPanel, true))
    || ($scriptDir === 'manager' && in_array($active, $backPagesManager, true))
    || ($scriptDir === 'admin' && in_array($active, $backPagesAdmin, true))) {
    $pageBack = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>document.documentElement.classList.add('js');
    try {
        var savedTheme = localStorage.getItem('goalspace-theme');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = savedTheme === 'dark' ? 'dark' : (savedTheme === 'system' ? (prefersDark ? 'dark' : 'light') : 'light');
        if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    } catch (e) {}
    try {
        if (localStorage.getItem('goalspace-sidebar-collapsed') === '1') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    } catch (e) {}
    if ('scrollRestoration' in history) { history.scrollRestoration = 'manual'; }
    // The host's anti-bot challenge reloads the page with ?i=1; clean it from the
    // address bar so the URL always stays / and the installed app opens clean.
    try {
        var __gsUrl = new URL(location.href);
        if (__gsUrl.searchParams.has('i') && __gsUrl.searchParams.get('i') === '1') {
            __gsUrl.searchParams.delete('i');
            history.replaceState(null, '', __gsUrl.pathname + __gsUrl.search + __gsUrl.hash);
        }
    } catch (e) {}</script>
    <title><?php echo isset($page_title) ? e($page_title) . ' | ' : ''; ?>GoalSpace</title>
    <?php
    $og_title = isset($page_title) ? e($page_title) . ' | GoalSpace' : 'GoalSpace - Book futsal courts online';
    $og_desc  = isset($page_description) ? e($page_description) : e('Book futsal courts online. Find a free court near you, choose your slot, and pay securely with GoalSpace.');
    $og_image = isset($page_image) ? e($page_image) : e(absolute_url('assets/img/social-og.png'));
    $og_url   = isset($page_url)    ? e($page_url) : e(absolute_url());
    $og_type  = isset($og_type) && $og_type === 'article' ? 'article' : 'website';
    ?>
    <meta name="description" content="<?php echo $og_desc; ?>">
    <meta property="og:title" content="<?php echo $og_title; ?>">
    <meta property="og:description" content="<?php echo $og_desc; ?>">
    <meta property="og:image" content="<?php echo $og_image; ?>">
    <meta property="og:url" content="<?php echo $og_url; ?>">
    <meta property="og:type" content="<?php echo $og_type; ?>">
    <meta property="og:site_name" content="GoalSpace">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $og_title; ?>">
    <meta name="twitter:description" content="<?php echo $og_desc; ?>">
    <meta name="twitter:image" content="<?php echo $og_image; ?>">
    <link rel="canonical" href="<?php echo $og_url; ?>">
    <link rel="icon" href="<?php echo base_url('assets/img/favicon.svg'); ?>">
    <link rel="manifest" href="<?php echo base_url('manifest.json'); ?>">
    <meta name="theme-color" content="#0a120e">
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('service-worker.js')
            .then((registration) => {
              console.log('SW registered: ', registration);
            })
            .catch((registrationError) => {
              console.log('SW registration failed: ', registrationError);
            });
        });
      }
    </script>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GoalSpace">
    <link rel="apple-touch-icon" href="<?php echo base_url('assets/img/icon-192.png'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Barlow+Condensed:wght@500;600;700&display=swap" media="print" onload="this.media='all'" crossorigin="anonymous">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Barlow+Condensed:wght@500;600;700&display=swap"></noscript>
    <link rel="stylesheet" href="<?php echo base_url('assets/vendor/fontawesome/css/all.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/style.css?v=282'); ?>">
</head>
<body data-role="<?php echo e($body_role); ?>" data-base="<?php echo e(rtrim(base_url(), '/')); ?>" data-csrf="<?php echo e(csrf_token()); ?>" class="<?php echo e($body_class); ?>">
<a class="skip-link" href="#mainContent">Skip to main content</a>
<div id="topBar" class="top-bar" aria-hidden="true"><span></span></div>
<div id="pageSkeleton" class="page-skeleton" aria-hidden="true">
    <div id="skeletonMsg" class="ps-msg"><i class="fa-solid fa-circle-notch fa-spin"></i> <span id="skeletonMsgText">Loading…</span></div>
    <?php if ($skeletonType === 'auth'): ?>
        <div class="ps-header ps-header-auth">
            <div class="container header-inner">
                <span class="ps-brandmark"></span>
                <span class="ps-logo"></span>
            </div>
        </div>
    <?php else: ?>
        <div class="ps-header">
            <div class="container header-inner">
                <span class="ps-brandmark"></span>
                <span class="ps-logo"></span>
                <span class="ps-nav ps-desktop"><?php
                    $navPills = 3;
                    if ($site_user && in_array($site_user['role'], ['admin', 'manager'], true)) { $navPills = 5; }
                    for ($i = 0; $i < $navPills; $i++) { echo '<span class="ps-pill"></span>'; }
                ?></span>
                <span class="ps-auth ps-desktop">
                    <span class="ps-avatar"></span>
                    <span class="ps-btn"></span>
                </span>
                <span class="ps-hamburger ps-mobile"><i></i><i></i><i></i></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($skeletonType === 'auth'): ?>
        <div class="ps-authbody">
            <div class="ps-brandcenter ps-desktop"></div>
            <div class="ps-form">
                <span class="ps-gtitle ps-center"></span>
                <span class="ps-line ps-center"></span>
                <div class="ps-fcard-vertical">
                    <i></i><i></i><i></i><i></i>
                    <span class="ps-fbtn"></span>
                </div>
            </div>
        </div>
    <?php elseif ($skeletonType === 'payment'): ?>
        <div class="container ps-main narrow">
            <span class="ps-hero-tag"></span>
            <span class="ps-title"></span>
            <span class="ps-line ps-line-w45"></span>
        </div>
        <div class="container ps-body narrow">
            <div class="ps-fcard"><i></i><i></i><span class="ps-fbtn"></span></div>
            <div class="ps-fcard stacks">
                <span class="ps-option"></span>
                <span class="ps-option"></span>
            </div>
            <span class="ps-fbtn wide"></span>
        </div>
    <?php elseif ($skeletonType === 'booking-detail'): ?>
        <div class="container ps-main">
            <span class="ps-title"></span>
            <span class="ps-line ps-line-w60"></span>
            <span class="ps-note"></span>
        </div>
        <div class="container ps-body">
            <div class="ps-grid ps-grid-2">
                <span class="ps-card"><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gline"></span><span class="ps-gline"></span></span>
                <span class="ps-card"><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gline"></span></span>
            </div>
            <div class="ps-card">
                <span class="ps-gtitle"></span>
                <span class="ps-pricerow"></span>
                <span class="ps-fbtn"></span>
            </div>
        </div>
    <?php elseif ($skeletonType === 'ground'): ?>
        <div class="container ps-main">
            <span class="ps-line ps-line-w30"></span>
        </div>
        <div class="container ps-body">
            <div class="ps-grid ps-grid-detail">
                <div class="ps-card tall">
                    <span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gline"></span><span class="ps-gbtn"></span>
                </div>
                <div class="ps-card tall side">
                    <span class="ps-title"></span><span class="ps-line"></span>
                    <div class="ps-slotgrid"><i></i><i></i><i></i><i></i><i></i><i></i></div>
                    <span class="ps-fbtn"></span>
                </div>
            </div>
        </div>
    <?php elseif ($skeletonType === 'bookings'): ?>
        <div class="container ps-main">
            <span class="ps-title"></span>
            <span class="ps-stats"><i></i><i></i><i></i></span>
        </div>
        <div class="container ps-body">
            <span class="ps-subtitle"></span>
            <span class="ps-mbooking"></span>
            <span class="ps-mbooking"></span>
            <span class="ps-mbooking"></span>
        </div>
    <?php elseif ($skeletonType === 'profile'): ?>
        <div class="container ps-main"><span class="ps-title"></span></div>
        <div class="container ps-body">
            <div class="ps-grid ps-grid-2">
                <div class="ps-card">
                    <span class="ps-avatar big"></span>
                    <span class="ps-gtitle center"></span>
                    <span class="ps-gline center"></span>
                    <span class="ps-fbtn"></span>
                </div>
                <div class="ps-card">
                    <span class="ps-gtitle"></span>
                    <div class="ps-fields-vertical"><i></i><i></i><i></i></div>
                    <span class="ps-fbtn"></span>
                </div>
            </div>
        </div>
    <?php elseif ($skeletonType === 'notifications'): ?>
        <div class="container ps-main"><span class="ps-title"></span><span class="ps-line"></span></div>
        <div class="container ps-body">
            <span class="ps-row"></span>
            <span class="ps-row"></span>
            <span class="ps-row"></span>
            <span class="ps-row"></span>
        </div>
    <?php elseif ($skeletonType === 'dashboard'): ?>
        <div class="container ps-main">
            <span class="ps-line ps-line-w60"></span>
        </div>
        <div class="container ps-body">
            <div class="ps-grid ps-grid-4"><span class="ps-stat"></span><span class="ps-stat"></span><span class="ps-stat"></span><span class="ps-stat"></span></div>
            <span class="ps-subtitle"></span>
            <span class="ps-table"></span>
            <span class="ps-subtitle"></span>
            <span class="ps-mbooking"></span>
            <span class="ps-mbooking"></span>
        </div>
    <?php elseif ($skeletonType === 'manage-grounds' || $skeletonType === 'grounds-form'): ?>
        <div class="container ps-main"><span class="ps-title"></span></div>
        <div class="container ps-body">
            <div class="ps-grid ps-grid-2">
                <span class="ps-card tall"><span class="ps-gtitle"></span><div class="ps-fields-vertical"><i></i><i></i><i></i><i></i></div><span class="ps-fbtn"></span></span>
                <span class="ps-card"><span class="ps-gtitle"></span><div class="ps-fields-vertical"><i></i><i></i></div></span>
            </div>
            <span class="ps-subtitle"></span>
            <span class="ps-mbooking"></span>
            <span class="ps-mbooking"></span>
        </div>
    <?php elseif ($skeletonType === 'page'): ?>
        <div class="container ps-main">
            <span class="ps-title"></span>
            <span class="ps-line ps-line-w60"></span>
        </div>
        <div class="container ps-body">
            <div class="ps-prose">
                <span class="ps-gtitle"></span>
                <span class="ps-gline"></span>
                <span class="ps-gline"></span>
                <span class="ps-gline"></span>
                <span class="ps-gline"></span>
                <span class="ps-gline"></span>
            </div>
        </div>
    <?php elseif ($skeletonType === 'contact-form'): ?>
        <div class="container ps-main">
            <span class="ps-title"></span>
            <span class="ps-line"></span>
        </div>
        <div class="container ps-body">
            <div class="ps-fcard">
                <span class="ps-field"><i></i></span>
                <span class="ps-field"><i></i></span>
                <span class="ps-field"><i></i></span>
                <span class="ps-field"><i></i></span>
                <span class="ps-field tall"><i></i></span>
                <span class="ps-fbtn"></span>
            </div>
        </div>
    <?php elseif ($skeletonType === 'confirmation'): ?>
        <div class="container ps-main">
            <span class="ps-title"></span>
            <span class="ps-line ps-line-w45"></span>
        </div>
        <div class="container ps-body">
            <div class="ps-fcard">
                <span class="ps-gtitle"></span>
                <span class="ps-gline"></span>
                <span class="ps-gline"></span>
                <span class="ps-fbtn"></span>
            </div>
        </div>
    <?php elseif ($skeletonType === 'receipt'): ?>
        <div class="container ps-main narrow">
            <span class="ps-hero-tag"></span>
            <span class="ps-title"></span>
            <span class="ps-line ps-line-w45"></span>
        </div>
        <div class="container ps-body narrow">
            <div class="ps-fcard">
                <span class="ps-gtitle"></span>
                <span class="ps-gline"></span>
                <span class="ps-gline"></span>
                <span class="ps-gline"></span>
            </div>
            <span class="ps-fbtn wide"></span>
        </div>
    <?php elseif ($skeletonType === 'reschedule'): ?>
        <div class="container ps-main">
            <span class="ps-title"></span>
            <span class="ps-line ps-line-w45"></span>
        </div>
        <div class="container ps-body">
            <div class="ps-fcard">
                <span class="ps-field"><i></i></span>
                <span class="ps-field"><i></i></span>
                <span class="ps-field"><i></i></span>
                <span class="ps-fbtn"></span>
            </div>
        </div>
    <?php elseif ($skeletonType === 'logout'): ?>
        <div class="container ps-authbody">
            <div class="ps-form">
                <span class="ps-gtitle ps-center"></span>
                <span class="ps-line ps-center"></span>
                <span class="ps-fbtn"></span>
            </div>
        </div>
    <?php elseif ($skeletonType === 'otp'): ?>
        <div class="container ps-main narrow"><span class="ps-title"></span><span class="ps-line ps-line-w45"></span></div>
        <div class="container ps-body narrow">
            <div class="ps-fcard">
                <span class="ps-field"><i></i></span>
                <span class="ps-fbtn"></span>
            </div>
        </div>
    <?php elseif ($skeletonType === 'settings'): ?>
        <div class="container ps-main"><span class="ps-title"></span></div>
        <div class="container ps-body">
            <div class="ps-settings-layout">
                <div class="ps-sidebar">
                    <span class="ps-nav-item"></span>
                    <span class="ps-nav-item"></span>
                    <span class="ps-nav-item"></span>
                    <span class="ps-nav-item"></span>
                </div>
                <div class="ps-settings-content">
                    <span class="ps-gtitle"></span>
                    <span class="ps-gline"></span>
                    <span class="ps-gline"></span>
                    <span class="ps-fbtn"></span>
                </div>
            </div>
        </div>
    <?php elseif ($skeletonType === 'admin-pages'): ?>
        <div class="container ps-main">
            <span class="ps-title"></span>
            <span class="ps-line"></span>
        </div>
        <div class="container ps-body">
            <div class="ps-table-skeleton">
                <span class="ps-row"></span>
                <span class="ps-row"></span>
                <span class="ps-row"></span>
                <span class="ps-row"></span>
                <span class="ps-row"></span>
            </div>
            <div class="ps-fcard">
                <span class="ps-field"><i></i></span>
                <span class="ps-field tall"><i></i></span>
                <span class="ps-fbtn"></span>
            </div>
        </div>
    <?php elseif ($skeletonType === 'admin-notify'): ?>
        <div class="container ps-main">
            <span class="ps-title"></span>
            <span class="ps-line"></span>
        </div>
        <div class="container ps-body">
            <div class="ps-fcard">
                <span class="ps-gtitle"></span>
                <div class="ps-options-skeleton">
                    <span class="ps-option-row"></span>
                    <span class="ps-option-row"></span>
                    <span class="ps-option-row"></span>
                    <span class="ps-option-row"></span>
                    <span class="ps-option-row"></span>
                </div>
                <div class="ps-options-skeleton">
                    <span class="ps-option-row"></span>
                    <span class="ps-option-row"></span>
                    <span class="ps-option-row"></span>
                    <span class="ps-option-row"></span>
                </div>
                <span class="ps-option-row"></span>
                <span class="ps-fbtn"></span>
            </div>
        </div>
    <?php elseif ($skeletonType === 'map'): ?>
        <div class="container ps-main"><span class="ps-line ps-line-w60"></span></div>
        <div class="container ps-body">
            <div class="ps-map"></div>
        </div>
    <?php elseif ($skeletonType === 'home'): ?>
        <?php if ($site_user && $site_user['role'] === 'user'): ?>
            <div class="container ps-main">
                <span class="ps-title"></span>
                <span class="ps-stats"><i></i><i></i><i></i><i></i></span>
            </div>
            <div class="container ps-body">
                <span class="ps-subtitle"></span>
                <span class="ps-mbooking"></span>
                <span class="ps-subtitle"></span>
                <div class="ps-grid ps-grid-3">
                    <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span></div>
                    <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span></div>
                    <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span></div>
                </div>
            </div>
        <?php else: ?>
            <div class="container ps-section pad">
                <div class="ps-hero">
                    <span class="ps-hero-tag"></span>
                    <span class="ps-title-lg"></span>
                    <span class="ps-title-lg short"></span>
                    <span class="ps-line-lg"></span>
                    <span class="ps-line-lg half"></span>
                </div>
                <div class="ps-fcard">
                    <span class="ps-field"><i></i></span>
                    <span class="ps-field"><i></i></span>
                    <span class="ps-fbtn"></span>
                </div>
            </div>
            <div class="ps-section">
                <div class="container ps-head">
                    <span class="ps-eyebrow"></span>
                    <span class="ps-title-sec"></span>
                </div>
                <div class="container ps-grid">
                    <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
                    <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
                    <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
                </div>
            </div>
            <div class="ps-section soft">
                <div class="container ps-head center">
                    <span class="ps-eyebrow"></span>
                    <span class="ps-title-sec"></span>
                </div>
                <div class="container ps-steps">
                    <span class="ps-step"></span>
                    <span class="ps-step"></span>
                    <span class="ps-step"></span>
                </div>
            </div>
        <?php endif; ?>
    <?php elseif ($skeletonType === 'list'): ?>
        <div class="container ps-main"><span class="ps-title"></span></div>
        <div class="container ps-body">
            <div class="ps-toolbar"><i></i><i></i><i></i><i></i><span class="ps-fbtn"></span></div>
            <div class="ps-grid ps-grid-3">
                <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
                <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
                <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
                <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
                <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
                <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
            </div>
        </div>
    <?php elseif ($skeletonType === 'favorites'): ?>
        <div class="container ps-main"><span class="ps-title"></span></div>
        <div class="container ps-body">
            <div class="ps-grid ps-grid-2">
                <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
                <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
                <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
                <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
            </div>
        </div>
    <?php elseif ($skeletonType === 'book'): ?>
        <div class="container ps-main"><span class="ps-title"></span><span class="ps-line ps-line-w45"></span></div>
        <div class="container ps-body">
            <div class="ps-fcard tall"><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-field"><i></i></span><span class="ps-fbtn"></span></div>
        </div>
    <?php else: ?>
        <div class="container ps-section pad">
            <div class="ps-hero">
                <span class="ps-hero-tag"></span>
                <span class="ps-title-lg"></span>
                <span class="ps-title-lg short"></span>
                <span class="ps-line-lg"></span>
                <span class="ps-line-lg half"></span>
            </div>
            <div class="ps-fcard">
                <span class="ps-field"><i></i></span>
                <span class="ps-field"><i></i></span>
                <span class="ps-fbtn"></span>
            </div>
        </div>
        <div class="ps-section">
            <div class="container ps-head">
                <span class="ps-eyebrow"></span>
                <span class="ps-title-sec"></span>
                <span class="ps-subline"></span>
            </div>
            <div class="container ps-grid">
                <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
                <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
                <div class="ps-gcard"><span class="ps-img"></span><span class="ps-gtitle"></span><span class="ps-gline"></span><span class="ps-gmeta"></span><span class="ps-gbtn"></span></div>
            </div>
        </div>
        <div class="ps-section soft">
            <div class="container ps-head center">
                <span class="ps-eyebrow"></span>
                <span class="ps-title-sec"></span>
            </div>
            <div class="container ps-steps">
                <span class="ps-step"></span>
                <span class="ps-step"></span>
                <span class="ps-step"></span>
            </div>
        </div>
        <div class="ps-footer">
            <div class="container ps-foot-grid">
                <span class="ps-fcol"></span>
                <span class="ps-fcol"></span>
                <span class="ps-fcol"></span>
                <span class="ps-fcol"></span>
            </div>
        </div>
    <?php endif; ?>
</div>
<div id="appLoader" class="app-loader" aria-hidden="true">
    <div class="al-card">
        <span class="al-ball" role="presentation"></span>
        <span class="al-ground" aria-hidden="true"></span>
        <span class="al-text"><span id="alText">Just a moment</span><span class="al-dots" id="alDots" aria-hidden="true"></span></span>
    </div>
</div>
<header class="site-header">
    <div class="container header-inner">
        <a href="<?php echo base_url('index.php'); ?>" class="brand" aria-label="GoalSpace home">
            <span class="brand-mark"><i class="fa-solid fa-futbol"></i></span>
            <span class="brand-name">GoalSpace</span>
        </a>

        <div class="header-search-wrap">
            <form method="get" action="<?php echo base_url('pages/courts.php'); ?>" class="header-search" role="search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="headerSearchInput" name="q" placeholder="Search courts by name or city..."
                       aria-label="Search courts" autocomplete="off" role="combobox"
                       aria-expanded="false" aria-controls="headerSearchPanel" aria-autocomplete="list">
                <button type="button" class="header-kbd" id="paletteTrigger" aria-label="Open quick navigation (Ctrl K)" title="Quick navigation (Ctrl K)"><kbd>Ctrl</kbd><kbd>K</kbd></button>
            </form>
            <div class="hs-panel" id="headerSearchPanel" hidden></div>
        </div>

        <div class="nav-auth">
            <button type="button" class="header-search-toggle" id="headerSearchToggle" aria-label="Search" aria-haspopup="true" aria-expanded="false"><i class="fa-solid fa-magnifying-glass"></i></button>
            <?php if ($site_user): ?>
                <?php $bellNotifications = user_notifications((int)$site_user['id'], 4); ?>
                <div class="bell-wrap" id="bellWrap">
                    <button type="button" class="bell-btn" id="bellBtn" aria-label="Notifications">
                        <i class="fa-regular fa-bell"></i>
                        <?php $unreadCount = unread_notification_count((int)$site_user['id']); ?>
                        <?php if ($unreadCount > 0): ?>
                            <span class="bell-dot" id="bellDot"><?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="bell-panel" id="bellPanel">
                        <div class="bell-head">
                            <span class="bell-title-wrap">Notifications<?php if ($unreadCount > 0): ?><span class="bell-head-count"><?php echo $unreadCount; ?> new</span><?php endif; ?></span>
                            <?php if ($bellNotifications): ?>
                                <form method="post" action="<?php echo base_url('pages/notifications.php'); ?>" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" name="mark_read" value="1" class="bell-markall" id="bellMarkAll" style="font-size:11px;padding:4px 10px;">Mark all read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="bell-list">
                            <?php if (!$bellNotifications): ?>
                                <p class="bell-empty">You're all caught up.</p>
                            <?php else: ?>
                                <?php foreach ($bellNotifications as $n): ?>
                                    <a href="<?php echo base_url('pages/notification_details.php?id=' . $n['id']); ?>" class="bell-item <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                                        <span class="bell-icon c-<?php echo notification_icon_color($n['icon']); ?>"><i class="fa-solid <?php echo e($n['icon']); ?>"></i></span>
                                        <span class="bell-text">
                                            <span class="bell-title"><?php echo e($n['title']); ?></span>
                                            <?php if ($n['body'] !== ''): ?><span class="bell-body"><?php echo e($n['body']); ?></span><?php endif; ?>
                                            <span class="bell-time"><i class="fa-regular fa-clock"></i> <?php echo e(notification_time($n['created_at'])); ?></span>
                                        </span>
                                        <?php if (!$n['is_read']): ?><span class="bell-unread-dot" title="Unread"></span><?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="bell-foot">
                            <a href="<?php echo base_url('pages/notifications.php'); ?>" class="bell-viewall"><i class="fa-solid fa-list"></i> View all notifications</a>
                        </div>
                    </div>
                </div>
                <div class="profile-wrap" id="profileWrap">
                    <button type="button" class="user-chip nav-profile" id="profileBtn" aria-haspopup="true" aria-expanded="false" title="My account">
                        <span class="avatar"><?php if (!empty($site_user['avatar'])): ?><img src="<?php echo base_url('uploads/avatars/' . rawurlencode($site_user['avatar'])); ?>" alt="<?php echo e($site_user['name']); ?>" decoding="async"><?php else: ?><?php echo e(strtoupper(substr($site_user['name'], 0, 1))); ?><?php endif; ?></span>
                    </button>
                    <div class="profile-menu" role="menu">
                        <div class="profile-menu-head">
                            <span class="avatar"><?php if (!empty($site_user['avatar'])): ?><img src="<?php echo base_url('uploads/avatars/' . rawurlencode($site_user['avatar'])); ?>" alt="<?php echo e($site_user['name']); ?>" decoding="async"><?php else: ?><?php echo e(strtoupper(substr($site_user['name'], 0, 1))); ?><?php endif; ?></span>
                            <span class="chip-name">
                                <span><?php echo e($site_user['name']); ?></span>
                                <em><?php echo e($site_user['email']); ?></em>
                            </span>
                        </div>
                        <a href="<?php echo base_url('pages/profile.php'); ?>" role="menuitem"><i class="fa-solid fa-user"></i> My Profile</a>
                        <a href="<?php echo base_url('pages/settings.php'); ?>" role="menuitem"><i class="fa-solid fa-gear"></i> Settings</a>
                        <button type="button" class="pm-theme" id="themeToggle" role="menuitem" aria-label="Toggle dark mode" aria-pressed="false">
                            <i class="fa-solid fa-moon" id="themeIcon"></i>
                            <span>Dark mode</span>
                            <span class="pm-switch" aria-hidden="true"><span class="pm-knob"></span></span>
                        </button>
                        <a href="<?php echo base_url('pages/logout.php?csrf=' . csrf_token()); ?>" role="menuitem" class="pm-danger" data-confirm="Log out of your account?" data-confirm-ok="Yes, log out" data-confirm-cancel="Cancel"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo base_url('pages/login.php'); ?>" class="btn btn-outline-dark btn-sm">Log in</a>
                <a href="<?php echo base_url('pages/register.php'); ?>" class="btn btn-primary btn-sm">Sign up</a>
            <?php endif; ?>
        </div>

        <button class="nav-toggle" id="navToggle" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
    </div>
</header>

<!-- dim backdrop for the mobile search sheet (rest of the page darkens) -->
<div class="hs-scrim" id="hsScrim"></div>

<!-- mobile search sheet (overlaps only the navbar; the page below is dimmed) -->
<div class="hs-overlay" id="hsOverlay" hidden>
    <div class="hs-overlay-top">
        <form method="get" action="<?php echo base_url('pages/courts.php'); ?>" class="header-search" role="search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="hsOverlayInput" name="q" placeholder="Search courts by name or city..."
                   aria-label="Search courts" autocomplete="off" role="combobox"
                   aria-expanded="false" aria-controls="hsOverlayPanel" aria-autocomplete="list">
        </form>
        <button type="button" class="hs-overlay-close" id="hsOverlayClose" aria-label="Close search"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="hs-panel" id="hsOverlayPanel" hidden></div>
</div>

<!-- persistent desktop sidebar (body-level so it's never clipped by header transforms) -->
<nav class="nav" id="mainNav" aria-label="Main navigation">
    <div class="nav-sidebar-head">
        <a href="<?php echo base_url('index.php'); ?>" class="brand" aria-label="GoalSpace home">
            <span class="brand-mark"><i class="fa-solid fa-futbol"></i></span>
            <span class="brand-name">GoalSpace</span>
        </a>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" aria-expanded="true">
            <i class="fa-solid fa-angles-left"></i>
        </button>
    </div>
    <?php if ($site_user && $site_user['role'] === 'user'): ?>
        <a href="<?php echo base_url('index.php'); ?>" class="<?php echo $active === 'index.php' ? 'active' : ''; ?>"> <i class="fa-solid fa-house"></i> <span class="nav-label">Home</span></a>
        <a href="<?php echo grounds_list_url(); ?>" class="<?php echo $activeSection === 'grounds' ? 'active' : ''; ?>"><i class="fa-solid fa-layer-group"></i> <span class="nav-label">Grounds</span></a>
        <a href="<?php echo base_url('pages/map.php'); ?>" class="<?php echo $active === 'map.php' ? 'active' : ''; ?>"><i class="fa-solid fa-map-location-dot"></i> <span class="nav-label">Map</span></a>
        <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="<?php echo $activeSection === 'my_bookings' || $active === 'my_bookings.php' ? 'active' : ''; ?>"><i class="fa-solid fa-calendar-check"></i> <span class="nav-label">My Bookings</span></a>
        <a href="<?php echo base_url('pages/favorites.php'); ?>" class="<?php echo $active === 'favorites.php' ? 'active' : ''; ?>"><i class="fa-solid fa-heart"></i> <span class="nav-label">Saved Courts</span></a>
    <?php elseif ($site_user && $site_user['role'] === 'manager'): ?>
        <a href="<?php echo base_url('manager/dashboard.php'); ?>" class="<?php echo $active === 'dashboard.php' ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i> <span class="nav-label">Home</span></a>
        <a href="<?php echo base_url('manager/grounds.php'); ?>" class="<?php echo $active === 'grounds.php' ? 'active' : ''; ?>"><i class="fa-solid fa-store"></i> <span class="nav-label">My Grounds</span></a>
        <a href="<?php echo base_url('manager/bookings.php'); ?>" class="<?php echo $active === 'bookings.php' ? 'active' : ''; ?>"><i class="fa-solid fa-list-check"></i> <span class="nav-label">Bookings</span></a>
        <a href="<?php echo base_url('manager/promos.php'); ?>" class="<?php echo $active === 'promos.php' ? 'active' : ''; ?>"><i class="fa-solid fa-tags"></i> <span class="nav-label">Promos</span></a>
    <?php elseif ($site_user && $site_user['role'] === 'admin'): ?>
        <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="<?php echo $active === 'dashboard.php' ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i> <span class="nav-label">Home</span></a>
        <a href="<?php echo base_url('admin/users.php'); ?>" class="<?php echo $active === 'users.php' ? 'active' : ''; ?>"><i class="fa-solid fa-users"></i> <span class="nav-label">Users</span></a>
        <a href="<?php echo base_url('admin/grounds.php'); ?>" class="<?php echo $active === 'grounds.php' ? 'active' : ''; ?>"><i class="fa-solid fa-store"></i> <span class="nav-label">Grounds</span></a>
        <a href="<?php echo base_url('admin/bookings.php'); ?>" class="<?php echo $active === 'bookings.php' ? 'active' : ''; ?>"><i class="fa-solid fa-list-check"></i> <span class="nav-label">Bookings</span></a>
        <a href="<?php echo base_url('admin/announce.php'); ?>" class="<?php echo $active === 'announce.php' ? 'active' : ''; ?>"><i class="fa-solid fa-bullhorn"></i> <span class="nav-label">Announce</span></a>
        <a href="<?php echo base_url('admin/settlements.php'); ?>" class="<?php echo $active === 'settlements.php' ? 'active' : ''; ?>"><i class="fa-solid fa-hand-holding-dollar"></i> <span class="nav-label">Billing</span></a>
        <a href="<?php echo base_url('admin/contact_messages.php'); ?>" class="<?php echo $active === 'contact_messages.php' ? 'active' : ''; ?>"><i class="fa-solid fa-inbox"></i> <span class="nav-label">Messages</span></a>
        <a href="<?php echo base_url('admin/pages.php'); ?>" class="<?php echo $active === 'pages.php' ? 'active' : ''; ?>"><i class="fa-solid fa-file-pen"></i> <span class="nav-label">Legal pages</span></a>
        <a href="<?php echo base_url('admin/notify_policy.php'); ?>" class="<?php echo $active === 'notify_policy.php' ? 'active' : ''; ?>"><i class="fa-solid fa-paper-plane"></i> <span class="nav-label">Announce update</span></a>
    <?php else: ?>
        <a href="<?php echo base_url('index.php'); ?>" class="<?php echo $active === 'index.php' ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i> <span class="nav-label">Home</span></a>
        <a href="<?php echo base_url('index.php#grounds'); ?>" class="<?php echo $activeSection === 'grounds' ? 'active' : ''; ?>"><i class="fa-solid fa-layer-group"></i> <span class="nav-label">Grounds</span></a>
        <a href="<?php echo base_url('index.php#how'); ?>"><i class="fa-solid fa-circle-info"></i> <span class="nav-label">How it works</span></a>
        <a href="<?php echo base_url('index.php#become-manager'); ?>" class="show-sm"><i class="fa-solid fa-chart-line"></i> <span class="nav-label">Become a Manager</span></a>
    <?php endif; ?>
    <a href="<?php echo base_url('pages/faq.php'); ?>" class="nav-faq <?php echo $active === 'faq.php' ? 'active' : ''; ?>"><i class="fa-solid fa-circle-question"></i> <span class="nav-label">FAQ</span></a>
    <div class="nav-sidebar-foot">
        <div class="nsf-wrap">
            <p class="nsf-meta">GoalSpace &middot; v1.0</p>
            <div class="nsf-social">
                <a href="#" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                <a href="#" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook"></i></a>
                <a href="#" target="_blank" rel="noopener" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
            </div>
        </div>
    </div>
</nav>

<nav class="bottom-nav" id="bottomNav" aria-label="Primary navigation">
    <?php
    $bnMorePages = ['announce.php', 'settlements.php', 'contact_messages.php', 'pages.php', 'notify_policy.php'];
    $bnMoreActive = in_array($active, $bnMorePages, true);
    ?>
    <?php if ($site_user && $site_user['role'] === 'user'): ?>
        <a href="<?php echo base_url('index.php'); ?>" class="<?php echo $active === 'index.php' ? 'active' : ''; ?>" title="Home" aria-label="Home"><i class="fa-solid fa-house"></i><span class="bn-label" hidden>Home</span></a>
        <a href="<?php echo grounds_list_url(); ?>" class="<?php echo $activeSection === 'grounds' ? 'active' : ''; ?>" title="Grounds" aria-label="Grounds"><i class="fa-solid fa-layer-group"></i><span class="bn-label" hidden>Grounds</span></a>
        <a href="<?php echo base_url('pages/map.php'); ?>" class="<?php echo $active === 'map.php' ? 'active' : ''; ?>" title="Map" aria-label="Map"><i class="fa-solid fa-map-location-dot"></i><span class="bn-label" hidden>Map</span></a>
        <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="<?php echo $active === 'my_bookings.php' ? 'active' : ''; ?>" title="My Bookings" aria-label="My Bookings"><i class="fa-solid fa-calendar-check"></i><span class="bn-label" hidden>Bookings</span></a>
        <a href="<?php echo base_url('pages/profile.php'); ?>" class="<?php echo $active === 'profile.php' ? 'active' : ''; ?>" title="Profile" aria-label="Profile"><i class="fa-solid fa-user"></i><span class="bn-label" hidden>Profile</span></a>
    <?php elseif ($site_user && $site_user['role'] === 'manager'): ?>
        <a href="<?php echo base_url('manager/dashboard.php'); ?>" class="<?php echo $active === 'dashboard.php' ? 'active' : ''; ?>" title="Home" aria-label="Home"><i class="fa-solid fa-house"></i><span class="bn-label" hidden>Home</span></a>
        <a href="<?php echo base_url('manager/grounds.php'); ?>" class="<?php echo $active === 'grounds.php' ? 'active' : ''; ?>" title="My Grounds" aria-label="My Grounds"><i class="fa-solid fa-store"></i><span class="bn-label" hidden>Grounds</span></a>
        <a href="<?php echo base_url('manager/bookings.php'); ?>" class="<?php echo $active === 'bookings.php' ? 'active' : ''; ?>" title="Bookings" aria-label="Bookings"><i class="fa-solid fa-list-check"></i><span class="bn-label" hidden>Bookings</span></a>
        <a href="<?php echo base_url('manager/promos.php'); ?>" class="<?php echo $active === 'promos.php' ? 'active' : ''; ?>" title="Promos" aria-label="Promos"><i class="fa-solid fa-tags"></i><span class="bn-label" hidden>Promos</span></a>
    <?php elseif ($site_user && $site_user['role'] === 'admin'): ?>
        <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="<?php echo $active === 'dashboard.php' ? 'active' : ''; ?>" title="Home" aria-label="Home"><i class="fa-solid fa-house"></i><span class="bn-label" hidden>Home</span></a>
        <a href="<?php echo base_url('admin/users.php'); ?>" class="<?php echo $active === 'users.php' ? 'active' : ''; ?>" title="Users" aria-label="Users"><i class="fa-solid fa-users"></i><span class="bn-label" hidden>Users</span></a>
        <a href="<?php echo base_url('admin/grounds.php'); ?>" class="<?php echo $active === 'grounds.php' ? 'active' : ''; ?>" title="Grounds" aria-label="Grounds"><i class="fa-solid fa-store"></i><span class="bn-label" hidden>Grounds</span></a>
        <a href="<?php echo base_url('admin/bookings.php'); ?>" class="<?php echo $active === 'bookings.php' ? 'active' : ''; ?>" title="Bookings" aria-label="Bookings"><i class="fa-solid fa-list-check"></i><span class="bn-label" hidden>Bookings</span></a>
        <button type="button" class="bn-more <?php echo $bnMoreActive ? 'active' : ''; ?>" id="bnMoreBtn" aria-expanded="false" aria-haspopup="true" title="More" aria-label="More"><i class="fa-solid fa-ellipsis"></i><span class="bn-label" hidden>More</span></button>
        <div class="bn-more-panel" id="bnMorePanel" hidden>
            <a href="<?php echo base_url('admin/announce.php'); ?>" class="<?php echo $active === 'announce.php' ? 'active' : ''; ?>"><i class="fa-solid fa-bullhorn"></i> Announce</a>
            <a href="<?php echo base_url('admin/settlements.php'); ?>" class="<?php echo $active === 'settlements.php' ? 'active' : ''; ?>"><i class="fa-solid fa-hand-holding-dollar"></i> Billing</a>
            <a href="<?php echo base_url('admin/contact_messages.php'); ?>" class="<?php echo $active === 'contact_messages.php' ? 'active' : ''; ?>"><i class="fa-solid fa-inbox"></i> Messages</a>
            <a href="<?php echo base_url('admin/pages.php'); ?>" class="<?php echo $active === 'pages.php' ? 'active' : ''; ?>"><i class="fa-solid fa-file-pen"></i> Legal pages</a>
            <a href="<?php echo base_url('admin/notify_policy.php'); ?>" class="<?php echo $active === 'notify_policy.php' ? 'active' : ''; ?>"><i class="fa-solid fa-paper-plane"></i> Announce update</a>
        </div>
    <?php else: ?>
        <a href="<?php echo base_url('index.php'); ?>" class="<?php echo $active === 'index.php' ? 'active' : ''; ?>" title="Home" aria-label="Home"><i class="fa-solid fa-house"></i><span class="bn-label" hidden>Home</span></a>
        <a href="<?php echo base_url('index.php#grounds'); ?>" class="<?php echo $activeSection === 'grounds' ? 'active' : ''; ?>" title="Grounds" aria-label="Grounds"><i class="fa-solid fa-layer-group"></i><span class="bn-label" hidden>Grounds</span></a>
        <a href="<?php echo base_url('pages/map.php'); ?>" class="<?php echo $active === 'map.php' ? 'active' : ''; ?>" title="Map" aria-label="Map"><i class="fa-solid fa-map-location-dot"></i><span class="bn-label" hidden>Map</span></a>
        <a href="<?php echo base_url('index.php#how'); ?>" title="How it works" aria-label="How it works"><i class="fa-solid fa-circle-info"></i><span class="bn-label" hidden>How it works</span></a>
        <a href="<?php echo base_url('index.php#become-manager'); ?>" title="Become a Manager" aria-label="Become a Manager"><i class="fa-solid fa-store"></i><span class="bn-label" hidden>Join as manager</span></a>
    <?php endif; ?>
</nav>

<?php if ($flash): ?>
    <?php
        $type = $flash['type'];
        $isSuccess = $type === 'success';
        $isError = $type === 'error' || $type === 'warning';
        $cardType = $isSuccess ? 'msg-success' : 'msg-error';
        $icon = $isSuccess ? 'fa-check' : 'fa-xmark';
        $title = $isSuccess ? 'Success' : 'Error';
        $btnClass = $isSuccess ? 'msg-btn-success' : 'msg-btn-error';
        $btnLabel = $isSuccess ? 'Continue' : 'Try again';
        $toastRole = $isSuccess ? 'status' : 'alert';
        $detail = $flash['detail'] ?? null;
        $backUrl = is_array($detail) && !empty($detail['how_url']) ? e($detail['how_url']) : '';
    ?>
    <?php if ($type === 'success' || $type === 'info'): ?>
        <div class="container">
            <div class="toast toast-<?php echo $type === 'success' ? 'success' : 'info'; ?> toast-inline" role="status">
                <div class="toast-icon"><i class="fa-solid <?php echo $type === 'success' ? 'fa-circle-check' : 'fa-circle-info'; ?>"></i></div>
                <div class="toast-content">
                    <div class="toast-msg"><span><?php echo e($flash['message']); ?></span></div>
                </div>
                <button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
    <?php else: ?>
        <div class="container">
            <div class="toast toast-error toast-inline" role="alert">
                <div class="toast-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                <div class="toast-content">
                    <div class="toast-msg">
                        <span><?php echo e($flash['message']); ?></span>
                        <?php if ($backUrl !== ''): ?>
                        <a href="<?php echo e($backUrl); ?>" class="toast-fix"><i class="fa-solid fa-arrow-left-long"></i> Go back</a>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<main class="container page" id="mainContent" tabindex="-1">

<?php if ($pageBack): ?>
<a href="<?php echo e($pageBackUrl); ?>" class="page-back-arrow page-back-main" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
<?php endif; ?>







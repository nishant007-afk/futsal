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
$role_label = $site_user ? ucfirst($site_user['role']) : '';
$body_role = $site_user ? $site_user['role'] : 'guest';
$body_classes = [$body_role];
if (in_array($active, ['login.php', 'register.php', 'forgot_password.php', 'reset_password.php', 'verify.php', 'otp_verify.php'], true)) {
    $body_classes[] = 'auth-page';
}
if ($active === 'page.php' && ($_GET['slug'] ?? '') === 'contact') {
    $body_classes[] = 'contact-page';
}
$body_class = implode(' ', $body_classes);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>document.documentElement.classList.add('js');
    if ('scrollRestoration' in history) { history.scrollRestoration = 'manual'; }</script>
    <title><?php echo isset($page_title) ? e($page_title) . ' | ' : ''; ?>GoalSpace</title>
    <link rel="icon" href="<?php echo base_url('assets/img/favicon.svg'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/style.css?v=97'); ?>">
</head>
<body data-role="<?php echo e($body_role); ?>" class="<?php echo e($body_class); ?>">
<a class="skip-link" href="#mainContent">Skip to main content</a>
<div id="pageSkeleton" class="page-skeleton" aria-hidden="true">
    <div class="ps-top">
        <span class="ps-logo"></span>
        <span class="ps-pill"></span>
        <span class="ps-pill"></span>
        <span class="ps-pill"></span>
        <span class="ps-avatar"></span>
        <span class="ps-btn"></span>
    </div>
    <div class="container ps-main">
        <span class="ps-title"></span>
        <span class="ps-line"></span>
        <span class="ps-card"></span>
        <span class="ps-card ps-card-wide"></span>
    </div>
</div>
<div id="appLoader" class="app-loader" aria-hidden="true">
    <div class="al-card">
        <span class="al-ball" role="presentation"></span>
        <span class="al-ground" aria-hidden="true"></span>
        <span class="al-text">Just a moment<span class="al-dots" id="alDots" aria-hidden="true"></span></span>
    </div>
</div>
<header class="site-header">
    <div class="container header-inner">
        <a href="<?php echo base_url('index.php'); ?>" class="brand" aria-label="GoalSpace home">
            <span class="brand-mark"><i class="fa-solid fa-futbol"></i></span>
        </a>

        <nav class="nav" id="mainNav">
            <div class="nav-sidebar-head">
                <a href="<?php echo base_url('index.php'); ?>" class="brand" aria-label="GoalSpace home">
                    <span class="brand-mark"><i class="fa-solid fa-futbol"></i></span>
                    GoalSpace
                </a>
            </div>
            <?php if ($site_user && $site_user['role'] === 'user'): ?>
                <a href="<?php echo base_url('index.php'); ?>" class="<?php echo $active === 'index.php' ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i> Home</a>
                <a href="<?php echo base_url('index.php#grounds'); ?>" class="<?php echo $activeSection === 'grounds' ? 'active' : ''; ?>"><i class="fa-solid fa-map-location-dot"></i> Grounds</a>
                <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="<?php echo $activeSection === 'my_bookings' || $active === 'my_bookings.php' ? 'active' : ''; ?>"><i class="fa-solid fa-calendar-check"></i> My Bookings</a>
            <?php elseif ($site_user && $site_user['role'] === 'manager'): ?>
                <a href="<?php echo base_url('manager/dashboard.php'); ?>" class="<?php echo $active === 'dashboard.php' ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i> Home</a>
                <a href="<?php echo base_url('manager/grounds.php'); ?>" class="<?php echo $active === 'grounds.php' ? 'active' : ''; ?>"><i class="fa-solid fa-store"></i> My Grounds</a>
                <a href="<?php echo base_url('manager/bookings.php'); ?>" class="<?php echo $active === 'bookings.php' ? 'active' : ''; ?>"><i class="fa-solid fa-list-check"></i> Bookings</a>
                <a href="<?php echo base_url('manager/promos.php'); ?>" class="<?php echo $active === 'promos.php' ? 'active' : ''; ?>"><i class="fa-solid fa-tags"></i> Promos</a>
            <?php elseif ($site_user && $site_user['role'] === 'admin'): ?>
                <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="<?php echo $active === 'dashboard.php' ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i> Home</a>
                <a href="<?php echo base_url('admin/users.php'); ?>" class="<?php echo $active === 'users.php' ? 'active' : ''; ?>"><i class="fa-solid fa-users"></i> Users</a>
                <a href="<?php echo base_url('admin/grounds.php'); ?>" class="<?php echo $active === 'grounds.php' ? 'active' : ''; ?>"><i class="fa-solid fa-store"></i> Grounds</a>
                <a href="<?php echo base_url('admin/bookings.php'); ?>" class="<?php echo $active === 'bookings.php' ? 'active' : ''; ?>"><i class="fa-solid fa-list-check"></i> Bookings</a>
                <a href="<?php echo base_url('admin/settlements.php'); ?>" class="<?php echo $active === 'settlements.php' ? 'active' : ''; ?>"><i class="fa-solid fa-hand-holding-dollar"></i> Billing</a>
                <a href="<?php echo base_url('admin/contact_messages.php'); ?>" class="<?php echo $active === 'contact_messages.php' ? 'active' : ''; ?>"><i class="fa-solid fa-inbox"></i> Messages</a>
            <?php else: ?>
                <a href="<?php echo base_url('index.php'); ?>" class="<?php echo $active === 'index.php' ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i> Home</a>
                <a href="<?php echo base_url('index.php#grounds'); ?>" class="<?php echo $activeSection === 'grounds' ? 'active' : ''; ?>"><i class="fa-solid fa-map-location-dot"></i> Grounds</a>
                <a href="<?php echo base_url('index.php#how'); ?>"><i class="fa-solid fa-circle-info"></i> How it works</a>
                <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="show-sm"><i class="fa-solid fa-chart-line"></i> Become a Manager</a>
            <?php endif; ?>
            <div class="nav-sidebar-foot">
                <p class="nsf-meta">GoalSpace &middot; v1.0 &middot; made for players &amp; managers</p>
            </div>
        </nav>

        <div class="nav-auth">
            <?php if ($site_user): ?>
                <?php $bellNotifications = user_notifications((int)$site_user['id'], 8); ?>
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
                                    <a href="<?php echo $n['link'] ? base_url($n['link']) : '#'; ?>" class="bell-item <?php echo $n['is_read'] ? '' : 'unread'; ?>">
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
                        <span class="avatar"><?php echo e(strtoupper(substr($site_user['name'], 0, 1))); ?></span>
                    </button>
                    <div class="profile-menu" role="menu">
                        <div class="profile-menu-head">
                            <span class="avatar"><?php echo e(strtoupper(substr($site_user['name'], 0, 1))); ?></span>
                            <span class="chip-name">
                                <span><?php echo e($site_user['name']); ?></span>
                                <em><?php echo e($site_user['email']); ?></em>
                            </span>
                        </div>
                        <a href="<?php echo base_url('pages/profile.php'); ?>" role="menuitem"><i class="fa-solid fa-user"></i> My Profile</a>
                        <a href="<?php echo base_url('pages/change_password.php'); ?>" role="menuitem"><i class="fa-solid fa-lock"></i> Change Password</a>
                        <a href="<?php echo base_url('pages/logout.php'); ?>" role="menuitem" class="pm-danger" data-confirm="Log out of your account?" data-confirm-ok="Yes, log out" data-confirm-cancel="Cancel"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="nav-drop hide-sm">
                    <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="nav-link"><i class="fa-solid fa-chart-line"></i> Become a Manager</a>
                    <div class="nav-drop-panel">
                        <div class="nd-head">Run your courts your way</div>
                        <div class="nd-list">
                            <div class="nd-item"><i class="fa-solid fa-calendar-check"></i><span><strong>See every booking</strong>All your court bookings in one list, always up to date.</span></div>
                            <div class="nd-item"><i class="fa-solid fa-sack-dollar"></i><span><strong>Know what's paid</strong>See exactly what's been paid and what's still pending.</span></div>
                            <div class="nd-item"><i class="fa-solid fa-bolt"></i><span><strong>Free a slot fast</strong>Cancel a booking in a couple of clicks when plans change.</span></div>
                        </div>
                        <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="btn btn-primary btn-sm nd-cta"><i class="fa-solid fa-right-to-bracket"></i> Get started</a>
                    </div>
                </div>
                <span class="nav-divider hide-sm"></span>
                <a href="<?php echo base_url('pages/login.php'); ?>" class="btn btn-outline-dark btn-sm">Log in</a>
                <a href="<?php echo base_url('pages/register.php'); ?>" class="btn btn-primary btn-sm">Sign up</a>
            <?php endif; ?>
        </div>

        <button class="nav-toggle" id="navToggle" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
    </div>
</header>

<?php if ($flash): ?>
    <?php
        $type = $flash['type'];
        $toastClass = 'toast-error';
        $toastIcon = 'fa-triangle-exclamation';
        $toastRole = 'alert';
        if ($type === 'success') { $toastClass = 'toast-success'; $toastIcon = 'fa-circle-check'; $toastRole = 'status'; }
        elseif ($type === 'info') { $toastClass = 'toast-info'; $toastIcon = 'fa-circle-info'; $toastRole = 'status'; }
        elseif ($type === 'warning') { $toastClass = 'toast-warning'; $toastIcon = 'fa-triangle-exclamation'; }
        $inline = in_array($type, ['success', 'info'], true) ? 'toast-inline' : '';
        $detail = $flash['detail'] ?? null;
        $hasDetail = is_array($detail) && (($detail['why'] ?? '') !== '' || ($detail['how'] ?? '') !== '');
    ?>
    <?php if ($inline): ?><div class="container"><?php endif; ?>
    <div class="toast <?php echo $toastClass . ' ' . $inline; ?><?php echo $hasDetail ? ' has-detail' : ''; ?>" role="<?php echo $toastRole; ?>">
        <div class="toast-icon"><i class="fa-solid <?php echo $toastIcon; ?>"></i></div>
        <div class="toast-content">
            <div class="toast-msg"><span><?php echo e($flash['message']); ?></span></div>
            <?php if ($hasDetail && ($detail['why'] ?? '') !== ''): ?>
                <p class="toast-detail toast-reason"><i class="fa-solid fa-circle-question"></i> <span><?php echo e($detail['why']); ?></span></p>
            <?php endif; ?>
            <?php if ($hasDetail && ($detail['how'] ?? '') !== ''): ?>
                <?php if (!empty($detail['how_url'])): ?>
                    <a class="toast-detail toast-fix" href="<?php echo e($detail['how_url']); ?>"><i class="fa-solid fa-lightbulb"></i> <span><?php echo e($detail['how']); ?></span></a>
                <?php else: ?>
                    <p class="toast-detail toast-fix"><i class="fa-solid fa-lightbulb"></i> <span><?php echo e($detail['how']); ?></span></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <?php if ($inline): ?></div><?php endif; ?>
<?php endif; ?>

<main class="container page" id="mainContent" tabindex="-1">

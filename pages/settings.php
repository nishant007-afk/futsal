<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();
$currentPage = 'settings';

$page_title = 'Settings';
require __DIR__ . '/../includes/header.php';
?>

<div class="stg-layout">
    <!-- Sidebar -->
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
            <a href="<?php echo base_url('pages/settings.php'); ?>" class="stg-sidebar-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                <i class="fa-solid fa-sliders"></i> General
            </a>
            <a href="<?php echo base_url('pages/settings_account.php'); ?>" class="stg-sidebar-link <?php echo $currentPage === 'account' ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-pen"></i> Edit profile
            </a>
            <a href="<?php echo base_url('pages/security.php'); ?>" class="stg-sidebar-link <?php echo $currentPage === 'security' ? 'active' : ''; ?>">
                <i class="fa-solid fa-lock"></i> Password
            </a>
        </div>

        <div class="stg-sidebar-section">
            <span class="stg-sidebar-label">Preferences</span>
            <a href="<?php echo base_url('pages/settings_notifications.php'); ?>" class="stg-sidebar-link <?php echo $currentPage === 'notifications' ? 'active' : ''; ?>">
                <i class="fa-solid fa-bell"></i> Notifications
            </a>
            <a href="<?php echo base_url('pages/settings_preferences.php'); ?>" class="stg-sidebar-link <?php echo $currentPage === 'appearance' ? 'active' : ''; ?>">
                <i class="fa-solid fa-palette"></i> Appearance
            </a>
        </div>

        <div class="stg-sidebar-section">
            <span class="stg-sidebar-label">Support</span>
            <a href="<?php echo base_url('pages/faq.php'); ?>" class="stg-sidebar-link">
                <i class="fa-solid fa-circle-question"></i> Help & FAQ
            </a>
            <a href="<?php echo base_url('pages/page.php?slug=terms'); ?>" class="stg-sidebar-link">
                <i class="fa-solid fa-file-lines"></i> Terms
            </a>
            <a href="<?php echo base_url('pages/page.php?slug=privacy'); ?>" class="stg-sidebar-link">
                <i class="fa-solid fa-shield-halved"></i> Privacy
            </a>
        </div>

        <div class="stg-sidebar-section">
            <form method="post" action="<?php echo base_url('pages/logout.php'); ?>" class="m-0">
                <?php echo csrf_field(); ?>
                <button type="submit" class="stg-sidebar-link stg-sidebar-danger" data-confirm="Log out of your account?" data-confirm-ok="Yes, log out" data-confirm-cancel="Cancel">
                    <i class="fa-solid fa-right-from-bracket"></i> Log out
                </button>
            </form>
        </div>
    </nav>

    <!-- Main content -->
    <div class="stg-main">
        <div class="stg-main-head">
            <h2>General</h2>
        </div>

        <div class="stg-content-card">
            <div class="stg-content-section">
                <h3>Profile</h3>
                <p class="stg-content-desc">Your basic account information.</p>
                <div class="stg-content-row">
                    <span class="stg-content-label">Name</span>
                    <span class="stg-content-value"><?php echo e($user['name']); ?></span>
                    <a href="<?php echo base_url('pages/settings_account.php'); ?>" class="btn btn-outline btn-sm">Edit</a>
                </div>
                <div class="stg-content-row">
                    <span class="stg-content-label">Email</span>
                    <span class="stg-content-value"><?php echo e($user['email']); ?></span>
                </div>
                <div class="stg-content-row">
                    <span class="stg-content-label">Phone</span>
                    <span class="stg-content-value"><?php echo e($user['phone'] ?: 'Not set'); ?></span>
                </div>
                <div class="stg-content-row">
                    <span class="stg-content-label">Role</span>
                    <span class="stg-content-value"><?php echo e(ucfirst($user['role'])); ?></span>
                </div>
            </div>

            <div class="stg-content-section">
                <h3>Security</h3>
                <p class="stg-content-desc">Manage your password and account security.</p>
                <div class="stg-content-row">
                    <span class="stg-content-label">Password</span>
                    <span class="stg-content-value">Last changed: unknown</span>
                    <a href="<?php echo base_url('pages/security.php'); ?>" class="btn btn-outline btn-sm">Change</a>
                </div>
            </div>

            <div class="stg-content-section">
                <h3>Danger zone</h3>
                <p class="stg-content-desc">Irreversible actions.</p>
                <div class="stg-content-row">
                    <span class="stg-content-label">Delete account</span>
                    <span class="stg-content-value stg-content-value-warn">This cannot be undone.</span>
                    <a href="<?php echo base_url('pages/security.php'); ?>" class="btn btn-outline btn-sm btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

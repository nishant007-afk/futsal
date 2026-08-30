<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();

$page_title = 'Settings';
require __DIR__ . '/../includes/header.php';
?>

<div class="stg-page">
    <div class="stg-back">
        <a href="<?php echo base_url('pages/profile.php'); ?>" class="page-back-arrow" aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>Settings</h1>
    </div>

    <a href="<?php echo base_url('pages/profile.php'); ?>" class="stg-profile-row">
        <span class="stg-profile-avatar">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?php echo base_url('uploads/avatars/' . rawurlencode($user['avatar'])); ?>" alt="" loading="lazy" decoding="async">
            <?php else: ?>
                <?php echo e(strtoupper(substr($user['name'], 0, 1))); ?>
            <?php endif; ?>
        </span>
        <span class="stg-profile-info">
            <strong><?php echo e($user['name']); ?></strong>
            <span><?php echo e($user['email']); ?></span>
        </span>
        <i class="fa-solid fa-chevron-right"></i>
    </a>

    <div class="stg-section">
        <div class="stg-section-title">Account</div>
        <div class="stg-list">
            <a href="<?php echo base_url('pages/settings_account.php'); ?>" class="stg-item">
                <span class="stg-item-icon"><i class="fa-solid fa-user-pen"></i></span>
                <span class="stg-item-label">Edit profile</span>
                <i class="fa-solid fa-chevron-right stg-item-chevr"></i>
            </a>
            <a href="<?php echo base_url('pages/security.php'); ?>" class="stg-item">
                <span class="stg-item-icon"><i class="fa-solid fa-lock"></i></span>
                <span class="stg-item-label">Change password</span>
                <i class="fa-solid fa-chevron-right stg-item-chevr"></i>
            </a>
        </div>
    </div>

    <div class="stg-section">
        <div class="stg-section-title">Preferences</div>
        <div class="stg-list">
            <a href="<?php echo base_url('pages/settings_notifications.php'); ?>" class="stg-item">
                <span class="stg-item-icon"><i class="fa-solid fa-bell"></i></span>
                <span class="stg-item-label">Notifications</span>
                <i class="fa-solid fa-chevron-right stg-item-chevr"></i>
            </a>
            <a href="<?php echo base_url('pages/settings_preferences.php'); ?>" class="stg-item">
                <span class="stg-item-icon"><i class="fa-solid fa-palette"></i></span>
                <span class="stg-item-label">Appearance</span>
                <i class="fa-solid fa-chevron-right stg-item-chevr"></i>
            </a>
        </div>
    </div>

    <div class="stg-section">
        <div class="stg-section-title">Support</div>
        <div class="stg-list">
            <a href="<?php echo base_url('pages/faq.php'); ?>" class="stg-item">
                <span class="stg-item-icon"><i class="fa-solid fa-circle-question"></i></span>
                <span class="stg-item-label">Help & FAQ</span>
                <i class="fa-solid fa-chevron-right stg-item-chevr"></i>
            </a>
            <a href="<?php echo base_url('pages/page.php?slug=terms'); ?>" class="stg-item">
                <span class="stg-item-icon"><i class="fa-solid fa-file-lines"></i></span>
                <span class="stg-item-label">Terms of Service</span>
                <i class="fa-solid fa-chevron-right stg-item-chevr"></i>
            </a>
            <a href="<?php echo base_url('pages/page.php?slug=privacy'); ?>" class="stg-item">
                <span class="stg-item-icon"><i class="fa-solid fa-shield-halved"></i></span>
                <span class="stg-item-label">Privacy Policy</span>
                <i class="fa-solid fa-chevron-right stg-item-chevr"></i>
            </a>
        </div>
    </div>

    <div class="stg-section">
        <div class="stg-list">
            <a href="<?php echo base_url('pages/logout.php?csrf=' . csrf_token()); ?>" class="stg-item stg-item-danger" data-confirm="Log out of your account?" data-confirm-ok="Yes, log out" data-confirm-cancel="Cancel">
                <span class="stg-item-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                <span class="stg-item-label">Log out</span>
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

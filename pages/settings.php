<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();
$activeSettings = 'account';

$page_title = 'Settings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head settings-page-head">
    <div class="ps-head-row">
        <a href="<?php echo base_url('index.php'); ?>" class="page-back-arrow" aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Settings</h2>
    </div>
</div>

<div class="settings-layout">
    <?php require __DIR__ . '/../includes/views/settings_sidebar.php'; ?>

    <div class="settings-content">
        <div class="settings-card settings-summary">
            <div class="settings-summary-row">
                <span class="settings-summary-avatar">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?php echo base_url('uploads/avatars/' . rawurlencode($user['avatar'])); ?>" alt="<?php echo e($user['name']); ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <?php echo e(strtoupper(substr($user['name'], 0, 1))); ?>
                    <?php endif; ?>
                </span>
                <span class="settings-summary-info">
                    <strong><?php echo e($user['name']); ?></strong>
                    <em><?php echo e($user['email']); ?></em>
                </span>
                <span class="badge badge-<?php echo (int)($user['email_verified'] ?? 1) ? 'confirmed' : 'pending'; ?>">
                    <i class="fa-solid fa-<?php echo (int)($user['email_verified'] ?? 1) ? 'circle-check' : 'circle-exclamation'; ?>"></i>
                    <?php echo (int)($user['email_verified'] ?? 1) ? 'Verified' : 'Unverified'; ?>
                </span>
            </div>
            <p class="settings-summary-foot muted">Member since <?php echo e(date('F Y', strtotime($user['created_at']))); ?> &middot; <a href="<?php echo base_url('pages/profile.php'); ?>">View profile</a></p>
        </div>

        <div class="settings-card">
            <div class="settings-group-title"><i class="fa-solid fa-user-gear"></i> Manage account</div>
            <ul class="settings-account-list">
                <li><a href="<?php echo base_url('pages/settings_account.php'); ?>">Edit Profile <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a></li>
                <li><a href="<?php echo base_url('pages/security.php'); ?>">Change Password <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a></li>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

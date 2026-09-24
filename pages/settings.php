<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();
$currentPage = 'settings';

$page_title = 'Settings';
require __DIR__ . '/../includes/header.php';
?>

<div class="stg-layout">
    <?php settings_sidebar('settings'); ?>

    <!-- Main content -->
    <div class="stg-main">
        <div class="stg-main-head">
            <h2>General</h2>
        </div>

        <div class="stg-content-card">
            <div class="stg-content-section">
                <h3>Profile</h3>
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
                <div class="stg-content-row">
                    <span class="stg-content-label">Password</span>
                    <span class="stg-content-value">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                    <a href="<?php echo base_url('pages/security.php'); ?>" class="btn btn-outline btn-sm">Change</a>
                </div>
            </div>

            <div class="stg-content-section">
                <h3>Danger zone</h3>
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

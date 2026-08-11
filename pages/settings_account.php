<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$page_title = 'Account';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head settings-page-head">
    <div class="ps-head-row">
        <a href="<?php echo base_url('pages/settings.php'); ?>" class="nav-back" aria-label="Back to settings"><i class="fa-solid fa-chevron-left"></i></a>
        <div>
            <h2>Account</h2>
            <p class="muted" style="font-size:13px;margin-top:4px;">Your GoalSpace profile and preferences.</p>
        </div>
    </div>
</div>

<div class="settings-card settings-narrow">
    <ul class="settings-account-list">
        <li><a href="<?php echo base_url('pages/profile.php'); ?>"><i class="fa-solid fa-user"></i> My Profile <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a></li>
        <li><a href="<?php echo base_url('pages/change_password.php'); ?>"><i class="fa-solid fa-key"></i> Change Password <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a></li>
    </ul>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

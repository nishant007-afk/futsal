<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$page_title = 'Settings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head settings-page-head">
    <div class="ps-head-row">
        <a href="<?php echo base_url('index.php'); ?>" class="nav-back mob-title-back" aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Settings</h2>
    </div>
</div>

<!-- mobile dedicated-page list -->
<nav class="settings-nav settings-nav-links" aria-label="Settings sections">
    <a href="<?php echo base_url('pages/settings_account.php'); ?>"><i class="fa-solid fa-user-gear"></i> Account <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a>
    <a href="<?php echo base_url('pages/settings_notifications.php'); ?>"><i class="fa-solid fa-bell"></i> Notifications <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a>
    <a href="<?php echo base_url('pages/settings_preferences.php'); ?>"><i class="fa-solid fa-sliders"></i> Preferences <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a>
</nav>

<!-- desktop: settings hierarchy -->
<div class="settings-hub">
    <div class="sh-group">
        <a href="<?php echo base_url('pages/settings_account.php'); ?>" class="sh-row"><i class="fa-solid fa-user-gear"></i><span class="hub-txt"><strong>Account</strong><em>Profile and password</em></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
        <div class="sh-sub">
            <a href="<?php echo base_url('pages/profile.php'); ?>" class="sh-row"><i class="fa-solid fa-user"></i><span>My Profile</span></a>
            <a href="<?php echo base_url('pages/change_password.php'); ?>" class="sh-row"><i class="fa-solid fa-key"></i><span>Change Password</span></a>
        </div>
    </div>
    <a href="<?php echo base_url('pages/settings_notifications.php'); ?>" class="sh-row"><i class="fa-solid fa-bell"></i><span class="hub-txt"><strong>Notifications</strong><em>What we email or text you about</em></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
    <a href="<?php echo base_url('pages/settings_preferences.php'); ?>" class="sh-row"><i class="fa-solid fa-sliders"></i><span class="hub-txt"><strong>Preferences</strong><em>Appearance and location</em></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
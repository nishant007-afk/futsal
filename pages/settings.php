<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$page_title = 'Settings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head settings-page-head">
    <div>
        <h2>Settings</h2>
        <p class="muted" style="font-size:13px;margin-top:4px;">Manage your account, notifications and preferences.</p>
    </div>
</div>

<!-- mobile dedicated-page list -->
<nav class="settings-nav settings-nav-links" aria-label="Settings sections">
    <a href="<?php echo base_url('pages/settings_account.php'); ?>"><i class="fa-solid fa-user-gear"></i> Account <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a>
    <a href="<?php echo base_url('pages/settings_notifications.php'); ?>"><i class="fa-solid fa-bell"></i> Notifications <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a>
    <a href="<?php echo base_url('pages/settings_preferences.php'); ?>"><i class="fa-solid fa-sliders"></i> Preferences <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a>
</nav>

<!-- desktop: settings hierarchy -->
<div class="settings-desktop">
    <?php include __DIR__ . '/../includes/settings_nav.php'; ?>
    <div class="settings-layout-main">
        <div class="settings-card settings-narrow settings-hub">
            <div class="settings-hub-list">
                <a href="<?php echo base_url('pages/settings_account.php'); ?>"><i class="fa-solid fa-user-gear"></i><span class="hub-txt"><strong>Account</strong><em>Profile and password</em></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                <a href="<?php echo base_url('pages/settings_notifications.php'); ?>"><i class="fa-solid fa-bell"></i><span class="hub-txt"><strong>Notifications</strong><em>What we email or text you about</em></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                <a href="<?php echo base_url('pages/settings_preferences.php'); ?>"><i class="fa-solid fa-sliders"></i><span class="hub-txt"><strong>Preferences</strong><em>Appearance and theme</em></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
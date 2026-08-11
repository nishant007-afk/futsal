<?php
$st = $active ?? basename($_SERVER['SCRIPT_NAME']);
?>
<!-- desktop settings hierarchy rail -->
<nav class="settings-hier" aria-label="Settings sections">
    <div class="settings-hier-group">
        <a href="<?php echo base_url('pages/settings_account.php'); ?>" class="settings-hier-link<?php echo $st === 'settings_account.php' ? ' active' : ''; ?>"><i class="fa-solid fa-user-gear"></i><span>Account</span></a>
        <div class="settings-hier-sub">
            <a href="<?php echo base_url('pages/profile.php'); ?>" class="settings-hier-link sub<?php echo in_array($st, ['profile.php'], true) ? ' active' : ''; ?>"><i class="fa-solid fa-user"></i><span>My Profile</span></a>
            <a href="<?php echo base_url('pages/change_password.php'); ?>" class="settings-hier-link sub<?php echo in_array($st, ['change_password.php'], true) ? ' active' : ''; ?>"><i class="fa-solid fa-key"></i><span>Change Password</span></a>
        </div>
    </div>
    <a href="<?php echo base_url('pages/settings_notifications.php'); ?>" class="settings-hier-link<?php echo $st === 'settings_notifications.php' ? ' active' : ''; ?>"><i class="fa-solid fa-bell"></i><span>Notifications</span></a>
    <a href="<?php echo base_url('pages/settings_preferences.php'); ?>" class="settings-hier-link<?php echo $st === 'settings_preferences.php' ? ' active' : ''; ?>"><i class="fa-solid fa-sliders"></i><span>Preferences</span></a>
</nav>
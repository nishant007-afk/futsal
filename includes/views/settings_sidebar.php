<?php
// Persistent settings navigation - vertical sidebar on desktop,
// horizontal pill bar on mobile. Expects $activeSettings to be one of:
// account | notifications | preferences | security
$settingsSections = [
    'account'       => ['pages/settings.php',               'fa-user-gear',     'Account'],
    'notifications' => ['pages/settings_notifications.php', 'fa-bell',          'Notifications'],
    'preferences'   => ['pages/settings_preferences.php',   'fa-sliders',       'Preferences'],
    'security'      => ['pages/security.php',               'fa-shield-halved', 'Security'],
];
$activeSettings = $activeSettings ?? '';
?>
<aside class="settings-sidebar">
    <nav class="ss-nav" aria-label="Settings sections">
        <?php foreach ($settingsSections as $key => [$href, $icon, $label]): ?>
            <a href="<?php echo base_url($href); ?>" class="ss-item<?php echo $activeSettings === $key ? ' active' : ''; ?>"<?php echo $activeSettings === $key ? ' aria-current="page"' : ''; ?>>
                <i class="fa-solid <?php echo $icon; ?>"></i><span><?php echo $label; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

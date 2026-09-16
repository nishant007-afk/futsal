<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_notifications') {
    verify_csrf();
    $notifyBookings = isset($_POST['notify_bookings']) ? 1 : 0;
    $notifyPromo = isset($_POST['notify_promo']) ? 1 : 0;
    $notifyExpiry = isset($_POST['notify_expiry']) ? 1 : 0;
    $notifySms = isset($_POST['notify_sms']) ? 1 : 0;
    $stmt = $conn->prepare('UPDATE users SET notify_bookings = ?, notify_promo = ?, notify_expiry = ?, notify_sms = ? WHERE id = ?');
    $stmt->bind_param('iiiii', $notifyBookings, $notifyPromo, $notifyExpiry, $notifySms, $_SESSION['user_id']);
    if ($stmt->execute()) {
        set_flash('success', 'Notification preferences saved.');
    } else {
        set_flash_error(
            'Could not save your preferences.',
            'The server returned an error while saving.',
            'Please try again in a moment.',
            'pages/settings_notifications.php'
        );
    }
    redirect('pages/settings_notifications.php');
}

$page_title = 'Notifications';
require __DIR__ . '/../includes/header.php';
?>

<div class="stg-layout">
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
            <a href="<?php echo base_url('pages/settings.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-sliders"></i> General</a>
            <a href="<?php echo base_url('pages/settings_account.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-user-pen"></i> Edit profile</a>
            <a href="<?php echo base_url('pages/security.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-lock"></i> Password</a>
        </div>
        <div class="stg-sidebar-section">
            <span class="stg-sidebar-label">Preferences</span>
            <a href="<?php echo base_url('pages/settings_notifications.php'); ?>" class="stg-sidebar-link active"><i class="fa-solid fa-bell"></i> Notifications</a>
            <a href="<?php echo base_url('pages/settings_preferences.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-palette"></i> Appearance</a>
        </div>
        <div class="stg-sidebar-section">
            <span class="stg-sidebar-label">Support</span>
            <a href="<?php echo base_url('pages/faq.php'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-circle-question"></i> Help & FAQ</a>
            <a href="<?php echo base_url('pages/page.php?slug=terms'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-file-lines"></i> Terms</a>
            <a href="<?php echo base_url('pages/page.php?slug=privacy'); ?>" class="stg-sidebar-link"><i class="fa-solid fa-shield-halved"></i> Privacy</a>
        </div>
        <div class="stg-sidebar-section">
            <form method="post" action="<?php echo base_url('pages/logout.php'); ?>" class="m-0">
                <?php echo csrf_field(); ?>
                <button type="submit" class="stg-sidebar-link stg-sidebar-danger" data-confirm="Log out of your account?" data-confirm-ok="Yes, log out" data-confirm-cancel="Cancel"><i class="fa-solid fa-right-from-bracket"></i> Log out</button>
            </form>
        </div>
    </nav>

    <div class="stg-main">
        <div class="stg-main-head"><h2>Notifications</h2></div>
        <div class="stg-content-card">
            <div class="stg-content-section">
                <h3>Email notifications</h3>
                <p class="stg-content-desc">Choose what email updates you receive.</p>
                <form method="post" action="" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_notifications">
                    <div class="settings-stack">
                        <div class="pref-row">
                            <div class="pref-row-text">
                                <strong><label for="notify_bookings">Booking updates</label></strong>
                                <em>Confirmations, reminders and changes to your bookings</em>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="notify_bookings" name="notify_bookings" <?php echo (int)($user['notify_bookings'] ?? 1) ? 'checked' : ''; ?> aria-label="Enable booking updates">
                                <span class="switch-track" aria-hidden="true"></span>
                            </label>
                        </div>
                        <div class="pref-row">
                            <div class="pref-row-text">
                                <strong><label for="notify_promo">Promotional emails</label></strong>
                                <em>Offers, seasonal deals and new court announcements</em>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="notify_promo" name="notify_promo" <?php echo (int)($user['notify_promo'] ?? 1) ? 'checked' : ''; ?> aria-label="Enable promotional emails">
                                <span class="switch-track" aria-hidden="true"></span>
                            </label>
                        </div>
                        <div class="pref-row">
                            <div class="pref-row-text">
                                <strong><label for="notify_expiry">Expiry reminders</label></strong>
                                <em>Heads-up when a booking is about to start or still needs payment</em>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="notify_expiry" name="notify_expiry" <?php echo (int)($user['notify_expiry'] ?? 1) ? 'checked' : ''; ?> aria-label="Enable expiry reminders">
                                <span class="switch-track" aria-hidden="true"></span>
                            </label>
                        </div>
                        <div class="pref-row">
                            <div class="pref-row-text">
                                <strong><label for="notify_sms">SMS notifications</label></strong>
                                <em>Text messages for confirmations and urgent updates</em>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="notify_sms" name="notify_sms" <?php echo (int)($user['notify_sms'] ?? 0) ? 'checked' : ''; ?> aria-label="Enable SMS notifications">
                                <span class="switch-track" aria-hidden="true"></span>
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-floppy-disk"></i> Save preferences</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

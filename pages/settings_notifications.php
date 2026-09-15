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

<div class="stg-page">
    <div class="stg-back">
        <a href="<?php echo base_url('pages/settings.php'); ?>" class="page-back-arrow" aria-label="Back to settings"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>Notifications</h1>
    </div>

    <div class="stg-section">
        <div class="settings-card settings-narrow">
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

<?php require __DIR__ . '/../includes/footer.php'; ?>

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

<div class="page-head settings-page-head">
    <div class="ps-head-row">
        <a href="<?php echo base_url('pages/settings.php'); ?>" class="nav-back" aria-label="Back to settings"><i class="fa-solid fa-chevron-left"></i></a>
        <div>
            <h2>Notifications</h2>
            <p class="muted" style="font-size:13px;margin-top:4px;">Choose what we email or text you about.</p>
        </div>
    </div>
</div>

<div class="settings-card settings-narrow">
    <form method="post" action="" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update_notifications">
        <ul class="notify-options">
            <li class="opt-row"><input type="checkbox" id="notify_bookings" name="notify_bookings" <?php echo (int)($user['notify_bookings'] ?? 1) ? 'checked' : ''; ?>> <label for="notify_bookings">Booking updates</label></li>
            <li class="opt-row"><input type="checkbox" id="notify_promo" name="notify_promo" <?php echo (int)($user['notify_promo'] ?? 1) ? 'checked' : ''; ?>> <label for="notify_promo">Promotional emails</label></li>
            <li class="opt-row"><input type="checkbox" id="notify_expiry" name="notify_expiry" <?php echo (int)($user['notify_expiry'] ?? 1) ? 'checked' : ''; ?>> <label for="notify_expiry">Expiry reminders</label></li>
            <li class="opt-row"><input type="checkbox" id="notify_sms" name="notify_sms" <?php echo (int)($user['notify_sms'] ?? 0) ? 'checked' : ''; ?>> <label for="notify_sms">SMS notifications</label></li>
        </ul>
        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-floppy-disk"></i> Save preferences</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

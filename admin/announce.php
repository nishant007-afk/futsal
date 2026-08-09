<?php
require_once __DIR__ . '/../config/db.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $subject  = trim((string)($_POST['subject'] ?? ''));
    $message  = trim((string)($_POST['message'] ?? ''));
    $scope    = trim((string)($_POST['scope'] ?? 'all'));
    $sendMail = !empty($_POST['send_mail']);

    $validScopes = ['all', 'admins', 'managers', 'users'];
    if (!in_array($scope, $validScopes, true)) {
        $scope = 'all';
    }

    if ($subject === '' || $message === '') {
        set_flash_error('Subject and message are required.', 'Both fields must be filled in.', 'Fill in the form and try again.', 'admin/announce.php');
    } else {
        $n = notify_announcement($subject, $message, $scope, $sendMail);
        set_flash('success', $n > 0
            ? "Announcement sent to $n user(s)."
            : 'No users matched that scope.');
    }
    redirect('admin/announce.php');
}

$page_title = 'Send announcement';
require __DIR__ . '/../includes/header.php';
?>

<div class="content-hero">
    <h1>Send announcement</h1>
    <p class="muted">Send a one-off message to all users, managers, or players. Creates an in-app notification; optionally also sends an email.</p>
</div>

<form method="post" action="">
    <?php echo csrf_field(); ?>
    <div class="form-group">
        <label for="subject"><strong>Subject</strong></label>
        <input type="text" id="subject" name="subject" required maxlength="120" placeholder="e.g. Scheduled maintenance this weekend">
    </div>

    <div class="form-group">
        <label for="message"><strong>Message</strong></label>
        <textarea id="message" name="message" rows="8" required placeholder="Your message here..."></textarea>
    </div>

    <div class="form-group">
        <strong>Send to</strong>
        <div class="scope-row">
            <span class="scope-label">All users</span>
            <input type="radio" name="scope" value="all" checked>
        </div>
        <div class="scope-row">
            <span class="scope-label">Admins only</span>
            <input type="radio" name="scope" value="admins">
        </div>
        <div class="scope-row">
            <span class="scope-label">Managers only</span>
            <input type="radio" name="scope" value="managers">
        </div>
        <div class="scope-row">
            <span class="scope-label">Players only</span>
            <input type="radio" name="scope" value="users">
        </div>
    </div>

    <div class="form-group">
        <div class="tick-row">
            <label for="send_mail" class="tick-label">Also send as email</label>
            <input type="checkbox" name="send_mail" value="1" id="send_mail">
        </div>
    </div>

    <style>
    .tick-row, .scope-row { display: flex; align-items: center; padding: 6px 0; }
    .tick-row label, .scope-row .scope-label { flex: 0 0 200px; text-align: left; cursor: default; }
    .tick-row input, .scope-row input { margin-left: auto; cursor: pointer; }
    </style>

    <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-paper-plane"></i> Send announcement
    </button>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
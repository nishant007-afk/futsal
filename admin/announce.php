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
    <div class="title-back-row">
        <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>Send announcement</h1>
    </div>
    <p class="muted">Send a broadcast message to all users, managers, or players. Creates an in-app notification and optionally delivers a formatted email.</p>
</div>

<div class="announce-layout">
    <div class="announce-form-pane">
        <form method="post" action="" id="announceForm">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="subject"><strong>Subject</strong></label>
                <input type="text" id="subject" name="subject" required maxlength="150" placeholder="e.g. Scheduled maintenance this weekend" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="message"><strong>Message</strong></label>
                <textarea id="message" name="message" rows="7" required placeholder="Write your announcement message here..."></textarea>
            </div>

            <div class="form-group" role="radiogroup" aria-labelledby="scopeLabel">
                <span id="scopeLabel"><strong>Target Audience</strong></span>
                <div class="scope-options">
                    <label class="scope-row">
                        <input type="radio" name="scope" value="all" checked>
                        <span class="scope-label"><i class="fa-solid fa-users"></i> All users</span>
                    </label>
                    <label class="scope-row">
                        <input type="radio" name="scope" value="admins">
                        <span class="scope-label"><i class="fa-solid fa-shield-halved"></i> Admins only</span>
                    </label>
                    <label class="scope-row">
                        <input type="radio" name="scope" value="managers">
                        <span class="scope-label"><i class="fa-solid fa-store"></i> Managers only</span>
                    </label>
                    <label class="scope-row">
                        <input type="radio" name="scope" value="users">
                        <span class="scope-label"><i class="fa-solid fa-futbol"></i> Players only</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="tick-card" for="send_mail">
                    <input type="checkbox" name="send_mail" value="1" id="send_mail">
                    <div class="tick-card-body">
                        <span class="tick-title"><i class="fa-solid fa-envelope"></i> Also deliver via email</span>
                        <span class="tick-desc muted">Dispatches a styled email to every verified user in the selected audience.</span>
                    </div>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fa-solid fa-paper-plane"></i> Send announcement
            </button>
        </form>
    </div>

    <div class="announce-preview-pane">
        <div class="preview-panel-head">
            <span class="preview-panel-tag"><i class="fa-solid fa-eye"></i> Live Preview</span>
            <span class="audience-badge" id="previewAudienceBadge"><i class="fa-solid fa-users"></i> All users</span>
        </div>

        <div class="preview-card-box">
            <h4 class="preview-subhead"><i class="fa-solid fa-bell"></i> In-App Notification</h4>
            <div class="notification-preview">
                <div class="notif-avatar"><i class="fa-solid fa-bullhorn"></i></div>
                <div class="notif-content">
                    <div class="notif-top">
                        <span class="notif-title" id="notifPreviewTitle">Scheduled maintenance this weekend</span>
                        <span class="notif-time">Just now</span>
                    </div>
                    <div class="notif-body" id="notifPreviewBody">Your announcement message will appear here in real time as you type...</div>
                </div>
            </div>
        </div>

        <div class="preview-card-box email-preview-box" id="emailPreviewBox">
            <h4 class="preview-subhead">
                <i class="fa-solid fa-envelope-open-text"></i> Email Dispatch Preview
                <span class="email-preview-status" id="emailStatusBadge">Email disabled</span>
            </h4>
            <div class="email-preview-card">
                <div class="email-header-bar">
                    <div class="email-dot"></div>
                    <div class="email-meta-info">
                        <div><strong>From:</strong> Futsal Booking &lt;support@futsal.local&gt;</div>
                        <div><strong>Subject:</strong> <span id="emailSubjectPreview">Scheduled maintenance this weekend</span></div>
                    </div>
                </div>
                <div class="email-rendered-body">
                    <div class="email-pill-tag">System Announcement</div>
                    <h3 id="emailHeadingPreview" class="email-heading">Scheduled maintenance this weekend</h3>
                    <div id="emailMessagePreview" class="email-text">Your announcement message will appear here in real time as you type...</div>
                    <div class="email-footer-note">
                        This is an official announcement from Futsal Booking. You received this because of your account settings.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.announce-layout {
    display: grid;
    grid-template-columns: minmax(320px, 1.15fr) minmax(320px, 1fr);
    gap: 28px;
    align-items: start;
    margin-top: 16px;
    margin-bottom: 40px;
}
.announce-form-pane {
    background: var(--surface, #ffffff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: var(--radius-lg, 12px);
    padding: 24px;
}
.scope-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 6px;
}
.scope-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: var(--radius-md, 8px);
    cursor: pointer;
    background: var(--surface-subtle, #f9fafb);
    transition: all 0.15s ease;
}
.scope-row:hover {
    border-color: var(--primary, #10b981);
    background: var(--surface, #ffffff);
}
.scope-row input[type="radio"] {
    margin: 0;
    accent-color: var(--primary, #10b981);
    cursor: pointer;
}
.scope-row .scope-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text, #1f2937);
    display: flex;
    align-items: center;
    gap: 6px;
}
.tick-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: var(--radius-md, 8px);
    cursor: pointer;
    background: var(--surface-subtle, #f9fafb);
    transition: all 0.15s ease;
}
.tick-card:hover {
    border-color: var(--primary, #10b981);
}
.tick-card input[type="checkbox"] {
    margin-top: 4px;
    accent-color: var(--primary, #10b981);
    cursor: pointer;
}
.tick-card-body {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.tick-title {
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--text, #1f2937);
    display: flex;
    align-items: center;
    gap: 6px;
}
.tick-desc {
    font-size: 0.82rem;
}
.announce-preview-pane {
    position: sticky;
    top: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.preview-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 4px;
}
.preview-panel-tag {
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--muted, #6b7280);
    display: flex;
    align-items: center;
    gap: 6px;
}
.audience-badge {
    font-size: 0.82rem;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 999px;
    background: var(--primary-subtle, #ecfdf5);
    color: var(--primary-dark, #065f46);
    border: 1px solid var(--primary, #10b981);
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.preview-card-box {
    background: var(--surface, #ffffff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: var(--radius-lg, 12px);
    padding: 18px;
}
.preview-subhead {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text, #1f2937);
    margin: 0 0 12px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.notification-preview {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 12px;
    background: var(--surface-subtle, #f9fafb);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: var(--radius-md, 8px);
}
.notif-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--primary, #10b981);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.95rem;
}
.notif-content {
    flex: 1;
    min-width: 0;
}
.notif-top {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 8px;
    margin-bottom: 4px;
}
.notif-title {
    font-weight: 600;
    font-size: 0.92rem;
    color: var(--text, #111827);
    word-break: break-word;
}
.notif-time {
    font-size: 0.75rem;
    color: var(--muted, #6b7280);
    white-space: nowrap;
}
.notif-body {
    font-size: 0.85rem;
    color: var(--text-muted, #4b5563);
    line-height: 1.45;
    word-break: break-word;
    white-space: pre-line;
}
.email-preview-status {
    margin-left: auto;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--muted, #6b7280);
    padding: 2px 7px;
    border-radius: 999px;
    background: var(--surface-subtle, #f3f4f6);
}
.email-preview-box.active-mail .email-preview-status {
    background: var(--primary-subtle, #ecfdf5);
    color: var(--primary-dark, #065f46);
}
.email-preview-card {
    border: 1px solid var(--border, #e5e7eb);
    border-radius: var(--radius-md, 8px);
    overflow: hidden;
    background: #ffffff;
    opacity: 0.65;
    transition: opacity 0.2s ease, border-color 0.2s ease;
}
.email-preview-box.active-mail .email-preview-card {
    opacity: 1;
    border-color: var(--primary, #10b981);
}
.email-header-bar {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 10px 14px;
    font-size: 0.82rem;
    color: #475569;
    display: flex;
    gap: 8px;
    align-items: center;
}
.email-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #cbd5e1;
    flex-shrink: 0;
}
.email-meta-info {
    line-height: 1.4;
    word-break: break-word;
}
.email-rendered-body {
    padding: 16px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    color: #1e293b;
}
.email-pill-tag {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    background: #ecfdf5;
    color: #047857;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 10px;
}
.email-heading {
    margin: 0 0 10px 0;
    font-size: 1.1rem;
    color: #0f172a;
    font-weight: 700;
    word-break: break-word;
}
.email-text {
    font-size: 0.88rem;
    line-height: 1.55;
    color: #334155;
    white-space: pre-line;
    word-break: break-word;
    margin-bottom: 16px;
}
.email-footer-note {
    border-top: 1px solid #f1f5f9;
    padding-top: 10px;
    font-size: 0.75rem;
    color: #94a3b8;
    line-height: 1.4;
}

@media (max-width: 900px) {
    .announce-layout {
        grid-template-columns: 1fr;
    }
    .announce-preview-pane {
        position: static;
    }
}
@media (max-width: 540px) {
    .scope-options {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var subjectInput = document.getElementById('subject');
    var messageInput = document.getElementById('message');
    var sendMailCheck = document.getElementById('send_mail');
    var scopeRadios = document.querySelectorAll('input[name="scope"]');

    var notifTitle = document.getElementById('notifPreviewTitle');
    var notifBody = document.getElementById('notifPreviewBody');
    var audienceBadge = document.getElementById('previewAudienceBadge');
    var emailBox = document.getElementById('emailPreviewBox');
    var emailStatus = document.getElementById('emailStatusBadge');
    var emailSubj = document.getElementById('emailSubjectPreview');
    var emailHead = document.getElementById('emailHeadingPreview');
    var emailMsg = document.getElementById('emailMessagePreview');

    var fallbackTitle = 'Scheduled maintenance this weekend';
    var fallbackMsg = 'Your announcement message will appear here in real time as you type...';

    var scopeLabels = {
        'all': '<i class="fa-solid fa-users"></i> All users',
        'admins': '<i class="fa-solid fa-shield-halved"></i> Admins only',
        'managers': '<i class="fa-solid fa-store"></i> Managers only',
        'users': '<i class="fa-solid fa-futbol"></i> Players only'
    };

    function updatePreview() {
        var s = subjectInput.value.trim();
        var m = messageInput.value.trim();

        var titleText = s !== '' ? s : fallbackTitle;
        var bodyText = m !== '' ? m : fallbackMsg;

        notifTitle.textContent = titleText;
        notifBody.textContent = bodyText;

        emailSubj.textContent = titleText;
        emailHead.textContent = titleText;
        emailMsg.textContent = bodyText;
    }

    function updateScope() {
        var selected = document.querySelector('input[name="scope"]:checked');
        if (selected && audienceBadge) {
            audienceBadge.innerHTML = scopeLabels[selected.value] || '<i class="fa-solid fa-users"></i> All users';
        }
    }

    function updateMailState() {
        if (!emailBox || !sendMailCheck || !emailStatus) { return; }
        if (sendMailCheck.checked) {
            emailBox.classList.add('active-mail');
            emailStatus.textContent = 'Email dispatch active';
        } else {
            emailBox.classList.remove('active-mail');
            emailStatus.textContent = 'Email disabled';
        }
    }

    subjectInput.addEventListener('input', updatePreview);
    messageInput.addEventListener('input', updatePreview);
    sendMailCheck.addEventListener('change', updateMailState);

    scopeRadios.forEach(function (r) {
        r.addEventListener('change', updateScope);
    });

    updatePreview();
    updateScope();
    updateMailState();
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
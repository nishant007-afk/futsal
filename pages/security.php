<?php
require_once __DIR__ . '/../config/db.php';

require_login();

$user = current_user();

$page_title = 'Security';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head settings-page-head" style="align-items:flex-start;">
    <div style="display:flex;align-items:center;gap:12px;">
        <a href="<?php echo base_url('pages/settings.php'); ?>" class="nav-back" aria-label="Back to settings"><i class="fa-solid fa-chevron-left"></i></a>
        <div>
            <h2>Security</h2>
            <p class="muted" style="font-size:13px;margin-top:4px;">Keep your GoalSpace account secure.</p>
        </div>
    </div>
</div>

<nav class="settings-nav" aria-label="Security sections">
    <a href="<?php echo base_url('pages/change_password.php'); ?>"><i class="fa-solid fa-key"></i> Change password <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a>
    <a href="<?php echo base_url('pages/security.php'); ?>" class="active" aria-current="page"><i class="fa-solid fa-shield-halved"></i> Security <i class="fa-solid fa-chevron-right settings-nav-chevr" aria-hidden="true"></i></a>
</nav>

<div class="settings-card settings-narrow">
    <div class="form-head">
        <h2>Password</h2>
        <p class="muted" style="font-size:13px;margin-top:4px;">Your password is the first line of defence for your account.</p>
    </div>
    <a href="<?php echo base_url('pages/change_password.php'); ?>" class="btn btn-outline btn-block"><i class="fa-solid fa-key"></i> Change password</a>
    <p class="form-hint" style="margin-top:14px;font-size:12.5px;">We recommend updating it every few months.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
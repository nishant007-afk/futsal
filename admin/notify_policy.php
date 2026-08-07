<?php
// Admin tool: announce a legal-page update to users.
// Sends ONE combined email + ONE in-app notification per recipient (never one email per page).
// The same-day guard (saved as setting('policy_notice_sent')) prevents accidental duplicate sends.
require_once __DIR__ . '/../config/db.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$legal = [
    'privacy' => 'Privacy Policy',
    'terms'   => 'Terms of Service',
    'about'   => 'About GoalSpace',
    'help'    => 'Help and Support',
    'contact' => 'Contact',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $slugs  = array_values($_POST['slugs'] ?? []);
    $scope  = $_POST['scope'] ?? 'admins';
    $force  = !empty($_POST['force']);

    $validScopes = ['all', 'admins', 'managers', 'users'];
    $invalid = array_diff($slugs, array_keys($legal));

    if (!empty($invalid) || !in_array($scope, $validScopes, true)) {
        set_flash_error('Invalid selection.', 'One of your choices was not recognised.', 'Please use the form below.', 'admin/notify_policy.php');
    } elseif (empty($slugs)) {
        set_flash_error('Nothing selected.', 'No pages were selected.', 'Choose the pages you updated.', 'admin/notify_policy.php');
    } else {
        $already = setting('policy_notice_sent') === date('Y-m-d');
        if ($already && !$force) {
            set_flash_error(
                'Already sent today.',
                'A notice was already sent today (' . setting('policy_notice_sent') . ').',
                'Check "Force resend" if you really want to send another.',
                'admin/notify_policy.php'
            );
        } else {
            $n = notify_policy_update($slugs, date('F j, Y'), $scope);
            save_setting('policy_notice_sent', date('Y-m-d'));
            set_flash('success', $n > 0
                ? "Notified $n user(s). Each received one combined email and one in-app notification."
                : 'No users matched that scope.');
        }
    }
    redirect('admin/notify_policy.php');
}

$page_title = 'Announce a policy update';
require __DIR__ . '/../includes/header.php';
?>

<div class="content-hero">
    <span class="eyebrow">GoalSpace</span>
    <h1>Announce a policy update</h1>
    <p class="muted">Run this after you update legal pages in <code>pages/page.php</code>. Each recipient gets one combined message (every selected page in a single email) and one notification in their tab.</p>
</div>

<form method="post" action="">
    <?php echo csrf_field(); ?>
    <div class="form-group">
        <strong>Pages that were updated</strong>
        <?php foreach ($legal as $slug => $label): ?>
            <div>
                <label>
                    <input type="checkbox" name="slugs[]" value="<?php echo e($slug); ?>">
                    <?php echo e($label); ?>
                </label>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-group">
        <strong>Send to</strong>
        <p><label><input type="radio" name="scope" value="all" checked> All users (default)</label></p>
        <p><label><input type="radio" name="scope" value="admins"> Admins only</label></p>
        <p><label><input type="radio" name="scope" value="managers"> Managers only</label></p>
        <p><label><input type="radio" name="scope" value="users"> Players only</label></p>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="force" value="1">
            Force resend (ignores the same-day guard)
        </label>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-paper-plane"></i> Send notice
    </button>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>

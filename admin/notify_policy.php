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
    <div class="title-back-row">
        <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>Announce a policy update</h1>
    </div>
    <div class="hero-subtext">
        <p class="muted"><strong>When to use:</strong> Run this after you update legal pages in <code>pages/page.php</code>.</p>
        <p class="muted"><strong>What happens:</strong> Each recipient gets one combined message (every selected page in a single email) and one notification in their tab.</p>
    </div>
</div>

<form method="post" action="">
    <?php echo csrf_field(); ?>
    <div class="form-group">
        <strong>Pages that were updated</strong>
        <?php foreach ($legal as $slug => $label): ?>
            <div class="tick-row">
                <label for="slug_<?php echo e($slug); ?>" class="tick-label"><?php echo e($label); ?></label>
                <input type="checkbox" name="slugs[]" value="<?php echo e($slug); ?>" id="slug_<?php echo e($slug); ?>">
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-group">
        <strong>Send to</strong>
        <div class="scope-row">
            <span class="scope-label">All users (default)</span>
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
            <label for="force" class="tick-label">Force resend (ignores the same-day guard)</label>
            <input type="checkbox" name="force" value="1" id="force">
        </div>
    </div>

    <style>
    .tick-row, .scope-row { display: flex; align-items: center; padding: 6px 0; }
    .tick-row label, .scope-row .scope-label { flex: 0 0 200px; text-align: left; cursor: default; }
    .tick-row input, .scope-row input { margin-left: auto; cursor: pointer; }
    .tick-row input:focus, .scope-row input:focus {
        outline: none !important;
        box-shadow: none !important;
        border-color: inherit !important;
    }
    .tick-row input:focus-visible, .scope-row input:focus-visible {
        outline: 2px solid #4a90d9 !important;
        outline-offset: 1px !important;
        box-shadow: none !important;
    }
    .tick-row:hover, .scope-row:hover, .tick-row:focus-within, .scope-row:focus-within { background: transparent; }
    .tick-row label:hover, .scope-row .scope-label:hover { background: transparent; color: inherit; }
    .hero-subtext { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }
    .hero-subtext p { margin: 0; padding: 10px 14px; background: var(--bg-soft); border-radius: 6px; border-left: 3px solid var(--brand); }
    .hero-subtext p strong { color: var(--ink); }
    </style>

    <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-paper-plane"></i> Send notice
    </button>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>

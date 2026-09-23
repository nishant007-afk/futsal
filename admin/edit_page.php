<?php
require_once __DIR__ . '/../config/db.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$legal = legal_pages_defaults();

// Only these slugs are editable
$slug = $_GET['slug'] ?? 'about';
if (!isset($legal[$slug])) {
    $slug = 'about';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postSlug = $_POST['slug'] ?? '';
    $title   = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $body    = sanitize_page_body($_POST['body'] ?? '');

    if (!isset($legal[$postSlug])) {
        set_flash_error('Unknown page.', 'The slug you sent was not recognised.', 'Use the editor form.', 'admin/edit_page.php?slug=' . urlencode($slug));
        redirect('admin/edit_page.php?slug=' . urlencode($slug));
    }
    if ($title === '' || $body === '') {
        set_flash_error('Missing fields.', 'The title and body are required.', 'Please fill the form and try again.', 'admin/edit_page.php?slug=' . urlencode($postSlug));
        redirect('admin/edit_page.php?slug=' . urlencode($postSlug));
    }

    if (save_page($postSlug, $title, $summary, $body)) {
        set_flash('success', "Saved '" . $legal[$postSlug]['title'] . "'.");
    } else {
        set_flash_error('Could not save.', 'The pages table may be missing.', 'Run the database migration, then try again.', 'admin/edit_page.php?slug=' . urlencode($postSlug));
    }
    redirect('admin/edit_page.php?slug=' . urlencode($postSlug));
}

$page = page_content($slug);
$title   = is_array($page) && ($page['title'] ?? '') !== '' ? $page['title'] : $legal[$slug]['title'];
$summary = is_array($page) ? ($page['summary'] ?? '') : (string)($legal[$slug]['summary'] ?? '');
$body    = is_array($page) ? ($page['body'] ?? '') : (string)$legal[$slug]['body'];

$page_title = 'Edit: ' . $legal[$slug]['title'];
require __DIR__ . '/../includes/header.php';
?>

<div class="content-hero">
    <div class="title-back-row">
        <a href="<?php echo base_url('admin/pages.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>Edit: <?php echo e($legal[$slug]['title']); ?></h1>
    </div>
</div>

<div class="admin-page-body">
<div class="admin-legal-edit-card">
    <form method="post" action="" class="admin-legal-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="slug" value="<?php echo e($slug); ?>">

        <div class="form-group">
            <label for="pTitle">Title</label>
            <input type="text" id="pTitle" name="title" value="<?php echo e($title); ?>" maxlength="150" required>
        </div>

        <div class="form-group">
            <label for="pSummary">Summary</label>
            <input type="text" id="pSummary" name="summary" value="<?php echo e($summary); ?>" maxlength="255">
        </div>

        <div class="form-group">
            <label for="pBody">Body (HTML allowed - live preview on the right)</label>
            <div class="editor-split">
                <textarea id="pBody" name="body" rows="24" required spellcheck="false"><?php echo e($body); ?></textarea>
                <div class="editor-preview" id="editorPreview"></div>
            </div>
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary">Save page</button>
            <a class="btn" href="<?php echo e(base_url('admin/pages.php')); ?>">Back to pages list</a>
        </div>
    </form>
    <p class="muted small">Tip: <code>&lt;p&gt;</code>, <code>&lt;h2&gt;</code>, <code>&lt;ul&gt;</code>/<code>&lt;li&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;a&gt;</code> are supported.</p>
</div>
</div>

<script>
(function () {
    var ta = document.getElementById('pBody');
    var pv = document.getElementById('editorPreview');
    if (!ta || !pv) { return; }
    var link = document.querySelector('link[rel="stylesheet"][href*="style.css"]');
    function render() {
        var html = (ta.value || '').replace(/<\/?script[^>]*>/gi, '');
        pv.innerHTML = '<link rel="stylesheet" href="' + (link ? link.href : '') + '">'
            + '<div class="prose">' + html + '</div>';
    }
    render();
    ta.addEventListener('input', render);
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

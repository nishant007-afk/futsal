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
    $body    = $_POST['body'] ?? '';

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
    <h1>Edit: <?php echo e($legal[$slug]['title']); ?></h1>
</div>

<main class="page">
<div class="admin-legal-edit-card">
    <form method="post" action="" class="admin-legal-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="slug" value="<?php echo e($slug); ?>">

        <div class="form-group">
            <label for="pTitle">Title</label>
            <input type="text" id="pTitle" name="title" value="<?php echo e($title); ?>" required>
        </div>

        <div class="form-group">
            <label for="pSummary">Summary</label>
            <input type="text" id="pSummary" name="summary" value="<?php echo e($summary); ?>" maxlength="255">
        </div>

        <div class="form-group">
            <label for="pBody">Body (HTML allowed — live preview on the right)</label>
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
</main>

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

<style>
.admin-legal-list .admin-table .actions { text-align: right; }
.admin-legal-edit-card { max-width: 1000px; margin: 24px 0; }
.admin-legal-form .form-group { margin-bottom: 18px; }
.admin-legal-form label { display:block; font-weight:600; margin-bottom:6px; }
.admin-legal-form input[type="text"] { width: 100%; padding: 10px; border: 1px solid #d0d7de; border-radius: 6px; }
.admin-legal-form textarea { width: 100%; padding: 12px; border: 1px solid #d0d7de; border-radius: 6px; font-family: ui-monospace, monospace; font-size: 13px; line-height: 1.6; background: #fafafa; }
.editor-split { display: flex; flex-direction: row; gap: 14px; align-items: flex-start; }
.editor-split textarea { flex: 1 1 50%; min-height: 520px; resize: horizontal; }
.editor-preview { flex: 1 1 50%; min-width: 0; border: 1px dashed #d0d7de; border-radius: 6px; padding: 16px; background: #fff; overflow: auto; }
.editor-preview .prose h2 { font-size: 1.2em; margin-top: 1em; }
.editor-preview .prose ul, .editor-preview .prose ol { margin: 8px 0 8px 24px; }
.editor-preview .prose li { margin: 3px 0; }
.muted.small { font-size: 12px; }
</style>

<?php require __DIR__ . '/../includes/footer.php'; ?>

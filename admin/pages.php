<?php
require_once __DIR__ . '/../config/db.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$legal = legal_pages_defaults();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $slug    = $_POST['slug'] ?? '';
    $title   = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $body    = $_POST['body'] ?? '';

    if (!isset($legal[$slug])) {
        set_flash_error('Unknown page.', 'The slug you sent was not recognised.', 'Use the editor form.', 'admin/pages.php');
        redirect('admin/pages.php');
    }
    if ($title === '' || $body === '') {
        set_flash_error('Missing fields.', 'The title and body are required.', 'Please fill the form and try again.', 'admin/pages.php');
        redirect('admin/pages.php?edit=' . urlencode($slug));
    }

    if (save_page($slug, $title, $summary, $body)) {
        set_flash('success', "Saved '" . $legal[$slug]['title'] . "'.");
    } else {
        set_flash_error('Could not save.', 'The pages table may be missing.', 'Run the database migration, then try again.', 'admin/pages.php?edit=' . urlencode($slug));
    }
    redirect('admin/pages.php?edit=' . urlencode($slug));
}

$editing = $_GET['edit'] ?? null;
if ($editing !== null && !isset($legal[$editing])) {
    $editing = null;
}
$page_title = 'Edit legal pages';
require __DIR__ . '/../includes/header.php';
?>

<div class="content-hero">
    <div class="title-back-row">
        <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>Legal pages</h1>
    </div>
</div>

<div class="admin-legal-list">
    <h2>Pages</h2>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Slug</th>
                <th>Label</th>
                <th>Status</th>
                <th class="actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($legal as $slug => $default): ?>
                <?php
                $live = page_content($slug);
                $liveAt = is_array($live) ? ($live['updated_at'] ?? '') : '';
                $modified = $liveAt !== '';
                ?>
                <tr>
                    <td><?php echo e($slug); ?></td>
                    <td><?php echo e($default['title']); ?></td>
                    <td><?php echo $modified ? ('Modified ' . e(date('M j, Y', strtotime($liveAt)))) : '<span class="muted">Using defaults</span>'; ?></td>
                    <td class="actions">
                        <a class="btn btn-sm" href="<?php echo e(base_url('admin/edit_page.php?slug=' . urlencode($slug))); ?>">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($editing !== null): ?>
    <?php
    $page = page_content($editing);
    $title   = is_array($page) && ($page['title'] ?? '') !== '' ? $page['title'] : $legal[$editing]['title'];
    $summary = is_array($page) ? ($page['summary'] ?? '') : (string)($legal[$editing]['summary'] ?? '');
    $body    = is_array($page) ? ($page['body'] ?? '') : (string)$legal[$editing]['body'];
    ?>
    <div class="admin-legal-edit-card">
        <h2>Editing: <?php echo e($legal[$editing]['title']); ?></h2>
        <form method="post" action="" class="admin-legal-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="slug" value="<?php echo e($editing); ?>">

            <div class="form-group">
                <label for="pTitle">Title</label>
                <input type="text" id="pTitle" name="title" value="<?php echo e($title); ?>" required>
            </div>

            <div class="form-group">
                <label for="pSummary">Summary</label>
                <input type="text" id="pSummary" name="summary" value="<?php echo e($summary); ?>" maxlength="255">
            </div>

            <div class="form-group">
                <label for="pBody">Body (HTML allowed - live preview below)</label>
                <div class="editor-split">
                    <textarea id="pBody" name="body" rows="24" required spellcheck="false"><?php echo e($body); ?></textarea>
                    <div class="editor-preview" id="editorPreview"></div>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Save page</button>
                <a class="btn" href="<?php echo e(base_url('admin/pages.php')); ?>">Cancel</a>
            </div>
        </form>
    </div>

    <script>
    (function () {
        var ta = document.getElementById('pBody');
        var pv = document.getElementById('editorPreview');
        if (!ta || !pv) { return; }
        // apply the site's prose styling inside the preview
        var link = document.querySelector('link[rel="stylesheet"][href*="style.css"]');
        function render() {
            var html = ta.value;
            // basic safety: refuse to render <script> blocks
            html = html.replace(/<\/?script[^>]*>/gi, '');
            pv.innerHTML = '<link rel="stylesheet" href="' + (link ? link.href : '') + '">'
                + '<div class="prose">' + html + '</div>';
        }
        render();
        ta.addEventListener('input', render);
        // smooth scroll syncing: hover preview to jump to textarea and vice-versa is omitted for simplicity
    })();
    </script>
<?php endif; ?>

<style>
.admin-legal-list h2 { margin-bottom: 18px; }
.admin-legal-list .admin-table { margin-top: 14px; }
.admin-legal-list .admin-table .actions,
.admin-legal-list .admin-table .actions { text-align: right; }
.admin-legal-list .btn-sm { text-decoration: none; }
.admin-legal-list .btn-sm:hover { text-decoration: underline; }
.admin-legal-edit-card { max-width: 980px; margin: 24px 0; }
.admin-legal-form .form-group { margin-bottom: 18px; }
.admin-legal-form label { display:block; font-weight:600; margin-bottom:6px; }
.admin-legal-form input[type="text"] { width: 100%; padding: 10px; border: 1px solid #d0d7de; border-radius: 6px; }
.admin-legal-form textarea { width: 100%; padding: 12px; border: 1px solid #d0d7de; border-radius: 6px; font-family: ui-monospace, monospace; font-size: 13px; line-height: 1.6; background: #fafafa; }
.editor-split { display: flex; flex-direction: row; gap: 16px; align-items: flex-start; }
.editor-split textarea { flex: 1 1 50%; height: 620px; min-height: 0; resize: vertical; }
.editor-preview { flex: 1 1 50%; min-width: 0; border: 1px dashed #d0d7de; border-radius: 6px; padding: 16px; background: #fff; overflow: auto; }
.editor-preview .prose h2 { font-size: 1.2em; margin-top: 1em; }
.editor-preview .prose ul, .editor-preview .prose ol { margin: 8px 0 8px 24px; }
.editor-preview .prose li { margin: 3px 0; }
@media (max-width: 760px) { .editor-split { flex-direction: column; } .editor-split textarea { height: 380px; } }
</style>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/db.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$legal = legal_pages_defaults();
$page_title = 'Legal pages';
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
    <div class="table-wrap">
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
                    <td data-label="Slug"><?php echo e($slug); ?></td>
                    <td data-label="Label"><?php echo e($default['title']); ?></td>
                    <td data-label="Status"><?php echo $modified ? ('Modified ' . e(date('M j, Y', strtotime($liveAt)))) : '<span class="muted">Using defaults</span>'; ?></td>
                    <td class="actions" data-label="">
                        <a class="btn btn-sm" href="<?php echo e(base_url('admin/edit_page.php?slug=' . urlencode($slug))); ?>">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

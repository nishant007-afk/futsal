<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    verify_csrf();
    $id = (int)$_POST['delete_review'];
    $stmt = $conn->prepare('DELETE FROM reviews WHERE id = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        set_flash('success', 'Review deleted successfully.');
    } else {
        set_flash_error('Could not delete review.', 'The review may have already been removed.', 'Refresh to view current reviews.', 'admin/reviews.php');
    }
    redirect('admin/reviews.php');
}

$grounds = $conn->query('SELECT id, name FROM grounds ORDER BY name ASC')->fetch_all(MYSQLI_ASSOC);

$f_ground = (int)($_GET['ground'] ?? 0);
$f_rating = (int)($_GET['rating'] ?? 0);
$f_search = trim((string)($_GET['search'] ?? ''));

$where = ['1=1'];
$params = [];
$types = '';

if ($f_ground > 0) {
    $where[] = 'r.ground_id = ?';
    $params[] = $f_ground;
    $types .= 'i';
}
if ($f_rating >= 1 && $f_rating <= 5) {
    $where[] = 'r.rating = ?';
    $params[] = $f_rating;
    $types .= 'i';
}
if ($f_search !== '') {
    $where[] = '(u.name LIKE ? OR g.name LIKE ? OR r.comment LIKE ?)';
    $sLike = '%' . $f_search . '%';
    $params[] = $sLike;
    $params[] = $sLike;
    $params[] = $sLike;
    $types .= 'sss';
}

$sql = 'SELECT r.id, r.ground_id, r.user_id, r.rating, r.comment, r.created_at,
               g.name AS ground_name, u.name AS user_name, u.email AS user_email
        FROM reviews r
        JOIN grounds g ON g.id = r.ground_id
        JOIN users u ON u.id = r.user_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY r.created_at DESC';

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalReviews = count($reviews);
$avgRating = 0;
if ($totalReviews > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avgRating = round($sum / $totalReviews, 1);
}

$page_title = 'Court Reviews & Ratings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div class="title-back-row">
        <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
        <h1 class="page-title">Court Reviews &amp; Moderation</h1>
    </div>
</div>

<div class="stat-grid tight">
    <div class="stat reveal">
        <div class="stat-icon"><i class="fa-solid fa-comments"></i></div>
        <h3>Total Reviews</h3>
        <p class="stat-amount"><?php echo $totalReviews; ?></p>
        <span class="muted">Across all courts</span>
    </div>
    <div class="stat reveal">
        <div class="stat-icon"><i class="fa-solid fa-star" style="color: #f59e0b;"></i></div>
        <h3>Average Rating</h3>
        <p class="stat-amount"><?php echo $avgRating > 0 ? $avgRating . ' / 5' : 'N/A'; ?></p>
        <span class="muted"><?php echo $totalReviews > 0 ? 'Verified player reviews' : 'No ratings yet'; ?></span>
    </div>
</div>

<div class="courts-toolbar reveal">
    <form method="get" action="<?php echo base_url('admin/reviews.php'); ?>" class="courts-search">
        <div class="toolbar-flex-main">
            <div class="search-field sf-grow">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text" name="search" placeholder="Search player, court, or feedback..." value="<?php echo e($f_search); ?>" autocomplete="off" aria-label="Search reviews">
            </div>
            <div class="search-field">
                <i class="fa-solid fa-store" aria-hidden="true"></i>
                <select name="ground" aria-label="Filter by ground">
                    <option value="0">All courts</option>
                    <?php foreach ($grounds as $g): ?>
                        <option value="<?php echo (int)$g['id']; ?>" <?php echo $f_ground === (int)$g['id'] ? 'selected' : ''; ?>><?php echo e($g['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-field">
                <i class="fa-solid fa-star" aria-hidden="true"></i>
                <select name="rating" aria-label="Filter by rating">
                    <option value="0">All ratings</option>
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                        <option value="<?php echo $star; ?>" <?php echo $f_rating === $star ? 'selected' : ''; ?>><?php echo $star; ?> Star<?php echo $star > 1 ? 's' : ''; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
            <?php if ($f_ground > 0 || $f_rating > 0 || $f_search !== ''): ?>
                <a href="<?php echo base_url('admin/reviews.php'); ?>" class="btn btn-outline btn-sm">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="table-wrap reveal">
    <table class="data-table">
        <thead>
            <tr>
                <th>Court</th>
                <th>Player</th>
                <th>Rating</th>
                <th>Feedback</th>
                <th>Date</th>
                <th style="text-align:right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$reviews): ?>
                <tr>
                    <td colspan="6" class="muted table-empty" style="text-align:center; padding: 24px;">No reviews found matching your filter criteria.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($reviews as $rv): ?>
                    <tr>
                        <td data-label="Court">
                            <strong><a href="<?php echo base_url('pages/ground.php?id=' . (int)$rv['ground_id']); ?>" target="_blank" rel="noopener"><?php echo e($rv['ground_name']); ?></a></strong>
                        </td>
                        <td data-label="Player">
                            <div><?php echo e($rv['user_name']); ?></div>
                            <span class="muted" style="font-size:12px;"><?php echo e($rv['user_email']); ?></span>
                        </td>
                        <td data-label="Rating">
                            <span style="color:#f59e0b; white-space:nowrap;">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <i class="fa-<?php echo $s <= (int)$rv['rating'] ? 'solid' : 'regular'; ?> fa-star"></i>
                                <?php endfor; ?>
                            </span>
                        </td>
                        <td data-label="Feedback" style="max-width:320px;">
                            <?php if (!empty($rv['comment'])): ?>
                                <span style="font-size:13.5px;"><?php echo e($rv['comment']); ?></span>
                            <?php else: ?>
                                <span class="muted" style="font-style:italic;">No written comment</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Date">
                            <?php echo date('M j, Y', strtotime($rv['created_at'])); ?>
                        </td>
                        <td data-label="Action" style="text-align:right;">
                            <form method="post" action="<?php echo base_url('admin/reviews.php'); ?>" style="display:inline;" data-confirm="Are you sure you want to delete this review? This action cannot be undone." data-confirm-title="Delete review" data-confirm-ok="Yes, delete" data-confirm-cancel="Cancel">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="delete_review" value="<?php echo (int)$rv['id']; ?>">
                                <button type="submit" class="btn btn-outline btn-sm" title="Delete Review" style="color:var(--danger); border-color:var(--line);"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

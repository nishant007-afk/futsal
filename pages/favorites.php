<?php
require_once __DIR__ . '/../config/db.php';
require_login();

$user_id = (int)$_SESSION['user_id'];
$page_title = 'Saved Courts';
$page_description = 'Courts you have saved on GoalSpace. Quickly jump back to your favorites and book again.';
require __DIR__ . '/../includes/header.php';

$fav_ids = favorite_ground_ids();
$grounds = [];
if ($fav_ids !== []) {
    $in = implode(',', array_fill(0, count($fav_ids), '?'));
    $types = str_repeat('i', count($fav_ids));
    $sql = 'SELECT g.*, u.name AS owner_name FROM grounds g
            LEFT JOIN users u ON u.id = g.manager_id
            WHERE g.id IN (' . $in . ') ORDER BY g.name ASC';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$fav_ids);
    $stmt->execute();
    $grounds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="title-back-row favorites-title-row">
    <a href="<?php echo base_url('index.php'); ?>" class="nav-back mob-title-back" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="page-title">Saved Courts</h1>
</div>

<div class="container">
    <div class="reveal">

    <?php if (!$grounds): ?>
        <div class="empty reveal">
            <span class="big"><i class="fa-solid fa-heart"></i></span>
            <h3>No saved courts yet</h3>
            <p>Browse courts and tap the heart icon to add your favorites.</p>
            <a href="<?php echo grounds_list_url(); ?>" class="btn btn-outline btn-sm">Browse courts</a>
        </div>
    <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($grounds as $ground) { ground_card_html($ground); } ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
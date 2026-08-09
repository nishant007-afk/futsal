<?php
require_once __DIR__ . '/../config/db.php';

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}
$city = trim($_GET['location'] ?? '');
$allowed_cities = ['Kathmandu', 'Bhaktapur', 'Lalitpur'];
if (!in_array($city, $allowed_cities, true)) {
    $city = '';
}
$date = trim($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = '';
}
$sort = $_GET['sort'] ?? 'price_asc';
if (!in_array($sort, ['price_asc', 'price_desc', 'name_asc'], true)) {
    $sort = 'price_asc';
}

$where = ['g.is_active = 1'];
$params = [];
$types = '';
if ($q !== '') {
    $where[] = '(g.name LIKE ? OR g.location LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
if ($city !== '') {
    $where[] = 'g.location LIKE ?';
    $params[] = '%' . $city . '%';
    $types .= 's';
}

$orderBy = 'g.price_per_hour ASC';
if ($sort === 'price_desc') {
    $orderBy = 'g.price_per_hour DESC';
} elseif ($sort === 'name_asc') {
    $orderBy = 'g.name ASC';
}

$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$sql = 'SELECT g.*, u.name AS owner_name FROM grounds g
        LEFT JOIN users u ON u.id = g.manager_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy . ' LIMIT ? OFFSET ?';
$types .= 'ii';
$params[] = $perPage;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$grounds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$availability = null;
if ($date !== '') {
    $availability = [];
    $gids = array_map(fn($g) => (int)$g['id'], $grounds);
    $takenMap = [];
    $blockedSet = [];
    if ($gids !== []) {
        $gidPlaceholders = implode(',', array_fill(0, count($gids), '?'));
        $takenStmt = $conn->prepare(
            "SELECT ground_id, COUNT(*) c FROM bookings
             WHERE booking_date = ? AND status != 'cancelled' AND ground_id IN ($gidPlaceholders)
             GROUP BY ground_id"
        );
        $takenTypes = 's' . str_repeat('i', count($gids));
        $takenStmt->bind_param($takenTypes, $date, ...$gids);
        $takenStmt->execute();
        $takenRows = $takenStmt->get_result();
        while ($row = $takenRows->fetch_assoc()) {
            $takenMap[(int)$row['ground_id']] = (int)$row['c'];
        }
        $blockedStmt = $conn->prepare(
            "SELECT ground_id FROM blocked_dates WHERE block_date = ? AND ground_id IN ($gidPlaceholders)"
        );
        $blockedTypes = 's' . str_repeat('i', count($gids));
        $blockedStmt->bind_param($blockedTypes, $date, ...$gids);
        $blockedStmt->execute();
        $blockedRows = $blockedStmt->get_result();
        while ($row = $blockedRows->fetch_assoc()) {
            $blockedSet[(int)$row['ground_id']] = true;
        }
    }
    foreach ($grounds as $g) {
        $gTotal = count(slots_for_day($date, (int)$g['id']));
        $free = isset($blockedSet[(int)$g['id']]) ? 0 : max(0, $gTotal - ($takenMap[(int)$g['id']] ?? 0));
        $availability[(int)$g['id']] = ['free' => $free, 'total' => $gTotal];
    }
}

$countSql = 'SELECT COUNT(*) c FROM grounds g WHERE ' . implode(' AND ', $where);
$countTypes = '';
$countParams = [];
if ($q !== '') {
    $countTypes .= 'ss';
    $countParams[] = '%' . $q . '%';
    $countParams[] = '%' . $q . '%';
}
if ($city !== '') {
    $countTypes .= 's';
    $countParams[] = '%' . $city . '%';
}
$cstmt = $conn->prepare($countSql);
if ($countTypes !== '') {
    $cstmt->bind_param($countTypes, ...$countParams);
}
$cstmt->execute();
$total = (int)$cstmt->get_result()->fetch_assoc()['c'];
$totalPages = max(1, (int)ceil($total / $perPage));

function build_query(array $overrides): string
{
    $params = array_merge(
        [
            'q' => trim($_GET['q'] ?? ''),
            'location' => trim($_GET['location'] ?? ''),
            'date' => trim($_GET['date'] ?? ''),
            'sort' => $_GET['sort'] ?? 'price_asc',
        ],
        $overrides
    );
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}

function city_label(string $city): string
{
    return $city === 'Kathmandu' ? 'Kathmandu' : ($city === 'Bhaktapur' ? 'Bhaktapur' : 'Lalitpur');
}

$page_title = 'Browse Courts';
$page_description = 'Browse all futsal courts in Kathmandu, Bhaktapur, and Lalitpur. Compare prices, amenities, and open slots to book your next game on GoalSpace.';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">All Courts</h1>

<div class="container">
    <div class="courts-toolbar reveal">
        <form method="get" action="<?php echo base_url('pages/courts.php'); ?>" class="courts-search">
            <div class="search-field">
                <label for="courtsQ">Search</label>
                <input type="text" id="courtsQ" name="q" placeholder="Court name or location" value="<?php echo e($q); ?>">
            </div>
            <div class="search-field">
                <label for="courtsLocation">Location</label>
                <select id="courtsLocation" name="location">
                    <option value="">All locations</option>
                    <?php foreach ($allowed_cities as $c): ?>
                        <option value="<?php echo e($c); ?>" <?php echo $city === $c ? 'selected' : ''; ?>><?php echo e($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-field">
                <label for="courtsSort">Sort by</label>
                <select id="courtsSort" name="sort">
                    <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: low to high</option>
                    <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: high to low</option>
                    <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                </select>
            </div>
            <div class="search-field">
                <label for="courtsDate">Date</label>
                <input type="date" id="courtsDate" name="date" value="<?php echo e($date); ?>" min="<?php echo e(date('Y-m-d')); ?>">
            </div>
            <div class="toolbar-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Apply</button>
                <?php if ($q !== '' || $city !== '' || $date !== '' || $sort !== 'price_asc'): ?>
                    <a href="<?php echo base_url('pages/courts.php'); ?>" class="btn btn-outline"><i class="fa-solid fa-xmark"></i> Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <p class="courts-count muted">
        <?php echo $total; ?> court<?php echo $total === 1 ? '' : 's'; ?><?php echo $q !== '' ? ' matching "' . e($q) . '"' : ''; ?>
    </p>

    <?php if (!$grounds): ?>
        <div class="empty reveal">
            <span class="big"><i class="fa-solid fa-futbol"></i></span>
            <h3>No courts match your filters</h3>
            <p><a href="<?php echo base_url('pages/courts.php'); ?>" class="btn btn-outline btn-sm">Clear filters &amp; browse all</a></p>
        </div>
    <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($grounds as $ground) { ground_card_html($ground, $availability[(int)$ground['id']] ?? null); } ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Courts pages">
                <?php if ($page > 1): ?>
                    <a class="page-link" href="<?php echo base_url('pages/courts.php?' . build_query(['page' => $page - 1])); ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="page-link <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo base_url('pages/courts.php?' . build_query(['page' => $i])); ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="page-link" href="<?php echo base_url('pages/courts.php?' . build_query(['page' => $page + 1])); ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

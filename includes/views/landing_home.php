<?php
$q = trim($_GET['q'] ?? '');
$date = trim($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = '';
}

$sql = 'SELECT g.*, u.name AS owner_name FROM grounds g
        LEFT JOIN users u ON u.id = g.manager_id
        WHERE g.is_active = 1';
$params = [];
$types = '';
if ($q !== '') {
    $sql .= ' AND (g.name LIKE ? OR g.location LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
$sql .= ' ORDER BY g.id LIMIT 6';

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$featured = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($date !== '') {
    $ids = array_map(fn($g) => (int)$g['id'], $featured);
    $idList = implode(',', $ids ?: [0]);
    $takenRows = $conn->query(
        "SELECT ground_id, COUNT(*) c FROM bookings
         WHERE booking_date = '$date' AND status != 'cancelled' AND ground_id IN ($idList)
         GROUP BY ground_id"
    );
    $takenMap = [];
    while ($row = $takenRows->fetch_assoc()) {
        $takenMap[(int)$row['ground_id']] = (int)$row['c'];
    }
    $totalSlots = count(slots_for_day(date('Y-m-d'), 0));
    $featured = array_values(array_filter($featured, function ($g) use ($takenMap, $totalSlots) {
        $taken = $takenMap[(int)$g['id']] ?? 0;
        return $totalSlots - $taken > 0;
    }));
}
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-media pitch"></div>
    <div class="container">
        <div class="hero-content">
            <span class="hero-tag"><i class="fa-solid fa-bolt"></i> No calls needed</span>
            <h1>Pick a court, grab a slot, <span>play</span></h1>
            <p>Skip the phone calls. See which courts near you are free right now, and book your game in under a minute.</p>
        </div>
    </div>
</section>

<!-- SEARCH -->
<div class="container">
    <div class="search-card reveal">
        <form method="get" action="<?php echo base_url('index.php#grounds'); ?>">
            <div class="search-field">
                <label for="searchQ">Location</label>
                <input type="text" id="searchQ" name="q" placeholder="City or area, e.g. Kathmandu" value="<?php echo e($q); ?>">
            </div>
            <div class="search-field">
                <label for="searchDate">Date</label>
                <input type="date" id="searchDate" name="date" value="<?php echo e($date); ?>" min="<?php echo e(date('Y-m-d')); ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        </form>
    </div>
</div>

<!-- GROUNDS -->
<section class="section" id="grounds">
    <div class="container">
        <div class="section-head reveal">
            <span class="eyebrow"><?php echo $q !== '' || $date !== '' ? 'Search results' : 'Courts near you'; ?></span>
            <h2 class="section-title">
                <?php echo $q !== '' ? 'Grounds in "' . e($q) . '"' : ($date !== '' ? 'Available on ' . e(date('M j, Y', strtotime($date))) : 'Ready to play today'); ?>
            </h2>
            <?php if ($q === '' && $date === ''): ?>
                <p class="section-sub">These courts are taking bookings right now. Open one, pick a free hour and you're set.</p>
            <?php endif; ?>
        </div>

        <?php if (!$featured): ?>
            <div class="empty">
                <span class="big"><i class="fa-solid fa-futbol"></i></span>
                <h3>No grounds match your search</h3>
                <p><a href="<?php echo base_url('index.php#grounds'); ?>" class="btn btn-outline btn-sm">Clear search &amp; browse all</a></p>
            </div>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($featured as $ground) { ground_card_html($ground); } ?>
            </div>
            <div class="section-foot reveal" style="text-align:center;">
                <a href="<?php echo grounds_list_url(); ?>" class="btn btn-outline btn-lg"><i class="fa-solid fa-layer-group"></i> View all courts</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="section section-alt" id="how">
    <div class="container">
        <div class="section-head reveal">
            <span class="eyebrow">How it works</span>
            <h2 class="section-title">Booking a game takes three steps</h2>
        </div>
        <div class="grid grid-3">
            <div class="step reveal">
                <span class="step-num">Step 1</span>
                <div class="step-icon"><i class="fa-solid fa-magnifying-glass-location"></i></div>
                <h3>Find your court</h3>
                <p>Type your area, compare prices and see how many players each court fits.</p>
            </div>
            <div class="step reveal">
                <span class="step-num">Step 2</span>
                <div class="step-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <h3>Pick a slot</h3>
                <p>See the free hours on your chosen day and grab the one that suits your team.</p>
            </div>
            <div class="step reveal">
                <span class="step-num">Step 3</span>
                <div class="step-icon"><i class="fa-solid fa-futbol"></i></div>
                <h3>Show up &amp; play</h3>
                <p>Your slot locks in instantly. Pay a small advance online or settle at the court.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-band">
    <div class="container cta-inner reveal">
        <div>
            <h2>Own a court? Keep your calendar full.</h2>
            <p>Join as a manager and keep tabs on every booking across your courts. Cancel a slot when you can't host, and always know what you've been paid.</p>
        </div>
        <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="btn btn-light btn-lg">Become a Manager</a>
    </div>
</section>

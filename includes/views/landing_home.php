<?php
$grounds = $conn->query('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 ORDER BY g.id LIMIT 6')->fetch_all(MYSQLI_ASSOC);
if (!empty($grounds)) {
    preload_ground_cards(array_column($grounds, 'id'));
}
$featured = $grounds[0] ?? null;
$featuredCover = $featured ? ground_cover((int)$featured['id']) : null;
$featuredRating = $featured ? ground_rating((int)$featured['id']) : ['avg' => 4.9, 'count' => 38];
?>

<section class="hero hero--clean">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-main">
                <h1>Find and book futsal courts.</h1>
                <p class="hero-sub">Real-time availability, direct court pricing, and instant booking confirmation.</p>

                <form action="<?php echo grounds_list_url(); ?>" method="GET" class="hero-search-box" role="search">
                    <div class="hsb-inner">
                        <i class="fa-solid fa-magnifying-glass hsb-icon" aria-hidden="true"></i>
                        <input type="text" name="q" class="hsb-input" placeholder="Search by area or court name..." aria-label="Search courts by location or name" autocomplete="off">
                        <button type="submit" class="btn btn-primary hsb-btn">Search</button>
                    </div>
                </form>

                <div class="hero-areas">
                    <span class="ha-label">Popular areas:</span>
                    <a href="<?php echo grounds_list_url() . '?q=Baneshwor'; ?>" class="ha-link">Baneshwor</a>
                    <span class="ha-sep">&middot;</span>
                    <a href="<?php echo grounds_list_url() . '?q=New+Road'; ?>" class="ha-link">New Road</a>
                    <span class="ha-sep">&middot;</span>
                    <a href="<?php echo grounds_list_url() . '?q=Lalitpur'; ?>" class="ha-link">Lalitpur</a>
                    <span class="ha-sep">&middot;</span>
                    <a href="<?php echo grounds_list_url() . '?q=Kirtipur'; ?>" class="ha-link">Kirtipur</a>
                </div>
            </div>

            <?php if ($featured): ?>
            <div class="hero-showcase">
                <div class="featured-card">
                    <a href="<?php echo base_url('pages/ground.php?id=' . (int)$featured['id']); ?>" class="fc-media" aria-label="View <?php echo e($featured['name']); ?>">
                        <?php if ($featuredCover): ?>
                            <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($featuredCover)); ?>" alt="<?php echo e($featured['name']); ?>" class="fc-img" loading="eager">
                        <?php else: ?>
                            <div class="pitch fc-pitch"></div>
                        <?php endif; ?>
                    </a>
                    <div class="fc-caption">
                        <div class="fc-details">
                            <span class="fc-badge">Featured Venue</span>
                            <h3 class="fc-title">
                                <a href="<?php echo base_url('pages/ground.php?id=' . (int)$featured['id']); ?>"><?php echo e($featured['name']); ?></a>
                            </h3>
                            <span class="fc-location"><i class="fa-solid fa-location-dot"></i> <?php echo e($featured['location']); ?></span>
                        </div>
                        <div class="fc-action-side">
                            <div class="fc-price">
                                <strong>Rs <?php echo number_format((float)$featured['price_per_hour']); ?></strong>
                                <small>/hr</small>
                            </div>
                            <a href="<?php echo base_url('pages/ground.php?id=' . (int)$featured['id']); ?>" class="btn btn-primary btn-sm fc-btn">Book Court</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section section-courts" id="courts">
    <div class="container">
        <div class="section-head section-head-between">
            <div>
                <h2 class="section-title">Available Courts</h2>
                <p class="section-sub">Browse verified futsal arenas open for booking.</p>
            </div>
            <div class="court-area-pills" role="navigation" aria-label="Filter pitches by neighborhood">
                <a href="<?php echo grounds_list_url(); ?>" class="cap-pill active">All Areas</a>
                <a href="<?php echo grounds_list_url() . '?q=New+Road'; ?>" class="cap-pill">New Road</a>
                <a href="<?php echo grounds_list_url() . '?q=Baneshwor'; ?>" class="cap-pill">Baneshwor</a>
                <a href="<?php echo grounds_list_url() . '?q=Lalitpur'; ?>" class="cap-pill">Lalitpur</a>
                <a href="<?php echo grounds_list_url() . '?q=Kirtipur'; ?>" class="cap-pill">Kirtipur</a>
            </div>
        </div>

        <?php if (!$grounds): ?>
            <?php empty_state('fa-solid fa-futbol', 'No grounds available right now', 'Check back soon &middot; courts open for booking will appear here.'); ?>
        <?php else: ?>
            <div class="courts-grid">
                <?php foreach ($grounds as $ground) { ground_card_html($ground); } ?>
            </div>
            <div class="section-foot center">
                <a href="<?php echo grounds_list_url(); ?>" class="btn btn-outline btn-lg">View All Pitches</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
$allGrounds = $conn->query('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 ORDER BY g.id LIMIT 7')->fetch_all(MYSQLI_ASSOC);
if (!empty($allGrounds)) {
    preload_ground_cards(array_column($allGrounds, 'id'));
}
$featured = $allGrounds[0] ?? null;
$featuredCover = $featured ? ground_cover((int)$featured['id']) : null;
$featuredRating = $featured ? ground_rating((int)$featured['id']) : ['avg' => 4.9, 'count' => 38];

// The grid below features the subsequent courts so the featured court is not duplicated
$gridGrounds = count($allGrounds) > 1 ? array_slice($allGrounds, 1, 6) : $allGrounds;
?>

<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "SportsActivityLocation",
    "name": "GoalSpace",
    "description": "Book futsal courts online in Kathmandu, Nepal",
    "url": "<?php echo absolute_url(''); ?>",
    "address": {
        "@type": "PostalAddress",
        "addressLocality": "Kathmandu",
        "addressCountry": "NP"
    },
    "priceRange": "Rs. 1500 - Rs. 2500"
}
</script>

<section class="hero hero--clean">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-main">
                <div class="hero-kicker">
                    <span class="kicker-pill"><i class="fa-solid fa-bolt"></i> Instant Booking</span>
                    <span class="kicker-sub">Kathmandu Valley</span>
                </div>
                <h1>Book your next match in under a minute.</h1>
                <p class="hero-sub">Live slots, instant confirmation, direct pricing.</p>

                <form action="<?php echo grounds_list_url(); ?>" method="GET" class="hero-search-box hero-search-box--multi" role="search">
                    <div class="hsb-inner">
                        <div class="hsb-segment hsb-segment-query">
                            <i class="fa-solid fa-magnifying-glass hsb-icon" aria-hidden="true"></i>
                            <input type="text" name="q" class="hsb-input" placeholder="Search area or court..." aria-label="Search courts by location or name" autocomplete="off">
                        </div>
                        <div class="hsb-divider" aria-hidden="true"></div>
                        <div class="hsb-segment hsb-segment-date">
                            <i class="fa-regular fa-calendar hsb-icon" aria-hidden="true"></i>
                            <input type="date" name="date" class="hsb-input hsb-date-input" aria-label="Select date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary hsb-btn">Find Courts</button>
                    </div>
                </form>
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
                        <span class="fc-badge"><span class="badge-pulse"></span> Featured Arena</span>
                    </a>
                    <div class="fc-caption">
                        <div class="fc-details">
                            <h2 class="fc-title">
                                <a href="<?php echo base_url('pages/ground.php?id=' . (int)$featured['id']); ?>"><?php echo e($featured['name']); ?></a>
                            </h2>
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
            <div class="section-head-copy">
                <h2 class="section-title">Verified Futsal Courts</h2>
                <p class="section-sub">Open courts across Kathmandu Valley.</p>
                <div class="court-area-pills" role="navigation" aria-label="Explore courts by area">
                    <span class="cap-label">Popular areas:</span>
                    <a href="<?php echo grounds_list_url() . '?q=New+Road'; ?>" class="cap-pill">New Road</a>
                    <a href="<?php echo grounds_list_url() . '?q=Baneshwor'; ?>" class="cap-pill">Baneshwor</a>
                    <a href="<?php echo grounds_list_url() . '?q=Lalitpur'; ?>" class="cap-pill">Lalitpur</a>
                    <a href="<?php echo grounds_list_url() . '?q=Kirtipur'; ?>" class="cap-pill">Kirtipur</a>
                </div>
            </div>
        </div>

        <?php if (!$gridGrounds): ?>
            <?php empty_state('fa-solid fa-futbol', 'No grounds available right now', 'Check back soon &middot; courts open for booking will appear here.'); ?>
        <?php else: ?>
            <div class="courts-grid">
                <?php foreach ($gridGrounds as $ground) { ground_card_html($ground); } ?>
            </div>
            <div class="section-foot center">
                <a href="<?php echo grounds_list_url(); ?>" class="btn btn-outline btn-lg">View All Courts <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-steps">
    <div class="container">
        <div class="section-head center">
            <h2 class="section-title">Three steps to kick off</h2>
        </div>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-card-head">
                    <div class="step-icon"><i class="fa-solid fa-magnifying-glass-location"></i></div>
                    <span class="step-badge">01</span>
                </div>
                <h3>Find a court</h3>
                <p>Compare venues, prices, and open hours.</p>
            </div>
            <div class="step-card">
                <div class="step-card-head">
                    <div class="step-icon"><i class="fa-solid fa-calendar-check"></i></div>
                    <span class="step-badge">02</span>
                </div>
                <h3>Pick a slot</h3>
                <p>See live availability and lock your hour.</p>
            </div>
            <div class="step-card">
                <div class="step-card-head">
                    <div class="step-icon"><i class="fa-solid fa-futbol"></i></div>
                    <span class="step-badge">03</span>
                </div>
                <h3>Play</h3>
                <p>Pay by QR or at the counter. Game on.</p>
            </div>
        </div>
    </div>
</section>

<section class="section section-manager-cta">
    <div class="container">
        <div class="manager-cta-banner">
            <div class="mcb-content">
                <span class="mcb-badge"><i class="fa-solid fa-store"></i> For Court Operators</span>
                <h2>Own or manage a futsal arena?</h2>
                <p>Fill empty slots and automate your schedule.</p>
                <div class="mcb-actions">
                    <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="btn btn-primary btn-lg">Register Your Court</a>
                    <a href="<?php echo base_url('pages/page.php?slug=help#for-managers'); ?>" class="btn btn-ghost btn-lg">Learn More</a>
                </div>
            </div>
        </div>
    </div>
</section>

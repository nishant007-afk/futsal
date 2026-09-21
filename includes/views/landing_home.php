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

                <form action="<?php echo grounds_list_url(); ?>" method="GET" class="hero-search-box hero-search-box--multi" role="search">
                    <div class="hsb-inner">
                        <div class="hsb-segment hsb-segment-query">
                            <i class="fa-solid fa-magnifying-glass hsb-icon" aria-hidden="true"></i>
                            <input type="text" name="q" class="hsb-input" placeholder="Search by area or court name..." aria-label="Search courts by location or name" autocomplete="off">
                        </div>
                        <div class="hsb-divider" aria-hidden="true"></div>
                        <div class="hsb-segment hsb-segment-date">
                            <i class="fa-regular fa-calendar hsb-icon" aria-hidden="true"></i>
                            <input type="date" name="date" class="hsb-input hsb-date-input" aria-label="Select date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary hsb-btn">Find Courts</button>
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

<section class="section section-steps">
    <div class="container">
        <div class="section-head center">
            <h2 class="section-title">How GoalSpace Works</h2>
            <p class="section-sub">Book your futsal game in under a minute without phone calls.</p>
        </div>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-num">1</div>
                <div class="step-icon"><i class="fa-solid fa-magnifying-glass-location"></i></div>
                <h3>Find your pitch</h3>
                <p>Browse futsal arenas across Kathmandu, Lalitpur, and Bhaktapur with clear hourly prices and real photos.</p>
            </div>
            <div class="step-card">
                <div class="step-num">2</div>
                <div class="step-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <h3>Choose a slot</h3>
                <p>Check live hourly availability for today or up to 60 days ahead. Pick your team's hour and lock it instantly.</p>
            </div>
            <div class="step-card">
                <div class="step-num">3</div>
                <div class="step-icon"><i class="fa-solid fa-futbol"></i></div>
                <h3>Show up and play</h3>
                <p>Pay advance via QR or pay cash at the court. Your confirmed digital receipt guarantees your game.</p>
            </div>
        </div>
    </div>
</section>

<section class="section section-manager-cta">
    <div class="container">
        <div class="manager-cta-banner">
            <div class="mcb-content">
                <span class="mcb-badge"><i class="fa-solid fa-store"></i> For Court Owners</span>
                <h2>Own or operate a futsal ground?</h2>
                <p>Keep your courts booked and stop answering phone calls. Manage availability, accept advance payments, and grow your revenue on GoalSpace.</p>
                <div class="mcb-actions">
                    <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="btn btn-primary btn-lg">Register Your Court</a>
                    <a href="<?php echo base_url('pages/page.php?slug=help#for-managers'); ?>" class="btn btn-ghost btn-lg">Learn More</a>
                </div>
            </div>
        </div>
    </div>
</section>

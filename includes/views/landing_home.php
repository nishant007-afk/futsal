<?php
$grounds = $conn->query('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 ORDER BY g.id LIMIT 6')->fetch_all(MYSQLI_ASSOC);
$featured = $grounds[0] ?? null;
$featuredCover = $featured ? ground_cover((int)$featured['id']) : null;
$featuredRating = $featured ? ground_rating((int)$featured['id']) : ['avg' => 4.9, 'count' => 38];
?>

<!-- HERO -->
<section class="hero hero--visual">
    <div class="hero-pitch-bg" aria-hidden="true"></div>
    <div class="container">
        <div class="hero-grid">
            <div class="hero-main">
                <span class="hero-eyebrow"><i class="fa-solid fa-bolt"></i> Instant Futsal Booking</span>
                <h1>No calls needed.<br>Book a court in seconds.</h1>
                <p>Find verified pitches across Kathmandu, check real-time open slots, and confirm your match with zero booking fees.</p>
                
                <!-- Quick Search Bar -->
                <form action="<?php echo grounds_list_url(); ?>" method="GET" class="hero-search-box" role="search">
                    <div class="hsb-inner">
                        <i class="fa-solid fa-location-dot hsb-icon" aria-hidden="true"></i>
                        <input type="text" name="q" class="hsb-input" placeholder="Search by area or court name (e.g. New Road, Baneshwor)..." aria-label="Search courts by location or name" autocomplete="off">
                        <button type="submit" class="btn btn-primary hsb-btn"><i class="fa-solid fa-magnifying-glass"></i> Find Courts</button>
                    </div>
                </form>

                <!-- Popular Area Tags -->
                <div class="hero-area-tags">
                    <span class="hat-label">Popular areas:</span>
                    <a href="<?php echo grounds_list_url() . '?q=New+Road'; ?>" class="hat-pill">New Road</a>
                    <a href="<?php echo grounds_list_url() . '?q=Baneshwor'; ?>" class="hat-pill">Baneshwor</a>
                    <a href="<?php echo grounds_list_url() . '?q=Lalitpur'; ?>" class="hat-pill">Lalitpur</a>
                    <a href="<?php echo grounds_list_url() . '?q=Kirtipur'; ?>" class="hat-pill">Kirtipur</a>
                </div>

                <div class="hero-actions">
                    <a href="<?php echo grounds_list_url(); ?>" class="btn btn-primary btn-lg"><i class="fa-solid fa-futbol"></i> Browse All Courts</a>
                    <a href="#how-it-works" class="btn btn-ghost btn-lg"><i class="fa-solid fa-circle-play"></i> How It Works</a>
                </div>
            </div>

            <?php if ($featured): ?>
            <!-- Hero Right Showcase Card -->
            <div class="hero-showcase">
                <div class="hero-preview-card">
                    <div class="hpc-badge-row">
                        <span class="hpc-tag-popular"><i class="fa-solid fa-fire"></i> Featured Venue</span>
                        <span class="hpc-tag-live"><span class="hpc-live-dot"></span> Slots Open Today</span>
                    </div>

                    <a href="<?php echo base_url('pages/ground.php?id=' . (int)$featured['id']); ?>" class="hpc-media" aria-label="View <?php echo e($featured['name']); ?>">
                        <?php if ($featuredCover): ?>
                            <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($featuredCover)); ?>" alt="<?php echo e($featured['name']); ?>" class="hpc-img" loading="eager">
                        <?php else: ?>
                            <div class="pitch hpc-pitch"></div>
                        <?php endif; ?>
                        <span class="hpc-price-badge">Rs <?php echo number_format((float)$featured['price_per_hour']); ?> <em>/ hr</em></span>
                    </a>

                    <div class="hpc-body">
                        <div class="hpc-header">
                            <h3 class="hpc-title">
                                <a href="<?php echo base_url('pages/ground.php?id=' . (int)$featured['id']); ?>"><?php echo e($featured['name']); ?></a>
                            </h3>
                            <div class="hpc-rating" title="<?php echo $featuredRating['count']; ?> player reviews">
                                <i class="fa-solid fa-star"></i>
                                <strong><?php echo $featuredRating['avg'] ? number_format($featuredRating['avg'], 1) : '4.9'; ?></strong>
                                <span>(<?php echo $featuredRating['count'] ?: '24'; ?>)</span>
                            </div>
                        </div>

                        <p class="hpc-location"><i class="fa-solid fa-location-dot"></i> <?php echo e($featured['location']); ?></p>

                        <!-- Live Slot Quick Pick -->
                        <div class="hpc-slots-wrap">
                            <span class="hpc-slots-label"><i class="fa-regular fa-clock"></i> Quick pick an evening slot:</span>
                            <div class="hpc-slots-row">
                                <a href="<?php echo base_url('pages/book.php?id=' . (int)$featured['id'] . '&date=' . date('Y-m-d') . '&time=17:00'); ?>" class="hpc-slot-pill">05:00 PM</a>
                                <a href="<?php echo base_url('pages/book.php?id=' . (int)$featured['id'] . '&date=' . date('Y-m-d') . '&time=18:00'); ?>" class="hpc-slot-pill">06:00 PM</a>
                                <a href="<?php echo base_url('pages/book.php?id=' . (int)$featured['id'] . '&date=' . date('Y-m-d') . '&time=19:00'); ?>" class="hpc-slot-pill active">07:00 PM</a>
                            </div>
                        </div>

                        <div class="hpc-foot">
                            <a href="<?php echo base_url('pages/book.php?id=' . (int)$featured['id']); ?>" class="btn btn-primary hpc-book-btn">
                                <i class="fa-solid fa-calendar-check"></i> Book This Court
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- TRUST STRIP -->
<section class="trust-strip">
    <div class="container">
        <div class="trust-grid">
            <div class="trust-card">
                <div class="tc-icon"><i class="fa-solid fa-bolt"></i></div>
                <div class="tc-text">
                    <strong>Instant Confirmation</strong>
                    <span>No waiting on manager phone calls</span>
                </div>
            </div>
            <div class="trust-card">
                <div class="tc-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="tc-text">
                    <strong>Verified Grounds</strong>
                    <span>Standard turf, floodlights & showers</span>
                </div>
            </div>
            <div class="trust-card">
                <div class="tc-icon"><i class="fa-solid fa-tags"></i></div>
                <div class="tc-text">
                    <strong>Zero Booking Fees</strong>
                    <span>Direct ground pricing with no markup</span>
                </div>
            </div>
            <div class="trust-card">
                <div class="tc-icon"><i class="fa-solid fa-star"></i></div>
                <div class="tc-text">
                    <strong>4.8★ Player Rating</strong>
                    <span>Trusted by futsal squads across the city</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="section section-how" id="how-it-works">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">SIMPLE 3-STEP PROCESS</span>
            <h2 class="section-title">How GoalSpace Works</h2>
            <p class="section-sub">Lock in your game in seconds without endless phone calls or schedule confusion.</p>
        </div>

        <div class="how-grid">
            <div class="how-step-card">
                <div class="hsc-step-num">1</div>
                <div class="hsc-icon"><i class="fa-solid fa-map-location-dot"></i></div>
                <h3>Choose Court & Slot</h3>
                <p>Explore verified futsal arenas across Kathmandu. Filter by area, inspect turf photos, and view real-time open hourly slots.</p>
            </div>

            <div class="how-step-card">
                <div class="hsc-step-num">2</div>
                <div class="hsc-icon"><i class="fa-solid fa-wallet"></i></div>
                <h3>Lock Your Time</h3>
                <p>Reserve your slot instantly. Pay online seamlessly with eSewa or select pay-at-venue with complete price transparency.</p>
            </div>

            <div class="how-step-card">
                <div class="hsc-step-num">3</div>
                <div class="hsc-icon"><i class="fa-solid fa-trophy"></i></div>
                <h3>Show Up & Play</h3>
                <p>Receive instant booking confirmation and a digital pass right on your phone. Arrive at the pitch and hit the ball.</p>
            </div>
        </div>
    </div>
</section>

<!-- COURTS -->
<section class="section" id="courts">
    <div class="container">
        <div class="section-head section-head-between">
            <div>
                <span class="eyebrow">Available Today</span>
                <h2 class="section-title">Pick your court</h2>
                <p class="section-sub">Top-rated futsal arenas open for booking right now in Kathmandu.</p>
            </div>
            <div class="court-area-pills" role="navigation" aria-label="Filter by area">
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
                <a href="<?php echo grounds_list_url(); ?>" class="btn btn-outline btn-lg"><i class="fa-solid fa-layer-group"></i> View all <?php echo count($grounds) >= 6 ? 'courts & arenas' : 'courts'; ?></a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- CTA - For Court Owners / Managers -->
<section class="section-cta">
    <div class="container">
        <div class="cta-grid">
            <div class="cta-text">
                <span class="eyebrow"><i class="fa-solid fa-store"></i> For Court Owners & Managers</span>
                <h2>Run your court without the phone ringing.</h2>
                <p>List your futsal venue on GoalSpace to fill open hours, eliminate double-bookings, and track your revenue in one clear dashboard.</p>
                <ul class="cta-list">
                    <li><i class="fa-solid fa-circle-check"></i> Set custom hourly rates, peak discounts, and operating schedules</li>
                    <li><i class="fa-solid fa-circle-check"></i> Instant slot locking ensures zero double-booking conflicts</li>
                    <li><i class="fa-solid fa-circle-check"></i> Verify player advance payments and run promo codes to fill off-peak slots</li>
                </ul>
                <div class="cta-actions">
                    <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="btn btn-primary btn-lg"><i class="fa-solid fa-store"></i> Become a Manager</a>
                    <a href="<?php echo base_url('pages/login.php'); ?>" class="btn btn-ghost btn-lg"><i class="fa-solid fa-arrow-right-to-bracket"></i> Manager Sign In</a>
                </div>
            </div>
            <div class="cta-visual">
                <div class="cta-stats-grid">
                    <div class="cta-stat-card">
                        <span class="cta-stat-num">2 min</span>
                        <span class="cta-stat-label">Fast Setup</span>
                        <span class="cta-stat-sub">Get your ground live in minutes</span>
                    </div>
                    <div class="cta-stat-card">
                        <span class="cta-stat-num">100%</span>
                        <span class="cta-stat-label">Double-Booking Free</span>
                        <span class="cta-stat-sub">Automated real-time slot locks</span>
                    </div>
                    <div class="cta-stat-card">
                        <span class="cta-stat-num">Rs 0</span>
                        <span class="cta-stat-label">No Hidden Cut</span>
                        <span class="cta-stat-sub">Keep 100% of ground earnings</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

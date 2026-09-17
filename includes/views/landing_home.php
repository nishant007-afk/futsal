<?php
$grounds = $conn->query('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 ORDER BY g.id LIMIT 6')->fetch_all(MYSQLI_ASSOC);
$featured = $grounds[0] ?? null;
$featuredCover = $featured ? ground_cover((int)$featured['id']) : null;
$featuredRating = $featured ? ground_rating((int)$featured['id']) : ['avg' => 4.9, 'count' => 38];
?>

<!-- HERO SECTION: Athletic Editorial -->
<section class="hero hero--visual">
    <div class="hero-pitch-bg" aria-hidden="true"></div>
    <div class="container">
        <div class="hero-grid">
            <div class="hero-main">
                <div class="hero-match-meta">
                    <span class="match-badge">Kathmandu Valley Network</span>
                    <span class="match-status"><span class="live-pulse-dot"></span> Live Pitch Slots</span>
                </div>

                <h1>Direct futsal booking.<br>Zero phone calls.</h1>
                <p>Lock open 60-minute match slots at verified arenas in Kathmandu. Real turf photos, live schedules, and instant entry passes.</p>
                
                <!-- Quick Location Search -->
                <form action="<?php echo grounds_list_url(); ?>" method="GET" class="hero-search-box" role="search">
                    <div class="hsb-inner">
                        <i class="fa-solid fa-location-dot hsb-icon" aria-hidden="true"></i>
                        <input type="text" name="q" class="hsb-input" placeholder="Search area or court name (e.g. New Road, Baneshwor)..." aria-label="Search courts by location or name" autocomplete="off">
                        <button type="submit" class="btn btn-primary hsb-btn">Find Courts</button>
                    </div>
                </form>

                <!-- Popular Area Filters -->
                <div class="hero-area-tags">
                    <span class="hat-label">Popular areas:</span>
                    <a href="<?php echo grounds_list_url() . '?q=New+Road'; ?>" class="hat-pill">New Road</a>
                    <a href="<?php echo grounds_list_url() . '?q=Baneshwor'; ?>" class="hat-pill">Baneshwor</a>
                    <a href="<?php echo grounds_list_url() . '?q=Lalitpur'; ?>" class="hat-pill">Lalitpur</a>
                    <a href="<?php echo grounds_list_url() . '?q=Kirtipur'; ?>" class="hat-pill">Kirtipur</a>
                </div>

                <div class="hero-actions">
                    <a href="<?php echo grounds_list_url(); ?>" class="btn btn-primary btn-lg">Browse All Courts</a>
                    <a href="#how-it-works" class="btn btn-outline btn-lg">How It Works</a>
                </div>
            </div>

            <?php if ($featured): ?>
            <!-- Hero Matchday Fixture Card -->
            <div class="hero-showcase">
                <div class="fixture-card">
                    <div class="fc-top">
                        <div class="fc-venue-info">
                            <span class="fc-kicker">Featured Pitch &middot; <?php echo e($featured['location']); ?></span>
                            <h3 class="fc-title">
                                <a href="<?php echo base_url('pages/ground.php?id=' . (int)$featured['id']); ?>"><?php echo e($featured['name']); ?></a>
                            </h3>
                        </div>
                        <div class="fc-score">
                            <span class="fc-rating-val">★ <?php echo $featuredRating['avg'] ? number_format($featuredRating['avg'], 1) : '4.9'; ?></span>
                            <span class="fc-rating-count"><?php echo $featuredRating['count'] ?: '24'; ?> matches</span>
                        </div>
                    </div>

                    <a href="<?php echo base_url('pages/ground.php?id=' . (int)$featured['id']); ?>" class="fc-media" aria-label="View <?php echo e($featured['name']); ?>">
                        <?php if ($featuredCover): ?>
                            <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($featuredCover)); ?>" alt="<?php echo e($featured['name']); ?>" class="fc-img" loading="eager">
                        <?php else: ?>
                            <div class="pitch fc-pitch"></div>
                        <?php endif; ?>
                        <div class="fc-price-tag">
                            <span>Rs <?php echo number_format((float)$featured['price_per_hour']); ?></span>
                            <small>/ 60 min match</small>
                        </div>
                    </a>

                    <div class="fc-body">
                        <!-- Evening Fixture Slot Selector -->
                        <div class="fc-slots">
                            <div class="fc-slots-header">
                                <span>Tonight's Open Slots</span>
                                <em>10-min reservation hold</em>
                            </div>
                            <div class="fc-slots-grid">
                                <a href="<?php echo base_url('pages/book.php?id=' . (int)$featured['id'] . '&date=' . date('Y-m-d') . '&time=17:00'); ?>" class="fc-slot-btn">17:00</a>
                                <a href="<?php echo base_url('pages/book.php?id=' . (int)$featured['id'] . '&date=' . date('Y-m-d') . '&time=18:00'); ?>" class="fc-slot-btn">18:00</a>
                                <a href="<?php echo base_url('pages/book.php?id=' . (int)$featured['id'] . '&date=' . date('Y-m-d') . '&time=19:00'); ?>" class="fc-slot-btn active">19:00</a>
                            </div>
                        </div>

                        <a href="<?php echo base_url('pages/book.php?id=' . (int)$featured['id']); ?>" class="btn btn-primary fc-action-btn">
                            Book This Match Slot
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- QUALITY STANDARDS STRIP -->
<section class="quality-strip">
    <div class="container">
        <div class="quality-grid">
            <div class="quality-item">
                <span class="qi-num">15+</span>
                <div class="qi-desc">
                    <strong>Verified Arenas</strong>
                    <span>Rubber-infill turf & floodlights</span>
                </div>
            </div>
            <div class="quality-item">
                <span class="qi-num">Rs 0</span>
                <div class="qi-desc">
                    <strong>Direct Court Rates</strong>
                    <span>Zero convenience markup</span>
                </div>
            </div>
            <div class="quality-item">
                <span class="qi-num">100%</span>
                <div class="qi-desc">
                    <strong>Double-Booking Free</strong>
                    <span>Real-time slot lock engine</span>
                </div>
            </div>
            <div class="quality-item">
                <span class="qi-num">1-Tap</span>
                <div class="qi-desc">
                    <strong>Digital Gate Pass</strong>
                    <span>Receipt with entry code</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- MATCHDAY BOOKING FLOW -->
<section class="section section-flow" id="how-it-works">
    <div class="container">
        <div class="section-head center">
            <h2 class="section-title">Matchday Booking Flow</h2>
            <p class="section-sub">Three fast steps from team group chat to kickoff on the pitch.</p>
        </div>

        <div class="flow-track">
            <div class="flow-step">
                <div class="fs-index">01</div>
                <div class="fs-content">
                    <h3>Pitch Discovery</h3>
                    <p>Compare turf quality, player ratings, and live open hourly slots across Kathmandu arenas without making phone calls.</p>
                </div>
            </div>

            <div class="flow-step">
                <div class="fs-index">02</div>
                <div class="fs-content">
                    <h3>Instant Slot Hold</h3>
                    <p>Select your 60-minute time window. The engine places a 10-minute hold so another team cannot grab your slot while you check out.</p>
                </div>
            </div>

            <div class="flow-step">
                <div class="fs-index">03</div>
                <div class="fs-content">
                    <h3>Pass & Kickoff</h3>
                    <p>Confirm via eSewa or pay upon arrival at the venue. Your booking reference and match pass arrive instantly on your screen.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- AVAILABLE COURTS -->
<section class="section" id="courts">
    <div class="container">
        <div class="section-head section-head-between">
            <div>
                <h2 class="section-title">Available Pitches Today</h2>
                <p class="section-sub">Verified futsal arenas with active hourly match slots.</p>
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
                <a href="<?php echo grounds_list_url(); ?>" class="btn btn-outline btn-lg">View All Pitches & Arenas</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- VENUE OPERATORS SECTION -->
<section class="section-cta">
    <div class="container">
        <div class="cta-grid">
            <div class="cta-text">
                <span class="venue-kicker">Venue Operators & Ground Managers</span>
                <h2>Run your arena without the phone ringing.</h2>
                <p>List your futsal courts on GoalSpace to keep slots full, stop double-bookings, and track all bookings in one clean workspace.</p>
                
                <div class="manager-benefits">
                    <div class="mb-item">
                        <strong class="mb-label">Live Schedule Management</strong>
                        <p class="mb-desc">Set custom hourly rates, morning discounts, and maintenance closures without manual logs.</p>
                    </div>
                    <div class="mb-item">
                        <strong class="mb-label">Collision Prevention</strong>
                        <p class="mb-desc">Automated slot holds ensure two squads never arrive for the same hour.</p>
                    </div>
                    <div class="mb-item">
                        <strong class="mb-label">Payment Clarity</strong>
                        <p class="mb-desc">Easily verify online advances, track cash-at-venue payments, and issue player promo codes.</p>
                    </div>
                </div>

                <div class="cta-actions">
                    <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="btn btn-primary btn-lg">Register Your Venue</a>
                    <a href="<?php echo base_url('pages/login.php'); ?>" class="btn btn-ghost btn-lg">Manager Portal Sign In</a>
                </div>
            </div>

            <div class="cta-visual">
                <div class="scoreboard-card">
                    <div class="sb-header">
                        <span class="sb-badge">Arena Metrics</span>
                        <span class="sb-status">GoalSpace Engine</span>
                    </div>
                    <div class="sb-rows">
                        <div class="sb-row">
                            <span class="sb-num">0</span>
                            <div class="sb-info">
                                <strong>Phone calls per booking</strong>
                                <span>Players reserve directly on screen</span>
                            </div>
                        </div>
                        <div class="sb-row">
                            <span class="sb-num">100%</span>
                            <div class="sb-info">
                                <strong>Protection against overlaps</strong>
                                <span>Real-time slot lock prevents double-booking</span>
                            </div>
                        </div>
                        <div class="sb-row">
                            <span class="sb-num">Rs 0</span>
                            <div class="sb-info">
                                <strong>Setup or platform fee</strong>
                                <span>Keep 100% of your court revenue</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

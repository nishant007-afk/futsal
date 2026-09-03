<?php
$grounds = $conn->query('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 ORDER BY g.id LIMIT 6')->fetch_all(MYSQLI_ASSOC);
?>

<!-- HERO - Search-focused, left-aligned -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <span class="hero-eyebrow">Futsal booking, simplified</span>
            <h1>Book a court.<br>Grab your squad.<br><em>Play.</em></h1>
            <p>Skip the phone calls. Find a free court near you and lock in your slot in seconds.</p>
        </div>
        <div class="hero-search-card">
            <form method="get" action="<?php echo grounds_list_url(); ?>" class="hero-search-form">
                <div class="hero-search-row">
                    <div class="hero-search-field">
                        <label for="heroQ">Where</label>
                        <input type="text" id="heroQ" name="q" placeholder="Court name or location">
                    </div>
                    <div class="hero-search-field">
                        <label for="heroDate">When</label>
                        <input type="date" id="heroDate" name="date">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg hero-search-btn">
                        <i class="fa-solid fa-magnifying-glass"></i> Search
                    </button>
                </div>
            </form>
            <div class="hero-popular">
                <span class="hero-popular-label">Popular:</span>
                <a href="<?php echo grounds_list_url(); ?>?q=inside">Indoor</a>
                <a href="<?php echo grounds_list_url(); ?>?q=outside">Outdoor</a>
                <a href="<?php echo grounds_list_url(); ?>?q=night">Night slots</a>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS - Horizontal steps -->
<section class="section section-steps">
    <div class="container">
        <div class="steps-row">
            <div class="step-item">
                <div class="step-num">1</div>
                <div class="step-text">
                    <h3>Find a court</h3>
                    <p>Browse venues near you, compare prices and turf types</p>
                </div>
            </div>
            <div class="step-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="step-item">
                <div class="step-num">2</div>
                <div class="step-text">
                    <h3>Pick your slot</h3>
                    <p>Choose date, time, and duration that works for your squad</p>
                </div>
            </div>
            <div class="step-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="step-item">
                <div class="step-num">3</div>
                <div class="step-text">
                    <h3>Play</h3>
                    <p>Get instant confirmation, show up, and kick off</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- COURTS - Asymmetric grid -->
<section class="section" id="courts">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Available now</span>
            <h2 class="section-title">Pick your court</h2>
        </div>

        <?php if (!$grounds): ?>
            <?php empty_state('fa-solid fa-futbol', 'No grounds available right now', 'Check back soon &middot; courts open for booking will appear here.'); ?>
        <?php else: ?>
            <div class="courts-grid">
                <?php foreach ($grounds as $i => $ground) { ground_card_html($ground, $i === 0 ? 'card-feature' : ''); } ?>
            </div>
            <div class="section-foot" style="text-align:center;">
                <a href="<?php echo grounds_list_url(); ?>" class="btn btn-outline btn-lg"><i class="fa-solid fa-layer-group"></i> View all courts</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- CTA - Left-aligned, editorial -->
<section class="section-cta">
    <div class="container">
        <div class="cta-grid">
            <div class="cta-text">
                <span class="eyebrow"><i class="fa-solid fa-store"></i> For court owners</span>
                <h2>Run your court without the hassle.</h2>
                <p>List your courts, take bookings without phone calls, and always know who has paid.</p>
                <ul class="cta-list">
                    <li><i class="fa-solid fa-circle-check"></i> Set your own hours, prices, and availability</li>
                    <li><i class="fa-solid fa-circle-check"></i> Bookings confirm instantly — no calls, no confusion</li>
                    <li><i class="fa-solid fa-circle-check"></i> Track payments and run promos to fill quiet hours</li>
                </ul>
                <div class="cta-actions">
                    <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="btn btn-primary btn-lg"><i class="fa-solid fa-store"></i> Become a Manager</a>
                    <a href="<?php echo base_url('pages/how_to_use.php#managers'); ?>" class="btn btn-ghost btn-lg"><i class="fa-solid fa-circle-question"></i> See the manager guide</a>
                </div>
            </div>
            <div class="cta-visual">
                <div class="cta-stat-card">
                    <span class="cta-stat-num">2 min</span>
                    <span class="cta-stat-label">Average booking time</span>
                </div>
                <div class="cta-stat-card">
                    <span class="cta-stat-num">100%</span>
                    <span class="cta-stat-label">Instant confirmation</span>
                </div>
                <div class="cta-stat-card">
                    <span class="cta-stat-num">Rs 0</span>
                    <span class="cta-stat-label">Booking fee</span>
                </div>
            </div>
        </div>
    </div>
</section>

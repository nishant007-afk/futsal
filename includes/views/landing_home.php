<?php
$grounds = $conn->query('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 ORDER BY g.id LIMIT 6')->fetch_all(MYSQLI_ASSOC);
?>

<!-- HERO -->
<section class="hero hero--visual">
    <div class="hero-pitch-bg" aria-hidden="true"></div>
    <div class="container">
        <div class="hero-content">
            <span class="hero-eyebrow"><i class="fa-solid fa-bolt"></i> Futsal booking, simplified</span>
            <h1>No calls needed.<br>Book a court in seconds.</h1>
            <p>Skip the phone calls. Find a free court near you and lock in your slot instantly.</p>
            <div class="hero-actions">
                <a href="<?php echo grounds_list_url(); ?>" class="btn btn-primary btn-lg"><i class="fa-solid fa-futbol"></i> Browse All Courts</a>
                <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="btn btn-ghost btn-lg"><i class="fa-solid fa-store"></i> List Your Court</a>
            </div>
        </div>
    </div>
</section>

<!-- TRUST STRIP -->
<section class="trust-strip">
    <div class="container">
        <div class="trust-inner">
            <span class="trust-check"><i class="fa-solid fa-circle-check"></i> <strong>Instant booking</strong></span>
            <span class="trust-check"><i class="fa-solid fa-circle-check"></i> <strong>Verified courts</strong></span>
            <span class="trust-check"><i class="fa-solid fa-circle-check"></i> <strong>Zero booking fees</strong></span>
        </div>
    </div>
</section>

<!-- COURTS - Asymmetric grid -->
<section class="section" id="courts">
    <div class="container">
        <div class="section-head eyebrowless">
            <span class="eyebrow">Available now</span>
            <h2 class="section-title">Pick your court</h2>
        </div>

        <?php if (!$grounds): ?>
            <?php empty_state('fa-solid fa-futbol', 'No grounds available right now', 'Check back soon &middot; courts open for booking will appear here.'); ?>
        <?php else: ?>
            <div class="courts-grid">
                <?php foreach ($grounds as $ground) { ground_card_html($ground); } ?>
            </div>
            <div class="section-foot center">
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
                    <li><i class="fa-solid fa-circle-check"></i> Bookings confirm instantly - no calls, no confusion</li>
                    <li><i class="fa-solid fa-circle-check"></i> Track payments and run promos to fill quiet hours</li>
                </ul>
                <div class="cta-actions">
                    <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="btn btn-primary btn-lg"><i class="fa-solid fa-store"></i> Become a Manager</a>
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

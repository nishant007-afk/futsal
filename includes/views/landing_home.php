<?php
$grounds = $conn->query('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 ORDER BY g.id LIMIT 6')->fetch_all(MYSQLI_ASSOC);
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-media pitch"></div>
    <div class="hero-grain" aria-hidden="true"></div>
    <div class="container">
        <div class="hero-content">
            <div class="hero-badge"><i class="fa-solid fa-futbol"></i> Futsal booking, simplified</div>
            <h1>Book a court.<br>Grab your squad.<br><span>Play.</span></h1>
            <p>Skip the phone calls. Find a free court near you and lock in your slot in seconds.</p>
            <div class="hero-actions">
                <a href="#courts" class="btn btn-primary btn-lg"><i class="fa-solid fa-magnifying-glass"></i> Find a Court</a>
                <a href="<?php echo base_url('pages/register.php'); ?>" class="btn btn-outline-light btn-lg"><i class="fa-solid fa-arrow-right"></i> Sign up free</a>
            </div>
        </div>
        <div class="hero-stats-panel">
            <div class="hero-stat-card">
                <span class="hero-stat-num">2 min</span>
                <span class="hero-stat-label">Average booking time</span>
            </div>
            <div class="hero-stat-card">
                <span class="hero-stat-num">100%</span>
                <span class="hero-stat-label">Instant confirmation</span>
            </div>
            <div class="hero-stat-card">
                <span class="hero-stat-num">Rs 0</span>
                <span class="hero-stat-label">Booking fee</span>
            </div>
        </div>
    </div>
</section>

<!-- COURTS -->
<section class="section" id="courts">
    <div class="container">
        <div class="section-head reveal">
            <span class="eyebrow">Available now</span>
            <h2 class="section-title">Pick your court</h2>
        </div>

        <?php if (!$grounds): ?>
            <?php empty_state('fa-solid fa-futbol', 'No grounds available right now', 'Check back soon &middot; courts open for booking will appear here.'); ?>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($grounds as $ground) { ground_card_html($ground); } ?>
            </div>
            <div class="section-foot reveal" style="text-align:center;">
                <a href="<?php echo grounds_list_url(); ?>" class="btn btn-outline btn-lg"><i class="fa-solid fa-layer-group"></i> View all courts</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- CTA -->
<section class="cta-band" id="become-manager">
    <div class="cta-pitch pitch" aria-hidden="true"></div>
    <div class="container cta-inner reveal">
        <div class="cta-main">
            <span class="cta-eyebrow"><i class="fa-solid fa-store"></i> For court owners</span>
            <h2>Run your court<br><span>without the hassle</span>.</h2>
            <p>List your courts, take bookings without phone calls, and always know who has paid.</p>
            <ul class="cta-benefits">
                <li><i class="fa-solid fa-circle-check"></i> Set your own hours, prices, and availability</li>
                <li><i class="fa-solid fa-circle-check"></i> Bookings confirm instantly — no calls, no confusion</li>
                <li><i class="fa-solid fa-circle-check"></i> Track payments and run promos to fill quiet hours</li>
            </ul>
            <div class="cta-actions">
                <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="btn btn-light btn-lg"><i class="fa-solid fa-store"></i> Become a Manager</a>
                <a href="<?php echo base_url('pages/how_to_use.php#managers'); ?>" class="btn cta-ghost btn-lg"><i class="fa-solid fa-circle-question"></i> See the manager guide</a>
            </div>
        </div>
    </div>
</section>

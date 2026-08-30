<?php
$grounds = $conn->query('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 ORDER BY g.id LIMIT 6')->fetch_all(MYSQLI_ASSOC);
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-media pitch"></div>
    <div class="container">
        <div class="hero-content">
            <h1>Pick a court, grab a slot, <span>play</span></h1>
            <p>Find a free court near you and book your game in under a minute.</p>
            <div class="hero-actions">
                <a href="#courts" class="btn btn-primary btn-lg"><i class="fa-solid fa-futbol"></i> Browse Courts</a>
                <a href="#how" class="btn btn-light btn-lg"><i class="fa-solid fa-circle-question"></i> How It Works</a>
            </div>
            <div class="hero-trust">
                <span><i class="fa-solid fa-circle-check"></i> Instant booking</span>
                <span><i class="fa-solid fa-circle-check"></i> No phone calls</span>
                <span><i class="fa-solid fa-circle-check"></i> Pay online</span>
                <span><i class="fa-solid fa-circle-check"></i> Free to browse</span>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="section section-alt" id="how">
    <div class="container">
        <div class="section-head reveal">
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

<!-- COURTS -->
<section class="section" id="courts">
    <div class="container">
        <div class="section-head reveal">
            <span class="eyebrow">Courts near you</span>
            <h2 class="section-title">Ready to play today</h2>
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
            <h2>Own a court? Keep your <span>calendar full</span>.</h2>
            <p>Join as a manager to list your courts, take bookings without phone calls, and always know who has paid.</p>
            <ul class="cta-benefits">
                <li><i class="fa-solid fa-circle-check"></i> List your court and set your own hours and price</li>
                <li><i class="fa-solid fa-circle-check"></i> Bookings confirm instantly, no calls needed</li>
                <li><i class="fa-solid fa-circle-check"></i> Track who has paid and run promos to fill quiet hours</li>
            </ul>
            <div class="cta-actions">
                <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="btn btn-light btn-lg"><i class="fa-solid fa-store"></i> Become a Manager</a>
                <a href="<?php echo base_url('pages/how_to_use.php#managers'); ?>" class="btn cta-ghost btn-lg"><i class="fa-solid fa-circle-question"></i> See the manager guide</a>
            </div>
        </div>
    </div>
</section>

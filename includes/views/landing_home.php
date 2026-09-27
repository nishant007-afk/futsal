<?php
$allGrounds = $conn->query('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 ORDER BY g.id LIMIT 6')->fetch_all(MYSQLI_ASSOC);
if (!empty($allGrounds)) {
    preload_ground_cards(array_column($allGrounds, 'id'));
}
$gridGrounds = $allGrounds;
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

<section class="hero hero--tubik">
    <div class="tubik-backdrop">
        <img src="<?php echo base_url('assets/img/hero-ronaldo-strike.jpg?v=' . filemtime(__DIR__ . '/../../assets/img/hero-ronaldo-strike.jpg')); ?>" alt="Cristiano Ronaldo iconic power strike stylized illustration" class="tubik-backdrop-img" width="1376" height="768" loading="eager">
    </div>
    <div class="container hero-tubik-grid">
        <div class="hero-left hero-tubik-left">
            <h1 class="tubik-title">Book verified courts instantly</h1>
            <div class="tubik-actions">
                <a href="<?php echo grounds_list_url(); ?>" class="btn tubik-btn-primary">
                    <span>Explore Courts</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
                <a href="#courts" class="btn tubik-btn-ghost">
                    <span>View Today's Arenas</span>
                </a>
            </div>
        </div>
    </div>
</section>

<section class="section section-courts" id="courts">
    <div class="container">
        <div class="section-head section-head-left">
            <div class="section-head-copy">
                <h2 class="section-title">Verified Futsal Courts</h2>
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

<div class="container">
    <hr class="section-divider">
</div>

<section class="section section-steps">
    <div class="container">
        <div class="section-head section-head-left">
            <h2 class="section-title">How GoalSpace Works</h2>
        </div>
        <div class="steps-visual">
            <div class="step-card">
                <div class="step-card-img">
                    <img src="<?php echo base_url('assets/img/steps/step-1-find.png'); ?>" alt="Browse futsal courts on GoalSpace" loading="lazy" decoding="async">
                </div>
                <div class="step-card-body">
                    <span class="step-badge">Step 1</span>
                    <h3>Find your court</h3>
                    <p>Browse courts by city, compare turf specs, amenities, ratings, and transparent hourly rates.</p>
                </div>
            </div>
            <div class="step-connector" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="step-card">
                <div class="step-card-img">
                    <img src="<?php echo base_url('assets/img/steps/step-2-slot.png'); ?>" alt="Pick a time slot on GoalSpace" loading="lazy" decoding="async">
                </div>
                <div class="step-card-body">
                    <span class="step-badge">Step 2</span>
                    <h3>Pick an open slot</h3>
                    <p>Live schedule synced with the arena calendar. Pick your kickoff time - zero double bookings.</p>
                </div>
            </div>
            <div class="step-connector" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="step-card">
                <div class="step-card-img">
                    <img src="<?php echo base_url('assets/img/steps/step-3-pass.png'); ?>" alt="Digital match pass on GoalSpace" loading="lazy" decoding="async">
                </div>
                <div class="step-card-body">
                    <span class="step-badge">Step 3</span>
                    <h3>Show up and play</h3>
                    <p>Get your digital match pass instantly. Pay advance online or settle at the venue.</p>
                </div>
            </div>
        </div>
    </div>
</section>

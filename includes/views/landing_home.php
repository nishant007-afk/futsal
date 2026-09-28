<?php
$allGrounds = $conn->query('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 ORDER BY g.id LIMIT 9')->fetch_all(MYSQLI_ASSOC);
if (!empty($allGrounds)) {
    preload_ground_cards(array_column($allGrounds, 'id'));
}
$gridGrounds = $allGrounds;

// Compute city counts for quick filter pills
$cityCounts = ['all' => count($gridGrounds), 'Kathmandu' => 0, 'Lalitpur' => 0, 'Bhaktapur' => 0];
foreach ($gridGrounds as $g) {
    $loc = (string)($g['location'] ?? '');
    if (stripos($loc, 'Lalitpur') !== false || stripos($loc, 'Patan') !== false || stripos($loc, 'Jawalakhel') !== false || stripos($loc, 'Balkumari') !== false) {
        $cityCounts['Lalitpur']++;
    } elseif (stripos($loc, 'Bhaktapur') !== false) {
        $cityCounts['Bhaktapur']++;
    } else {
        $cityCounts['Kathmandu']++;
    }
}
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
        <img src="<?php echo base_url('assets/img/hero-futsal-cartoon.jpg?v=' . filemtime(__DIR__ . '/../../assets/img/hero-futsal-cartoon.jpg')); ?>" alt="Dynamic futsal power strike cartoon illustration" class="tubik-backdrop-img" width="1920" height="1080" loading="eager">
    </div>
    <div class="container hero-tubik-grid">
        <div class="hero-left hero-tubik-left">
            <h1 class="tubik-title">Book verified courts instantly</h1>
            <p class="hero-subtext">Live calendar synced directly with arena reception. Instant confirmation with zero double bookings.</p>
            
            <!-- Inline Quick-Search Booking Widget -->
            <form action="<?php echo grounds_list_url(); ?>" method="get" class="hero-quick-search" role="search" aria-label="Quick Court Search">
                <div class="hqs-field hqs-field-loc">
                    <label for="hqsLocation" class="hqs-label"><i class="fa-solid fa-location-dot"></i> Area / City</label>
                    <div class="hqs-control">
                        <select id="hqsLocation" name="location" class="hqs-select">
                            <option value="">All Locations</option>
                            <option value="Kathmandu">Kathmandu</option>
                            <option value="Lalitpur">Lalitpur</option>
                            <option value="Bhaktapur">Bhaktapur</option>
                        </select>
                    </div>
                </div>
                <div class="hqs-divider" aria-hidden="true"></div>
                <div class="hqs-field hqs-field-date">
                    <label for="hqsDate" class="hqs-label"><i class="fa-regular fa-calendar"></i> Date</label>
                    <div class="hqs-control">
                        <input type="date" id="hqsDate" name="date" class="hqs-input" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                    </div>
                </div>
                <div class="hqs-divider" aria-hidden="true"></div>
                <div class="hqs-field hqs-field-slot">
                    <label for="hqsSlot" class="hqs-label"><i class="fa-regular fa-clock"></i> Time Slot</label>
                    <div class="hqs-control">
                        <select id="hqsSlot" name="slot" class="hqs-select">
                            <option value="">Any Time Slot</option>
                            <option value="morning">Morning (06:00 - 12:00)</option>
                            <option value="afternoon">Afternoon (12:00 - 17:00)</option>
                            <option value="evening" selected>Prime Evening (17:00 - 22:00)</option>
                            <option value="night">Late Night (22:00+)</option>
                        </select>
                    </div>
                </div>
                <div class="hqs-submit">
                    <button type="submit" class="btn btn-primary hqs-btn hqs-btn-icon" aria-label="Search Courts" title="Search Courts">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<section class="section section-courts" id="courts">
    <div class="container">
        <div class="section-head section-head-between courts-header-row">
            <div class="section-head-copy">
                <h2 class="section-title">Verified Futsal Courts</h2>
                <p class="section-sub">Certified turf surfaces, clean amenities, and transparent hourly rates.</p>
            </div>
            <!-- Quick Location Filter Controls -->
            <div class="court-location-pills" role="tablist" aria-label="Filter courts by location">
                <button type="button" class="loc-pill active" data-city="all" role="tab" aria-selected="true">
                    <span>All</span>
                    <span class="loc-pill-count"><?php echo $cityCounts['all']; ?></span>
                </button>
                <button type="button" class="loc-pill" data-city="Kathmandu" role="tab" aria-selected="false">
                    <span>Kathmandu</span>
                    <span class="loc-pill-count"><?php echo $cityCounts['Kathmandu']; ?></span>
                </button>
                <button type="button" class="loc-pill" data-city="Lalitpur" role="tab" aria-selected="false">
                    <span>Lalitpur</span>
                    <span class="loc-pill-count"><?php echo $cityCounts['Lalitpur']; ?></span>
                </button>
                <?php if ($cityCounts['Bhaktapur'] > 0): ?>
                    <button type="button" class="loc-pill" data-city="Bhaktapur" role="tab" aria-selected="false">
                        <span>Bhaktapur</span>
                        <span class="loc-pill-count"><?php echo $cityCounts['Bhaktapur']; ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$gridGrounds): ?>
            <?php empty_state('fa-solid fa-futbol', 'No grounds available right now', 'Check back soon &middot; courts open for booking will appear here.'); ?>
        <?php else: ?>
            <div class="courts-grid" id="landingCourtsGrid">
                <?php foreach ($gridGrounds as $ground) { ground_card_html($ground); } ?>
            </div>
            <div class="courts-filter-empty" id="courtsFilterEmpty" style="display:none;">
                <div class="empty-icon"><i class="fa-solid fa-futbol"></i></div>
                <p>No courts found in this city for the preview grid.</p>
                <a href="<?php echo grounds_list_url(); ?>" class="btn btn-primary btn-sm">Explore All in Directory</a>
            </div>
            <div class="section-foot center">
                <a href="<?php echo grounds_list_url(); ?>" class="btn btn-outline btn-lg">View All Courts in Directory <i class="fa-solid fa-arrow-right"></i></a>
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
            <p class="section-sub">Reserve your kickoff in 3 simple steps with guaranteed pitch availability.</p>
        </div>
        <div class="steps-visual">
            <div class="step-card">
                <div class="step-card-img step-img-zoom-find">
                    <img src="<?php echo base_url('assets/img/steps/step-1-find.png'); ?>" alt="Browse futsal courts on GoalSpace" loading="lazy" decoding="async">
                    <div class="step-img-caption"><i class="fa-solid fa-filter"></i> Search & Turf Filters</div>
                </div>
                <div class="step-card-body">
                    <span class="step-badge"><i class="fa-solid fa-magnifying-glass-location"></i> Step 1</span>
                    <h3>Find your court</h3>
                    <p>Compare turf specifications (Rubberized, Parquet, FIFA Turf), venue amenities, and verified player ratings.</p>
                </div>
            </div>
            <div class="step-connector" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="step-card">
                <div class="step-card-img step-img-zoom-slot">
                    <img src="<?php echo base_url('assets/img/steps/step-2-slot.png'); ?>" alt="Pick a time slot on GoalSpace" loading="lazy" decoding="async">
                    <div class="step-img-caption"><i class="fa-solid fa-calendar-check"></i> Real-time Slot Picker</div>
                </div>
                <div class="step-card-body">
                    <span class="step-badge"><i class="fa-solid fa-calendar-days"></i> Step 2</span>
                    <h3>Pick an open slot</h3>
                    <p>Live schedule directly synced with the venue calendar. Select your prime kickoff slot with zero double bookings.</p>
                </div>
            </div>
            <div class="step-connector" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="step-card">
                <div class="step-card-img step-img-zoom-pass">
                    <img src="<?php echo base_url('assets/img/steps/step-3-pass.png'); ?>" alt="Digital match pass on GoalSpace" loading="lazy" decoding="async">
                    <div class="step-img-caption"><i class="fa-solid fa-qrcode"></i> Official Digital Match Pass</div>
                </div>
                <div class="step-card-body">
                    <span class="step-badge"><i class="fa-solid fa-ticket"></i> Step 3</span>
                    <h3>Show up and play</h3>
                    <p>Get your QR match pass instantly with guaranteed pitch access. Enjoy complete flexibility with digital or cash payment.</p>
                    
                    <!-- Neat horizontal payment badges -->
                    <div class="step-payment-badges">
                        <span class="spo-pill spo-digital" title="Instant digital confirmation">
                            <i class="fa-solid fa-bolt"></i> eSewa / Khalti / Fonepay
                        </span>
                        <span class="spo-pill spo-cash" title="Settle on arrival at venue reception">
                            <i class="fa-solid fa-hand-holding-dollar"></i> Pay at Venue (Cash)
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Interactive Quick Location Filter for Courts Grid
document.addEventListener('DOMContentLoaded', function() {
    var pills = document.querySelectorAll('.court-location-pills .loc-pill');
    var cards = document.querySelectorAll('#landingCourtsGrid .card');
    var emptyMsg = document.getElementById('courtsFilterEmpty');

    pills.forEach(function(pill) {
        pill.addEventListener('click', function() {
            pills.forEach(function(p) {
                p.classList.remove('active');
                p.setAttribute('aria-selected', 'false');
            });
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');

            var filter = this.getAttribute('data-city');
            var visibleCount = 0;

            cards.forEach(function(card) {
                var cardCity = card.getAttribute('data-city') || '';
                if (filter === 'all' || cardCity.toLowerCase() === filter.toLowerCase()) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (emptyMsg) {
                emptyMsg.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        });
    });
});
</script>

<?php
$allGrounds = $conn->query('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 ORDER BY g.id LIMIT 9')->fetch_all(MYSQLI_ASSOC);
if (!empty($allGrounds)) {
    preload_ground_cards(array_column($allGrounds, 'id'));
}
$gridGrounds = $allGrounds;

// Area counts across every active ground (feeds the hero location select)
$areaCounts = ['Kathmandu' => 0, 'Lalitpur' => 0, 'Bhaktapur' => 0];
$activeRows = $conn->query('SELECT location FROM grounds WHERE is_active = 1')->fetch_all(MYSQLI_ASSOC);
foreach ($activeRows as $r) {
    $loc = (string)($r['location'] ?? '');
    if (stripos($loc, 'Lalitpur') !== false || stripos($loc, 'Patan') !== false || stripos($loc, 'Jawalakhel') !== false || stripos($loc, 'Balkumari') !== false) {
        $areaCounts['Lalitpur']++;
    } elseif (stripos($loc, 'Bhaktapur') !== false) {
        $areaCounts['Bhaktapur']++;
    } else {
        $areaCounts['Kathmandu']++;
    }
}

// How many courts still have at least one free slot today, and which slot
// periods are actually bookable (a court that closes at 22:00 has no night slots)
$periodFree = ['morning' => false, 'afternoon' => false, 'evening' => false, 'night' => false];
$periodWindows = ['morning' => [360, 720], 'afternoon' => [720, 1020], 'evening' => [1020, 1320], 'night' => [1320, 1800]];
$today = date('Y-m-d');
$todayGrounds = $conn->query('SELECT id, open_time, close_time, slot_interval FROM grounds WHERE is_active = 1')->fetch_all(MYSQLI_ASSOC);
if ($todayGrounds) {
    $tids = array_map(fn($g) => (int)$g['id'], $todayGrounds);
    $ph = implode(',', array_fill(0, count($tids), '?'));
    $taken = [];
    $bs = $conn->prepare("SELECT ground_id, start_time FROM bookings WHERE booking_date = ? AND status != 'cancelled' AND ground_id IN ($ph)");
    $bs->bind_param('s' . str_repeat('i', count($tids)), $today, ...$tids);
    $bs->execute();
    while ($row = $bs->get_result()->fetch_assoc()) {
        $taken[(int)$row['ground_id']][(string)$row['start_time']] = true;
    }
    $blocked = [];
    $bds = $conn->prepare("SELECT ground_id FROM blocked_dates WHERE block_date = ? AND ground_id IN ($ph)");
    $bds->bind_param('s' . str_repeat('i', count($tids)), $today, ...$tids);
    $bds->execute();
    while ($row = $bds->get_result()->fetch_assoc()) {
        $blocked[(int)$row['ground_id']] = true;
    }
    foreach ($todayGrounds as $tg) {
        if (isset($blocked[(int)$tg['id']])) {
            continue;
        }
        $gid = (int)$tg['id'];
        $open = (int)substr($tg['open_time'], 0, 2) * 60 + (int)substr($tg['open_time'], 3, 2);
        $close = (int)substr($tg['close_time'], 0, 2) * 60 + (int)substr($tg['close_time'], 3, 2);
        $interval = max(15, (int)$tg['slot_interval']);
        for ($t = $open; $t + $interval <= $close; $t += $interval) {
            $startKey = sprintf('%02d:%02d:00', intdiv($t, 60), $t % 60);
            if (isset($taken[$gid][$startKey])) {
                continue;
            }
            foreach ($periodWindows as $pName => $win) {
                if ($t >= $win[0] && $t < $win[1]) {
                    $periodFree[$pName] = true;
                }
            }
        }
    }
}

// Default slot = the period containing the next upcoming hour, but only when
// venues can actually be booked in it; otherwise fall back to "Any time".
$nowMin = (int)date('H') * 60 + (int)date('i');
$ceilMin = (int)(ceil(($nowMin + 1) / 60) * 60);
if ($ceilMin >= 360 && $ceilMin < 720) {
    $candidateSlot = 'morning';
} elseif ($ceilMin >= 720 && $ceilMin < 1020) {
    $candidateSlot = 'afternoon';
} elseif ($ceilMin >= 1020 && $ceilMin < 1320) {
    $candidateSlot = 'evening';
} else {
    $candidateSlot = 'night';
}
$defaultSlot = $periodFree[$candidateSlot] ? $candidateSlot : '';

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
        <picture>
            <source type="image/webp" srcset="<?php echo base_url('assets/img/hero-futsal-arena-640.webp'); ?> 640w, <?php echo base_url('assets/img/hero-futsal-arena-960.webp'); ?> 960w, <?php echo base_url('assets/img/hero-futsal-arena-1376.webp'); ?> 1376w" sizes="100vw">
            <img src="<?php echo base_url('assets/img/hero-futsal-arena.jpg'); ?>" alt="Floodlit futsal arena with players mid-match" class="tubik-backdrop-img" width="1376" height="768" fetchpriority="high">
        </picture>
    </div>
    <div class="container hero-tubik-grid">
        <div class="hero-left hero-tubik-left">
            <h1 class="tubik-title">Book verified futsal courts instantly</h1>
            <p class="hero-subtext">Live availability from real arenas. Instant confirmation, zero double bookings.</p>

            <!-- Inline Quick-Search Booking Widget -->
            <form action="<?php echo grounds_list_url(); ?>" method="get" class="hero-quick-search" id="heroSearchForm" role="search" aria-label="Quick Court Search">
                <div class="hqs-field hqs-field-loc">
                    <label for="hqsLocation" class="hqs-label"><i class="fa-solid fa-location-dot"></i> Area / City</label>
                    <div class="hqs-control">
                        <select id="hqsLocation" name="location" class="hqs-select">
                            <option value="">All Locations</option>
                            <?php foreach ($areaCounts as $areaName => $areaN): ?>
                                <option value="<?php echo e($areaName); ?>"><?php echo e($areaName); ?> (<?php echo (int)$areaN; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="hqs-field hqs-field-date">
                    <label for="hqsDate" class="hqs-label"><i class="fa-regular fa-calendar"></i> Date</label>
                    <div class="hqs-control">
                        <input type="text" id="hqsDate" name="date" class="hqs-input" inputmode="numeric" autocomplete="off" placeholder="dd/mm/yyyy" maxlength="10" value="<?php echo date('d/m/Y'); ?>" data-min="<?php echo date('Y-m-d'); ?>" data-max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                    </div>
                </div>
                <div class="hqs-field hqs-field-slot">
                    <label for="hqsSlot" class="hqs-label"><i class="fa-regular fa-clock"></i> Time Slot</label>
                    <div class="hqs-control">
                        <select id="hqsSlot" name="slot" class="hqs-select">
                            <option value="">Any time</option>
                            <optgroup label="06:00 - 12:00">
                                <option value="morning" <?php echo $defaultSlot === 'morning' ? 'selected' : ''; ?>>Morning</option>
                            </optgroup>
                            <optgroup label="12:00 - 17:00">
                                <option value="afternoon" <?php echo $defaultSlot === 'afternoon' ? 'selected' : ''; ?>>Afternoon</option>
                            </optgroup>
                            <optgroup label="17:00 - 22:00">
                                <option value="evening" <?php echo $defaultSlot === 'evening' ? 'selected' : ''; ?>>Prime Evening</option>
                            </optgroup>
                            <optgroup label="22:00 - 06:00">
                                <option value="night" <?php echo $defaultSlot === 'night' ? 'selected' : ''; ?>>Late Night</option>
                            </optgroup>
                        </select>
                    </div>
                </div>
                <div class="hqs-submit">
                    <button type="submit" class="btn btn-primary hqs-btn">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span class="hqs-btn-label">Find Courts</span>
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
            <p class="section-sub">Reserve your kickoff in 3 simple steps with guaranteed pitch availability.</p>
        </div>
        <div class="steps-visual">
            <div class="step-card">
                <div class="step-card-img step-img-zoom-find">
                    <img src="<?php echo base_url('assets/img/steps/step-1-find.png'); ?>" alt="Browse futsal courts on GoalSpace" loading="lazy" decoding="async">
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
                </div>
                <div class="step-card-body">
                    <span class="step-badge"><i class="fa-solid fa-ticket"></i> Step 3</span>
                    <h3>Show up and play</h3>
                    <p>Get your QR match pass instantly with guaranteed pitch access. Enjoy complete flexibility with digital or cash payment.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Hero date field for the hero search
document.addEventListener('DOMContentLoaded', function() {
    var dateInput = document.getElementById('hqsDate');

    function toISO(d) {
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    // The date field is dd/mm/yyyy text (native pickers render per browser locale)
    function toDMY(iso) {
        if (!iso) return '';
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function isoFromDMY(v) {
        var m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(String(v || '').trim());
        if (!m) return '';
        var day = parseInt(m[1], 10), mon = parseInt(m[2], 10), yr = parseInt(m[3], 10);
        var d = new Date(yr, mon - 1, day);
        if (d.getFullYear() !== yr || d.getMonth() !== mon - 1 || d.getDate() !== day) return '';
        return toISO(d);
    }

    function clampISO(iso) {
        if (!iso) return '';
        if (dateInput.dataset.min && iso < dateInput.dataset.min) return dateInput.dataset.min;
        if (dateInput.dataset.max && iso > dateInput.dataset.max) return dateInput.dataset.max;
        return iso;
    }

    if (dateInput) {
        // Keep typing in dd/mm/yyyy shape
        dateInput.addEventListener('input', function() {
            var digits = dateInput.value.replace(/\D/g, '').slice(0, 8);
            if (digits.length > 4) {
                digits = digits.slice(0, 2) + '/' + digits.slice(2, 4) + '/' + digits.slice(4);
            } else if (digits.length > 2) {
                digits = digits.slice(0, 2) + '/' + digits.slice(2);
            }
            dateInput.value = digits;
        });
        dateInput.addEventListener('change', function() {
            var iso = isoFromDMY(dateInput.value);
            if (iso) {
                dateInput.value = toDMY(clampISO(iso));
            } else if (dateInput.value.trim() !== '') {
                dateInput.value = toDMY(dateInput.dataset.min);
            }
        });
    }

    // Keep the resulting URL tidy: empty filters should not appear as params
    var heroForm = document.getElementById('heroSearchForm');
    if (heroForm) {
        heroForm.addEventListener('submit', function() {
            ['location', 'q', 'sort', 'slot', 'date'].forEach(function(name) {
                var ctl = heroForm.elements[name];
                if (ctl && !ctl.value) ctl.disabled = true;
            });
        });
    }
});
</script>

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

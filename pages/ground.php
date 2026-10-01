<?php
require_once __DIR__ . '/../config/db.php';

function hm12(string $hm): string
{
    $parts = explode(':', $hm);
    $h = (int)$parts[0];
    $min = $parts[1] ?? '00';
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 === 0) $h12 = 12;
    return sprintf('%02d:%s %s', $h12, $min, $ampm);
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.id = ? AND g.is_active = 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$ground = $stmt->get_result()->fetch_assoc();

if (!$ground) {
    http_error_page(404, 'Court not found', 'We couldn\'t find that court. It may have been removed or is no longer active.', 'Browse all courts', 'pages/courts.php');
}

ground_seo_meta($ground);
$json_ld = ground_json_ld($ground);

$selected_date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

$taken = [];
$stmt = $conn->prepare('SELECT start_time FROM bookings WHERE ground_id = ? AND booking_date = ? AND status != "cancelled"');
$stmt->bind_param('is', $ground['id'], $selected_date);
$stmt->execute();
$rows = $stmt->get_result();
while ($row = $rows->fetch_assoc()) {
    $taken[] = $row['start_time'];
}

cleanup_expired_slot_holds();
$curUid = (int)($_SESSION['user_id'] ?? 0);
try {
    $heldQuery = $conn->prepare('SELECT start_time FROM slot_holds WHERE ground_id = ? AND booking_date = ? AND user_id != ? AND expires_at > NOW()');
    if ($heldQuery) {
        $heldQuery->bind_param('isi', $ground['id'], $selected_date, $curUid);
        $heldQuery->execute();
        $hRows = $heldQuery->get_result();
        while ($hRow = $hRows->fetch_assoc()) {
            $taken[] = $hRow['start_time'];
        }
        $heldQuery->close();
    }
} catch (Throwable $e) {
    error_log('heldQuery error: ' . $e->getMessage());
}

$slots = slots_for_day($selected_date, $ground['id']);
$price = ground_price_for_date((int)$ground['id'], (float)$ground['price_per_hour'], $selected_date);
$photosList = ground_gallery((int)$ground['id']);
$photos = array_map(fn($img) => ['image' => $img], $photosList);
$reviews = ground_reviews((int)$ground['id']);
$rating = ground_rating((int)$ground['id']);
$my_review = user_rating_for((int)$ground['id']);
$groundFeatures = ground_card_features($ground);
$is_blocked = date_is_blocked((int)$ground['id'], $selected_date);

// Similar courts: same city first, then fill with any other active courts.
$similar = [];
$cityMatch = '';
foreach (['Kathmandu', 'Bhaktapur', 'Lalitpur'] as $cityName) {
    if (mb_stripos((string)$ground['location'], $cityName) !== false) {
        $cityMatch = $cityName;
        break;
    }
}
if ($cityMatch !== '') {
    $stmt = $conn->prepare('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 AND g.id <> ? AND g.location LIKE ? ORDER BY g.id LIMIT 3');
    $cityLike = '%' . $cityMatch . '%';
    $stmt->bind_param('is', $ground['id'], $cityLike);
    $stmt->execute();
    $similar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
if (count($similar) < 3) {
    $exclude = [(int)$ground['id']];
    foreach ($similar as $s) { $exclude[] = (int)$s['id']; }
    $exclude = array_values(array_unique($exclude));
    $ph = implode(',', array_fill(0, count($exclude), '?'));
    $types = str_repeat('i', count($exclude));
    $fillLimit = 3 - count($similar);
    $stmt = $conn->prepare("SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 AND g.id NOT IN ($ph) ORDER BY RAND() LIMIT $fillLimit");
    $stmt->bind_param($types, ...$exclude);
    $stmt->execute();
    $similar = array_merge($similar, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submit'])) {
    verify_csrf();
    if (!is_logged_in()) {
        set_flash_error(
            'You need to be logged in to leave a review.',
            'Reviews are tied to your account so other players can trust them.',
            'Log in, then come back to post your review.',
            'pages/login.php'
        );
        redirect('pages/ground.php?id=' . (int)$ground['id']);
    }
    if (!is_player()) {
        set_flash_error(
            'Only player accounts can leave reviews.',
            'Manager and admin accounts don\'t play at the court.',
            'Sign in with a player account to share your experience.',
            'pages/login.php'
        );
        redirect('pages/ground.php?id=' . (int)$ground['id']);
    }
    // Rate limit: max 3 review submissions per user per 5 minutes
    if (rate_limit_exceeded('review:' . $_SESSION['user_id'], 3, 300)) {
        set_flash_error(
            'Slow down.',
            'You\'re submitting reviews too quickly.',
            'Wait a few minutes before trying again.',
            'pages/ground.php?id=' . (int)$ground['id']
        );
        redirect('pages/ground.php?id=' . (int)$ground['id']);
    }
    $rating_val = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if (mb_strlen($comment) > 600) {
        $comment = mb_substr($comment, 0, 600);
    }
    if ($rating_val < 1 || $rating_val > 5) {
        $rating_val = 0;
    }
    if ($rating_val === 0) {
        set_flash_error(
            'No star rating was selected.',
            'A review needs a rating to be counted.',
            'Tap a star from 1 to 5, then submit again.',
            'pages/ground.php?id=' . (int)$ground['id']
        );
    } else {
        if ($my_review) {
            $stmt = $conn->prepare('UPDATE reviews SET rating = ?, comment = ? WHERE id = ? AND user_id = ?');
            $stmt->bind_param('isii', $rating_val, $comment, $my_review['id'], $_SESSION['user_id']);
            $stmt->execute();
            set_flash('success', 'Review updated.');
        } else {
            $stmt = $conn->prepare('INSERT INTO reviews (ground_id, user_id, rating, comment) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iiis', $ground['id'], $_SESSION['user_id'], $rating_val, $comment);
            $stmt->execute();
            set_flash('success', 'Thanks! Your review has been posted.');
        }
    }
    redirect('pages/ground.php?id=' . (int)$ground['id']);
}

$page_title = $ground['name'];
$page_description = 'Check availability, pricing, and amenities at ' . $ground['name'] . '. Pick a free slot and book your futsal game online with GoalSpace.';
$hasCoords = $ground['latitude'] !== null && $ground['longitude'] !== null;
require __DIR__ . '/../includes/header.php';
?>
<?php if ($hasCoords): ?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/leaflet/leaflet.css'); ?>">
<?php endif; ?>
<?php echo $json_ld; // JSON-LD structured data ?>
<div class="page-head">
    <div class="title-back-row">
        <a href="<?php echo grounds_list_url(); ?>" class="page-back-arrow" data-back aria-label="Back to courts"><i class="fa-solid fa-arrow-left"></i></a>
        <nav class="breadcrumb">
            <a href="<?php echo base_url('index.php'); ?>">Home</a> &nbsp;/&nbsp;
            <a href="<?php echo grounds_list_url(); ?>">Courts</a> &nbsp;/&nbsp;
            <span><?php echo e($ground['name']); ?></span>
        </nav>
    </div>
</div>

<?php if (is_demo_ground($ground)): ?>
    <div class="notice">
        <i class="fa-solid fa-circle-info"></i>
        <span><strong>Practice court.</strong> This is a demo for trying GoalSpace. Feel free to book and pay with test data, nothing here is real.</span>
    </div>
<?php endif; ?>

<div class="ground-detail">
    <div class="gallery-wrap reveal" id="galleryWrap"<?php if ($photos): ?> data-images="<?php echo e(json_encode(array_map(function ($p) { return base_url('uploads/grounds/' . rawurlencode($p['image'])); }, $photos))); ?>"<?php endif; ?>>
            <?php if ($photos): ?>
                <div class="gallery-main">
                    <img id="galleryMain" src="<?php echo base_url('uploads/grounds/' . rawurlencode($photos[0]['image'])); ?>" alt="<?php echo e($ground['name']); ?>" decoding="async">
                    <span class="gallery-counter" aria-live="polite" aria-atomic="true"><span class="gallery-counter-current">1</span>&nbsp;/&nbsp;<span class="gallery-counter-total"><?php echo count($photos); ?></span></span>
                </div>
                <?php if (count($photos) > 1): ?>
                    <button type="button" class="gallery-nav gallery-prev" aria-label="Previous photo" hidden><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="gallery-nav gallery-next" aria-label="Next photo"><i class="fa-solid fa-chevron-right"></i></button>
                <?php endif; ?>
                <button type="button" class="gallery-zoom" aria-label="View fullscreen" title="View fullscreen"><i class="fa-solid fa-expand"></i></button>
            <?php else: ?>
                <div class="gallery pitch"></div>
            <?php endif; ?>
        </div>
    <div class="detail-box booking-panel">
        <span class="book-eyebrow">Book this court</span>
        <?php
        $discPrice = $ground['discount_price'] ?? null;
        $isWeekendRate = (int)date('N', strtotime($selected_date)) >= 6 && !empty($ground['price_weekend']);
        $showDiscount = !$isWeekendRate && $discPrice !== null && (float)$discPrice > 0 && (float)$discPrice < (float)$ground['price_per_hour'];
        $showOff = $showDiscount ? (int)round((1 - (float)$discPrice / (float)$ground['price_per_hour']) * 100) : 0;
        ?>
        <p class="price-line">
            <?php if ($showDiscount): ?><span class="price-orig">Rs <?php echo number_format((float)$ground['price_per_hour'], 0); ?></span><?php endif; ?>
            <strong>Rs <?php echo number_format($price, 0); ?></strong> / hour<?php echo $isWeekendRate ? ' <span class="weekend-tag">weekend</span>' : ''; ?><?php if ($showOff > 0): ?> <span class="price-off">-<?php echo $showOff; ?>%</span><?php endif; ?>
        </p>

        <div class="bp-section">
            <div class="bp-label-row">
                <span class="bp-label">Pick a date</span>
            </div>
            <form method="get" action="" class="bp-date-form" id="bpDateField" hidden>
                <input type="hidden" name="id" value="<?php echo (int)$ground['id']; ?>">
                <label class="visually-hidden" for="bookingDate">Pick another date</label>
                <input type="date" id="bookingDate" name="date" value="<?php echo e($selected_date); ?>" min="<?php echo e(date('Y-m-d')); ?>" max="<?php echo e(date('Y-m-d', strtotime('+60 days'))); ?>">
            </form>
            <div class="date-chips" id="dateChips" role="tablist" aria-label="Select date">
                <?php
                // Quick picks: next 5 days - use the calendar for anything later.
                for ($d = 0; $d < 5; $d++):
                    $chipTs = strtotime("+$d day");
                    $chipDate = date('Y-m-d', $chipTs);
                    $chipActive = ($chipDate === $selected_date);
                ?>
                    <a href="?id=<?php echo (int)$ground['id']; ?>&amp;date=<?php echo e($chipDate); ?>"
                       class="date-chip <?php echo $chipActive ? 'active' : ''; ?>"
                       role="tab"
                       aria-selected="<?php echo $chipActive ? 'true' : 'false'; ?>">
                        <span class="chip-day"><?php echo e(date('D', $chipTs)); ?></span>
                        <span class="chip-num"><?php echo e(date('j', $chipTs)); ?></span>
                        <span class="chip-mon"><?php echo e(date('M', $chipTs)); ?></span>
                    </a>
                <?php endfor; ?>
                <?php if ($selected_date > date('Y-m-d', strtotime('+6 days'))): ?>
                    <a href="?id=<?php echo (int)$ground['id']; ?>&amp;date=<?php echo e($selected_date); ?>"
                       class="date-chip active"
                       role="tab"
                       aria-selected="true">
                        <span class="chip-day"><?php echo e(date('D', strtotime($selected_date))); ?></span>
                        <span class="chip-num"><?php echo e(date('j', strtotime($selected_date))); ?></span>
                        <span class="chip-mon"><?php echo e(date('M', strtotime($selected_date))); ?></span>
                    </a>
                <?php endif; ?>
                <button type="button" class="date-chip date-chip-cal" id="calToggle" aria-expanded="false" aria-controls="bpDateField" title="Pick another date">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>Pick Date</span>
                </button>
            </div>
        </div>

        <?php if ($is_blocked): ?>
            <div class="role-lock">
                <i class="fa-solid fa-ban"></i>
                <span>Closed on <strong><?php echo e(date('D, M j', strtotime($selected_date))); ?></strong>  -  pick another day.</span>
            </div>
        <?php else: ?>
            <?php
            $slotSet = ground_slot_settings((int)$ground['id']);
            $openHM = substr((string)$slotSet['open_time'], 0, 5);
            $closeHM = substr((string)$slotSet['close_time'], 0, 5);
            $openEndHM = $openHM;
            $closeStartHM = $closeHM;
            // Earliest end = open + interval; latest start = close − interval.
            $iv = max(15, (int)$slotSet['slot_interval']);
            $openM = (int)substr($openHM, 0, 2) * 60 + (int)substr($openHM, 3, 2);
            $closeM = (int)substr($closeHM, 0, 2) * 60 + (int)substr($closeHM, 3, 2);
            $openEndHM = sprintf('%02d:%02d', intdiv($openM + $iv, 60) % 24, ($openM + $iv) % 60);
            $closeStartHM = sprintf('%02d:%02d', intdiv($closeM - $iv, 60), ($closeM - $iv) % 60);

            // Busy ranges (active bookings + other users' live holds)  -  display only.
            $busyRanges = [];
            $bs = $conn->prepare('SELECT start_time, end_time FROM bookings WHERE ground_id = ? AND booking_date = ? AND status != "cancelled"');
            $bs->bind_param('is', $ground['id'], $selected_date);
            $bs->execute();
            $br = $bs->get_result();
            while ($row = $br->fetch_assoc()) {
                $busyRanges[] = [substr($row['start_time'], 0, 5), substr($row['end_time'], 0, 5)];
            }
            try {
                $hs = $conn->prepare('SELECT start_time FROM slot_holds WHERE ground_id = ? AND booking_date = ? AND user_id != ? AND expires_at > NOW()');
                if ($hs) {
                    $hs->bind_param('isi', $ground['id'], $selected_date, $curUid);
                    $hs->execute();
                    $hr = $hs->get_result();
                    while ($row = $hr->fetch_assoc()) {
                        $hsM = (int)substr($row['start_time'], 0, 2) * 60 + (int)substr($row['start_time'], 3, 2);
                        $busyRanges[] = [
                            substr($row['start_time'], 0, 5),
                            sprintf('%02d:%02d', intdiv(min($closeM, $hsM + $iv), 60), min($closeM, $hsM + $iv) % 60),
                        ];
                    }
                    $hs->close();
                }
            } catch (Throwable $e) {
                error_log('busyRanges slot_holds error: ' . $e->getMessage());
            }

            // Slot presentation: free-slot helpers + per-period free counts.
            $freeSlots = array_values(array_filter($slots, fn($s) => !in_array($s['start'], $taken, true)));
            $periodFree = ['morning' => 0, 'afternoon' => 0, 'evening' => 0];
            foreach ($slots as $slotItem) {
                if (in_array($slotItem['start'], $taken, true)) continue;
                $slotHour = (int)substr($slotItem['start'], 0, 2);
                $periodFree[$slotHour < 12 ? 'morning' : ($slotHour < 17 ? 'afternoon' : 'evening')]++;
            }
            $nextOpenDate = '';
            if (!$freeSlots) {
                $rangeEnd = date('Y-m-d', strtotime('+14 day'));
                $futureBusy = [];
                try {
                    $fq = $conn->prepare('SELECT booking_date, start_time FROM bookings WHERE ground_id = ? AND booking_date > ? AND booking_date <= ? AND status != "cancelled"');
                    $fq->bind_param('iss', $ground['id'], $selected_date, $rangeEnd);
                    $fq->execute();
                    $fr = $fq->get_result();
                    while ($fRow = $fr->fetch_assoc()) {
                        $futureBusy[$fRow['booking_date']][] = $fRow['start_time'];
                    }
                    $fq->close();
                    $fh = $conn->prepare('SELECT booking_date, start_time FROM slot_holds WHERE ground_id = ? AND booking_date > ? AND booking_date <= ? AND expires_at > NOW()');
                    $fh->bind_param('iss', $ground['id'], $selected_date, $rangeEnd);
                    $fh->execute();
                    $hr2 = $fh->get_result();
                    while ($hRow2 = $hr2->fetch_assoc()) {
                        $futureBusy[$hRow2['booking_date']][] = $hRow2['start_time'];
                    }
                    $fh->close();
                } catch (Throwable $e) {
                    error_log('nextOpenDate query error: ' . $e->getMessage());
                }
                for ($fd = 1; $fd <= 14 && $nextOpenDate === ''; $fd++) {
                    $checkDate = date('Y-m-d', strtotime($selected_date . ' +' . $fd . ' day'));
                    if (date_is_blocked((int)$ground['id'], $checkDate)) {
                        continue;
                    }
                    $dateBusy = $futureBusy[$checkDate] ?? [];
                    foreach (slots_for_day($checkDate, (int)$ground['id']) as $freeCheck) {
                        if (!in_array($freeCheck['start'], $dateBusy, true)) {
                            $nextOpenDate = $checkDate;
                            break;
                        }
                    }
                }
            }
            ?>
            <form method="post" action="<?php echo base_url('pages/book.php'); ?>" class="bp-cta-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="ground_id" value="<?php echo (int)$ground['id']; ?>">
                <input type="hidden" name="booking_date" value="<?php echo e($selected_date); ?>">
                <input type="hidden" name="selected_slot" id="selectedSlot" value="">
                <div class="bp-section bp-time-section">
                    <div class="bp-label-row">
                        <span class="bp-label">Pick your time</span>
                    </div>
                    <?php if (!$freeSlots): ?>
                        <div class="slot-empty">
                            <i class="fa-solid fa-calendar-xmark"></i>
                            <span>No free slots this day<?php if ($nextOpenDate !== ''): ?> &mdash; <a href="?id=<?php echo (int)$ground['id']; ?>&amp;date=<?php echo e($nextOpenDate); ?>">next open day: <strong><?php echo e(date('D, M j', strtotime($nextOpenDate))); ?></strong></a><?php endif; ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="slot-tabs" role="tablist" aria-label="Filter slots by time of day">
                        <button type="button" class="slot-tab active" data-filter="all" role="tab" aria-selected="true">All</button>
                        <button type="button" class="slot-tab" data-filter="morning" role="tab" aria-selected="false">Morning <span class="st-count"><?php echo $periodFree['morning'] > 0 ? $periodFree['morning'] . ' free' : 'full'; ?></span></button>
                        <button type="button" class="slot-tab" data-filter="afternoon" role="tab" aria-selected="false">Afternoon <span class="st-count"><?php echo $periodFree['afternoon'] > 0 ? $periodFree['afternoon'] . ' free' : 'full'; ?></span></button>
                        <button type="button" class="slot-tab" data-filter="evening" role="tab" aria-selected="false">Evening <span class="st-count"><?php echo $periodFree['evening'] > 0 ? $periodFree['evening'] . ' free' : 'full'; ?></span></button>
                    </div>
                    <div class="slot-grid" id="slotGrid" data-hourly="<?php echo (float)$price; ?>" data-date="<?php echo e($selected_date); ?>">
                        <?php foreach ($slots as $slot): ?>
                            <?php
                            $isTaken = in_array($slot['start'], $taken, true);
                            $slotHour = (int)substr($slot['start'], 0, 2);
                            $period = $slotHour < 12 ? 'morning' : ($slotHour < 17 ? 'afternoon' : 'evening');
                            $s12 = hm12($slot['start']);
                            $e12 = hm12($slot['end']);
                            $slotRange = (substr($s12, -2) === substr($e12, -2))
                                ? substr($s12, 0, 5) . ' - ' . $e12
                                : $s12 . ' - ' . $e12;
                            ?>
                            <button type="button" class="slot <?php echo $isTaken ? 'taken' : ''; ?>"
                                 data-start="<?php echo e($slot['start']); ?>"
                                 data-end="<?php echo e($slot['end']); ?>"
                                 data-label="<?php echo e($slotRange); ?>"
                                 data-price="Rs <?php echo number_format($price, 0); ?>"
                                 data-period="<?php echo $period; ?>"
                                 aria-label="<?php echo e($slotRange); ?>, <?php echo $isTaken ? 'booked' : 'available'; ?>"
                                 <?php echo $isTaken ? 'disabled aria-disabled="true"' : 'aria-pressed="false"'; ?>>
                                <span class="slot-time"><?php echo e($slotRange); ?></span>
                                <?php if ($isTaken): ?><span class="slot-note">Booked</span><?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="startTime" name="start_time" value="">
                    <input type="hidden" id="endTime" name="end_time" value="">
                </div>
                <div class="bp-summary" id="bpSummary" hidden>
                    <div class="bp-sum-info">
                        <span class="bp-sum-when" id="sumWhen"></span>
                        <span class="bp-sum-note" id="sumNote"></span>
                    </div>
                    <div class="bp-sum-price" id="sumPrice"></div>
                </div>
                <?php if (!is_logged_in()): ?>
                    <?php
                    // Preserve this court/date so login returns here.
                    $returnTo = 'pages/ground.php?id=' . (int)$ground['id'];
                    if (!empty($selected_date)) {
                        $returnTo .= '&date=' . rawurlencode((string)$selected_date);
                    }
                    $_SESSION['return_path'] = $returnTo;
                    ?>
                    <a href="<?php echo base_url('pages/login.php'); ?>" class="btn btn-primary btn-block btn-lg">Log in to book</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary btn-block btn-lg" id="bookBtn" disabled>Select a time first</button>
                    <?php if (!is_player()): ?>
                        <p class="bp-role-note">
                            <i class="fa-solid fa-circle-info"></i> Signed in as <?php echo e(ucfirst($site_user['role'])); ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </form>

            <div class="bp-trust">
                <span class="bp-trust-item"><i class="fa-solid fa-bolt"></i> Instant confirmation</span>
                <span class="bp-trust-item"><i class="fa-solid fa-calendar-check"></i> Free cancellation up to 2 hours before kickoff</span>
                <span class="bp-trust-item"><i class="fa-solid fa-wallet"></i> eSewa &bull; Khalti &bull; Pay at Venue</span>
            </div>

            <?php
            $takenSlots = array_values(array_filter($slots, fn($s) => in_array($s['start'], $taken, true)));
            if ($takenSlots && (is_logged_in())) :
                ob_start(); ?>
                <div class="waitlist-list">
                    <?php foreach ($takenSlots as $ts): ?>
                        <?php $wcount = waitlist_count((int)$ground['id'], $selected_date, $ts['start']); ?>
                        <?php $joined = is_logged_in() && on_waitlist((int)$ground['id'], $selected_date, $ts['start'], (int)$site_user['id']); ?>
                        <form method="post" action="<?php echo base_url('pages/book.php'); ?>" data-fullscreen-loader>
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="ground_id" value="<?php echo (int)$ground['id']; ?>">
                            <input type="hidden" name="booking_date" value="<?php echo e($selected_date); ?>">
                            <input type="hidden" name="selected_slot" value="<?php echo e($ts['start'] . '|' . $ts['end']); ?>">
                            <button type="submit" name="join_waitlist" value="1" class="waitlist-row <?php echo $joined ? 'joined' : ''; ?>" <?php echo $joined ? 'disabled' : ''; ?>>
                                <span class="wl-slot"><i class="fa-regular fa-clock"></i> <?php echo e(hm12(substr($ts['start'], 0, 5)) . ' - ' . hm12(substr($ts['end'], 0, 5))); ?></span>
                                <span class="wl-info"><?php echo $joined ? 'In line' : ($wcount > 0 ? $wcount . ' waiting' : 'Be first'); ?></span>
                                <span class="wl-btn"><i class="fa-solid <?php echo $joined ? 'fa-check' : 'fa-plus'; ?>"></i></span>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
                <?php $wlList = ob_get_clean(); ?>
                <div class="waitlist-box">
                    <?php if (count($takenSlots) > 3): ?>
                        <details class="waitlist-details">
                            <summary class="waitlist-head"><i class="fa-solid fa-bell"></i> Full times — join a waitlist</summary>
                            <?php echo $wlList; ?>
                        </details>
                    <?php else: ?>
                        <div class="waitlist-head"><i class="fa-solid fa-bell"></i> Full times — join a waitlist</div>
                        <?php echo $wlList; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="detail-box ground-info ground-facts reveal">
        <div class="ground-title-row">
            <h1 class="ground-title"><?php echo e($ground['name']); ?></h1>
            <?php if (is_logged_in()): ?>
                <button type="button"
                        class="fav-toggle ground-fav"
                        data-ground-id="<?php echo (int)$ground['id']; ?>"
                        data-url="<?php echo base_url('ajax/favorite.php'); ?>"
                        aria-label="<?php echo favorite_exists((int)$ground['id']) ? 'Remove from saved courts' : 'Save this court'; ?>"
                        title="<?php echo favorite_exists((int)$ground['id']) ? 'Remove from saved courts' : 'Save this court'; ?>"
                        data-saved="<?php echo favorite_exists((int)$ground['id']) ? '1' : '0'; ?>">
                    <i class="fa-heart <?php echo favorite_exists((int)$ground['id']) ? 'fa-solid' : 'fa-regular'; ?>" aria-hidden="true"></i>
                    <span class="fav-text fav-text-save">Save</span>
                    <span class="fav-text fav-text-saved">Saved</span>
                </button>
            <?php endif; ?>
        </div>
        <div class="rating-summary">
            <?php if ($rating['count'] > 0): ?>
                <span class="rating-big"><?php echo number_format((float)$rating['avg'], 1); ?></span>
                <?php echo star_html($rating['avg']); ?>
                <span class="rating-meta"><?php echo $rating['count']; ?> review<?php echo $rating['count'] === 1 ? '' : 's'; ?></span>
            <?php else: ?>
                <span class="rating-meta">No reviews yet – be the first to play here.</span>
            <?php endif; ?>
        </div>
        <div class="info-row"><i class="fa-solid fa-location-dot"></i> <span><?php echo e($ground['location']); ?></span></div>
        <div class="info-row"><i class="fa-solid fa-clock"></i> <span>Open <?php echo e(substr($ground['open_time'], 0, 5)); ?> – <?php echo e(substr($ground['close_time'], 0, 5)); ?> · <?php echo $ground['slot_interval'] == 60 ? 'Hourly' : (int)$ground['slot_interval'] . '-minute'; ?> slots</span></div>
        <div class="info-row"><i class="fa-solid fa-users"></i> <span>Fits up to <?php echo (int)$ground['capacity']; ?> players</span></div>
        <div class="info-row"><i class="fa-solid fa-layer-group"></i> <span><?php echo e($groundFeatures['surface']); ?> · <?php echo (int)$groundFeatures['sides']; ?>-a-side</span></div>
        <?php $ownerLabel = ground_owner_label($ground); if ($ownerLabel !== ''): ?>
            <div class="info-row"><i class="fa-solid fa-store"></i> <span>Managed by <strong><?php echo e($ownerLabel); ?></strong></span></div>
        <?php endif; ?>
        <?php $hasCoords = $ground['latitude'] !== null && $ground['longitude'] !== null; ?>
        <?php $mapQuery = $hasCoords
            ? (float)$ground['latitude'] . ',' . (float)$ground['longitude']
            : ($ground['address'] !== '' ? $ground['address'] : $ground['location']); ?>
        <?php if (!empty($ground['address'])): ?>
            <a class="btn btn-outline btn-block" href="https://maps.google.com/?q=<?php echo e(rawurlencode($mapQuery)); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-map-location-dot"></i> Open in Google Maps</a>
        <?php endif; ?>
    </div>

    <div class="detail-box about-box reveal">
        <h3>About this court</h3>
        <?php if (!empty($ground['description'])): ?>
            <p class="desc"><?php echo e($ground['description']); ?></p>
        <?php else: ?>
            <p class="desc muted">No description yet – check the photos, hours and reviews to get a feel for this court.</p>
        <?php endif; ?>
        <div class="facilities">
            <div class="facilities-head"><i class="fa-solid fa-circle-check"></i> Facilities</div>
            <ul class="facility-chips">
                <?php foreach ($groundFeatures['amenities'] as $am): ?>
                    <li><i class="<?php echo e($am['icon']); ?>"></i> <?php echo e($am['label']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="ground-map">
            <div class="ground-map-head"><i class="fa-solid fa-map-location-dot"></i> Where you'll play</div>
            <div class="ground-map-frame">
                <?php if ($hasCoords): ?>
                    <div id="groundMap" class="ground-map-canvas"
                         data-lat="<?php echo (float)$ground['latitude']; ?>"
                         data-lng="<?php echo (float)$ground['longitude']; ?>"
                         role="img"
                         aria-label="Map showing <?php echo e($ground['name']); ?>"></div>
                <?php else: ?>
                    <iframe
                        src="<?php echo e('https://maps.google.com/maps?q=' . rawurlencode($mapQuery) . '&z=16&output=embed'); ?>"
                        width="100%" height="260" class="map-frame" allowfullscreen loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        title="Map showing <?php echo e($ground['name']); ?>"></iframe>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($ground['address'])): ?>
            <p class="about-address"><i class="fa-solid fa-location-dot"></i> <?php echo e($ground['address']); ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="reviews-wrap reveal" id="reviews">
    <div class="reviews-horizon-head">
        <div class="rh-left">
            <span class="rh-kicker">PLAYER FEEDBACK</span>
            <div class="rh-title-row">
                <h2 class="rh-title">Reviews &amp; Ratings</h2>
                <?php if ($rating['count'] > 0): ?>
                    <span class="rh-score-pill">
                        <i class="fa-solid fa-star"></i> <?php echo number_format((float)$rating['avg'], 1); ?>
                    </span>
                    <span class="rh-count-meta"><?php echo $rating['count']; ?> match review<?php echo $rating['count'] === 1 ? '' : 's'; ?></span>
                <?php else: ?>
                    <span class="rh-score-pill rh-score-pill--empty"><i class="fa-solid fa-star"></i> New</span>
                    <span class="rh-count-meta">Be the first to review</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (is_logged_in() && is_player()): ?>
            <button type="button" class="btn btn-outline btn-sm rh-write-btn" id="toggleReviewFormBtn" onclick="toggleReviewForm()">
                <i class="fa-solid fa-pen-to-square"></i> <?php echo $my_review ? 'Edit Review' : 'Write a Review'; ?>
            </button>
        <?php elseif (!is_logged_in()): ?>
            <a href="<?php echo base_url('pages/login.php'); ?>" class="btn btn-outline btn-sm rh-write-btn">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign in to Review
            </a>
        <?php endif; ?>
    </div>

    <!-- Review Submission Card -->
    <?php if (is_logged_in() && is_player()): ?>
        <div class="review-form-card" id="reviewFormDrawer" style="<?php echo $my_review ? '' : 'display:none;'; ?>">
            <div class="rfc-head">
                <h4><?php echo $my_review ? 'Update your review' : 'Rate your match experience'; ?></h4>
                <p>Share turf condition, lighting, parking, or match atmosphere.</p>
            </div>
            <form method="post" action="" class="rfc-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="review_submit" value="1">
                <div class="rfc-rating-group">
                    <label id="ratingLabel">Your Score</label>
                    <div class="star-input" role="radiogroup" aria-labelledby="ratingLabel">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" role="radio" aria-label="<?php echo $i; ?> star<?php echo $i === 1 ? '' : 's'; ?>" <?php echo ($my_review['rating'] ?? 0) == $i ? 'checked' : ''; ?>>
                            <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> star<?php echo $i === 1 ? '' : 's'; ?>"><i class="fa-solid fa-star"></i></label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="form-group mb-12">
                    <label for="reviewComment">Your Feedback <span class="muted font-normal">(optional)</span></label>
                    <textarea id="reviewComment" name="comment" rows="3" placeholder="Tell other players about the pitch quality, lighting, and amenities..."><?php echo e($my_review['comment'] ?? ''); ?></textarea>
                </div>
                <div class="rfc-actions">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> <?php echo $my_review ? 'Save Changes' : 'Publish Review'; ?></button>
                    <button type="button" class="btn btn-ghost" onclick="toggleReviewForm()">Cancel</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($reviews): ?>
        <div class="review-list">
            <?php foreach ($reviews as $rv):
                $helped = false;
                if (is_logged_in()) {
                    $hvStmt = $conn->prepare('SELECT 1 FROM review_votes WHERE review_id = ? AND user_id = ?');
                    $hvStmt->bind_param('ii', $rv['id'], $_SESSION['user_id']);
                    $hvStmt->execute();
                    $helped = (bool)$hvStmt->get_result()->fetch_assoc();
                    $hvStmt->close();
                }
            ?>
                <div class="review-item">
                    <div class="review-avatar">
                        <?php if (!empty($rv['avatar'])): ?>
                            <img src="<?php echo base_url('uploads/avatars/' . rawurlencode($rv['avatar'])); ?>" alt="<?php echo e($rv['user_name']); ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                            <?php echo e(strtoupper(substr($rv['user_name'], 0, 1))); ?>
                        <?php endif; ?>
                    </div>
                    <div class="review-body">
                        <div class="review-top-bar">
                            <div class="review-author-wrap">
                                <span class="review-name"><?php echo e($rv['user_name']); ?></span>
                                <span class="review-verified"><i class="fa-solid fa-circle-check"></i> Verified Player</span>
                            </div>
                            <span class="review-date"><?php echo e(date('M j, Y', strtotime($rv['created_at']))); ?></span>
                        </div>
                        <div class="review-stars-wrap">
                            <?php echo star_html($rv['rating']); ?>
                        </div>
                        <?php if ($rv['comment'] !== ''): ?>
                            <p class="review-comment"><?php echo e($rv['comment']); ?></p>
                        <?php endif; ?>
                        <div class="review-helpful">
                            <form method="post" action="<?php echo base_url('ajax/review_helpful.php'); ?>" class="helpful-form" data-review="<?php echo (int)$rv['id']; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="review_id" value="<?php echo (int)$rv['id']; ?>">
                                <button type="submit" class="helpful-btn <?php echo $helped ? 'helped' : ''; ?>" <?php echo $helped ? 'disabled' : ''; ?> aria-label="<?php echo $helped ? 'You found this helpful' : 'Mark as helpful'; ?>">
                                    <i class="fa-regular fa-thumbs-up"></i> <span>Helpful</span> <?php if ((int)$rv['helpful_count'] > 0): ?><strong class="helpful-count"><?php echo (int)$rv['helpful_count']; ?></strong><?php endif; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="reviews-empty-state">
            <i class="fa-regular fa-star"></i>
            <p class="res-title">No reviews yet</p>
            <p class="res-sub">Be the first to review <?php echo e($ground['name']); ?> — book your match, then share how the pitch, lights and atmosphere felt.</p>
            <?php if (is_logged_in() && is_player()): ?>
                <button type="button" class="btn btn-outline btn-sm" onclick="toggleReviewForm()">
                    <i class="fa-solid fa-pen-to-square"></i> Be the first to review
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleReviewForm() {
    var drawer = document.getElementById('reviewFormDrawer');
    if (!drawer) return;
    if (drawer.style.display === 'none' || drawer.style.display === '') {
        drawer.style.display = 'block';
        drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        drawer.style.display = 'none';
    }
}
</script>

<?php if ($similar): ?>
    <section class="section similar-courts">
        <div class="section-head reveal full mb-18">
            <h2 class="section-title">Similar courts<?php echo $cityMatch !== '' ? ' in ' . e($cityMatch) : ''; ?></h2>
        </div>
        <div class="grid grid-3 similar-grid">
            <?php foreach ($similar as $sg) { ground_card_html($sg); } ?>
        </div>
    </section>
<?php endif; ?>

<div class="bp-sticky-bar" id="bpStickyBar">
    <div class="bsb-info">
        <span class="bsb-price">
            <?php if ($showDiscount): ?><s class="bsb-price-orig">Rs <?php echo number_format((float)$ground['price_per_hour'], 0); ?></s><?php endif; ?>
            Rs <?php echo number_format($price, 0); ?><small>/hr</small><?php if ($showOff > 0): ?><span class="price-off">-<?php echo $showOff; ?>%</span><?php endif; ?>
        </span>
        <span class="bsb-slot" id="bsbSlot">Select a time</span>
    </div>
    <?php if (!is_logged_in()): ?>
        <a href="<?php echo base_url('pages/login.php'); ?>" class="btn btn-primary bsb-cta">Log in to book</a>
    <?php else: ?>
        <button type="button" class="btn btn-primary bsb-cta" id="bsbBook">Select Time</button>
    <?php endif; ?>
</div>
<div class="bp-bar-spacer"></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<?php if ($hasCoords): ?>
<script src="<?php echo base_url('assets/js/leaflet/leaflet.js'); ?>"></script>
<script>
(function () {
    var el = document.getElementById('groundMap');
    if (!el || typeof L === 'undefined') return;
    var lat = parseFloat(el.dataset.lat);
    var lng = parseFloat(el.dataset.lng);
    if (!isFinite(lat) || !isFinite(lng)) return;
    var map = L.map(el, { scrollWheelZoom: false, attributionControl: true }).setView([lat, lng], 16);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);
    L.marker([lat, lng], {
        icon: L.divIcon({ className: 'gs-map-pin', html: '<i></i>', iconSize: [18, 18], iconAnchor: [9, 17] })
    }).addTo(map);
})();
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.id = ? AND g.is_active = 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$ground = $stmt->get_result()->fetch_assoc();

if (!$ground) {
    set_flash_error(
        'We couldn\'t find that court.',
        'It may have been removed or is no longer active.',
        'Browse the courts list to find another place to play.',
        'pages/courts.php'
    );
    redirect('index.php');
}

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
    $taken[] = substr($row['start_time'], 0, 2);
}

$slots = slots_for_day($selected_date, $ground['id']);
$price = ground_price_for_date((int)$ground['id'], (float)$ground['price_per_hour'], $selected_date);

$photos = ground_images((int)$ground['id']);
$reviews = ground_reviews((int)$ground['id']);
$rating = ground_rating((int)$ground['id']);
$my_review = user_rating_for((int)$ground['id']);
$is_blocked = date_is_blocked((int)$ground['id'], $selected_date);

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
require __DIR__ . '/../includes/header.php';
?>

<nav class="breadcrumb">
    <a href="<?php echo base_url('index.php'); ?>">Home</a> &nbsp;/&nbsp;
    <a href="<?php echo grounds_list_url(); ?>">Grounds</a> &nbsp;/&nbsp;
    <span><?php echo e($ground['name']); ?></span>
</nav>

<div class="ground-detail">
    <div class="reveal">
        <div class="gallery-wrap" id="galleryWrap">
            <?php if ($photos): ?>
                <div class="gallery-main">
                    <img id="galleryMain" src="<?php echo base_url('uploads/grounds/' . rawurlencode($photos[0]['image'])); ?>" alt="<?php echo e($ground['name']); ?>" decoding="async">
                </div>
                <?php if (count($photos) > 1): ?>
                    <button type="button" class="gallery-nav gallery-prev" aria-label="Previous photo" hidden><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="gallery-nav gallery-next" aria-label="Next photo"><i class="fa-solid fa-chevron-right"></i></button>
                <?php endif; ?>
                <button type="button" class="gallery-zoom" aria-label="Zoom photo"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                <?php if (count($photos) > 1): ?>
                    <div class="gallery-thumbs">
                        <?php foreach ($photos as $pi => $ph): ?>
                            <button type="button" class="gallery-thumb" data-src="<?php echo base_url('uploads/grounds/' . rawurlencode($ph['image'])); ?>" aria-label="View photo <?php echo $pi + 1; ?> of <?php echo count($photos); ?>">
                                <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($ph['image'])); ?>" alt="" loading="lazy" decoding="async">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="gallery pitch"></div>
            <?php endif; ?>
        </div>
        <div class="detail-box ground-info" style="margin-top:20px;">
            <h3>About this ground</h3>
            <p class="about-name"><i class="fa-solid fa-futbol"></i> <span><?php echo e($ground['name']); ?></span></p>
            <div class="info-row"><i class="fa-solid fa-location-dot"></i> <span><?php echo e($ground['location']); ?></span></div>
            <?php $hasCoords = $ground['latitude'] !== null && $ground['longitude'] !== null; ?>
            <?php $mapQuery = $hasCoords
                ? (float)$ground['latitude'] . ',' . (float)$ground['longitude']
                : ($ground['address'] !== '' ? $ground['address'] : $ground['location']); ?>
            <div class="ground-map">
                <div class="ground-map-head"><i class="fa-solid fa-map-location-dot"></i> Location</div>
                <div class="ground-map-frame">
                    <iframe
                        src="<?php echo e('https://maps.google.com/maps?q=' . rawurlencode($mapQuery) . '&z=16&output=embed'); ?>"
                        width="100%" height="320" style="border:0;" allowfullscreen loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        title="Map showing <?php echo e($ground['name']); ?>"></iframe>
                </div>
                <?php if (!empty($ground['address'])): ?>
                    <a class="ground-map-link" href="https://maps.google.com/?q=<?php echo e(rawurlencode($mapQuery)); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open in Google Maps</a>
                <?php endif; ?>
            </div>
            <div class="info-row"><i class="fa-solid fa-users"></i> <span>Fits up to <?php echo (int)$ground['capacity']; ?> players</span></div>
            <div class="info-row"><i class="fa-solid fa-clock"></i> <span>Open 08:00 - 22:00</span></div>
            <?php if (!empty($ground['owner_name'])): ?>
                <div class="info-row"><i class="fa-solid fa-store"></i> <span>Managed by <strong><?php echo e($ground['owner_name']); ?></strong></span></div>
            <?php endif; ?>
            <div class="rating-summary">
                <?php if ($rating['count'] > 0): ?>
                    <span class="rating-big"><?php echo number_format((float)$rating['avg'], 1); ?></span>
                    <?php echo star_html($rating['avg']); ?>
                    <span class="rating-meta"><?php echo $rating['count']; ?> review<?php echo $rating['count'] === 1 ? '' : 's'; ?></span>
                <?php else: ?>
                    <span class="rating-meta">No reviews yet</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($ground['description'])): ?>
                <p class="desc"><?php echo e($ground['description']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="detail-box booking-panel reveal">
        <h1><?php echo e($ground['name']); ?></h1>
        <?php
        $discPrice = $ground['discount_price'] ?? null;
        $isWeekendRate = (int)date('N', strtotime($selected_date)) >= 6 && !empty($ground['price_weekend']);
        $showDiscount = !$isWeekendRate && $discPrice !== null && (float)$discPrice > 0 && (float)$discPrice < (float)$ground['price_per_hour'];
        ?>
        <p class="price-line">
            <?php if ($showDiscount): ?><span class="price-orig">Rs <?php echo number_format((float)$ground['price_per_hour'], 0); ?></span><?php endif; ?>
            <strong>Rs <?php echo number_format($price, 0); ?></strong> per hour<?php echo $isWeekendRate ? ' <span class="weekend-tag">weekend rate</span>' : ''; ?>
        </p>
        <p class="muted" style="font-size:12.5px;margin:-8px 0 14px;">Open <?php echo e(substr($ground['open_time'], 0, 5)); ?> - <?php echo e(substr($ground['close_time'], 0, 5)); ?> &middot; <?php echo $ground['slot_interval'] == 60 ? 'hourly' : $ground['slot_interval'] . '-min'; ?> slots</p>

        <form method="get" action="">
            <div class="form-group">
                <label for="bookingDate">Pick a day</label>
                <input type="date" id="bookingDate" name="date" value="<?php echo e($selected_date); ?>" min="<?php echo e(date('Y-m-d')); ?>">
            </div>
        </form>

        <?php if ($is_blocked): ?>
            <div class="role-lock">
                <i class="fa-solid fa-ban"></i>
                <span>The court is closed on <strong><?php echo e(date('D, M j', strtotime($selected_date))); ?></strong>. Pick another day.</span>
            </div>
        <?php else: ?>
            <p class="slot-hint" id="priceHint">Choose an hour that works for your team</p>
            <div class="slot-grid" id="slotGrid">
                <?php foreach ($slots as $slot): ?>
                    <?php $isTaken = in_array(substr($slot['start'], 0, 2), $taken, true); ?>
                    <button type="button" class="slot <?php echo $isTaken ? 'taken' : ''; ?>"
                         data-start="<?php echo e($slot['start']); ?>"
                         data-end="<?php echo e($slot['end']); ?>"
                         data-label="<?php echo e($slot['label']); ?>"
                         data-price="<?php echo 'Rs ' . number_format($price, 0); ?>" <?php echo $isTaken ? 'disabled' : ''; ?>>
                        <?php echo e($slot['label']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <form method="post" action="<?php echo base_url('pages/book.php'); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="ground_id" value="<?php echo (int)$ground['id']; ?>">
                <input type="hidden" name="booking_date" value="<?php echo e($selected_date); ?>">
                <input type="hidden" name="selected_slot" id="selectedSlot" value="">
                <?php if (!is_logged_in()): ?>
                    <a href="<?php echo base_url('pages/login.php'); ?>" class="btn btn-primary btn-block btn-lg">Log in to book</a>
                <?php elseif (is_player()): ?>
                    <div class="repeat-row" id="repeatRow">
                        <label class="repeat-check" for="repeatWeeks">
                            <input type="checkbox" id="repeatToggle">
                            <span><i class="fa-solid fa-arrows-rotate"></i> Repeat this booking weekly</span>
                        </label>
                        <div class="repeat-weeks" id="repeatWeeksWrap" style="display:none;">
                            <label for="repeatWeeks">for</label>
                            <select name="repeat_weeks" id="repeatWeeks">
                                <?php for ($i = 2; $i <= 8; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> weeks</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg" id="bookBtn">Reserve this slot</button>
                <?php else: ?>
                    <div class="role-lock">
                        <i class="fa-solid fa-lock"></i>
                        <span>You're signed in as a <strong><?php echo ucfirst($site_user['role']); ?></strong>. Booking is for player accounts only. Use a player account to reserve this court.</span>
                    </div>
                <?php endif; ?>
            </form>

            <?php
            $takenSlots = array_values(array_filter($slots, fn($s) => in_array(substr($s['start'], 0, 2), $taken, true)));
            if ($takenSlots && (is_logged_in())): ?>
                <div class="waitlist-box">
                    <div class="waitlist-head"><i class="fa-solid fa-bell"></i> Sold out? Join the waitlist</div>
                    <p class="muted" style="font-size:12.5px;margin-bottom:10px;">Fully booked slots below. Join a waitlist and we'll ping you the second someone cancels.</p>
                    <div class="waitlist-list">
                        <?php foreach ($takenSlots as $ts): ?>
                            <?php $wcount = waitlist_count((int)$ground['id'], $selected_date, $ts['start']); ?>
                            <?php $joined = is_logged_in() && on_waitlist((int)$ground['id'], $selected_date, $ts['start'], (int)$site_user['id']); ?>
                            <form method="post" action="<?php echo base_url('pages/book.php'); ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="ground_id" value="<?php echo (int)$ground['id']; ?>">
                                <input type="hidden" name="booking_date" value="<?php echo e($selected_date); ?>">
                                <input type="hidden" name="selected_slot" value="<?php echo e($ts['start'] . '|' . $ts['end']); ?>">
                                <button type="submit" name="join_waitlist" value="1" class="waitlist-row <?php echo $joined ? 'joined' : ''; ?>" <?php echo $joined ? 'disabled' : ''; ?>>
                                    <span class="wl-slot"><i class="fa-regular fa-clock"></i> <?php echo e($ts['label']); ?></span>
                                    <span class="wl-info"><?php echo $joined ? 'You\'re in line' : ($wcount > 0 ? $wcount . ' waiting' : 'Be first'); ?></span>
                                    <span class="wl-btn"><i class="fa-solid <?php echo $joined ? 'fa-check' : 'fa-plus'; ?>"></i></span>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="reviews-wrap">
    <?php if ($rating['count'] > 0): ?>
        <div class="section-head reveal" style="max-width:none;margin-bottom:18px;">
            <span class="eyebrow">Reviews</span>
            <h2 class="section-title" style="font-size:22px;">What players say</h2>
        </div>
    <?php endif; ?>

    <div class="review-form-card reveal">
        <?php if (!is_logged_in()): ?>
            <p class="muted" style="font-size:14px;">Played here or want to share your experience? <a href="<?php echo base_url('pages/login.php'); ?>" class="inline-link">Log in</a> to leave a review.</p>
        <?php elseif (is_player()): ?>
            <?php if ($my_review): ?>
                <p class="muted" style="font-size:13.5px;margin-bottom:12px;">You've rated this court <?php echo star_html($my_review['rating']); ?>. Update it below.</p>
            <?php endif; ?>
            <form method="post" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="review_submit" value="1">
                <div class="form-group">
                    <label>Your rating</label>
                    <div class="star-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" aria-label="<?php echo $i; ?> star<?php echo $i === 1 ? '' : 's'; ?>" <?php echo ($my_review['rating'] ?? 0) == $i ? 'checked' : ''; ?>>
                            <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> star<?php echo $i === 1 ? '' : 's'; ?>"><i class="fa-solid fa-star"></i></label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="reviewComment">Your review <span class="muted" style="font-weight:400;">(optional)</span></label>
                    <textarea id="reviewComment" name="comment" rows="3" placeholder="Tell others about the court, lights, booking and atmosphere…"><?php echo e($my_review['comment'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> <?php echo $my_review ? 'Update review' : 'Post review'; ?></button>
            </form>
        <?php else: ?>
            <p class="muted" style="font-size:14px;">Only player accounts can leave reviews.</p>
        <?php endif; ?>
    </div>

    <?php if ($reviews): ?>
        <div class="review-list">
            <?php foreach ($reviews as $rv): ?>
                <div class="review-item reveal">
                    <div class="review-avatar">
                        <?php if (!empty($rv['avatar'])): ?>
                            <img src="<?php echo base_url('uploads/avatars/' . rawurlencode($rv['avatar'])); ?>" alt="<?php echo e($rv['user_name']); ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                            <?php echo e(strtoupper(substr($rv['user_name'], 0, 1))); ?>
                        <?php endif; ?>
                    </div>
                    <div class="review-body">
                        <div class="review-head">
                            <span class="review-name"><?php echo e($rv['user_name']); ?></span>
                            <span class="review-date"><?php echo e(date('M j, Y', strtotime($rv['created_at']))); ?></span>
                        </div>
                        <?php echo star_html($rv['rating']); ?>
                        <?php if ($rv['comment'] !== ''): ?>
                            <p class="review-comment"><?php echo e($rv['comment']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

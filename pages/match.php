<?php
require_once __DIR__ . '/../config/db.php';

$ref = trim($_GET['ref'] ?? '');
$id = (int)($_GET['id'] ?? 0);

if ($ref === '' && $id <= 0) {
    http_error_page(400, 'Match not specified', 'Please provide a valid match reference or link.', 'Browse courts', 'pages/courts.php');
}

if ($ref !== '') {
    $stmt = $conn->prepare(
        'SELECT b.*, g.name AS ground_name, g.location, g.address, g.court_number, g.manager_id, g.capacity,
                u.name AS booker_name, m.phone AS manager_phone
         FROM bookings b
         JOIN grounds g ON g.id = b.ground_id
         JOIN users u ON u.id = b.user_id
         LEFT JOIN users m ON m.id = g.manager_id
         WHERE b.booking_ref = ?'
    );
    $stmt->bind_param('s', $ref);
} else {
    $stmt = $conn->prepare(
        'SELECT b.*, g.name AS ground_name, g.location, g.address, g.court_number, g.manager_id, g.capacity,
                u.name AS booker_name, m.phone AS manager_phone
         FROM bookings b
         JOIN grounds g ON g.id = b.ground_id
         JOIN users u ON u.id = b.user_id
         LEFT JOIN users m ON m.id = g.manager_id
         WHERE b.id = ?'
    );
    $stmt->bind_param('i', $id);
}
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();

if (!$b) {
    http_error_page(404, 'Match not found', 'We couldn\'t find that match. The link might be invalid or outdated.', 'Find courts', 'pages/courts.php');
}

if ($b['status'] === 'cancelled') {
    http_error_page(410, 'Match cancelled', 'This booking was cancelled by the organizer.', 'Browse other courts', 'pages/courts.php');
}

$page_title = 'Match Invitation: ' . $b['ground_name'];
$page_description = 'Match invitation and details for ' . $b['ground_name'] . ' on ' . date('M j, Y', strtotime($b['booking_date'])) . '.';

$matchStart = strtotime($b['booking_date'] . ' ' . $b['start_time']);
$matchEnd = strtotime($b['booking_date'] . ' ' . $b['end_time']);
if ($matchEnd <= $matchStart) {
    $matchEnd += 86400;
}
$now = time();

/* Dev state switch (query param). Default: open_guest. */
$stateParam = $_GET['state'] ?? 'open_guest';
$state = in_array($stateParam, ['open_guest', 'open_member', 'joined', 'declined', 'full', 'waitlisted', 'past', 'cancelled', 'loading', 'error'], true)
    ? $stateParam : 'open_guest';

/* Hero values. */
$cover = ground_cover((int)$b['ground_id']);
$coverUrl = $cover ? base_url('uploads/grounds/' . rawurlencode($cover)) : base_url('uploads/grounds/court_brastad_arena.jpg');

$venueLine = !empty($b['address']) ? $b['address'] : $b['location'];
$courtName = !empty($b['court_number']) ? $b['court_number'] : 'Pitch 1';
$capacity = (int)($b['capacity'] ?? 10);
$courtFormat = ($capacity >= 14) ? '7-A-Side' : (($capacity >= 12) ? '6-A-Side' : '5-A-Side');

/* Time badge + hero overlay text. */
$kickTime = substr($b['start_time'], 0, 5);
if ($now < $matchStart) {
    $dayGap = (int)((strtotime(date('Y-m-d', $matchStart)) - strtotime(date('Y-m-d'))) / 86400);
    if ($dayGap <= 0) {
        $whenWord = 'Today';
    } elseif ($dayGap === 1) {
        $whenWord = 'Tomorrow';
    } else {
        $whenWord = date('D, M j', strtotime($b['booking_date']));
    }
} elseif ($now <= $matchEnd) {
    $whenWord = 'Live now';
} else {
    $whenWord = 'Full time';
}

/* Time & place block. */
$essDate = date('D, M j', strtotime($b['booking_date']));
$essTime = substr($b['start_time'], 0, 5) . ' – ' . substr($b['end_time'], 0, 5);
$durationHours = max(1, round(($matchEnd - $matchStart) / 3600, 1));
$durationLabel = ((int)$durationHours === (float)$durationHours) ? (string)(int)$durationHours : (string)$durationHours;
$whenLine = $essDate . ' • ' . $essTime . ' (' . $durationLabel . ' hr)';
$countdownLabel = '';
if ($now < $matchStart) {
    $diffSec = $matchStart - $now;
    if ($diffSec >= 86400) {
        $days = (int)floor($diffSec / 86400);
        $countdownLabel = $days . ' day' . ($days === 1 ? '' : 's') . ' to go';
    } elseif ($diffSec >= 3600) {
        $hrs = (int)floor($diffSec / 3600);
        $countdownLabel = $hrs . ' hour' . ($hrs === 1 ? '' : 's') . ' to go';
    } else {
        $mins = max(1, (int)floor($diffSec / 60));
        $countdownLabel = $mins . ' min to go';
    }
} elseif ($now <= $matchEnd) {
    $countdownLabel = 'Happening now';
} else {
    $countdownLabel = 'Ended';
}

preg_match('/(\d+)-A-Side/', $courtFormat, $fm);
$sides = isset($fm[1]) ? (int)$fm[1] : 5;
$players = $sides * 2;

$netPrice = max(0, (float)$b['total_price'] - (float)($b['discount'] ?? 0));
$yourShare = (int)ceil($netPrice / max(1, $players));

$matchRef = !empty($b['booking_ref']) ? $b['booking_ref'] : ('#BK-' . $b['id']);
$destQuery = !empty($b['address']) ? ($b['ground_name'] . ', ' . $b['address']) : ($b['ground_name'] . ', ' . $b['location']);
$mapsDirUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destQuery);
$icsUrl = base_url('pages/booking_ics.php?' . (!empty($b['booking_ref']) ? ('ref=' . urlencode($b['booking_ref'])) : ('id=' . (int)$b['id'])));
$groundUrl = base_url('pages/ground.php?id=' . (int)$b['ground_id']);

/* Organizer (mock avatar fallback via first letter). */
$organizerName = $b['booker_name'];
$organizerInitial = mb_substr($organizerName, 0, 1);
$organizerPhone = $b['manager_phone']; // reuse ground manager phone for venue call button
$hasOrganizerPhone = false;
if (is_logged_in() && (int)$_SESSION['user_id'] === (int)$b['user_id']) {
    // Logged-in organizer viewing their own invite: still no direct phone here.
}
$showOrganizerCall = false; // optional future enhancement

/* Roster: derive slot names from identity_key if present, else seed guests. */
$rosterStmt = $conn->prepare("SELECT identity_key, status FROM match_rsvps WHERE booking_id = ? AND status = 'in' ORDER BY id");
$rosterStmt->bind_param('i', $b['id']);
$rosterStmt->execute();
$rosterRows = $rosterStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rosterStmt->close();
$confirmedCount = count($rosterRows);
$spotsLeft = max(0, $players - $confirmedCount);

/* Build player slots for roster display. */
$slotPlayers = [];
for ($i = 0; $i < $players; $i++) {
    if (isset($rosterRows[$i])) {
        $ik = $rosterRows[$i]['identity_key'];
        $displayName = '';
        if (strpos($ik, 'u:') === 0) {
            $displayName = 'Player';
        } elseif (strpos($ik, 'g:') === 0) {
            $displayName = str_replace(['g:', '-'], ['', ' '], substr($ik, 2));
            $displayName = ucwords($displayName);
        } else {
            $displayName = ucwords($ik);
        }
        $firstName = explode(' ', trim($displayName))[0];
        $slotPlayers[] = ['name' => $firstName, 'filled' => true];
    } else {
        $slotPlayers[] = ['name' => '', 'filled' => false];
    }
}

/* My RSVP status from session or localStorage-like fallback. */
$myRsvp = '';
if (is_logged_in()) {
    $ik = 'u:' . (int)$_SESSION['user_id'];
    $myStmt = $conn->prepare('SELECT status FROM match_rsvps WHERE booking_id = ? AND identity_key = ?');
    $myStmt->bind_param('is', $b['id'], $ik);
    $myStmt->execute();
    $myRow = $myStmt->get_result()->fetch_assoc();
    $myRsvp = $myRow ? $myRow['status'] : '';
    $myStmt->close();
}

/* Price display. */
$pricePerPlayer = $yourShare;
$totalPitchFee = $netPrice;

/* Icons. */
$svgOpen = '<svg class="mfx-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
$icPin = $svgOpen . '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>';
$icPhone = $svgOpen . '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>';
$icCal = $svgOpen . '<rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>';
$icCheck = $svgOpen . '<polyline points="20 6 9 17 4 12"></polyline></svg>';

/* WhatsApp share text (computed below for meta + button). */
$dayWord = $whenWord === 'Today' ? 'Today' : ($whenWord === 'Tomorrow' ? 'Tomorrow' : $whenWord);
$ampm = substr($essTime, 0, 5) === '00:00' ? '12 PM' : (substr($essTime, 0, 2) >= 12 ? rtrim(ltrim(substr($essTime, 0, 5), '0'), ':') . ' PM' : rtrim(ltrim(substr($essTime, 0, 5), '0'), ':') . ' AM');
$shareText = ($b['ground_name'] . ' at ' . $venueLine . '. ' . $dayWord . ' ' . $ampm . ', ' . $players . '-a-side. Rs. ' . number_format($yourShare, 0) . ' each. Join: ' . $_SERVER['REQUEST_URI']);
$waText = rawurlencode($shareText);
$metaTitle = 'Futsal ' . ($whenWord === 'Today' ? 'today' : ($whenWord === 'Tomorrow' ? 'tomorrow' : 'on ' . $essDate)) . ' ' . $essTime . ' at ' . $b['ground_name'] . ' · ' . $spotsLeft . ' spots left';
$metaDesc = $b['ground_name'] . ', ' . $venueLine . '. Organized by ' . $organizerName . '.';
$selfUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

$page_title = $metaTitle;
$page_description = $metaDesc;
$page_image = $coverUrl;
$page_url = $selfUrl;

require __DIR__ . '/../includes/header.php';
?>

<style>
/* 1. SEPARATE THE TWO ACTION BUTTONS */
.mfx-quick-actions {
    display: flex;
    gap: 10px;
    width: 100%;
    margin-top: 16px;
}
.mfx-quick-btn {
    flex: 1;
    height: 38px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
    color: #334155;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.mfx-quick-btn:hover {
    background: #f8fafc;
}

/* 2. STYLE THE DETAILS CONTAINER */
.mfx-details-group {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 16px;
    margin: 16px 0;
}
html[data-theme="dark"] .mfx-details-group {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.08);
}
@media (max-width: 600px) {
    .mfx-quick-actions {
        flex-direction: column;
        gap: 10px;
        width: 100%;
    }
}
</style>

<div class="mfx-wrap reveal"
     data-ref="<?php echo e($matchRef); ?>"
     data-id="<?php echo (int)$b['id']; ?>"
     data-auth="<?php echo is_logged_in() ? '1' : '0'; ?>"
     data-csrf="<?php echo e(csrf_token()); ?>"
     data-endpoint="<?php echo e(base_url('ajax/match_rsvp.php')); ?>"
     data-state="<?php echo e($state); ?>">

    <article class="mfx-card" aria-label="Match fixture">

        <!-- HERO -->
        <div class="mfx-hero">
            <img class="mfx-hero-img" src="<?php echo e($coverUrl); ?>" alt="Futsal turf at <?php echo e($b['ground_name']); ?>" decoding="async">
            <span class="mfx-hero-shade" aria-hidden="true"></span>
        </div>

        <div class="mfx-body">
            <p class="mfx-kicker">Organized by <?php echo e($b['booker_name']); ?> • <?php echo e($matchRef); ?></p>
            <h1 class="mfx-title"><?php echo e($b['ground_name']); ?></h1>
            <p class="mfx-subtitle"><?php echo e($venueLine); ?> • <?php echo e($courtName . ' (' . $courtFormat . ')'); ?></p>

            <div class="mfx-quick-actions" style="display: flex; gap: 10px; width: 100%;">
                <a href="<?php echo e($mapsDirUrl); ?>" target="_blank" rel="noopener" class="mfx-quick-btn" style="flex: 1; border: 1px solid #cbd5e1; border-radius: 6px;"><?php echo $icPin; ?> Open in Maps</a>
                <a href="tel:<?php echo e($organizerPhone); ?>" class="mfx-quick-btn" style="flex: 1; border: 1px solid #cbd5e1; border-radius: 6px;"><?php echo $icPhone; ?> Call Venue</a>
            </div>

            <!-- DETAILS GROUP -->
            <div class="mfx-details-group" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin: 16px 0;">
                <ul class="mfx-details">
                    <li class="mfx-detail-row">
                        <span class="mfx-detail-label"><?php echo $icCal; ?> Kickoff</span>
                        <span class="mfx-detail-value">
                            <strong><?php echo e($essDate); ?> • <?php echo e($essTime); ?></strong>
                            <?php if ($whenWord === 'Today' || $whenWord === 'Tomorrow'): ?>
                                <span class="mfx-badge"><?php echo e($whenWord); ?></span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <li class="mfx-detail-row">
                        <span class="mfx-detail-label"><?php echo $icPhone; ?> Surface &amp; Rules</span>
                        <span class="mfx-detail-value"><?php echo e($courtFormat); ?> • Flats or Turf Shoes Only</span>
                    </li>
                    <li class="mfx-detail-row">
                        <span class="mfx-detail-label mfx-detail-label-stack">
                            Your Share
                            <span class="mfx-detail-sub">(Rs <?php echo number_format($netPrice, 0); ?> total / <?php echo (int)$players; ?> players)</span>
                        </span>
                        <span class="mfx-detail-value">
                            <strong class="mfx-share-amount">Rs <?php echo number_format($yourShare, 0); ?></strong>
                        </span>
                    </li>
                </ul>
            </div>

            <!-- ATTENDANCE -->
            <div class="mfx-attendance">
                <span>Squad Status</span>
                <strong><?php echo (int)$confirmedCount; ?> Confirmed • <?php echo (int)max(0, $players - $confirmedCount); ?> Spots Left</strong>
            </div>

            <!-- ACTIONS -->
            <div class="mfx-actions-wrap">
                <div class="mfx-actions" id="mfxActions">
                    <?php if ($state === 'open_guest'): ?>
                        <button type="button" class="mfx-btn mfx-btn-primary" data-sheet-open aria-haspopup="dialog" aria-expanded="false">
                            I'm Playing (Rs <?php echo number_format($yourShare, 0); ?>)
                        </button>
                        <button type="button" class="mfx-btn mfx-btn-secondary" data-decline>
                            Can't Make It
                        </button>
                    <?php elseif ($state === 'open_member'): ?>
                        <button type="button" class="mfx-btn mfx-btn-primary" data-join>
                            I'm Playing (Rs <?php echo number_format($yourShare, 0); ?>)
                        </button>
                        <button type="button" class="mfx-btn mfx-btn-secondary" data-decline>
                            Can't Make It
                        </button>
                    <?php elseif ($state === 'joined'): ?>
                        <div class="mfx-joined">
                            <span class="mfx-joined-icon"><?php echo $icCheck; ?></span>
                            <span class="mfx-joined-text">You're in</span>
                        </div>
                        <button type="button" class="mfx-btn mfx-btn-link" data-cancel>
                            Cancel my spot
                        </button>
                    <?php elseif ($state === 'declined'): ?>
                        <div class="mfx-declined">
                            <p>You said you can't make it</p>
                            <button type="button" class="mfx-btn mfx-btn-link" data-reopen>
                                Changed my mind
                            </button>
                        </div>
                    <?php elseif ($state === 'full'): ?>
                        <button type="button" class="mfx-btn mfx-btn-primary" data-waitlist>
                            Join waitlist
                        </button>
                    <?php elseif ($state === 'waitlisted'): ?>
                        <p class="mfx-note">You're on the waitlist</p>
                        <button type="button" class="mfx-btn mfx-btn-link" data-cancel-waitlist>
                            Leave waitlist
                        </button>
                    <?php elseif ($state === 'past'): ?>
                        <p class="mfx-note">This match has ended</p>
                        <a href="<?php echo e(base_url('pages/courts.php')); ?>" class="mfx-btn mfx-btn-secondary">Browse courts</a>
                    <?php elseif ($state === 'cancelled'): ?>
                        <p class="mfx-note">This match was cancelled by the organizer</p>
                        <a href="<?php echo e(base_url('pages/courts.php')); ?>" class="mfx-btn mfx-btn-secondary">Browse courts</a>
                    <?php elseif ($state === 'error'): ?>
                        <p class="mfx-note">This match link is invalid.</p>
                    <?php endif; ?>
                </div>
                <?php if ($state === 'full'): ?>
                    <p class="mfx-note">Full (<?php echo (int)$players; ?> of <?php echo (int)$players; ?>)</p>
                <?php endif; ?>
                <?php if ($state === 'open_guest' || $state === 'open_member'): ?>
                    <p class="mfx-fee">
                        Settle your Rs <?php echo number_format($yourShare, 0); ?> share directly with <?php echo e($organizerName); ?> via eSewa, Khalti, or cash.
                    </p>
                <?php endif; ?>
            </div>

            <!-- FOOTER ACTIONS -->
            <div class="mfx-foot-actions">
                <a href="<?php echo e($icsUrl); ?>" class="mfx-foot-link">Add to Calendar (.ics)</a>
                <a href="<?php echo e($groundUrl); ?>" class="mfx-foot-link">View Venue Profile &rarr;</a>
            </div>

        </div>
    </article>
</div>

<!-- Guest bottom sheet (open_guest state) -->
<div class="mfx-sheet" id="mfxSheet" role="dialog" aria-modal="true" aria-labelledby="sheetTitle" hidden>
    <div class="mfx-sheet-backdrop" data-sheet-close></div>
    <div class="mfx-sheet-panel">
        <h2 id="sheetTitle">Join this match</h2>
        <p>No account needed. Just your first name and phone.</p>
        <form id="mfxSheetForm">
            <label>First name<input type="text" name="first_name" required autocomplete="given-name"></label>
            <label>Phone<input type="tel" name="phone" required autocomplete="tel"></label>
            <div class="mfx-sheet-actions">
                <button type="submit" class="mfx-btn mfx-btn-primary">Join</button>
                <button type="button" class="mfx-btn mfx-btn-secondary" data-sheet-close>Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var wrap = document.querySelector('.mfx-wrap');
    if (!wrap) return;
    var state = wrap.getAttribute('data-state') || 'open_guest';
    var endpoint = wrap.getAttribute('data-endpoint') || '';
    var csrf = wrap.getAttribute('data-csrf') || '';
    var ref = wrap.getAttribute('data-ref') || '';
    var bkId = wrap.getAttribute('data-id') || '';
    var auth = wrap.getAttribute('data-auth') === '1';

    // Bottom sheet controls
    var sheet = document.getElementById('mfxSheet');
    var sheetOpen = document.querySelector('[data-sheet-open]');
    var sheetCloses = document.querySelectorAll('[data-sheet-close]');
    if (sheetOpen && sheet) {
        sheetOpen.addEventListener('click', function () {
            sheet.hidden = false;
            sheetOpen.setAttribute('aria-expanded', 'true');
        });
    }
    sheetCloses.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (sheet) sheet.hidden = true;
            if (sheetOpen) sheetOpen.setAttribute('aria-expanded', 'false');
        });
    });

    // Guest join via sheet
    var sheetForm = document.getElementById('mfxSheetForm');
    if (sheetForm) {
        sheetForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(sheetForm);
            fd.append('csrf_token', csrf);
            fd.append('status', 'in');
            if (ref) fd.append('ref', ref); else fd.append('id', bkId);
            var guestKey = 'g-' + Math.random().toString(36).slice(2, 10);
            fd.append('guest_key', guestKey);
            fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.ok) {
                        window.location.href = window.location.pathname + '?ref=' + encodeURIComponent(ref) + '&state=joined';
                    } else {
                        alert(d.error || 'Could not join.');
                    }
                })
                .catch(function () { alert('Network error.'); });
        });
    }

    // Direct actions (member join, decline, cancel, waitlist, reopen)
    function postStatus(st, extra) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('status', st);
        if (ref) fd.append('ref', ref); else fd.append('id', bkId);
        if (extra) { for (var k in extra) fd.append(k, extra[k]); }
        fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    var next = st === 'in' ? 'joined' : (st === 'out' ? 'declined' : (st === 'cancel' ? 'open_member' : 'open_member'));
                    if (st === 'waitlist') next = 'waitlisted';
                    if (st === 'cancel_waitlist') next = 'open_member';
                    window.location.href = window.location.pathname + (ref ? '?ref=' + encodeURIComponent(ref) : '?id=' + encodeURIComponent(bkId)) + '&state=' + next;
                } else { alert(d.error || 'Action failed.'); }
            })
            .catch(function () { alert('Network error.'); });
    }

    document.querySelectorAll('[data-join]').forEach(function (b) { b.addEventListener('click', function () { postStatus('in'); }); });
    document.querySelectorAll('[data-decline]').forEach(function (b) { b.addEventListener('click', function () { postStatus('out'); }); });
    document.querySelectorAll('[data-cancel]').forEach(function (b) { b.addEventListener('click', function () { postStatus('cancel'); }); });
    document.querySelectorAll('[data-waitlist]').forEach(function (b) { b.addEventListener('click', function () { postStatus('waitlist'); }); });
    document.querySelectorAll('[data-cancel-waitlist]').forEach(function (b) { b.addEventListener('click', function () { postStatus('cancel_waitlist'); }); });
    document.querySelectorAll('[data-reopen]').forEach(function (b) { b.addEventListener('click', function () { postStatus('reopen'); }); });

    // Dev-only: change state via URL hash for previewing (?state=joined)
    var devStates = ['open_guest','open_member','joined','declined','full','waitlisted','past','cancelled','loading','error'];
    if (location.search.indexOf('debug=1') !== -1) {
        var div = document.createElement('div');
        div.style.cssText = 'position:fixed;bottom:8px;right:8px;background:#111;color:#fff;padding:6px 10px;border-radius:6px;font-size:12px;z-index:9999';
        div.innerHTML = 'State: <strong>' + state + '</strong> | <a href="?state=open_guest" style="color:#6cf">guest</a> <a href="?state=open_member" style="color:#6cf">member</a> <a href="?state=joined" style="color:#6cf">joined</a> <a href="?state=declined" style="color:#6cf">declined</a> <a href="?state=full" style="color:#6cf">full</a>';
        document.body.appendChild(div);
    }
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

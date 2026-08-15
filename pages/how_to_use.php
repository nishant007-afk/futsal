<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Prefer the dedicated demo court so people can simulate the full flow.
$demo_ground = null;
$res = $conn->query("SELECT id FROM grounds WHERE slug = 'demo-court' AND is_active = 1 LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $demo_ground = ground_detail_url((int) $row['id']);
}
if (!$demo_ground) {
    $res = $conn->query('SELECT id FROM grounds WHERE is_active = 1 ORDER BY id LIMIT 1');
    if ($res && $row = $res->fetch_assoc()) {
        $demo_ground = ground_detail_url((int) $row['id']);
    }
}

$page_title = 'How to use';
$page_description = 'Step-by-step guides for players and managers, from finding your first court to booking a slot and paying with a QR code.';

require __DIR__ . '/../includes/header.php';
?>

<div class="content-hero">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="nav-back mob-title-back" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>How to use</h1>
    </div>
    <p>Here&rsquo;s how to get going, step by step. Pick your role and follow along. Every step links to the real page, so you can try it as you read.</p>
    <div class="howto-hero-actions">
        <a href="#players" class="btn btn-primary"><i class="fa-solid fa-user"></i> I&rsquo;m a player</a>
        <a href="#managers" class="btn btn-outline"><i class="fa-solid fa-store"></i> I&rsquo;m a manager</a>
    </div>
</div>

<!-- ============ PLAYERS ============ -->
<div class="howto-role-head" id="players">
    <span class="howto-role-icon"><i class="fa-solid fa-user"></i></span>
    <div>
        <h2>For players</h2>
        <p>From creating your account to kicking off your first game.</p>
    </div>
</div>

<div class="howto-grid">
    <div class="detail-box howto-step">
        <span class="step-num">Step 1</span>
        <h3>Create your account</h3>
        <p>Make a free account with your name and email, pick the <strong>Player</strong> role, and you&rsquo;re in. No phone calls, no waiting.</p>
        <div class="howto-actions">
            <a href="<?php echo base_url('pages/register.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-user-plus"></i> Create account</a>
        </div>
    </div>

    <div class="detail-box howto-step">
        <span class="step-num">Step 2</span>
        <h3>Find a court</h3>
        <p>Search by name or city, or open the map. Compare prices, capacity and what each court offers.</p>
        <div class="howto-actions">
            <a href="<?php echo base_url('pages/courts.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-layer-group"></i> Browse courts</a>
            <a href="<?php echo base_url('pages/map.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-map-location-dot"></i> Open the map</a>
        </div>
    </div>

    <div class="detail-box howto-step">
        <span class="step-num">Step 3</span>
        <h3>Open a court</h3>
        <p>A court page shows photos, reviews, live availability and prices. Choose a date and see the free hours at a glance.</p>
        <div class="howto-actions">
            <?php if ($demo_ground): ?>
                <a href="<?php echo e($demo_ground); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-arrow-up-right-from-square"></i> Try the demo court</a>
            <?php else: ?>
                <a href="<?php echo base_url('pages/courts.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-layer-group"></i> Browse courts</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="detail-box howto-step">
        <span class="step-num">Step 4</span>
        <h3>Pick a slot and book</h3>
        <p>Tap a free time on your chosen day, confirm, and the slot is yours. Then just pick how you&rsquo;d like to pay.</p>
        <div class="howto-actions">
            <?php if ($demo_ground): ?>
                <a href="<?php echo e($demo_ground); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-calendar-check"></i> Book a demo slot</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="detail-box howto-step">
        <span class="step-num">Step 5</span>
        <h3>Pay for your game</h3>
        <p>Choose to pay a small advance now and settle the rest at the court, or pay the full amount upfront. When you pick an option, the court&rsquo;s QR code is shown. Scan it with your payment app (Khalti/eSewa/IME Pay etc.) to transfer the amount. Your slot is confirmed instantly and a receipt is emailed to you.</p>
        <div class="howto-actions">
            <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-wallet"></i> Manage payments</a>
        </div>
    </div>

    <div class="detail-box howto-step">
        <span class="step-num">Step 6</span>
        <h3>Track your bookings</h3>
        <p>See what&rsquo;s coming up and what&rsquo;s done, view details and receipts, reschedule, or cancel within the refund policy.</p>
        <div class="howto-actions">
            <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-calendar-check"></i> My bookings</a>
        </div>
    </div>

    <div class="detail-box howto-step">
        <span class="step-num">Step 7</span>
        <h3>Manage your profile</h3>
        <p>Add a photo, update your details, change your password, and set how often you want to hear from us.</p>
        <div class="howto-actions">
            <a href="<?php echo base_url('pages/profile.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-user-gear"></i> Open profile</a>
        </div>
    </div>
</div>

<!-- ============ MANAGERS ============ -->
<div class="howto-role-head" id="managers">
    <span class="howto-role-icon manager"><i class="fa-solid fa-store"></i></span>
    <div>
        <h2>For managers</h2>
        <p>From signing up to keeping your courts full and knowing who has paid.</p>
    </div>
</div>

<div class="howto-grid">
    <div class="detail-box howto-step">
        <span class="step-num">Step 1</span>
        <h3>Create a manager account</h3>
        <p>Sign up and pick the <strong>Manager</strong> role. You&rsquo;ll land straight on your dashboard, ready to add your first court.</p>
        <div class="howto-actions">
            <a href="<?php echo base_url('pages/register.php?role=manager'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-plus"></i> Sign up as manager</a>
        </div>
    </div>

    <div class="detail-box howto-step">
        <span class="step-num">Step 2</span>
        <h3>See your dashboard</h3>
        <p>Your dashboard gives you the big picture: revenue, upcoming bookings, and how each court is doing.</p>
        <div class="howto-actions">
            <a href="<?php echo base_url('manager/dashboard.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-gauge-high"></i> Open dashboard</a>
        </div>
    </div>

    <div class="detail-box howto-step">
        <span class="step-num">Step 3</span>
        <h3>Add your first court</h3>
        <p>In &ldquo;My Grounds&rdquo;, click <strong>Add Ground</strong>. Fill in the name, location, capacity, price and opening hours, then drop a pin on the map.</p>
        <div class="howto-actions">
            <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-store"></i> My grounds</a>
        </div>
    </div>

    <div class="detail-box howto-step">
        <span class="step-num">Step 4</span>
        <h3>Manage your courts</h3>
        <p>Edit prices and details, change photos, or make a court inactive so it pauses bookings without deleting it.</p>
        <div class="howto-actions">
            <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-pen-to-square"></i> Edit a court</a>
        </div>
    </div>

    <div class="detail-box howto-step">
        <span class="step-num">Step 5</span>
        <h3>Manage bookings</h3>
        <p>Bookings confirm instantly. See who&rsquo;s coming, mark a payment as paid at the court, or cancel a slot if you can&rsquo;t host.</p>
        <div class="howto-actions">
            <a href="<?php echo base_url('manager/bookings.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-list-check"></i> Open bookings</a>
        </div>
    </div>

    <div class="detail-box howto-step">
        <span class="step-num">Step 6</span>
        <h3>Run promos and make money</h3>
        <p>Create discount codes to fill quiet hours, and always know who has paid (Paid, Partial or Unpaid) from your dashboard.</p>
        <div class="howto-actions">
            <a href="<?php echo base_url('manager/promos.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-tags"></i> Run promos</a>
            <a href="<?php echo base_url('manager/dashboard.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-hand-holding-dollar"></i> Make money</a>
        </div>
    </div>
</div>

<div class="notice howto-foot">
    <i class="fa-solid fa-circle-question"></i>
    <span>Need more detail on a specific step? <a href="<?php echo base_url('pages/page.php?slug=help'); ?>">Visit the help centre</a> or <a href="<?php echo base_url('pages/page.php?slug=contact'); ?>">contact us</a>.</span>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
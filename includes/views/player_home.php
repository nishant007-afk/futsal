<?php
$me = current_user();
$today = date('Y-m-d');
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$stats = $conn->query(
    'SELECT COUNT(*) AS total,
            SUM(status = "confirmed") AS confirmed,
            SUM(status = "cancelled") AS cancelled,
            SUM(payment_status = "unpaid" AND status != "cancelled") AS unpaid
     FROM bookings WHERE user_id = ' . (int)$_SESSION['user_id']
)->fetch_assoc();

$upcoming = $conn->query(
    'SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status, b.total_price, b.payment_status, b.amount_paid, g.name AS ground_name, g.location
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     WHERE b.user_id = ' . (int)$_SESSION['user_id'] . " AND b.booking_date >= '$today' AND b.status != 'cancelled'
     ORDER BY b.booking_date ASC, b.start_time ASC LIMIT 3"
)->fetch_all(MYSQLI_ASSOC);

$grounds = $conn->query('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 ORDER BY g.id LIMIT 6')->fetch_all(MYSQLI_ASSOC);
?>

<section class="welcome reveal">
    <span class="eyebrow">Player dashboard</span>
    <h1><span id="greeting"><?php echo $greeting; ?></span>, <?php echo e($me['name']); ?></h1>
    <p>Here's what's coming up on your schedule, and a few courts ready for a game.</p>
    <div class="actions" style="margin-top:18px;">
        <a href="<?php echo base_url('index.php#courts'); ?>" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass-location"></i> Find a court</a>
        <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="btn btn-outline"><i class="fa-solid fa-calendar-check"></i> My bookings</a>
    </div>
</section>

<?php if ((int)$stats['unpaid'] > 0): ?>
    <div class="notice reveal">
        <i class="fa-solid fa-wallet"></i>
        <span><strong><?php echo (int)$stats['unpaid']; ?> booking<?php echo $stats['unpaid'] > 1 ? 's need' : ' needs'; ?> payment</strong> to secure your slot.</span>
        <a href="<?php echo base_url('pages/my_bookings.php'); ?>">Pay now</a>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat">
        <h3>Total bookings</h3>
        <p><?php echo (int)$stats['total']; ?></p>
    </div>
    <div class="stat">
        <h3>Confirmed</h3>
        <p><?php echo (int)$stats['confirmed']; ?></p>
    </div>
    <div class="stat">
        <h3>Awaiting payment</h3>
        <p><?php echo (int)$stats['unpaid']; ?></p>
    </div>
    <div class="stat">
        <h3>Cancelled</h3>
        <p><?php echo (int)$stats['cancelled']; ?></p>
    </div>
</div>

<section class="section section-pad-top" style="padding-top:8px;">
    <div class="section-head reveal">
        <span class="eyebrow">Your schedule</span>
        <h2 class="section-title">Upcoming bookings</h2>
    </div>

    <?php if (!$upcoming): ?>
        <div class="empty">
            <span class="big"><i class="fa-regular fa-calendar-xmark"></i></span>
            <h3>Nothing scheduled yet</h3>
            <p>Pick a court below and make it a match.</p>
            <a href="<?php echo grounds_list_url(); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass-location"></i> Browse courts</a>
        </div>
    <?php else: ?>
        <div class="mbookings reveal">
            <?php foreach ($upcoming as $b): ?>
                <?php booking_card($b); ?>
            <?php endforeach; ?>
        </div>
        <p class="section-foot"><a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="inline-link">View all bookings <i class="fa-solid fa-arrow-right" style="font-size:11px;"></i></a></p>
    <?php endif; ?>
</section>

<section class="section section-alt" id="courts">
    <div class="section-head reveal">
        <span class="eyebrow">Book now</span>
        <h2 class="section-title">Courts available near you</h2>
        <p class="section-sub">Tap a court to see its free hours and grab a slot for your team.</p>
    </div>
    <?php if (!$grounds): ?>
        <div class="empty"><span class="big"><i class="fa-solid fa-futbol"></i></span><h3>No grounds available right now</h3><p>Check back soon &middot; courts open for booking will appear here.</p></div>
    <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($grounds as $ground) { ground_card_html($ground); } ?>
        </div>
        <div class="section-foot reveal" style="text-align:center;">
            <a href="<?php echo grounds_list_url(); ?>" class="btn btn-outline btn-lg"><i class="fa-solid fa-layer-group"></i> View all courts</a>
        </div>
    <?php endif; ?>
</section>

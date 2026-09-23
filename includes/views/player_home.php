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
    'SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status, b.total_price, b.discount, b.promo_code, b.payment_status, b.payment_method, b.amount_paid, g.name AS ground_name, g.location
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     WHERE b.user_id = ' . (int)$_SESSION['user_id'] . " AND b.booking_date >= '$today' AND b.status != 'cancelled'
     ORDER BY b.booking_date ASC, b.start_time ASC LIMIT 3"
)->fetch_all(MYSQLI_ASSOC);

$grounds = $conn->query('SELECT g.*, u.name AS owner_name FROM grounds g LEFT JOIN users u ON u.id = g.manager_id WHERE g.is_active = 1 ORDER BY g.id LIMIT 6')->fetch_all(MYSQLI_ASSOC);
?>

<section class="welcome reveal">
    <div class="welcome-head">
        <div>
            <h1><span id="greeting"><?php echo $greeting; ?></span>, <?php echo e($me['name']); ?></h1>
        </div>
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

<section class="section section-pad-top section-top-compact">
    <div class="section-head section-head-row reveal">
        <div>
            <h2 class="section-title">Upcoming bookings</h2>
            <?php if ($upcoming): ?>
                <p class="section-sub"><?php echo count($upcoming); ?> game<?php echo count($upcoming) === 1 ? '' : 's'; ?> on your schedule</p>
            <?php endif; ?>
        </div>
        <?php if ($upcoming): ?>
            <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="btn btn-outline btn-sm section-head-link"><i class="fa-solid fa-calendar-check"></i> View all</a>
        <?php endif; ?>
    </div>

    <?php if (!$upcoming): ?>
        <?php empty_state('fa-regular fa-calendar-xmark', 'Nothing scheduled yet', '', grounds_list_url(), 'Browse courts', 'btn btn-primary btn-sm'); ?>
    <?php else: ?>
        <div class="table-wrap reveal">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Court</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th class="num">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming as $b): ?>
                        <?php
                        $needsPayment = $b['status'] === 'confirmed' && $b['payment_status'] !== 'paid';
                        $netDue = max(0, (float)$b['total_price'] - (float)($b['discount'] ?? 0));
                        if ($b['status'] === 'cancelled') {
                            $statusText = 'Cancelled';
                            $statusIcon = 'fa-circle-xmark';
                            $statusClass = 'status-cancelled';
                        } elseif ($b['payment_status'] === 'paid') {
                            $statusText = 'Confirmed · Paid';
                            $statusIcon = 'fa-circle-check';
                            $statusClass = 'status-confirmed';
                        } elseif ($b['payment_status'] === 'partial') {
                            $statusText = 'Partially paid';
                            $statusIcon = 'fa-circle-half-stroke';
                            $statusClass = 'status-pending';
                        } elseif ($needsPayment || $b['status'] === 'pending') {
                            $statusText = 'Payment pending';
                            $statusIcon = 'fa-clock';
                            $statusClass = 'status-pending';
                        } else {
                            $statusText = 'Confirmed';
                            $statusIcon = 'fa-circle-check';
                            $statusClass = 'status-confirmed';
                        }
                        ?>
                        <tr>
                            <td data-label="Court">
                                <div class="mbt-court">
                                    <strong><?php echo e($b['ground_name']); ?></strong>
                                    <span class="muted"><i class="fa-solid fa-location-dot"></i> <?php echo e($b['location']); ?></span>
                                </div>
                            </td>
                            <td data-label="Date">
                                <?php echo e(date('D, M j', strtotime($b['booking_date']))); ?><br>
                                <span class="muted" style="font-size:12px;"><?php echo e(date('Y', strtotime($b['booking_date']))); ?></span>
                            </td>
                            <td class="mbt-time" data-label="Time"><?php echo e(substr($b['start_time'], 0, 5)); ?> &ndash; <?php echo e(substr($b['end_time'], 0, 5)); ?></td>
                            <td data-label="Status"><span class="status-badge <?php echo $statusClass; ?>"><i class="fa-solid <?php echo $statusIcon; ?>"></i> <?php echo e($statusText); ?></span></td>
                            <td class="num strong" data-label="Total">
                                Rs <?php echo number_format($netDue, 0); ?>
                                <?php if ((float)$b['discount'] > 0): ?>
                                    <br><span class="muted" style="font-size:12px;text-decoration:line-through;">Rs <?php echo number_format((float)$b['total_price'], 0); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="mbt-actions" data-label="">
                                <div class="mbt-actions-row">
                                    <?php if ($needsPayment): ?>
                                        <a href="<?php echo base_url('pages/payment.php?booking_id=' . (int)$b['id']); ?>" class="mb-cta mb-cta-pay"><i class="fa-solid fa-wallet"></i> Pay</a>
                                    <?php endif; ?>
                                    <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="btn btn-outline btn-sm" title="View details"><i class="fa-solid fa-chevron-right"></i><span class="sr-only">Details</span></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="section section-alt section-courts-pad" id="courts">
    <div class="section-head reveal section-head-offset">
        <h2 class="section-title">Courts available near you</h2>
    </div>
    <?php if (!$grounds): ?>
        <?php empty_state('fa-solid fa-futbol', 'No grounds available right now', 'Check back soon &middot; courts open for booking will appear here.'); ?>
    <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($grounds as $ground) { ground_card_html($ground); } ?>
        </div>
        <div class="section-foot reveal center">
            <a href="<?php echo grounds_list_url(); ?>" class="btn btn-outline btn-lg"><i class="fa-solid fa-layer-group"></i> View all courts</a>
        </div>
    <?php endif; ?>
</section>

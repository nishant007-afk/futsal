<?php
require_once __DIR__ . '/../config/db.php';
require_manager();

$myGrounds = $conn->query(
    'SELECT id, name FROM grounds WHERE manager_id = ' . (int)$_SESSION['user_id']
)->fetch_all(MYSQLI_ASSOC);

$ids = array_map(fn($g) => (int)$g['id'], $myGrounds);
$idList = implode(',', $ids ?: [0]);

$totalGrounds = count($myGrounds);
$todayBookings = $conn->query("SELECT COUNT(*) c FROM bookings WHERE ground_id IN ($idList) AND booking_date = CURDATE() AND status != 'cancelled'")->fetch_assoc()['c'];
$paidCount = $conn->query("SELECT COUNT(*) c FROM bookings WHERE ground_id IN ($idList) AND payment_status = 'paid' AND status != 'cancelled'")->fetch_assoc()['c'];
$revenue = $conn->query("SELECT COALESCE(SUM(total_price), 0) s FROM bookings WHERE ground_id IN ($idList) AND status != 'cancelled'")->fetch_assoc()['s'];
$weekDays = [];
$weekMax = 1;
for ($d = 6; $d >= 0; $d--) {
    $day = date('Y-m-d', strtotime("-$d days"));
    $c = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE ground_id IN ($idList) AND booking_date = '$day' AND status != 'cancelled'")->fetch_assoc()['c'];
    $weekDays[] = ['day' => date('D', strtotime($day)), 'short' => date('M j', strtotime($day)), 'count' => $c];
    if ($c > $weekMax) { $weekMax = $c; }
}
$weekRevenue = (float)$conn->query("SELECT COALESCE(SUM(total_price), 0) s FROM bookings WHERE ground_id IN ($idList) AND booking_date >= CURDATE() - INTERVAL 6 DAY AND status != 'cancelled'")->fetch_assoc()['s'];
$subStatus = subscription_status((int)$_SESSION['user_id']);
$setupFee = manager_setup_fee();
$monthlyFee = manager_monthly_fee();

// Payout history from settlements
$settlements = $conn->query(
    "SELECT period_start, period_end, gross, fee, payout, paid_at
     FROM settlements
     WHERE manager_id = " . (int)$_SESSION['user_id'] . "
     ORDER BY period_end DESC
     LIMIT 12"
)->fetch_all(MYSQLI_ASSOC);

$recent = $conn->query(
    "SELECT b.id, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.amount_paid, b.created_at,
            g.name AS ground_name, u.name AS user_name
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     WHERE b.ground_id IN ($idList)
     ORDER BY b.created_at DESC
     LIMIT 6"
)->fetch_all(MYSQLI_ASSOC);

$groundSummary = $conn->query(
    "SELECT g.id, g.name,
            SUM(CASE WHEN b.status != 'cancelled' THEN 1 ELSE 0 END) AS booking_count,
            SUM(CASE WHEN b.status != 'cancelled' AND b.payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
            COALESCE(SUM(CASE WHEN b.status != 'cancelled' THEN b.total_price ELSE 0 END), 0) AS gross
     FROM grounds g
     LEFT JOIN bookings b ON b.ground_id = g.id
     WHERE g.manager_id = " . (int)$_SESSION['user_id'] . "
     GROUP BY g.id, g.name
     ORDER BY gross DESC"
)->fetch_all(MYSQLI_ASSOC);

$grossTotal = 0;
$platformFeeTotal = 0;
foreach ($groundSummary as $gs) {
    $grossTotal += (float)$gs['gross'];
    $platformFeeTotal += platform_fee_amount((float)$gs['gross']);
}
$payoutTotal = $grossTotal - $platformFeeTotal;

$page_title = 'Manager Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h2><i class="fa-solid fa-gauge-high"></i> Manager Dashboard</h2>
    <div class="actions">
        <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Ground</a>
        <a href="<?php echo base_url('manager/bookings.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-list-check"></i> All Bookings</a>
        <button type="button" class="btn btn-outline btn-sm" id="nearMeBtn" aria-label="Use my location"><i class="fa-solid fa-walkie-talkie"></i> Use my location</button>
    </div>
</div>

<?php if (!$subStatus['active']): ?>
    <div class="toast toast-warning toast-inline reveal" role="status">
        <div class="toast-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
        <div class="toast-content">
            <div class="toast-msg"><?php echo e($subStatus['label']); ?> - your courts are hidden from players. Pay the setup fee or renew your monthly service charge to go live again. Contact the platform admin.</div>
        </div>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat reveal">
        <div class="stat-icon"><i class="fa-solid fa-store"></i></div>
        <h3>My Grounds</h3>
        <p><?php echo $totalGrounds; ?></p>
    </div>
    <div class="stat reveal">
        <div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
        <h3>Bookings Today</h3>
        <p><?php echo $todayBookings; ?></p>
    </div>
    <div class="stat reveal">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <h3>Fully Paid</h3>
        <p><?php echo $paidCount; ?></p>
    </div>
    <div class="stat reveal">
        <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
        <h3>Revenue</h3>
        <p class="stat-amount"><?php echo format_price($revenue); ?></p>
        <!-- <span class="muted" style="font-size:12px;">All yours, no commission</span> -->
    </div>
    <div class="stat reveal">
        <div class="stat-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        <h3>Subscription</h3>
        <p class="stat-amount"><?php echo e($subStatus['label']); ?></p>
        <span class="muted">
            <?php if ($subStatus['sub'] && $subStatus['sub']['period_end']): ?>
                Next renewal: <?php echo e(date('M j', strtotime($subStatus['sub']['period_end']))); ?>
            <?php else: ?>
                Rs <?php echo number_format($setupFee, 0); ?> setup &middot; Rs <?php echo number_format($monthlyFee, 0); ?>/mo
            <?php endif; ?>
        </span>
    </div>
</div>

<h3 class="reveal dash-section"><i class="fa-solid fa-chart-column"></i> Bookings this week</h3>
<div class="chart-wrap reveal" style="margin-bottom:30px;">
    <div class="chart-legend"><span><i class="fa-solid fa-calendar-day"></i> Last 7 days</span><span class="strong"><?php echo format_price($weekRevenue); ?> gross &middot; <?php echo (int)array_sum(array_column($weekDays, 'count')); ?> bookings</span></div>
    <div class="bar-chart" role="img" aria-label="Bookings per day for the last 7 days">
        <?php foreach ($weekDays as $wd): ?>
            <?php $pct = $weekMax > 0 ? (int)round(($wd['count'] / $weekMax) * 100) : 0; ?>
            <div class="bar-group">
                <div class="bar-track"><div class="bar-fill" style="height:<?php echo max($pct, 6); ?>%;"></div></div>
                <div class="bar-value"><?php echo (int)$wd['count']; ?></div>
                <div class="bar-label"><?php echo e($wd['short']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<h3 class="reveal dash-section"><i class="fa-solid fa-chart-column"></i> Financial Summary</h3>
<div class="table-wrap reveal" style="margin-bottom:30px;">
    <table class="data-table">
        <thead>
            <tr>
                <th>Ground</th>
                <th>Bookings</th>
                <th>Paid</th>
                <th>Gross Revenue</th>
                <th>Platform Fee</th>
                <th>Your Payout</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$groundSummary): ?>
                <tr><td colspan="6" class="muted table-empty">Add a ground to see your financial summary.</td></tr>
            <?php else: ?>
                <?php foreach ($groundSummary as $gs): ?>
                    <?php $gross = (float)$gs['gross']; $fee = platform_fee_amount($gross); ?>
                    <tr>
                        <td class="strong"><?php echo e($gs['name']); ?></td>
                        <td><?php echo (int)$gs['booking_count']; ?></td>
                        <td><?php echo (int)$gs['paid_count']; ?></td>
                        <td><?php echo format_price($gross); ?></td>
                        <td><?php echo format_price($fee); ?>
                            <span class="muted" style="font-size:11px">(<?php echo (int)platform_fee_percent(); ?>%)</span>
                        </td>
                        <td class="strong"><?php echo format_price(manager_payout($gross)); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-total">
                    <td class="strong">Total</td>
                    <td><?php echo (int)array_sum(array_column($groundSummary, 'booking_count')); ?></td>
                    <td><?php echo (int)array_sum(array_column($groundSummary, 'paid_count')); ?></td>
                    <td><?php echo format_price($grossTotal); ?></td>
                    <td><?php echo format_price($platformFeeTotal); ?></td>
                    <td class="strong"><?php echo format_price($payoutTotal); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h3 class="reveal block-title"><i class="fa-solid fa-money-bill-transfer"></i> Payout History</h3>
<div class="table-wrap reveal">
    <table class="data-table">
        <thead>
            <tr>
                <th>Period</th>
                <th>Gross Revenue</th>
                <th>Platform Fee</th>
                <th>Your Payout</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$settlements): ?>
                <tr><td colspan="5" class="muted table-empty">No payouts yet. They appear here after each billing period.</td></tr>
            <?php else: ?>
                <?php foreach ($settlements as $s): ?>
                    <tr>
                        <td><?php echo e(date('M j, Y', strtotime($s['period_start']))); ?> &ndash; <?php echo e(date('M j, Y', strtotime($s['period_end']))); ?></td>
                        <td><?php echo format_price((float)$s['gross']); ?></td>
                        <td><?php echo format_price((float)$s['fee']); ?></td>
                        <td class="strong"><?php echo format_price((float)$s['payout']); ?></td>
                        <td>
                            <?php if ($s['paid_at']): ?>
                                <span class="badge badge-confirmed">Paid <?php echo e(date('M j, Y', strtotime($s['paid_at']))); ?></span>
                            <?php else: ?>
                                <span class="badge badge-pending">Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h3 class="reveal block-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Bookings on My Grounds</h3>
<?php if (!$recent): ?>
    <div class="empty reveal"><span class="big"><i class="fa-regular fa-calendar-xmark"></i></span><h3>No bookings yet</h3><p>New bookings on your grounds will appear here.</p></div>
<?php else: ?>
    <div class="mbookings reveal">
        <?php foreach ($recent as $b): ?>
            <?php booking_card_mini($b, 'user'); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

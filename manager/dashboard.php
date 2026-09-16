<?php
require_once __DIR__ . '/../config/db.php';
require_manager();

$days = (int)($_GET['days'] ?? 7);
if (!in_array($days, [7, 14, 30], true)) {
    $days = 7;
}

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
for ($d = $days - 1; $d >= 0; $d--) {
    $day = date('Y-m-d', strtotime("-$d days"));
    $c = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE ground_id IN ($idList) AND booking_date = '$day' AND status != 'cancelled'")->fetch_assoc()['c'];
    $weekDays[] = ['day' => date('D', strtotime($day)), 'short' => date('M j', strtotime($day)), 'count' => $c];
    if ($c > $weekMax) { $weekMax = $c; }
}
$weekRevenue = (float)$conn->query("SELECT COALESCE(SUM(total_price), 0) s FROM bookings WHERE ground_id IN ($idList) AND booking_date >= CURDATE() - INTERVAL " . ($days - 1) . " DAY AND status != 'cancelled'")->fetch_assoc()['s'];
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
            b.payment_status, b.amount_paid, b.payment_method, b.created_at,
            g.name AS ground_name, u.name AS user_name
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     WHERE b.ground_id IN ($idList)
     ORDER BY b.created_at DESC
     LIMIT 6"
)->fetch_all(MYSQLI_ASSOC);

$todayList = $conn->query(
    "SELECT b.id, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.amount_paid, b.payment_method, b.created_at,
            g.name AS ground_name, u.name AS user_name
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     WHERE b.ground_id IN ($idList) AND b.booking_date = CURDATE() AND b.status != 'cancelled'
     ORDER BY b.start_time ASC
     LIMIT 6"
)->fetch_all(MYSQLI_ASSOC);

$todaySlotData = [];
// Single GROUP BY instead of per-ground COUNT (N+1 fix).
$bookedMap = [];
if (!empty($myGrounds)) {
    $gids = array_map(fn($g) => (int)$g['id'], $myGrounds);
    $ph = implode(',', array_fill(0, count($gids), '?'));
    $types = str_repeat('i', count($gids));
    $cStmt = $conn->prepare("SELECT ground_id, COUNT(*) c FROM bookings WHERE ground_id IN ($ph) AND booking_date = CURDATE() AND status != 'cancelled' GROUP BY ground_id");
    $cStmt->bind_param($types, ...$gids);
    $cStmt->execute();
    foreach ($cStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $bookedMap[(int)$r['ground_id']] = (int)$r['c'];
    }
    $cStmt->close();
}
foreach ($myGrounds as $g) {
    $totalSlots = count(slots_for_day(date('Y-m-d'), (int)$g['id']));
    $todaySlotData[] = [
        'name' => $g['name'],
        'total' => $totalSlots,
        'booked' => $bookedMap[(int)$g['id']] ?? 0,
    ];
}
$todaySlotBooked = array_sum(array_column($todaySlotData, 'booked'));
$todaySlotTotal = array_sum(array_column($todaySlotData, 'total'));

// Needs-attention: upcoming unpaid bookings + grounds fully booked today
$attentionUnpaid = $conn->query(
    "SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.amount_paid, b.total_price,
            g.name AS ground_name, u.name AS user_name
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     WHERE b.ground_id IN ($idList) AND b.status = 'confirmed' AND b.payment_status = 'unpaid'
           AND b.booking_date >= CURDATE()
     ORDER BY b.booking_date ASC, b.start_time ASC
     LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);
$attentionFull = [];
foreach ($todaySlotData as $ts) {
    if ($ts['total'] > 0 && $ts['booked'] >= $ts['total']) {
        $attentionFull[] = $ts['name'];
    }
}

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
    <h2>Manager Dashboard</h2>
    <div class="actions">
        <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Ground</a>
        <a href="<?php echo base_url('manager/bookings.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-list-check"></i> All Bookings</a>
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

<?php if ($attentionUnpaid || $attentionFull): ?>
    <div class="attention-strip reveal">
        <?php if ($attentionUnpaid): ?>
            <a href="<?php echo base_url('manager/bookings.php?payment=unpaid&status=confirmed'); ?>" class="attention-item">
                <i class="fa-solid fa-wallet"></i>
                <span><strong><?php echo count($attentionUnpaid); ?> booking<?php echo count($attentionUnpaid) > 1 ? 's' : ''; ?> need payment</strong> <em><?php echo count($attentionUnpaid) > 1 ? 'The first is ' . e($attentionUnpaid[0]['user_name']) . ' at ' . e($attentionUnpaid[0]['ground_name']) . '.' : e($attentionUnpaid[0]['user_name']) . ' at ' . e($attentionUnpaid[0]['ground_name']) . ' - ' . e(date('D, M j', strtotime($attentionUnpaid[0]['booking_date']))); ?></em></span>
                <i class="fa-solid fa-arrow-right attention-go"></i>
            </a>
        <?php endif; ?>
        <?php if ($attentionFull): ?>
            <a href="<?php echo base_url('manager/dashboard.php#todaySlots'); ?>" class="attention-item">
                <i class="fa-solid fa-signal"></i>
                <span><strong>Fully booked today:</strong> <em><?php echo e(implode(', ', array_slice($attentionFull, 0, 3))); ?><?php echo count($attentionFull) > 3 ? ' +' . (count($attentionFull) - 3) . ' more' : ''; ?></em></span>
                <i class="fa-solid fa-arrow-right attention-go"></i>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat reveal">
        <h3>My Grounds</h3>
        <p><?php echo $totalGrounds; ?></p>
    </div>
    <div class="stat reveal">
        <h3>Bookings Today</h3>
        <p><?php echo $todayBookings; ?></p>
    </div>
    <div class="stat reveal">
        <h3>Fully Paid</h3>
        <p><?php echo $paidCount; ?></p>
    </div>
    <div class="stat reveal">
        <h3>Revenue</h3>
        <p class="stat-amount"><?php echo format_price($revenue); ?></p>
    </div>
    <div class="stat reveal">
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

<h3 class="reveal dash-section" id="todaySlots">Today's slots</h3>
<div class="chart-wrap reveal" style="margin-bottom:30px;">
    <div class="chart-legend"><span>Capacity today</span><span class="strong"><?php echo $todaySlotBooked; ?> of <?php echo $todaySlotTotal; ?> hours booked</span></div>
    <?php if (!$todaySlotData): ?>
        <p class="muted table-empty">Add a ground to see today's slot availability.</p>
    <?php else: ?>
        <div class="slot-strip-grid">
        <?php foreach ($todaySlotData as $ts): ?>
            <?php $pct = $ts['total'] > 0 ? (int)round(($ts['booked'] / $ts['total']) * 100) : 0; ?>
            <div class="slot-strip-card">
                <div class="slot-strip-head">
                    <span class="slot-strip-name" title="<?php echo e($ts['name']); ?>"><?php echo e($ts['name']); ?></span>
                    <span class="slot-strip-count <?php echo $ts['booked'] > 0 ? 'active' : ''; ?>"><?php echo $ts['booked']; ?> / <?php echo $ts['total']; ?></span>
                </div>
                <div class="slot-strip-track"><div class="slot-strip-fill" style="width:<?php echo $pct; ?>%;"></div></div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<h3 class="reveal dash-section">Bookings</h3>
<div class="chart-wrap reveal" style="margin-bottom:30px;">
    <div class="chart-legend"><span>Last <?php echo $days; ?> days</span><span class="strong"><?php echo format_price($weekRevenue); ?> gross &middot; <?php echo (int)array_sum(array_column($weekDays, 'count')); ?> bookings</span></div>
    <div class="chart-range" role="group" aria-label="Chart date range">
        <?php foreach ([7 => '7 days', 14 => '14 days', 30 => '30 days'] as $rangeDays => $rangeLabel): ?>
            <a href="<?php echo base_url('manager/dashboard.php?days=' . $rangeDays); ?>" class="cr-link<?php echo $days === $rangeDays ? ' active' : ''; ?>"><?php echo $rangeLabel; ?></a>
        <?php endforeach; ?>
    </div>
    <div class="bar-chart" role="img" aria-label="Bookings per day for the last <?php echo $days; ?> days">
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

<h3 class="reveal dash-section">Financial Summary</h3>
<div class="table-wrap reveal" style="margin-bottom:30px;">
    <table class="data-table">
        <thead>
            <tr>
                <th>Ground</th>
                <th class="num">Bookings</th>
                <th class="num">Paid</th>
                <th class="num">Gross Revenue</th>
                <th class="num">Platform Fee</th>
                <th class="num">Your Payout</th>
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
                        <td class="num"><?php echo (int)$gs['booking_count']; ?></td>
                        <td class="num"><?php echo (int)$gs['paid_count']; ?></td>
                        <td class="num"><?php echo format_price($gross); ?></td>
                        <td class="num"><?php echo format_price($fee); ?>
                            <span class="muted" style="font-size:11px">(<?php echo (int)platform_fee_percent(); ?>%)</span>
                        </td>
                        <td class="num strong"><?php echo format_price(manager_payout($gross)); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-total">
                    <td class="strong">Total</td>
                    <td class="num"><?php echo (int)array_sum(array_column($groundSummary, 'booking_count')); ?></td>
                    <td class="num"><?php echo (int)array_sum(array_column($groundSummary, 'paid_count')); ?></td>
                    <td class="num"><?php echo format_price($grossTotal); ?></td>
                    <td class="num"><?php echo format_price($platformFeeTotal); ?></td>
                    <td class="num strong"><?php echo format_price($payoutTotal); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h3 class="reveal block-title">Payout History</h3>
<div class="table-wrap reveal">
    <table class="data-table">
        <thead>
            <tr>
                <th>Period</th>
                <th class="num">Gross Revenue</th>
                <th class="num">Platform Fee</th>
                <th class="num">Your Payout</th>
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
                        <td class="num"><?php echo format_price((float)$s['gross']); ?></td>
                        <td class="num"><?php echo format_price((float)$s['fee']); ?></td>
                        <td class="num strong"><?php echo format_price((float)$s['payout']); ?></td>
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

<h3 class="reveal block-title">Today's Bookings</h3>
<?php if (!$todayList): ?>
    <?php empty_state('fa-regular fa-calendar-check', 'Nothing booked today', "Today's bookings on your grounds will appear here."); ?>
<?php else: ?>
    <div class="mbookings reveal" style="margin-bottom:30px;">
        <?php foreach ($todayList as $b): ?>
            <?php booking_card_mini($b, 'user'); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h3 class="reveal block-title">Recent Bookings on My Grounds</h3>
<?php if (!$recent): ?>
    <?php empty_state('fa-regular fa-calendar-xmark', 'No bookings yet', 'New bookings on your grounds will appear here.'); ?>
<?php else: ?>
    <div class="mbookings reveal">
        <?php foreach ($recent as $b): ?>
            <?php booking_card_mini($b, 'user'); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

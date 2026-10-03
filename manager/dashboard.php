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
$startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
$countsQuery = $conn->query("
    SELECT booking_date, COUNT(*) c, COALESCE(SUM(total_price), 0) rev
    FROM bookings
    WHERE ground_id IN ($idList)
      AND booking_date >= '$startDate'
      AND booking_date <= CURDATE()
      AND status != 'cancelled'
    GROUP BY booking_date
");
$dateCounts = [];
$dateRevs = [];
if ($countsQuery) {
    while ($row = $countsQuery->fetch_assoc()) {
        $dateCounts[$row['booking_date']] = (int)$row['c'];
        $dateRevs[$row['booking_date']] = (float)$row['rev'];
    }
}

$weekDays = [];
$weekMax = 1;
for ($d = $days - 1; $d >= 0; $d--) {
    $day = date('Y-m-d', strtotime("-$d days"));
    $c = $dateCounts[$day] ?? 0;
    $r = $dateRevs[$day] ?? 0.0;
    $weekDays[] = ['day' => date('D', strtotime($day)), 'short' => date('M j', strtotime($day)), 'count' => $c, 'rev' => $r];
    if ($c > $weekMax) { $weekMax = $c; }
}
$weekRevenue = (float)$conn->query("SELECT COALESCE(SUM(total_price), 0) s FROM bookings WHERE ground_id IN ($idList) AND booking_date >= CURDATE() - INTERVAL " . ($days - 1) . " DAY AND status != 'cancelled'")->fetch_assoc()['s'];
$subStatus = subscription_status((int)$_SESSION['user_id']);
$setupFee = manager_setup_fee();
$monthlyFee = manager_monthly_fee();

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
foreach ($groundSummary as $gs) {
    $grossTotal += (float)$gs['gross'];
}

$page_title = 'Manager Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head dash-page-head">
    <div>
        <h1 class="page-title">Manager Dashboard</h1>
        <p class="muted" style="margin-top:4px; font-size:14px;">Court availability, booking activity, and earnings overview</p>
    </div>
    <div class="actions">
        <a href="<?php echo base_url('manager/grounds.php?add=1'); ?>" class="btn btn-primary btn-sm">+ Add Ground</a>
        <a href="<?php echo base_url('manager/bookings.php'); ?>" class="btn btn-outline btn-sm">All Bookings</a>
        <a href="<?php echo base_url('manager/promos.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-tags"></i> Promo Codes</a>
    </div>
</div>

<?php if (!$subStatus['active']): ?>
    <div class="toast toast-warning toast-inline reveal" role="status">
        <div class="toast-content">
            <div class="toast-msg"><strong>Subscription Alert:</strong> <?php echo e($subStatus['label']); ?> &ndash; your courts are hidden from players. Pay the setup fee or renew your monthly service charge to go live again.</div>
        </div>
    </div>
<?php endif; ?>

<?php if ($attentionUnpaid || $attentionFull): ?>
    <div class="attention-strip reveal">
        <?php if ($attentionUnpaid): ?>
            <a href="<?php echo base_url('manager/bookings.php?payment=unpaid&status=confirmed'); ?>" class="attention-item">
                <i class="fa-solid fa-wallet"></i>
                <span><strong><?php echo count($attentionUnpaid); ?> booking<?php echo count($attentionUnpaid) > 1 ? 's need' : ' needs'; ?> payment:</strong> <em>Latest: <?php echo e($attentionUnpaid[0]['user_name']); ?> &middot; <?php echo e($attentionUnpaid[0]['ground_name']); ?></em></span>
                <i class="fa-solid fa-arrow-right attention-go"></i>
            </a>
        <?php endif; ?>
        <?php if ($attentionFull): ?>
            <a href="<?php echo base_url('manager/dashboard.php#todaySlots'); ?>" class="attention-item">
                <i class="fa-solid fa-chart-simple"></i>
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
        <span class="muted"><?php echo $totalGrounds === 1 ? '1 court listed' : $totalGrounds . ' courts listed'; ?></span>
    </div>
    <div class="stat reveal">
        <h3>Bookings Today</h3>
        <p><?php echo $todayBookings; ?></p>
        <span class="muted"><?php echo $paidCount; ?> paid across all time</span>
    </div>
    <div class="stat reveal">
        <h3>Revenue</h3>
        <p class="stat-amount"><?php echo format_price($revenue); ?></p>
        <span class="muted">All-time confirmed &middot; 100% yours</span>
    </div>
    <div class="stat reveal">
        <h3>Subscription</h3>
        <p class="stat-amount" style="font-size:20px;"><?php echo e($subStatus['label']); ?></p>
        <span class="muted">
            <?php if ($subStatus['sub'] && $subStatus['sub']['period_end']): ?>
                Renewal: <?php echo e(date('M j, Y', strtotime($subStatus['sub']['period_end']))); ?>
            <?php else: ?>
                Rs <?php echo number_format($setupFee, 0); ?> setup &middot; Rs <?php echo number_format($monthlyFee, 0); ?>/mo
            <?php endif; ?>
        </span>
    </div>
</div>

<h3 class="reveal dash-section" id="todaySlots">Today's Court Capacity</h3>
<div class="chart-wrap reveal">
    <div class="chart-legend">
        <span>Slot Utilization</span>
        <span class="strong"><?php echo $todaySlotBooked; ?> of <?php echo $todaySlotTotal; ?> hours booked</span>
    </div>
    <?php if (!$todaySlotData): ?>
        <p class="muted table-empty">Add a ground to see today's slot availability.</p>
    <?php else: ?>
        <div class="slot-strip-grid">
        <?php foreach ($todaySlotData as $ts): ?>
            <?php $pct = $ts['total'] > 0 ? (int)round(($ts['booked'] / $ts['total']) * 100) : 0; ?>
            <div class="slot-strip-card">
                <div class="slot-strip-head">
                    <span class="slot-strip-name" title="<?php echo e($ts['name']); ?>"><?php echo e($ts['name']); ?></span>
                    <span class="slot-strip-count <?php echo $ts['booked'] > 0 ? 'active' : ''; ?>"><?php echo $ts['booked']; ?> / <?php echo $ts['total']; ?> hrs</span>
                </div>
                <div class="slot-strip-track"><div class="slot-strip-fill" style="width:<?php echo $pct; ?>%;"></div></div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<h3 class="reveal dash-section">Booking Activity &amp; Revenue</h3>
<div class="chart-wrap reveal">
    <div class="chart-legend">
        <span>Last <?php echo $days; ?> days</span>
        <span class="strong"><?php echo format_price($weekRevenue); ?> gross &middot; <?php echo (int)array_sum(array_column($weekDays, 'count')); ?> bookings</span>
    </div>
    <div class="chart-range" role="group" aria-label="Chart date range">
        <?php foreach ([7 => '7 days', 14 => '14 days', 30 => '30 days'] as $rangeDays => $rangeLabel): ?>
            <a href="<?php echo base_url('manager/dashboard.php?days=' . $rangeDays); ?>" class="cr-link<?php echo $days === $rangeDays ? ' active' : ''; ?>"><?php echo $rangeLabel; ?></a>
        <?php endforeach; ?>
    </div>
    <div class="bar-chart" role="img" aria-label="Bookings per day for the last <?php echo $days; ?> days">
        <?php foreach ($weekDays as $wd): ?>
            <?php $pct = $weekMax > 0 ? (int)round(($wd['count'] / $weekMax) * 100) : 0; ?>
            <div class="bar-group" title="<?php echo (int)$wd['count']; ?> bookings &middot; <?php echo format_price($wd['rev']); ?>">
                <div class="bar-track"><div class="bar-fill" style="height:<?php echo max($pct, 6); ?>%;"></div></div>
                <div class="bar-value"><?php echo (int)$wd['count']; ?></div>
                <div class="bar-label"><?php echo e($wd['short']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<h3 class="reveal dash-section">Financial Summary</h3>
<div class="table-wrap reveal">
    <table class="data-table">
        <thead>
            <tr>
                <th>Ground</th>
                <th class="num">Bookings</th>
                <th class="num">Paid</th>
                <th class="num">Revenue</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$groundSummary): ?>
                <tr><td colspan="4" class="muted table-empty">Add a ground to see your financial summary.</td></tr>
            <?php else: ?>
                <?php foreach ($groundSummary as $gs): ?>
                    <tr>
                        <td class="strong" data-label="Ground"><?php echo e($gs['name']); ?></td>
                        <td class="num" data-label="Bookings"><?php echo (int)$gs['booking_count']; ?></td>
                        <td class="num" data-label="Paid"><?php echo (int)$gs['paid_count']; ?></td>
                        <td class="num strong" data-label="Revenue"><?php echo format_price((float)$gs['gross']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-total">
                    <td class="strong" data-label="Total">Total</td>
                    <td class="num" data-label="Bookings"><?php echo (int)array_sum(array_column($groundSummary, 'booking_count')); ?></td>
                    <td class="num" data-label="Paid"><?php echo (int)array_sum(array_column($groundSummary, 'paid_count')); ?></td>
                    <td class="num strong" data-label="Revenue"><?php echo format_price($grossTotal); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h3 class="reveal dash-section">Today's Bookings</h3>
<?php if (!$todayList): ?>
    <?php empty_state('fa-regular fa-calendar-check', 'Nothing booked today', "Today's bookings on your grounds will appear here."); ?>
<?php else: ?>
    <div class="mbookings reveal">
        <?php foreach ($todayList as $b): ?>
            <?php booking_card_mini($b, 'user'); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h3 class="reveal dash-section">Recent Bookings on My Grounds</h3>
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

<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$totalGrounds = (int)$conn->query('SELECT COUNT(*) c FROM grounds')->fetch_assoc()['c'];
$totalUsers = (int)$conn->query('SELECT COUNT(*) c FROM users')->fetch_assoc()['c'];
$totalBookings = (int)$conn->query('SELECT COUNT(*) c FROM bookings')->fetch_assoc()['c'];
$revenue = $conn->query('SELECT COALESCE(SUM(total_price), 0) s FROM bookings WHERE status != "cancelled"')->fetch_assoc()['s'];
$grossFloat = (float)$revenue;
$platformTake = platform_fee_amount($grossFloat);
$managerPayout = manager_payout($grossFloat);
$setupFee = manager_setup_fee();
$monthlyFee = manager_monthly_fee();
$managerCount = (int)$conn->query('SELECT COUNT(*) c FROM users WHERE role = "manager"')->fetch_assoc()['c'];
$setupCollected = $conn->query('SELECT COUNT(*) c FROM manager_subscriptions WHERE setup_paid_at IS NOT NULL')->fetch_assoc()['c'];
$activeSubs = $conn->query('SELECT COUNT(*) c FROM manager_subscriptions WHERE setup_paid_at IS NOT NULL AND (period_end IS NULL OR period_end >= CURDATE())')->fetch_assoc()['c'];
$overdueSubs = $conn->query('SELECT COUNT(*) c FROM manager_subscriptions WHERE setup_paid_at IS NOT NULL AND period_end IS NOT NULL AND period_end < CURDATE()')->fetch_assoc()['c'];
$setupTotal = $conn->query('SELECT COALESCE(SUM(setup_fee), 0) s FROM manager_subscriptions WHERE setup_paid_at IS NOT NULL')->fetch_assoc()['s'];
$lastPayments = $conn->query('SELECT COUNT(*) c FROM manager_subscriptions WHERE last_paid_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)')->fetch_assoc()['c'];
$subRevenue = round((float)$setupTotal + (int)$lastPayments * $monthlyFee, 2);

$roleCounts = $conn->query('SELECT role, COUNT(*) c FROM users GROUP BY role')->fetch_all(MYSQLI_ASSOC);
$roleMap = ['admin' => 0, 'manager' => 0, 'user' => 0];
foreach ($roleCounts as $r) {
    $roleMap[$r['role']] = (int)$r['c'];
}

$recent = $conn->query(
    'SELECT b.id, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.amount_paid, b.created_at,
            g.name AS ground_name, u.name AS user_name, u.email AS user_email,
            m.name AS manager_name
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     LEFT JOIN users m ON m.id = g.manager_id
     ORDER BY b.created_at DESC
     LIMIT 6'
)->fetch_all(MYSQLI_ASSOC);

$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[date('Y-m', strtotime("-$i months"))] = ['gross' => 0, 'count' => 0, 'label' => date('M', strtotime("-$i months"))];
}
$monthRows = $conn->query(
    'SELECT DATE_FORMAT(booking_date, "%Y-%m") ym, SUM(total_price) s, COUNT(*) c
     FROM bookings WHERE status != "cancelled" GROUP BY ym'
);
while ($row = $monthRows->fetch_assoc()) {
    if (isset($months[$row['ym']])) {
        $months[$row['ym']]['gross'] = (float)$row['s'];
        $months[$row['ym']]['count'] = (int)$row['c'];
    }
}
$maxGross = max(1, max(array_column($months, 'gross')));

$topGrounds = $conn->query(
    'SELECT g.name, COUNT(*) bookings, COALESCE(SUM(b.total_price),0) revenue
     FROM bookings b JOIN grounds g ON g.id = b.ground_id
     WHERE b.status != "cancelled"
     GROUP BY g.id ORDER BY revenue DESC LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);
$maxTop = max(1, max(array_map(fn($t) => (float)$t['revenue'], $topGrounds) ?: [1]));

$page_title = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h2><i class="fa-solid fa-shield-halved"></i> Admin Dashboard</h2>
</div>

<div class="stat-grid">
    <div class="stat reveal">
        <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
        <h3>Bookings Revenue</h3><p class="stat-amount"><?php echo format_price($revenue); ?></p>
    </div>
    <div class="stat reveal">
        <div class="stat-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        <h3>Subscription Revenue</h3><p class="stat-amount"><?php echo format_price($subRevenue); ?></p>
    </div>
    <div class="stat reveal">
        <div class="stat-icon"><i class="fa-solid fa-percentage"></i></div>
        <h3>Platform Fees</h3><p class="stat-amount"><?php echo format_price($platformTake); ?></p>
        <span class="muted" style="font-size:12px;"><?php echo (int)platform_fee_percent(); ?>% of gross bookings</span>
    </div>
    <div class="stat reveal">
        <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
        <h3>Manager Payouts</h3><p class="stat-amount"><?php echo format_price($managerPayout); ?></p>
        <span class="muted" style="font-size:12px;">to be paid out to court owners</span>
    </div>
</div>

<div class="grid grid-3 reveal mt-20">
    <div class="card-mini"><div class="mini-icon admin"><i class="fa-solid fa-file-invoice-dollar"></i></div><span>Setup paid</span><strong><?php echo $setupCollected; ?> / <?php echo $managerCount; ?></strong></div>
    <div class="card-mini"><div class="mini-icon manager"><i class="fa-solid fa-circle-check"></i></div><span>Active subs</span><strong><?php echo $activeSubs; ?></strong></div>
    <div class="card-mini"><div class="mini-icon user"><i class="fa-solid fa-triangle-exclamation"></i></div><span>Overdue</span><strong><?php echo $overdueSubs; ?></strong></div>
</div>

<div class="dash-grid reveal">
    <div class="chart-card">
        <h3 class="chart-title"><i class="fa-solid fa-chart-line"></i> Revenue, last 6 months</h3>
        <div class="bar-chart">
            <?php foreach ($months as $ym => $m): ?>
                <div class="bar-col" title="<?php echo $m['label']; ?>: Rs <?php echo number_format($m['gross'], 0); ?> (<?php echo $m['count']; ?> bookings)">
                    <div class="bar" style="height: <?php echo round($m['gross'] / $maxGross * 100, 1); ?>%;"></div>
                    <span class="bar-label"><?php echo e($m['label']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="chart-card">
        <h3 class="chart-title"><i class="fa-solid fa-trophy"></i> Top grounds by revenue</h3>
        <?php if (!$topGrounds): ?>
            <p class="muted" style="padding:10px 0;">No revenue yet.</p>
        <?php else: ?>
            <div class="top-grounds">
                <?php foreach ($topGrounds as $i => $tg): ?>
                    <div class="top-row" title="<?php echo e($tg['name']); ?>">
                        <span class="top-rank"><?php echo $i + 1; ?></span>
                        <div class="top-main">
                            <span class="top-name"><?php echo e($tg['name']); ?></span>
                            <div class="top-track"><div class="top-fill" style="width: <?php echo round((float)$tg['revenue'] / $maxTop * 100, 1); ?>%;"></div></div>
                        </div>
                        <span class="top-val">Rs <?php echo number_format((float)$tg['revenue'], 0); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<h3 class="reveal dash-section"><i class="fa-solid fa-clock-rotate-left"></i> Recent Bookings</h3>
<?php if (!$recent): ?>
    <div class="empty reveal"><span class="big"><i class="fa-regular fa-calendar-xmark"></i></span><h3>No bookings yet</h3><p>New bookings from across the platform will appear here.</p></div>
<?php else: ?>
    <div class="mbookings reveal">
        <?php foreach ($recent as $b): ?>
            <?php booking_card_mini($b, 'user'); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$totalGrounds = (int)$conn->query('SELECT COUNT(*) c FROM grounds')->fetch_assoc()['c'];
$totalUsers = (int)$conn->query('SELECT COUNT(*) c FROM users')->fetch_assoc()['c'];
$totalBookings = (int)$conn->query('SELECT COUNT(*) c FROM bookings')->fetch_assoc()['c'];
$revenue = $conn->query('SELECT COALESCE(SUM(total_price), 0) s FROM bookings WHERE status != "cancelled"')->fetch_assoc()['s'];
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
            b.payment_status, b.amount_paid, b.payment_method, b.created_at,
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

// Managers who haven't paid the setup fee yet
$setupPending = $conn->query(
    "SELECT u.id, u.name, u.email, g.ground_count
     FROM users u
     LEFT JOIN (
         SELECT manager_id, COUNT(*) ground_count FROM grounds GROUP BY manager_id
     ) g ON g.manager_id = u.id
     LEFT JOIN manager_subscriptions ms ON ms.manager_id = u.id
     WHERE u.role = 'manager' AND (ms.id IS NULL OR ms.setup_paid_at IS NULL)
     ORDER BY u.name
     LIMIT 6"
)->fetch_all(MYSQLI_ASSOC);

$page_title = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head dash-page-head">
    <div>
        <h1 class="page-title">Admin Dashboard</h1>
        <p class="muted" style="margin-top:4px; font-size:14px;">Platform financials, active courts, and system overview</p>
    </div>
    <div class="actions">
        <a href="<?php echo base_url('admin/grounds.php'); ?>" class="btn btn-outline btn-sm">Courts</a>
        <a href="<?php echo base_url('admin/users.php'); ?>" class="btn btn-outline btn-sm">Users</a>
        <a href="<?php echo base_url('admin/bookings.php'); ?>" class="btn btn-primary btn-sm">All Bookings</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat reveal">
        <h3>Bookings Revenue</h3>
        <p class="stat-amount"><?php echo format_price($revenue); ?></p>
        <span class="muted"><?php echo $totalBookings; ?> total bookings &middot; paid to courts directly</span>
    </div>
    <div class="stat reveal">
        <h3>Subscription Revenue</h3>
        <p class="stat-amount"><?php echo format_price($subRevenue); ?></p>
        <span class="muted"><?php echo $setupCollected; ?> setup fees collected</span>
    </div>
    <div class="stat reveal">
        <h3>Total Courts</h3>
        <p><?php echo $totalGrounds; ?></p>
        <span class="muted">Across <?php echo $managerCount; ?> manager<?php echo $managerCount === 1 ? '' : 's'; ?></span>
    </div>
    <div class="stat reveal">
        <h3>Platform Players</h3>
        <p><?php echo $roleMap['user'] ?? $totalUsers; ?></p>
        <span class="muted"><?php echo $totalUsers; ?> total registered accounts</span>
    </div>
    <div class="stat reveal">
        <h3>Active Subscriptions</h3>
        <p><?php echo $activeSubs; ?></p>
        <span class="muted"><?php echo $setupCollected; ?> / <?php echo $managerCount; ?> managers setup</span>
    </div>
    <div class="stat reveal">
        <h3>Overdue Accounts</h3>
        <p style="color: <?php echo $overdueSubs > 0 ? 'var(--warn)' : 'var(--ink)'; ?>;"><?php echo $overdueSubs; ?></p>
        <span class="muted"><?php echo $overdueSubs > 0 ? 'Requires subscription renewal' : 'All accounts current'; ?></span>
    </div>
</div>

<?php if ($setupPending): ?>
    <div class="attention-strip reveal">
        <a href="<?php echo base_url('admin/settlements.php'); ?>" class="attention-item">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span><strong><?php echo count($setupPending); ?> manager<?php echo count($setupPending) > 1 ? "s haven't" : " hasn't"; ?> paid the setup fee:</strong> <em><?php echo e(implode(', ', array_map(fn($m) => $m['name'], array_slice($setupPending, 0, 3)))); ?><?php echo count($setupPending) > 3 ? ' +' . (count($setupPending) - 3) . ' more' : ''; ?></em></span>
            <i class="fa-solid fa-arrow-right attention-go"></i>
        </a>
    </div>
<?php endif; ?>

<div class="dash-grid reveal">
    <div class="chart-card">
        <div class="chart-title">
            <span>Revenue (Last 6 Months)</span>
            <span class="muted" style="font-weight: 500; font-size: 13px;">Monthly gross</span>
        </div>
        <div class="bar-chart" role="img" aria-label="Monthly revenue">
            <?php foreach ($months as $ym => $m): ?>
                <?php $pct = $maxGross > 0 ? round(($m['gross'] / $maxGross) * 100, 1) : 0; ?>
                <div class="bar-group" title="<?php echo $m['label']; ?>: Rs <?php echo number_format($m['gross'], 0); ?> (<?php echo $m['count']; ?> bookings)">
                    <div class="bar-track"><div class="bar-fill" style="height:<?php echo max((int)$pct, 6); ?>%;"></div></div>
                    <div class="bar-value"><?php echo $m['gross'] > 0 ? 'Rs ' . number_format($m['gross'] / 1000, 0) . 'k' : '-'; ?></div>
                    <div class="bar-label"><?php echo e($m['label']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="chart-card">
        <div class="chart-title">
            <span>Top Courts by Revenue</span>
            <span class="muted" style="font-weight: 500; font-size: 13px;">Confirmed total</span>
        </div>
        <?php if (!$topGrounds): ?>
            <p class="muted" style="padding:10px 0;">No revenue recorded yet.</p>
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

<h3 class="reveal dash-section">Recent Bookings</h3>
<?php if (!$recent): ?>
    <?php empty_state('fa-regular fa-calendar-xmark', 'No bookings yet', 'New bookings from across the platform will appear here.'); ?>
<?php else: ?>
    <div class="mbookings reveal">
        <?php foreach ($recent as $b): ?>
            <?php booking_card_mini($b, 'user'); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$setupFee = manager_setup_fee();
$monthlyFee = manager_monthly_fee();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_setup'])) {
    verify_csrf();
    $manager_id = (int)$_POST['manager_id'];
    $sub = manager_subscription($manager_id);
    if ($sub && $sub['setup_paid_at'] === null) {
        $stmt = $conn->prepare('UPDATE manager_subscriptions SET setup_paid_at = CURDATE(), period_start = CURDATE(), period_end = DATE_ADD(CURDATE(), INTERVAL 1 MONTH) WHERE manager_id = ?');
        $stmt->bind_param('i', $manager_id);
        if ($stmt->execute()) {
            notify_user($manager_id, 'Setup fee received', 'Welcome aboard! Your Rs ' . number_format($setupFee, 0) . ' setup fee is confirmed and your courts are now live.', 'fa-store', 'manager/dashboard.php');
            set_flash('success', 'Setup fee marked as paid. Manager\'s grounds are live.');
        }
    }
    redirect('admin/settlements.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew'])) {
    verify_csrf();
    $manager_id = (int)$_POST['manager_id'];
    $sub = manager_subscription($manager_id);
    if ($sub && $sub['setup_paid_at'] !== null) {
        $period_start = $sub['period_end'] && $sub['period_end'] >= date('Y-m-d') ? $sub['period_end'] : date('Y-m-d');
        $period_end = date('Y-m-d', strtotime($period_start . ' +30 days'));
        $stmt = $conn->prepare('UPDATE manager_subscriptions SET period_start = ?, period_end = ?, last_paid_at = CURDATE() WHERE manager_id = ?');
        $stmt->bind_param('ssi', $period_start, $period_end, $manager_id);
        if ($stmt->execute()) {
            $conn->query('UPDATE grounds SET is_active = 1 WHERE manager_id = ' . (int)$manager_id);
            notify_user($manager_id, 'Monthly charge paid', 'Thanks! Your subscription is active until ' . date('M j, Y', strtotime($period_end)) . '.', 'fa-calendar-check', 'manager/dashboard.php');
            set_flash('success', 'Monthly charge recorded. Subscription renewed and grounds re-activated.');
        }
    }
    redirect('admin/settlements.php');
}

$managerRows = $conn->query(
    'SELECT u.id, u.name, u.email,
            s.setup_fee, s.setup_paid_at, s.monthly_fee, s.period_start, s.period_end, s.last_paid_at,
            (SELECT COUNT(*) FROM grounds g WHERE g.manager_id = u.id) AS ground_count
     FROM users u
     LEFT JOIN manager_subscriptions s ON s.manager_id = u.id
     WHERE u.role = "manager"
     ORDER BY s.setup_paid_at IS NULL DESC, u.name ASC'
)->fetch_all(MYSQLI_ASSOC);

$today = date('Y-m-d');
$totalSetupCollected = 0;
$totalMonthlyCollected = 0;
foreach ($managerRows as $m) {
    if ($m['setup_paid_at']) {
        $totalSetupCollected += (float)$m['setup_fee'];
    }
    if ($m['last_paid_at'] && $m['last_paid_at'] >= date('Y-m-d', strtotime('-30 days'))) {
        $totalMonthlyCollected += (float)$m['monthly_fee'];
    }
}

if (isset($_GET['export']) || isset($_GET['export_excel'])) {
    $rows = [['Manager', 'Email', 'Grounds', 'Setup Fee (Rs)', 'Setup Paid At', 'Monthly Fee (Rs)', 'Period Start', 'Period End', 'Last Paid At']];
    foreach ($managerRows as $m) {
        $rows[] = [$m['name'], $m['email'], $m['ground_count'], $m['setup_fee'] ?? '', $m['setup_paid_at'] ?? '', $m['monthly_fee'] ?? '', $m['period_start'] ?? '', $m['period_end'] ?? '', $m['last_paid_at'] ?? ''];
    }
    if (isset($_GET['export_excel'])) {
        export_excel($rows, 'manager-billing.xlsx');
    }
    export_csv($rows, 'manager-billing.csv');
}

$page_title = 'Manager Billing';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h2><i class="fa-solid fa-hand-holding-dollar"></i> Manager Billing</h2>
    <div class="actions">
        <a href="<?php echo base_url('admin/settlements.php?export_excel=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-excel"></i> Export Excel</a>
        <a href="<?php echo base_url('admin/settlements.php?export=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
    </div>
</div>
<p class="muted page-sub">Managers keep 100% of booking revenue. GoalSpace earns a one-time setup fee + a monthly service charge, tracked here.</p>

<div class="stat-grid tight">
    <div class="stat reveal">
        <div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        <h3>Setup fee</h3>
        <p class="stat-amount">Rs <?php echo number_format($setupFee, 0); ?></p>
        <span class="muted"><?php echo format_price($totalSetupCollected); ?> collected</span>
    </div>
    <div class="stat reveal">
        <div class="stat-icon"><i class="fa-solid fa-calendar-week"></i></div>
        <h3>Monthly charge</h3>
        <p class="stat-amount">Rs <?php echo number_format($monthlyFee, 0); ?></p>
        <span class="muted"><?php echo format_price($totalMonthlyCollected); ?> last 30 days</span>
    </div>
</div>

<div class="table-wrap reveal">
    <table>
        <thead>
            <tr>
                <th>Manager</th>
                <th>Grounds</th>
                <th>Setup fee</th>
                <th>Monthly charge</th>
                <th>Period</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$managerRows): ?>
                <tr><td colspan="7" class="muted table-empty">No managers yet.</td></tr>
            <?php else: ?>
                <?php foreach ($managerRows as $m): ?>
                    <?php
                    $status = 'no_sub';
                    $statusLabel = 'Not subscribed';
                    if ($m['setup_paid_at'] === null) {
                        $status = 'setup_pending';
                        $statusLabel = 'Setup fee due';
                    } elseif ($m['period_end'] && $m['period_end'] < $today) {
                        $status = 'overdue';
                        $statusLabel = 'Overdue';
                    } else {
                        $status = 'active';
                        $statusLabel = 'Active';
                    }
                    ?>
                    <tr>
                        <td class="strong"><?php echo e($m['name']); ?><br><span class="muted"><?php echo e($m['email']); ?></span></td>
                        <td><?php echo (int)$m['ground_count']; ?></td>
                        <td>
                            Rs <?php echo number_format((float)$m['setup_fee'], 0); ?>
                            <?php if ($m['setup_paid_at']): ?><span class="badge badge-confirmed ml-4">Paid <?php echo e(date('M j', strtotime($m['setup_paid_at']))); ?></span><?php endif; ?>
                        </td>
                        <td>Rs <?php echo number_format((float)$m['monthly_fee'], 0); ?>/mo</td>
                        <td>
                            <?php if ($m['period_end']): ?>
                                <?php echo e(date('M j', strtotime($m['period_start']))); ?> &rarr; <?php echo e(date('M j, Y', strtotime($m['period_end']))); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($status === 'active'): ?>
                                <span class="badge badge-confirmed">Active</span>
                            <?php elseif ($status === 'overdue'): ?>
                                <span class="badge badge-cancelled">Overdue &middot; grounds hidden</span>
                            <?php elseif ($status === 'setup_pending'): ?>
                                <span class="badge badge-pending">Setup fee due</span>
                            <?php else: ?>
                                <span class="badge badge-pending">No subscription</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <?php if ($status === 'setup_pending'): ?>
                                    <form method="post" action="">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="manager_id" value="<?php echo (int)$m['id']; ?>">
                                        <button type="submit" name="mark_setup" value="1" class="btn btn-primary btn-xs"><i class="fa-solid fa-file-invoice-dollar"></i> Mark setup paid</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($status !== 'setup_pending' && $m['setup_paid_at']): ?>
                                    <form method="post" action="">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="manager_id" value="<?php echo (int)$m['id']; ?>">
                                        <button type="submit" name="renew" value="1" class="btn btn-outline btn-xs"><i class="fa-solid fa-calendar-plus"></i> Record monthly charge</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$setupFee = manager_setup_fee();
$monthlyFee = manager_monthly_fee();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_setup'])) {
    verify_csrf();
    $manager_id = (int)$_POST['manager_id'];
    $channel = trim((string)($_POST['pay_channel'] ?? ''));
    $txn_ref = trim((string)($_POST['pay_txn_ref'] ?? ''));
    $sub = manager_subscription($manager_id);
    if (!$sub) {
        set_flash_error(
            'No subscription record found.',
            'This manager has no subscription row to update.',
            'Create the subscription record first, then mark the setup fee paid.',
            'admin/settlements.php'
        );
    } elseif ($sub['setup_paid_at'] !== null) {
        set_flash('info', 'That setup fee was already marked as paid.');
    } elseif (!in_array($channel, subscription_payment_channels(), true)) {
        set_flash_error(
            'Payment channel is required.',
            'Choose how the manager paid (eSewa, Khalti or bank transfer) so an invoice can be issued.',
            'Pick a channel and enter the transaction reference, then save again.',
            'admin/settlements.php'
        );
    } elseif ($txn_ref === '') {
        set_flash_error(
            'Transaction reference is required.',
            'The reference from the payment receipt is printed on the manager\'s invoice.',
            'Enter the transaction ID from the receipt, then save again.',
            'admin/settlements.php'
        );
    } else {
        // Period is computed in PHP (not with CURDATE()/DATE_ADD in SQL) so the
        // invoice row and the subscription row are guaranteed to describe the
        // same period.
        $period_start = date('Y-m-d');
        $period_end = date('Y-m-d', strtotime($period_start . ' +1 month'));
        // KEPT (removed per request): the period was set with SQL date functions,
        // so the values were unavailable for the invoice record.
        // $stmt = $conn->prepare('UPDATE manager_subscriptions SET setup_paid_at = CURDATE(), period_start = CURDATE(), period_end = DATE_ADD(CURDATE(), INTERVAL 1 MONTH) WHERE manager_id = ?');
        $stmt = $conn->prepare('UPDATE manager_subscriptions SET setup_paid_at = ?, period_start = ?, period_end = ? WHERE manager_id = ?');
        $stmt->bind_param('sssi', $period_start, $period_start, $period_end, $manager_id);
        if ($stmt->execute()) {
            $invoice = record_subscription_payment($manager_id, 'setup', $setupFee, $channel, $txn_ref, $period_start, $period_end);
            notify_user($manager_id, 'Setup fee received', 'Welcome aboard! Your Rs ' . number_format($setupFee, 0) . ' setup fee is confirmed and your courts are now live.', 'fa-store', 'manager/dashboard.php');
            if ($invoice['ok']) {
                set_flash('success', 'Setup fee marked as paid. Invoice ' . $invoice['receipt_no'] . ' issued.');
            } else {
                set_flash('success', 'Setup fee marked as paid, but the invoice could not be saved: ' . $invoice['error']);
            }
        }
    }
    redirect('admin/settlements.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew'])) {
    verify_csrf();
    $manager_id = (int)$_POST['manager_id'];
    $channel = trim((string)($_POST['pay_channel'] ?? ''));
    $txn_ref = trim((string)($_POST['pay_txn_ref'] ?? ''));
    $sub = manager_subscription($manager_id);
    if ($sub && $sub['setup_paid_at'] === null) {
        set_flash_error(
            'Setup fee still due.',
            'Record the setup fee before the first monthly charge.',
            'Use "Mark setup paid" first, then record the monthly charge.',
            'admin/settlements.php'
        );
    } elseif (!$sub) {
        set_flash_error(
            'No subscription record found.',
            'This manager has no subscription row to renew.',
            'Create the subscription record first, then record the monthly charge.',
            'admin/settlements.php'
        );
    } elseif (!in_array($channel, subscription_payment_channels(), true)) {
        set_flash_error(
            'Payment channel is required.',
            'Choose how the manager paid so an invoice can be issued.',
            'Pick a channel and enter the transaction reference, then save again.',
            'admin/settlements.php'
        );
    } elseif ($txn_ref === '') {
        set_flash_error(
            'Transaction reference is required.',
            'The reference from the payment receipt is printed on the manager\'s invoice.',
            'Enter the transaction ID from the receipt, then save again.',
            'admin/settlements.php'
        );
    } else {
        $period_start = $sub['period_end'] && $sub['period_end'] >= date('Y-m-d') ? $sub['period_end'] : date('Y-m-d');
        $period_end = date('Y-m-d', strtotime($period_start . ' +30 days'));
        $stmt = $conn->prepare('UPDATE manager_subscriptions SET period_start = ?, period_end = ?, last_paid_at = CURDATE() WHERE manager_id = ?');
        $stmt->bind_param('ssi', $period_start, $period_end, $manager_id);
        if ($stmt->execute()) {
            $invoice = record_subscription_payment($manager_id, 'renewal', $monthlyFee, $channel, $txn_ref, $period_start, $period_end);
            notify_user($manager_id, 'Monthly charge paid', 'Thanks! Your subscription is active until ' . date('M j, Y', strtotime($period_end)) . '.', 'fa-calendar-check', 'manager/dashboard.php');
            if ($invoice['ok']) {
                set_flash('success', 'Monthly charge recorded. Invoice ' . $invoice['receipt_no'] . ' issued.');
            } else {
                set_flash('success', 'Monthly charge recorded, but the invoice could not be saved: ' . $invoice['error']);
            }
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
    <div class="title-back-row">
        <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1 class="page-title">Manager Billing</h1>
    </div>
    <div class="actions">
        <a href="<?php echo base_url('admin/settlements.php?export_excel=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-excel"></i> Export Excel</a>
        <a href="<?php echo base_url('admin/settlements.php?export=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
    </div>
</div>

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
                <th class="num">Grounds</th>
                <th class="num">Setup fee</th>
                <th class="num">Monthly charge</th>
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
                    if ($m['setup_paid_at'] === null) {
                        $status = 'setup_pending';
                    } elseif ($m['period_end'] && $m['period_end'] < $today) {
                        $status = 'overdue';
                    } else {
                        $status = 'active';
                    }
                    ?>
                    <tr>
                        <td class="strong" data-label="Manager"><?php echo e($m['name']); ?><br><span class="muted"><?php echo e($m['email']); ?></span></td>
                        <td class="num" data-label="Grounds"><?php echo (int)$m['ground_count']; ?></td>
                        <td class="num" data-label="Setup fee">
                            Rs <?php echo number_format((float)$m['setup_fee'], 0); ?>
                            <?php if ($m['setup_paid_at']): ?><span class="badge badge-confirmed ml-4">Paid <?php echo e(date('M j', strtotime($m['setup_paid_at']))); ?></span><?php endif; ?>
                        </td>
                        <td class="num" data-label="Monthly charge">Rs <?php echo number_format((float)$m['monthly_fee'], 0); ?>/mo</td>
                        <td data-label="Period">
                            <?php if (!empty($m['period_end']) && !empty($m['period_start'])): ?>
                                <?php echo e(date('M j', strtotime($m['period_start']))); ?> &rarr; <?php echo e(date('M j, Y', strtotime($m['period_end']))); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
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
                        <td data-label="">
                            <div class="row-actions">
                                <!-- KEPT (removed per request): both actions were bare submit buttons,
                                     so confirming a payment recorded no channel and no transaction
                                     reference and no invoice could ever be produced. The fields are now
                                     captured inline before the subscription is updated.
                                <?php if ($status === 'setup_pending'): ?>
                                    <form method="post" action="" novalidate>
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="manager_id" value="<?php echo (int)$m['id']; ?>">
                                        <button type="submit" name="mark_setup" value="1" class="btn btn-primary btn-xs"><i class="fa-solid fa-file-invoice-dollar"></i> Mark setup paid</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($status !== 'setup_pending' && $m['setup_paid_at']): ?>
                                    <form method="post" action="" novalidate>
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="manager_id" value="<?php echo (int)$m['id']; ?>">
                                        <button type="submit" name="renew" value="1" class="btn btn-outline btn-xs"><i class="fa-solid fa-calendar-plus"></i> Record monthly charge</button>
                                    </form>
                                <?php endif; ?>
                                -->

                                <?php if ($status === 'setup_pending'): ?>
                                    <details class="pay-capture">
                                        <summary class="btn btn-primary btn-xs"><i class="fa-solid fa-file-invoice-dollar"></i> Mark setup paid</summary>
                                        <form method="post" action="" class="pay-capture-form" novalidate>
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="manager_id" value="<?php echo (int)$m['id']; ?>">

                                            <label class="pay-capture-label" for="ch-<?php echo (int)$m['id']; ?>">Channel</label>
                                            <select class="gs-select" name="pay_channel" id="ch-<?php echo (int)$m['id']; ?>" required>
                                                <option value="">Select…</option>
                                                <?php foreach (subscription_payment_channels() as $ch): ?>
                                                    <option value="<?php echo e($ch); ?>"><?php echo e(subscription_channel_label($ch)); ?></option>
                                                <?php endforeach; ?>
                                            </select>

                                            <label class="pay-capture-label" for="tx-<?php echo (int)$m['id']; ?>">Transaction reference</label>
                                            <input class="gs-input" type="text" name="pay_txn_ref" id="tx-<?php echo (int)$m['id']; ?>"
                                                   maxlength="80" required placeholder="e.g. 240310001234">

                                            <p class="pay-capture-hint">Amount: <?php echo format_price((float)$m['setup_fee']); ?></p>
                                            <button type="submit" name="mark_setup" value="1" class="btn btn-primary btn-xs">
                                                <i class="fa-solid fa-check"></i> Confirm &amp; issue invoice
                                            </button>
                                        </form>
                                    </details>
                                <?php endif; ?>

                                <?php if ($status !== 'setup_pending' && $m['setup_paid_at']): ?>
                                    <details class="pay-capture">
                                        <summary class="btn btn-outline btn-xs"><i class="fa-solid fa-calendar-plus"></i> Record monthly charge</summary>
                                        <form method="post" action="" class="pay-capture-form" novalidate>
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="manager_id" value="<?php echo (int)$m['id']; ?>">

                                            <label class="pay-capture-label" for="rch-<?php echo (int)$m['id']; ?>">Channel</label>
                                            <select class="gs-select" name="pay_channel" id="rch-<?php echo (int)$m['id']; ?>" required>
                                                <option value="">Select…</option>
                                                <?php foreach (subscription_payment_channels() as $ch): ?>
                                                    <option value="<?php echo e($ch); ?>"><?php echo e(subscription_channel_label($ch)); ?></option>
                                                <?php endforeach; ?>
                                            </select>

                                            <label class="pay-capture-label" for="rtx-<?php echo (int)$m['id']; ?>">Transaction reference</label>
                                            <input class="gs-input" type="text" name="pay_txn_ref" id="rtx-<?php echo (int)$m['id']; ?>"
                                                   maxlength="80" required placeholder="e.g. 240310001234">

                                            <p class="pay-capture-hint">Amount: <?php echo format_price((float)$m['monthly_fee']); ?>/mo</p>
                                            <button type="submit" name="renew" value="1" class="btn btn-outline btn-xs">
                                                <i class="fa-solid fa-check"></i> Confirm &amp; issue invoice
                                            </button>
                                        </form>
                                    </details>
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

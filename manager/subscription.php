<?php
require_once __DIR__ . '/../config/db.php';
require_manager();

$managerId = (int)$_SESSION['user_id'];
$sub = manager_subscription($managerId);
$status = subscription_status($managerId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['request_setup_payment']) && $sub && $sub['setup_paid_at'] === null) {
        notify_announcement(
            'Setup fee payment request',
            'Manager #' . $managerId . ' paid the setup fee of ' . format_price((float)$sub['setup_fee']) . ' and requests activation. Review in Settlements.',
            'admins',
            false
        );
        notify_user($managerId, 'Setup payment recorded', 'We received your setup fee request. An admin will activate your courts shortly.', 'fa-circle-check', 'manager/subscription.php');
        set_flash('success', 'Payment request sent. Your courts will go live once the admin confirms the setup fee.');
        redirect('manager/subscription.php');
    }
    if (isset($_POST['request_renew']) && $sub && $status['key'] === 'overdue') {
        notify_announcement(
            'Subscription renewal request',
            'Manager #' . $managerId . ' paid the monthly fee of ' . format_price((float)$sub['monthly_fee']) . ' and requests renewal. Review in Settlements.',
            'admins',
            false
        );
        notify_user($managerId, 'Renewal payment recorded', 'We received your renewal request. Your courts will reactivate once confirmed.', 'fa-calendar-check', 'manager/subscription.php');
        set_flash('success', 'Renewal request sent. Courts reactivate after admin confirmation.');
        redirect('manager/subscription.php');
    }
    set_flash('info', 'No action taken.');
    redirect('manager/subscription.php');
}

$page_title = 'Subscription';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-lg-40">
    <div class="page-top">
        <a href="<?php echo base_url('manager/grounds.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>Subscription</h1>
    </div>

    <?php if (flash_msg()): render_inline(flash_msg()); endif; ?>

    <div class="settings-card reveal">
        <div class="settings-head">
            <h3>Current Plan</h3>
        </div>
        <div class="settings-body">
            <?php if ($sub): ?>
            <div class="sub-status-row">
                <span class="badge-pill <?php echo $status['active'] ? 'badge-green' : 'badge-red'; ?>">
                    <i class="fa-solid <?php echo $status['active'] ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                    <?php echo e($status['label']); ?>
                </span>
            </div>
            <div class="table-wrap">
            <table class="settings-table">
                <tr><td data-label="Setup fee">Setup fee</td><td data-label="Amount"><?php echo format_price($sub['setup_fee']); ?></td><td data-label="Status"><?php echo $sub['setup_paid_at'] ? 'Paid ' . date('M j, Y', strtotime($sub['setup_paid_at'])) : '<span class="text-red">Unpaid</span>'; ?></td></tr>
                <tr><td data-label="Monthly fee">Monthly fee</td><td data-label="Amount"><?php echo format_price($sub['monthly_fee']); ?></td><td data-label="Status"><?php echo $sub['period_end'] && !$status['active'] ? '<span class="text-red">Due</span>' : ($status['active'] ? 'Current period' : ''); ?></td></tr>
                <tr><td data-label="Period start">Period start</td><td data-label="Date"><?php echo $sub['period_start'] ? date('M j, Y', strtotime($sub['period_start'])) : '-'; ?></td><td data-label="Status"></td></tr>
                <tr><td data-label="Period end">Period end</td><td data-label="Date"><?php echo $sub['period_end'] ? date('M j, Y', strtotime($sub['period_end'])) : '-'; ?></td><td data-label="Status"><?php if ($sub['period_end'] && !$status['active']): ?><span class="text-red">Expired</span><?php endif; ?></td></tr>
            </table>
            </div>
            <?php if ($status['key'] === 'setup_pending'): ?>
                <div class="notice notice-info mt-14 mb-10">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Pay the setup fee via eSewa / Khalti / bank transfer (see platform admin), then submit the request below.</span>
                </div>
                <form method="post" action="">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="request_setup_payment" value="1" class="btn btn-primary">
                        <i class="fa-solid fa-file-invoice-dollar"></i> I paid — request activation
                    </button>
                </form>
            <?php elseif ($status['key'] === 'overdue'): ?>
                <div class="notice notice-info mt-14 mb-10">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Your subscription has expired. Pay the monthly fee of <?php echo format_price((float)$sub['monthly_fee']); ?>, then submit the request below to reactivate your courts.</span>
                </div>
                <form method="post" action="">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="request_renew" value="1" class="btn btn-primary">
                        <i class="fa-solid fa-calendar-plus"></i> I paid — renew subscription
                    </button>
                </form>
            <?php endif; ?>
            <?php else: ?>
                <p class="muted">No subscription found. Contact support to get started as a manager.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

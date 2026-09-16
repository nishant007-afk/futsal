<?php
require_once __DIR__ . '/../config/db.php';
require_manager();

$managerId = (int)$_SESSION['user_id'];
$sub = manager_subscription($managerId);
$status = subscription_status($managerId);

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
                    <i class="fa-solid <?php echo $status['active'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo e($status['label']); ?>
                </span>
            </div>
            <table class="settings-table">
                <tr><td>Setup fee</td><td><?php echo format_price($sub['setup_fee']); ?></td><td><?php echo $sub['setup_paid_at'] ? 'Paid ' . date('M j, Y', strtotime($sub['setup_paid_at'])) : '<span class="text-red">Unpaid</span>'; ?></td></tr>
                <tr><td>Monthly fee</td><td><?php echo format_price($sub['monthly_fee']); ?></td><td></td></tr>
                <tr><td>Period start</td><td><?php echo $sub['period_start'] ? date('M j, Y', strtotime($sub['period_start'])) : '—'; ?></td><td></td></tr>
                <tr><td>Period end</td><td><?php echo $sub['period_end'] ? date('M j, Y', strtotime($sub['period_end'])) : '—'; ?></td><td><?php if ($sub['period_end'] && !$status['active']): ?><span class="text-red">Expired</span><?php endif; ?></td></tr>
            </table>
            <?php if ($status['key'] === 'setup_pending'): ?>
                <p class="form-hint mt-12"><i class="fa-solid fa-circle-info"></i> Pay the setup fee to activate your courts and start receiving bookings.</p>
            <?php elseif ($status['key'] === 'overdue'): ?>
                <p class="form-hint mt-12"><i class="fa-solid fa-circle-info"></i> Your subscription has expired. Renew to reactivate your courts.</p>
            <?php endif; ?>
            <?php else: ?>
                <p class="muted">No subscription found. Contact support to get started as a manager.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

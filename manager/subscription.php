<?php
require_once __DIR__ . '/../config/db.php';
require_manager();

$managerId = (int)$_SESSION['user_id'];
$sub = manager_subscription($managerId);
$status = subscription_status($managerId);

// Invoice history + lifetime totals for the Recent Invoices table.
$invoices = manager_subscription_payments($managerId);
$invoiceSummary = manager_subscription_payment_summary($managerId);

// Venue checkout QR codes, one per court, for the QR dialog.
$qrGrounds = $conn->query(
    'SELECT id, name, payment_qr FROM grounds
     WHERE manager_id = ' . $managerId . ' AND payment_qr IS NOT NULL AND payment_qr <> \'\'
     ORDER BY name ASC'
)->fetch_all(MYSQLI_ASSOC);

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
    if (isset($_POST['request_renew']) && $sub) {
        notify_announcement(
            'Subscription renewal request',
            'Manager #' . $managerId . ' paid the monthly fee of ' . format_price((float)$sub['monthly_fee']) . ' and requests renewal. Review in Settlements.',
            'admins',
            false
        );
        notify_user($managerId, 'Renewal payment recorded', 'We received your renewal request. An admin will review and extend your subscription period shortly.', 'fa-calendar-check', 'manager/subscription.php');
        set_flash('success', 'Renewal request sent successfully. Courts remain active while an admin confirms your payment.');
        redirect('manager/subscription.php');
    }
    set_flash('info', 'No action taken.');
    redirect('manager/subscription.php');
}

$page_title = 'Subscription & Billing';
require __DIR__ . '/../includes/header.php';

// Calculate days remaining in period if active
$daysRemaining = 0;
if ($sub && !empty($sub['period_end'])) {
    $now = new DateTime();
    $end = new DateTime($sub['period_end']);
    $diff = $now->diff($end);
    $daysRemaining = $now < $end ? (int)$diff->format('%a') : 0;
}
?>

<div class="page-head dash-page-head">
    <a href="<?php echo base_url('manager/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <div>
            <h1 class="page-title">Subscription & Billing</h1>
        </div>
        <div class="actions">
            <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-store"></i> My Courts</a>
            <a href="<?php echo base_url('manager/bookings.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-list-check"></i> Bookings</a>
        </div>
    </div>
</div>

<?php if (!empty(get_flash())): render_inline(get_flash()); endif; ?>

<div class="sub-layout">
    <?php if ($sub): ?>
        <!-- Hero Subscription Card -->
        <div class="sub-hero-card">
            <div class="shc-header">
                <div class="shc-badge-group">
                    <span class="shc-plan-name"><i class="fa-solid fa-crown text-amber"></i> Venue Partner Tier</span>
                    <span class="badge <?php echo $status['active'] ? 'badge-confirmed' : 'badge-cancelled'; ?>">
                        <i class="fa-solid <?php echo $status['active'] ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                        <?php echo e($status['label']); ?>
                    </span>
                </div>
                <div class="shc-pricing">
                    <span class="shc-price-val"><?php echo format_price((float)$sub['monthly_fee']); ?></span>
                    <span class="shc-price-period">/ month</span>
                </div>
            </div>

            <!-- Key Subscription Metrics -->
            <div class="sub-metrics-grid">
                <div class="sub-metric-box">
                    <span class="smb-label">Monthly Rate</span>
                    <strong class="smb-value"><?php echo format_price((float)$sub['monthly_fee']); ?></strong>
                    <span class="smb-hint">Flat subscription fee</span>
                </div>
                <div class="sub-metric-box">
                    <span class="smb-label">Setup Fee</span>
                    <strong class="smb-value"><?php echo $sub['setup_paid_at'] ? '<span class="text-green"><i class="fa-solid fa-check"></i> Paid</span>' : '<span class="text-red">Unpaid</span>'; ?></strong>
                    <span class="smb-hint"><?php echo $sub['setup_paid_at'] ? date('M j, Y', strtotime($sub['setup_paid_at'])) : format_price((float)$sub['setup_fee']) . ' one-time'; ?></span>
                </div>
                <div class="sub-metric-box">
                    <span class="smb-label">Current Period</span>
                    <strong class="smb-value"><?php echo $sub['period_start'] ? date('M j', strtotime($sub['period_start'])) : '-'; ?> &ndash; <?php echo $sub['period_end'] ? date('M j, Y', strtotime($sub['period_end'])) : '-'; ?></strong>
                    <span class="smb-hint">Active billing cycle</span>
                </div>
                <div class="sub-metric-box">
                    <span class="smb-label">Status & Expiry</span>
                    <strong class="smb-value <?php echo $status['active'] ? 'text-green' : 'text-red'; ?>">
                        <?php if ($status['active']): ?>
                            <?php echo $daysRemaining > 0 ? $daysRemaining . ' days left' : 'Renews today'; ?>
                        <?php else: ?>
                            Expired
                        <?php endif; ?>
                    </strong>
                    <span class="smb-hint"><?php echo $status['active'] ? 'Renews ' . ($sub['period_end'] ? date('M j, Y', strtotime($sub['period_end'])) : 'soon') : 'Immediate action required'; ?></span>
                </div>
            </div>

            <?php if ($status['key'] === 'setup_pending'): ?>
                <div class="notice notice-warning mt-14 mb-14">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><strong>Setup fee required:</strong> Transfer the one-time onboarding fee of <?php echo format_price((float)$sub['setup_fee']); ?> to platform admin to publish your courts to players.</span>
                </div>
            <?php elseif ($status['key'] === 'overdue'): ?>
                <div class="notice notice-error mt-14 mb-14">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><strong>Subscription Overdue:</strong> Your courts are temporarily hidden from search results. Pay the monthly fee of <?php echo format_price((float)$sub['monthly_fee']); ?> to restore live player bookings.</span>
                </div>
            <?php endif; ?>

            <!-- Action Buttons Toolbar
                 KEPT (removed per request): "Platform Payment Details" toggled an inline panel and
                 "Venue Checkout QR" navigated away to manager/grounds.php#panel-media, losing the
                 billing context. Both are now in-page <dialog> overlays; only Renew stays inline
                 because it is a real form submission.
            <div class="sub-actions-bar">
                <button type="button" class="btn btn-primary" id="btnToggleRenew">
                    <i class="fa-solid fa-arrows-rotate"></i> <?php echo $status['active'] ? 'Extend / Renew Subscription' : 'Pay & Reactivate Account'; ?>
                </button>
                <button type="button" class="btn btn-outline" id="btnTogglePayInfo">
                    <i class="fa-solid fa-building-columns"></i> Platform Payment Details
                </button>
                <a href="<?php echo base_url('manager/grounds.php#panel-media'); ?>" class="btn btn-outline">
                    <i class="fa-solid fa-qrcode"></i> Venue Checkout QR
                </a>
                <a href="<?php echo base_url('pages/faq.php'); ?>" class="btn btn-ghost">
                    <i class="fa-solid fa-circle-question"></i> Billing FAQ
                </a>
            </div>
            -->
            <div class="sub-actions-bar">
                <button type="button" class="btn btn-primary" id="btnToggleRenew">
                    <i class="fa-solid fa-arrows-rotate"></i> <?php echo $status['active'] ? 'Extend / Renew Subscription' : 'Pay & Reactivate Account'; ?>
                </button>
                <button type="button" class="btn btn-outline" data-open-dialog="dlgPayInfo">
                    <i class="fa-solid fa-building-columns"></i> Platform Payment Details
                </button>
                <button type="button" class="btn btn-outline" data-open-dialog="dlgQr">
                    <i class="fa-solid fa-qrcode"></i> Venue Checkout QR
                </button>
                <a href="<?php echo base_url('pages/faq.php'); ?>" class="btn btn-ghost">
                    <i class="fa-solid fa-circle-question"></i> Billing FAQ
                </a>
            </div>

            <!-- Collapsible: Renewal & Payment Request Form -->
            <div class="sub-expand-panel" id="panelRenew" <?php echo ($status['key'] === 'overdue' || $status['key'] === 'setup_pending') ? '' : 'hidden'; ?>>
                <div class="sep-header">
                    <h4><i class="fa-solid fa-receipt"></i> Submit Payment Notification</h4>
                    <p>Transfer the fee via eSewa, Khalti, or Bank, then confirm below for admin verification.</p>
                </div>
                <form method="post" action="" class="sep-form" novalidate>
                    <?php echo csrf_field(); ?>
                    <?php if ($status['key'] === 'setup_pending'): ?>
                        <div class="form-group mb-12">
                            <label>Amount to pay</label>
                            <input type="text" value="<?php echo format_price((float)$sub['setup_fee']); ?> (One-time Setup Fee)" disabled>
                        </div>
                        <button type="submit" name="request_setup_payment" value="1" class="btn btn-primary">
                            <i class="fa-solid fa-file-invoice-dollar"></i> I Have Paid Setup Fee  -  Activate Courts
                        </button>
                    <?php else: ?>
                        <div class="form-group mb-12">
                            <label>Amount to pay</label>
                            <input type="text" value="<?php echo format_price((float)$sub['monthly_fee']); ?> (1 Month Extension)" disabled>
                        </div>
                        <button type="submit" name="request_renew" value="1" class="btn btn-primary">
                            <i class="fa-solid fa-calendar-check"></i> I Have Transferred Monthly Fee  -  Confirm Renewal
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Collapsible: Official Platform Bank / Digital Wallet Credentials
                 KEPT (removed per request): this was an inline expanding panel toggled by
                 #btnTogglePayInfo. The same markup now lives in the <dialog id="dlgPayInfo">
                 at the end of the page, so nothing is fetched or lost when it opens.
            <div class="sub-expand-panel" id="panelPayInfo" hidden>
                <div class="sep-header">
                    <h4><i class="fa-solid fa-wallet"></i> GoalSpace Platform Accounts for Subscription Settlement</h4>
                    <p>Use any of the official methods below and mention your Ground / Manager name in the remarks.</p>
                </div>
                <div class="payment-methods-grid">
                    <div class="pay-method-card">
                        <div class="pm-head"><i class="fa-solid fa-mobile-screen-button text-green"></i> <strong>eSewa / Khalti</strong></div>
                        <p class="pm-data">ID: <code>9800000000</code></p>
                        <p class="pm-note">Name: GoalSpace Sports Pvt. Ltd.</p>
                    </div>
                    <div class="pay-method-card">
                        <div class="pm-head"><i class="fa-solid fa-building-columns text-emerald"></i> <strong>Bank Transfer</strong></div>
                        <p class="pm-data">Global IME Bank &middot; Current A/C</p>
                        <p class="pm-note">A/C: <code>01201010009988</code> &middot; Kathmandu Branch</p>
                    </div>
                    <div class="pay-method-card">
                        <div class="pm-head"><i class="fa-solid fa-clock text-amber"></i> <strong>Verification Timeline</strong></div>
                        <p class="pm-data">Confirmed within 2 hours</p>
                        <p class="pm-note">Need instant help? Contact admin via <a href="<?php echo base_url('pages/page.php?slug=contact'); ?>">Support</a></p>
                    </div>
                </div>
            </div>
            -->
        </div>

        <!-- Recent Invoices / Payment History
             KEPT (removed per request): the four marketing cards below ("Included in Your
             Partner Tier") were static sales copy sitting on the billing page, where managers
             come to reconcile payments. They are kept here in full and can be restored.
        <div class="sub-features-section">
            <h3 class="section-title">Included in Your Partner Tier</h3>
            <div class="sub-features-grid">
                <div class="sub-feature-card">
                    <div class="sfc-icon"><i class="fa-solid fa-layer-group"></i></div>
                    <h4>Unlimited Court Management</h4>
                    <p>List and organize all pitches, indoor turfs, and court variants under a unified manager dashboard.</p>
                </div>
                <div class="sub-feature-card">
                    <div class="sfc-icon"><i class="fa-solid fa-percent"></i></div>
                    <h4>0% Booking Commission</h4>
                    <p>Keep 100% of player payments. Direct on-site cash and your own custom QR collections incur zero fees.</p>
                </div>
                <div class="sub-feature-card">
                    <div class="sfc-icon"><i class="fa-solid fa-bolt"></i></div>
                    <h4>Real-time Slot Engine</h4>
                    <p>Automatic collision prevention prevents double-bookings, with customizable interval durations and blackout dates.</p>
                </div>
                <div class="sub-feature-card">
                    <div class="sfc-icon"><i class="fa-solid fa-chart-line"></i></div>
                    <h4>Revenue & Analytics</h4>
                    <p>Track occupancy rates, popular game hours, customer contact lists, and export comprehensive booking records.</p>
                </div>
            </div>
        </div>
        -->
        <div class="sub-invoices-section">
            <div class="sub-invoices-head">
                <h3 class="section-title">Recent Invoices</h3>
                <div class="sub-invoices-meta">
                    <span class="muted"><?php echo (int)$invoiceSummary['count']; ?> invoice<?php echo $invoiceSummary['count'] === 1 ? '' : 's'; ?></span>
                    <span class="strong"><?php echo format_price($invoiceSummary['total']); ?> paid</span>
                </div>
            </div>

            <div class="table-wrap">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice no.</th>
                            <th>Type</th>
                            <th class="num">Amount</th>
                            <th>Channel</th>
                            <th>Transaction ref.</th>
                            <th>Period covered</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$invoices): ?>
                            <tr>
                                <td colspan="8" class="muted table-empty">
                                    <i class="fa-solid fa-receipt"></i>
                                    No invoices yet. An invoice is issued automatically when an admin
                                    confirms your setup fee or a renewal.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td data-label="Date"><?php echo e(date('M j, Y', strtotime($inv['paid_at']))); ?></td>
                                    <td data-label="Invoice no."><code><?php echo e($inv['receipt_no']); ?></code></td>
                                    <td data-label="Type">
                                        <span class="badge <?php echo $inv['kind'] === 'setup' ? 'badge-pending' : 'badge-confirmed'; ?>">
                                            <?php echo e(subscription_kind_label($inv['kind'])); ?>
                                        </span>
                                    </td>
                                    <td class="num strong" data-label="Amount"><?php echo format_price((float)$inv['amount']); ?></td>
                                    <td data-label="Channel"><?php echo e(subscription_channel_label($inv['channel'])); ?></td>
                                    <td data-label="Transaction ref."><code><?php echo e($inv['txn_ref']); ?></code></td>
                                    <td data-label="Period covered">
                                        <?php if (!empty($inv['period_start']) && !empty($inv['period_end'])): ?>
                                            <?php echo e(date('M j', strtotime($inv['period_start']))); ?> &rarr;
                                            <?php echo e(date('M j, Y', strtotime($inv['period_end']))); ?>
                                        <?php else: ?>
                                            <span class="muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Receipt">
                                        <a class="btn btn-outline btn-xs"
                                           href="<?php echo base_url('manager/invoice_pdf.php?no=' . rawurlencode($inv['receipt_no'])); ?>"
                                           target="_blank" rel="noopener">
                                            <i class="fa-solid fa-file-pdf"></i> PDF
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <div class="empty reveal">
            <span class="big"><i class="fa-solid fa-receipt"></i></span>
            <h3>No Active Subscription Record</h3>
            <p>Your account is registered as a manager. Please contact the platform administration to activate your partner tier.</p>
            <a href="<?php echo base_url('pages/page.php?slug=contact'); ?>" class="btn btn-primary btn-sm mt-12">Contact Platform Admin</a>
        </div>
    <?php endif; ?>
</div>

<style>
.sub-layout {
    display: flex;
    flex-direction: column;
    gap: 32px;
    margin-top: 14px;
    margin-bottom: 40px;
}
.sub-hero-card {
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: var(--r-lg);
    padding: 28px;
    box-shadow: var(--s1);
}
.shc-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    padding-bottom: 22px;
    border-bottom: 1px solid var(--line);
}
.shc-badge-group {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.shc-plan-name {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -0.02em;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.shc-pricing {
    display: flex;
    align-items: baseline;
    gap: 4px;
}
.shc-price-val {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--brand-700);
    letter-spacing: -0.03em;
}
.shc-price-period {
    font-size: 0.88rem;
    color: var(--ink-3);
    font-weight: 600;
}
.sub-metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-top: 22px;
    margin-bottom: 22px;
}
.sub-metric-box {
    background: var(--bg-soft);
    border: 1px solid var(--line);
    border-radius: var(--r-md);
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.smb-label {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ink-3);
}
.smb-value {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--ink);
}
.smb-hint {
    font-size: 0.76rem;
    color: var(--ink-3);
}
.sub-actions-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding-top: 18px;
    border-top: 1px solid var(--line);
}
.sub-expand-panel {
    background: var(--bg-soft);
    border: 1px solid var(--line);
    border-radius: var(--r-md);
    padding: 22px;
    margin-top: 20px;
    animation: fadeInTab 0.18s ease;
}
.sep-header h4 {
    margin: 0 0 4px 0;
    font-size: 1.05rem;
    color: var(--ink);
}
.sep-header p {
    margin: 0 0 16px 0;
    font-size: 0.85rem;
    color: var(--ink-3);
}
.sep-form {
    max-width: 440px;
}
.payment-methods-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
}
.pay-method-card {
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: var(--r-md);
    padding: 14px 16px;
    font-size: 0.85rem;
}
.pm-head {
    margin-bottom: 6px;
    font-size: 0.95rem;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 6px;
}
.pm-data {
    margin: 0 0 4px 0;
    font-weight: 600;
    color: var(--ink);
}
.pm-note {
    margin: 0;
    color: var(--ink-3);
    font-size: 0.8rem;
}
.sub-features-section {
    margin-top: 10px;
}
.section-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 16px;
}
.sub-features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 18px;
}
.sub-feature-card {
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: var(--r-lg);
    padding: 22px 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-shadow: var(--s1);
    transition: transform 0.15s ease, border-color 0.15s ease;
}
.sub-feature-card:hover {
    transform: translateY(-2px);
    border-color: var(--line-2);
}
.sfc-icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: var(--brand-soft);
    color: var(--brand-700);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    margin-bottom: 4px;
}
.sub-feature-card h4 {
    margin: 0;
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--ink);
}
.sub-feature-card p {
    margin: 0;
    font-size: 0.84rem;
    color: var(--ink-3);
    line-height: 1.5;
}
@media (max-width: 600px) {
    .sub-hero-card {
        padding: 18px 16px;
    }
    .sub-actions-bar .btn {
        width: 100%;
        text-align: center;
        justify-content: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var btnRenew = document.getElementById('btnToggleRenew');
    var panelRenew = document.getElementById('panelRenew');

    // KEPT (removed per request): payment details used to toggle #panelPayInfo inline, and
    // the checkout QR used to be a link to manager/grounds.php#panel-media. Both are now
    // native <dialog> overlays opened by [data-open-dialog].
    // var btnPayInfo = document.getElementById('btnTogglePayInfo');
    // var panelPayInfo = document.getElementById('panelPayInfo');
    // if (btnPayInfo && panelPayInfo) {
    //     btnPayInfo.addEventListener('click', function () {
    //         panelPayInfo.hidden = !panelPayInfo.hidden;
    //         if (!panelPayInfo.hidden) {
    //             panelPayInfo.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    //         }
    //     });
    // }

    if (btnRenew && panelRenew) {
        btnRenew.addEventListener('click', function () {
            panelRenew.hidden = !panelRenew.hidden;
            if (!panelRenew.hidden) {
                panelRenew.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }

    // Native <dialog> overlays. Esc and the backdrop close them for free; we only
    // wire the explicit open buttons and the [data-close-dialog] controls.
    document.querySelectorAll('[data-open-dialog]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var dlg = document.getElementById(trigger.dataset.openDialog);
            if (!dlg) return;
            if (typeof dlg.showModal === 'function') {
                dlg.showModal();
            } else {
                dlg.setAttribute('open', '');
            }
        });
    });

    document.querySelectorAll('[data-close-dialog]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var dlg = trigger.closest('dialog');
            if (!dlg) return;
            if (typeof dlg.close === 'function') {
                dlg.close();
            } else {
                dlg.removeAttribute('open');
            }
        });
    });

    // Click on the backdrop (outside the dialog box) closes it.
    document.querySelectorAll('dialog.gs-dialog').forEach(function (dlg) {
        dlg.addEventListener('click', function (ev) {
            if (ev.target === dlg) {
                dlg.close();
            }
        });
    });
});
</script>

<?php /* ---------- Modal: Platform Payment Details ---------- */ ?>
<dialog class="gs-dialog" id="dlgPayInfo" aria-labelledby="dlgPayInfoTitle">
    <div class="gs-dialog-head">
        <h3 id="dlgPayInfoTitle"><i class="fa-solid fa-wallet"></i> Platform Payment Details</h3>
        <button type="button" class="gs-dialog-close" data-close-dialog aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="gs-dialog-body">
        <p class="sep-header-p">Use any of the official methods below and mention your Ground / Manager name in the remarks.</p>
        <div class="payment-methods-grid">
            <div class="pay-method-card">
                <div class="pm-head"><i class="fa-solid fa-mobile-screen-button text-green"></i> <strong>eSewa / Khalti</strong></div>
                <p class="pm-data">ID: <code>9800000000</code></p>
                <p class="pm-note">Name: GoalSpace Sports Pvt. Ltd.</p>
            </div>
            <div class="pay-method-card">
                <div class="pm-head"><i class="fa-solid fa-building-columns text-emerald"></i> <strong>Bank Transfer</strong></div>
                <p class="pm-data">Global IME Bank &middot; Current A/C</p>
                <p class="pm-note">A/C: <code>01201010009988</code> &middot; Kathmandu Branch</p>
            </div>
            <div class="pay-method-card">
                <div class="pm-head"><i class="fa-solid fa-clock text-amber"></i> <strong>Verification Timeline</strong></div>
                <p class="pm-data">Confirmed within 2 hours</p>
                <p class="pm-note">Need instant help? Contact admin via <a href="<?php echo base_url('pages/page.php?slug=contact'); ?>">Support</a></p>
            </div>
        </div>
    </div>
    <div class="gs-dialog-foot">
        <button type="button" class="btn btn-outline" data-close-dialog>Close</button>
    </div>
</dialog>

<?php /* ---------- Modal: Venue Checkout QR ---------- */ ?>
<dialog class="gs-dialog" id="dlgQr" aria-labelledby="dlgQrTitle">
    <div class="gs-dialog-head">
        <h3 id="dlgQrTitle"><i class="fa-solid fa-qrcode"></i> Venue Checkout QR</h3>
        <button type="button" class="gs-dialog-close" data-close-dialog aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="gs-dialog-body">
        <?php if (!$qrGrounds): ?>
            <div class="empty">
                <span class="big"><i class="fa-solid fa-qrcode"></i></span>
                <h3>No checkout QR uploaded</h3>
                <p>Upload the payment QR players should scan for each of your courts.</p>
                <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-primary btn-sm mt-12">Manage court QR codes</a>
            </div>
        <?php else: ?>
            <p class="sep-header-p">Players scan the code for the court they are booking.</p>
            <div class="qr-grid">
                <?php foreach ($qrGrounds as $qg): ?>
                    <div class="qr-item">
                        <div class="qr-frame">
                            <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($qg['payment_qr'])); ?>"
                                 alt="Checkout QR for <?php echo e($qg['name']); ?>"
                                 loading="lazy" decoding="async">
                        </div>
                        <span class="qr-name"><?php echo e($qg['name']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="gs-dialog-foot">
        <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-outline">
            <i class="fa-solid fa-sliders"></i> Edit QR codes
        </a>
        <button type="button" class="btn btn-primary" data-close-dialog>Close</button>
    </div>
</dialog>

<?php require __DIR__ . '/../includes/footer.php'; ?>

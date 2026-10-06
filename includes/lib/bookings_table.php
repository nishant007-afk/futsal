<?php
// Shared renderer for the "bookings" data-table used by every role
// (player My Bookings, manager bookings/dashboard, admin bookings).
// Keeps the status/payment badge logic and the money maths in ONE place so the
// player, manager and admin tables can never drift apart again.

/**
 * @param array $rows   Booking rows (must include total_price, discount, amount_paid, status, payment_status).
 * @param array $opts   show_player: bool   show_ground_meta: bool   show_ref: bool
 *                      manager_action: bool  detail_link: bool  drawer: bool  empty_icon/title/sub
 *
 * drawer: opt into the slide-over booking viewer. The link keeps its real href either way,
 *         so it still works with JS off, on middle-click, and for the roles that navigate.
 */
function bookings_table_html(array $rows, array $opts = []): void
{
    $showPlayer = (bool)($opts['show_player'] ?? false);
    $showRef = (bool)($opts['show_ref'] ?? false);
    $managerAction = (bool)($opts['manager_action'] ?? false);
    $detailLink = !array_key_exists('detail_link', $opts) || (bool)$opts['detail_link'];
    $drawer = (bool)($opts['drawer'] ?? false);

    if (!$rows) {
        $icon = $opts['empty_icon'] ?? 'fa-regular fa-calendar-xmark';
        $title = $opts['empty_title'] ?? 'Nothing here yet';
        $sub = $opts['empty_sub'] ?? '';
        $url = $opts['empty_url'] ?? '';
        $label = $opts['empty_label'] ?? '';
        $cls = $opts['empty_class'] ?? 'btn btn-primary btn-sm';
        if ($url !== '' && $label !== '') {
            empty_state($icon, $title, $sub, $url, $label, $cls);
        } else {
            empty_state($icon, $title, $sub);
        }
        return;
    }
    ?>
    <div class="table-wrap reveal">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Court</th>
                    <?php if ($showPlayer): ?><th>Player</th><?php endif; ?>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th class="num">Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $b): ?>
                    <?php
                    $netDue = max(0, (float)$b['total_price'] - (float)($b['discount'] ?? 0));
                    $payDue = max(0, $netDue - (float)($b['amount_paid']));
                    $cancelled = $b['status'] === 'cancelled';
                    $canMarkPaid = $managerAction && $b['status'] === 'confirmed'
                        && in_array($b['payment_status'], ['unpaid', 'partial'], true);

                    if ($cancelled) {
                        $statusText = 'Cancelled';
                        $statusIcon = 'fa-circle-xmark';
                        $statusClass = 'status-cancelled';
                    } elseif ($b['status'] === 'pending') {
                        $statusText = 'Pending';
                        $statusIcon = 'fa-clock';
                        $statusClass = 'status-pending';
                    } else {
                        $statusText = 'Confirmed';
                        $statusIcon = 'fa-circle-check';
                        $statusClass = 'status-confirmed';
                    }

                    if ($b['payment_status'] === 'paid') {
                        $payClass = 'badge-paid';
                        $payText = 'Paid';
                    } elseif ($b['payment_status'] === 'partial') {
                        $payClass = 'badge-partial';
                        $payText = $payDue > 0 ? 'Partially paid · Due Rs ' . number_format($payDue, 0) : 'Partially paid';
                    } elseif (!$cancelled && $payDue > 0) {
                        $payClass = 'badge-unpaid';
                        $payText = 'Due Rs ' . number_format($payDue, 0);
                    } else {
                        $payClass = '';
                        $payText = '';
                    }
                    ?>
                    <tr>
                        <td data-label="Court">
                            <div class="mbt-court">
                                <strong><?php echo e($b['ground_name']); ?></strong>
                                <?php if (!empty($b['location'])): ?>
                                    <span class="muted"><i class="fa-solid fa-location-dot"></i> <?php echo e($b['location']); ?></span>
                                <?php elseif ($showRef && !empty($b['booking_ref'])): ?>
                                    <span class="muted"><?php echo e($b['booking_ref']); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php if ($showPlayer): ?>
                            <td data-label="Player">
                                <div class="mbt-court">
                                    <strong><?php echo e($b['user_name'] ?? ''); ?></strong>
                                    <span class="muted">
                                        <?php if ($showRef && !empty($b['booking_ref'])): ?><?php echo e($b['booking_ref']); ?><?php endif; ?>
                                        <?php if (!empty($b['manager_name'])): ?>
                                            <?php echo $showRef && !empty($b['booking_ref']) ? ' &middot; ' : ''; ?><?php echo e($b['manager_name']); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                        <?php endif; ?>
                        <td data-label="Date"><?php echo e(date('D, M j', strtotime($b['booking_date']))); ?></td>
                        <td class="mbt-time" data-label="Time"><?php echo e(substr($b['start_time'], 0, 5)); ?> &ndash; <?php echo e(substr($b['end_time'], 0, 5)); ?></td>
                        <td data-label="Status">
                            <span class="status-badge <?php echo $statusClass; ?>"><i class="fa-solid <?php echo $statusIcon; ?>"></i> <?php echo e($statusText); ?></span>
                            <?php /* KEPT (removed per request): the payment flag used to sit in the Status
                                     cell, so payment state was mixed in with booking state. It is now a
                                     sub-label under the Total instead:
                                     <?php if ($payClass !== ''): ?><span class="badge <?php echo $payClass; ?> mb-pay-pill"><?php echo e($payText); ?></span><?php endif; ?>
                            */ ?>
                        </td>
                        <td class="num strong" data-label="Total">
                            Rs <?php echo number_format($netDue, 0); ?>
                            <?php if ((float)($b['discount'] ?? 0) > 0): ?>
                                <span class="muted" style="font-size:12px;text-decoration:line-through;">Rs <?php echo number_format((float)$b['total_price'], 0); ?></span>
                            <?php endif; ?>
                            <?php if ($payClass !== ''): ?><span class="mb-pay-sub mb-pay-sub--<?php echo e($payClass); ?>"><?php echo e($payText); ?></span><?php endif; ?>
                        </td>
                        <td class="mbt-actions" data-label="">
                            <div class="mbt-actions-row">
                                <?php if ($canMarkPaid): ?>
                                    <?php echo post_action_form(
                                        base_url('manager/bookings.php'),
                                        'mark_paid',
                                        (string)(int)$b['id'],
                                        '<i class="fa-solid fa-coins"></i> Mark paid',
                                        'mb-cta mb-cta-mark',
                                        'Mark this booking as paid at court?',
                                        'Mark paid',
                                        [],
                                        'Mark as paid?'
                                    ); ?>
                                <?php endif; ?>
                                <?php if ($detailLink): ?>
                                    <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="btn btn-outline btn-sm" title="View details"<?php echo $drawer ? ' data-booking-drawer="' . (int)$b['id'] . '"' : ''; ?>><i class="fa-solid fa-chevron-right"></i><span class="sr-only">Details</span></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

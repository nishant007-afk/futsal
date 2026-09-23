<?php

/**
 * Echo a booking's price.
 * Shows the discounted (net) amount. When a promo/discount was applied,
 * the original total is shown struck through (reusing .price-orig) and a
 * green "Rs X off" .discount-tag pill is shown - matching the ground card
 * and booking_details conventions.
 */
function booking_price_html(array $b): void
{
    $total = (float)($b['total_price'] ?? 0);
    $discount = (float)($b['discount'] ?? 0);
    $netDue = max(0, $total - $discount);
    $hasDiscount = $discount > 0;

    echo '<strong class="mb-price"><span class="mb-price-cur">Rs</span> ' . number_format($netDue, 0) . '</strong>';
    if ($hasDiscount):
        echo '<span class="price-orig">Rs ' . number_format($total, 0) . '</span>';
        echo '<span class="discount-tag" title="' . e(!empty($b['promo_code']) ? ('Promo ' . $b['promo_code'] . ' applied, Rs ' . number_format($discount, 0) . ' off') : 'Rs ' . number_format($discount, 0) . ' off') . '"><i class="fa-solid fa-tag"></i> Rs ' . number_format($discount, 0) . ' off</span>';
    endif;
}

/**
 * Minimal booking card for admin/manager lists, matching the player's card.
 * $show: '' (none), 'user' (player name), 'manager' (manager name).
 */
function booking_card_mini(array $b, string $show = '', string $search = '', ?string $actions_html = null): void
{
    $dateLabel = date('M j, Y', strtotime($b['booking_date']));
    $dayLabel = date('D', strtotime($b['booking_date']));
    $searchAttr = $search !== '' ? ' data-search="' . e(strtolower($search)) . '"' : '';
    // Whitelist status rendering (defense if $b is tampered).
    $allowedClasses = ['sm-cancelled' => true, 'sm-unpaid' => true, 'sm-paid' => true, 'sm-partial' => true];
    $allowedIcons = ['fa-circle-xmark' => true, 'fa-clock' => true, 'fa-circle-check' => true, 'fa-circle-half-stroke' => true];
    if ($b['status'] === 'cancelled') {
        $statusText = 'Cancelled';
        $statusIcon = 'fa-circle-xmark';
        $statusClass = 'sm-cancelled';
    } elseif ($b['payment_status'] === 'paid') {
        $statusText = 'Confirmed · Paid';
        $statusIcon = 'fa-circle-check';
        $statusClass = 'sm-paid';
    } elseif ($b['payment_status'] === 'partial') {
        $statusText = 'Awaiting payment';
        $statusIcon = 'fa-circle-half-stroke';
        $statusClass = 'sm-partial';
    } else {
        $statusText = 'Payment pending';
        $statusIcon = 'fa-clock';
        $statusClass = 'sm-unpaid';
    }
    // Enforce whitelist before output.
    if (!isset($allowedClasses[$statusClass])) { $statusClass = 'sm-unpaid'; }
    if (!isset($allowedIcons[$statusIcon])) { $statusIcon = 'fa-clock'; }
    $statusText = in_array($statusText, ['Cancelled', 'Confirmed · Paid', 'Awaiting payment', 'Payment pending', 'Pending'], true) ? $statusText : 'Pending';
    ?>
    <div class="mbooking mbooking--card"<?php echo $searchAttr; ?>>
        <div class="mbooking-date mb-date" aria-label="<?php echo e($dateLabel); ?>">
            <span class="bd-month"><?php echo e(strtoupper(date('M', strtotime($b['booking_date'])))); ?></span>
            <span class="bd-day"><?php echo (int)date('d', strtotime($b['booking_date'])); ?></span>
            <span class="bd-year"><?php echo e(date('Y', strtotime($b['booking_date']))); ?></span>
            <span class="bd-session">
                <span class="bd-when"><?php echo e($dayLabel); ?></span>
                <span class="bd-time"><i class="fa-regular fa-clock"></i> <?php echo e(substr($b['start_time'], 0, 5)); ?> &ndash; <?php echo e(substr($b['end_time'], 0, 5)); ?></span>
            </span>
        </div>
        <div class="mb-main" title="<?php echo e($b['ground_name']); ?>">
            <h3><?php echo e($b['ground_name']); ?></h3>
            <?php if ($show !== '' && !empty($b[$show . '_name'])): ?>
                <span class="mb-person"><i class="fa-solid fa-user"></i> <?php echo e($b[$show . '_name']); ?></span>
            <?php endif; ?>
            <div class="mb-price-inline"><?php booking_price_html($b); ?></div>
            <?php echo booking_payment_method_html($b); ?>
        </div>
        <span class="mb-st <?php echo $statusClass; ?>"><i class="fa-solid <?php echo $statusIcon; ?>"></i> <?php echo e($statusText); ?></span>
        <div class="mb-side">
            <div class="mb-actions">
                <?php // $actions_html must be built with e()/base_url() by the caller; never pass raw user input.
                if ($actions_html !== null): ?><?php echo $actions_html; ?><?php endif; ?>
                <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="mb-cta mb-cta-more">Details <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Badge showing how a booking was paid (QR / at court / online).
 * Returns '' when there is nothing meaningful to show.
 */
function booking_payment_method_html(array $b): string
{
    $method = (string)($b['payment_method'] ?? '');
    $status = (string)($b['payment_status'] ?? '');
    if ($method === '') {
        return '';
    }
    if ($status === 'partial' && $method === 'qr') {
        return '<span class="mb-paymethod pm-qr"><i class="fa-solid fa-qrcode"></i> Partially paid via QR</span>';
    }
    if ($status !== 'paid') {
        return '';
    }
    $map = [
        'qr'       => ['Paid via QR', 'fa-qrcode', 'pm-qr'],
        'at_court' => ['Paid at court', 'fa-coins', 'pm-court'],
        'online'   => ['Paid online', 'fa-credit-card', 'pm-online'],
    ];
    if (!isset($map[$method])) {
        return '';
    }
    [$label, $icon, $class] = $map[$method];
    return '<span class="mb-paymethod ' . $class . '"><i class="fa-solid ' . $icon . '"></i> ' . $label . '</span>';
}

function booking_refund_policy(string $booking_date, string $start_time, float $amount_paid): array
{
    $slot = strtotime($booking_date . ' ' . $start_time);
    $hoursLeft = ($slot - time()) / 3600;

    if ($hoursLeft >= 24) {
        return [
            'allowed' => true,
            'refund' => $amount_paid,
            'fee' => 0.0,
            'label' => 'Free cancellation with full refund.',
        ];
    }
    if ($hoursLeft > 0) {
        $fee = round($amount_paid * 0.5, 2);
        return [
            'allowed' => true,
            'refund' => round($amount_paid - $fee, 2),
            'fee' => $fee,
            'label' => 'Less than 24 hours before your game. A 50% cancellation fee applies.',
        ];
    }
    return [
        'allowed' => false,
        'refund' => 0.0,
        'fee' => 0.0,
        'label' => 'This booking has already started and can no longer be cancelled online.',
    ];
}

function cancellation_policy_html(): string
{
    return '<div class="cancel-policy-notice"><i class="fa-solid fa-circle-info"></i> '
        . '<strong>Cancellation:</strong> Free up to 24h before. Within 24h: 50% fee. After start: no refund. '
        . '<a href="' . e(base_url('pages/page.php?slug=terms')) . '" class="inline-link">Full terms</a>.</div>';
}

<?php

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    global $conn;
    $stmt = $conn->prepare('SELECT id, name, email, phone, avatar, role, email_verified, created_at FROM users WHERE id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function is_admin(): bool
{
    return is_role('admin');
}

function is_manager(): bool
{
    return is_role('manager');
}

function is_player(): bool
{
    return is_role('user');
}

function is_role(string $role): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === $role;
}

function user_owns_ground(int $ground_id): bool
{
    if (is_admin()) {
        return true;
    }
    if (!is_manager()) {
        return false;
    }
    global $conn;
    $stmt = $conn->prepare('SELECT id FROM grounds WHERE id = ? AND manager_id = ?');
    $stmt->bind_param('ii', $ground_id, $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . base_url('pages/login.php'));
        exit;
    }
}

function require_admin(): void
{
    if (!is_admin()) {
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

function require_manager(): void
{
    if (!is_manager()) {
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

function require_player(): void
{
    if (!is_player()) {
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

function base_url(string $path = ''): string
{
    $root = '/futsal';
    return $root . '/' . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root   = '/futsal';
    return $scheme . '://' . $host . $root . '/' . ltrim($path, '/');
}

function grounds_list_url(): string
{
    return base_url('pages/courts.php');
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function http_error_page(int $code, string $title, string $message, ?string $cta_label = null, ?string $cta_url = null): void
{
    global $conn;
    http_response_code($code);
    $page_title = $title;
    require __DIR__ . '/header.php';
    require __DIR__ . '/views/error_page.php';
    require __DIR__ . '/footer.php';
    exit;
}

function set_flash(string $type, string $message, ?array $detail = null): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message, 'detail' => $detail];
}

function set_flash_error(string $what, ?string $why = null, ?string $how = null, ?string $how_url = null): void
{
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => $what,
        'detail' => ['why' => $why, 'how' => $how, 'how_url' => $how_url],
    ];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid request. Please go back and try again.');
    }
}

function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function release_stale_bookings(): void
{
    $now = time();
    if (isset($_SESSION['last_cleanup']) && $now - (int)$_SESSION['last_cleanup'] < 300) {
        return;
    }
    $_SESSION['last_cleanup'] = $now;

    global $conn;
    $conn->query(
        "UPDATE bookings
         SET status = 'cancelled'
         WHERE status = 'confirmed'
           AND payment_status = 'unpaid'
           AND TIMESTAMP(booking_date, start_time) < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_price($amount): string
{
    return 'Rs. ' . number_format((float)$amount, 2);
}

function validate_promo_code(string $code, float $total, int $ground_id): ?array
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM promo_codes WHERE code = ?');
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $promo = $stmt->get_result()->fetch_assoc();
    if (!$promo || (int)$promo['is_active'] !== 1) {
        return ['error' => 'That promo code is invalid.'];
    }
    $groundStmt = $conn->prepare('SELECT manager_id FROM grounds WHERE id = ?');
    $groundStmt->bind_param('i', $ground_id);
    $groundStmt->execute();
    $ground = $groundStmt->get_result()->fetch_assoc();
    $promoManagerId = $ground ? (int)$ground['manager_id'] : 0;
    if ((int)$promo['manager_id'] !== $promoManagerId || $promoManagerId === 0) {
        return ['error' => 'That promo code doesn\'t apply to this court.'];
    }
    $today = date('Y-m-d');
    if ($promo['starts_at'] && $today < $promo['starts_at']) {
        return ['error' => 'That promo code has not started yet.'];
    }
    if ($promo['expires_at'] && $today > $promo['expires_at']) {
        return ['error' => 'That promo code has expired.'];
    }
    if ($promo['max_uses'] > 0 && (int)$promo['used_count'] >= (int)$promo['max_uses']) {
        return ['error' => 'That promo code has reached its usage limit.'];
    }
    if ((float)$promo['min_total'] > 0 && $total < (float)$promo['min_total']) {
        return ['error' => 'This promo needs a minimum booking of Rs ' . number_format((float)$promo['min_total'], 0) . '.'];
    }
    $discount = $promo['discount_type'] === 'percent'
        ? round($total * (float)$promo['discount_value'] / 100, 2)
        : min((float)$promo['discount_value'], $total);
    return ['promo' => $promo, 'discount' => round($discount, 2)];
}

function increment_promo_usage(int $promo_id): void
{
    global $conn;
    $stmt = $conn->prepare('UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ?');
    $stmt->bind_param('i', $promo_id);
    $stmt->execute();
}

function promo_codes_list(): array
{
    global $conn;
    return $conn->query('SELECT * FROM promo_codes ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);
}

function manager_promo_codes(int $manager_id): array
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM promo_codes WHERE manager_id = ? ORDER BY id DESC');
    $stmt->bind_param('i', $manager_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function slot_is_taken(int $ground_id, string $booking_date, string $start_time): bool
{
    global $conn;
    $stmt = $conn->prepare(
        'SELECT id FROM bookings
         WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND status != "cancelled"'
    );
    $stmt->bind_param('iss', $ground_id, $booking_date, $start_time);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function on_waitlist(int $ground_id, string $booking_date, string $start_time, int $user_id): bool
{
    global $conn;
    $stmt = $conn->prepare('SELECT id FROM waitlist WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND user_id = ?');
    $stmt->bind_param('issi', $ground_id, $booking_date, $start_time, $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function waitlist_count(int $ground_id, string $booking_date, string $start_time): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) c FROM waitlist WHERE ground_id = ? AND booking_date = ? AND start_time = ?');
    $stmt->bind_param('iss', $ground_id, $booking_date, $start_time);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}

function waitlist_position(int $ground_id, string $booking_date, string $start_time, int $user_id): ?int
{
    global $conn;
    $stmt = $conn->prepare(
        'SELECT w.user_id FROM waitlist w
         WHERE w.ground_id = ? AND w.booking_date = ? AND w.start_time = ?
         ORDER BY w.created_at ASC'
    );
    $stmt->bind_param('iss', $ground_id, $booking_date, $start_time);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $i => $row) {
        if ((int)$row['user_id'] === $user_id) {
            return $i + 1;
        }
    }
    return null;
}

function notify_waitlist_freed(int $ground_id, string $booking_date, string $start_time): void
{
    global $conn;
    $gStmt = $conn->prepare('SELECT name FROM grounds WHERE id = ?');
    $gStmt->bind_param('i', $ground_id);
    $gStmt->execute();
    $groundName = $gStmt->get_result()->fetch_assoc()['name'] ?? 'the court';
    $label = date('D, M j', strtotime($booking_date)) . ' at ' . substr($start_time, 0, 5);
    $stmt = $conn->prepare(
        'SELECT id, user_id FROM waitlist
         WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND is_notified = 0
         ORDER BY id ASC'
    );
    $stmt->bind_param('iss', $ground_id, $booking_date, $start_time);
    $stmt->execute();
    $rows = $stmt->get_result();
    while ($row = $rows->fetch_assoc()) {
        notify_user((int)$row['user_id'], 'A slot opened up!', $groundName . ' is free on ' . $label . '. Book before someone else does.', 'fa-bell', 'pages/ground.php?id=' . (int)$ground_id . '&date=' . urlencode($booking_date));
        $uStmt = $conn->prepare('SELECT email FROM users WHERE id = ?');
        $uStmt->bind_param('i', $row['user_id']);
        $uStmt->execute();
        $uRow = $uStmt->get_result()->fetch_assoc();
        if ($uRow) {
            $bookUrl = absolute_url('pages/ground.php?id=' . (int)$ground_id . '&date=' . urlencode($booking_date));
            send_booking_email(
                $uRow['email'],
                'A slot opened up at ' . $groundName,
                'Your waitlist slot just freed up',
                [
                    'Court'      => $groundName,
                    'Date'       => date('D, M j, Y', strtotime($booking_date)),
                    'Time'       => substr($start_time, 0, 5) . ' onwards',
                    'Book now'   => $bookUrl,
                ],
                'Slots go fast. Book it now before someone else grabs it.'
            );
        }
        $upd = $conn->prepare('UPDATE waitlist SET is_notified = 1 WHERE id = ?');
        $upd->bind_param('i', $row['id']);
        $upd->execute();
    }
}

function date_is_blocked(int $ground_id, string $date): bool
{
    global $conn;
    $stmt = $conn->prepare('SELECT id FROM blocked_dates WHERE ground_id = ? AND block_date = ?');
    $stmt->bind_param('is', $ground_id, $date);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function ground_slot_settings(int $ground_id): array
{
    global $conn;
    $default = ['open_time' => '08:00:00', 'close_time' => '22:00:00', 'slot_interval' => 60];
    if ($ground_id <= 0) {
        return $default;
    }
    $stmt = $conn->prepare('SELECT open_time, close_time, slot_interval FROM grounds WHERE id = ?');
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: $default;
}

function ground_price_for_date(int $ground_id, float $base_price, string $date): float
{
    global $conn;
    $stmt = $conn->prepare('SELECT price_weekend, discount_price FROM grounds WHERE id = ?');
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $discounted = $row['discount_price'] ?? null;
    $effective = $base_price;
    if ($discounted !== null && (float)$discounted > 0 && (float)$discounted < (float)$base_price) {
        $effective = (float)$discounted;
    }
    $day = (int)date('N', strtotime($date)); // 1=Mon .. 7=Sun
    if ($day >= 6 && $row['price_weekend'] !== null && (float)$row['price_weekend'] > 0) {
        return (float)$row['price_weekend'];
    }
    return $effective;
}

function slots_for_day($date, $ground_id): array
{
    $s = ground_slot_settings((int)$ground_id);
    $open = (int)substr($s['open_time'], 0, 2) * 60 + (int)substr($s['open_time'], 3, 2);
    $close = (int)substr($s['close_time'], 0, 2) * 60 + (int)substr($s['close_time'], 3, 2);
    $interval = max(15, (int)$s['slot_interval']);
    $slots = [];
    for ($t = $open; $t + $interval <= $close; $t += $interval) {
        $startMin = $t;
        $endMin = $t + $interval;
        $start = sprintf('%02d:%02d:00', intdiv($startMin, 60), $startMin % 60);
        $end = sprintf('%02d:%02d:00', intdiv($endMin, 60), $endMin % 60);
        $label = sprintf('%02d:%02d - %02d:%02d', intdiv($startMin, 60), $startMin % 60, intdiv($endMin, 60), $endMin % 60);
        $slots[] = ['start' => $start, 'end' => $end, 'label' => $label];
    }
    return $slots;
}

function validate_password(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Use at least 8 characters.';
    }
    if (strlen($password) > 72) {
        return 'Passwords must be 72 characters or fewer.';
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        return 'Add at least one letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Add at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Add at least one special character (like ! or @).';
    }
    return null;
}

function ground_images(int $ground_id): array
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM ground_images WHERE ground_id = ? ORDER BY id');
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function ground_cover(int $ground_id): ?string
{
    global $conn;
    $stmt = $conn->prepare('SELECT image FROM ground_images WHERE ground_id = ? ORDER BY id LIMIT 1');
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? $row['image'] : null;
}

function ground_reviews(int $ground_id): array
{
    global $conn;
    $stmt = $conn->prepare(
        'SELECT r.*, u.name AS user_name, u.avatar
         FROM reviews r JOIN users u ON u.id = r.user_id
         WHERE r.ground_id = ? ORDER BY r.created_at DESC'
    );
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function ground_rating(int $ground_id): array
{
    global $conn;
    $stmt = $conn->prepare('SELECT AVG(rating) AS avg, COUNT(*) AS count FROM reviews WHERE ground_id = ?');
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return [
        'avg' => $row['avg'] !== null ? round((float)$row['avg'], 1) : null,
        'count' => (int)$row['count'],
    ];
}

function user_rating_for(int $ground_id): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM reviews WHERE ground_id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $ground_id, $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function user_has_played(int $ground_id): bool
{
    if (!is_logged_in()) {
        return false;
    }
    global $conn;
    $stmt = $conn->prepare(
        'SELECT id FROM bookings
         WHERE ground_id = ? AND user_id = ? AND status = "confirmed"
           AND booking_date < CURDATE()
         LIMIT 1'
    );
    $stmt->bind_param('ii', $ground_id, $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

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

function booking_card(array $b): void
{
    $needsPayment = $b['status'] === 'confirmed' && $b['payment_status'] !== 'paid';
    $netDue = max(0, (float)$b['total_price'] - (float)($b['discount'] ?? 0));
    $payLabel = $b['payment_status'] === 'partial' ? 'Pay Rs ' . number_format(max(0, $netDue - (float)$b['amount_paid']), 0) : 'Pay now';
    $dateLabel = date('M j, Y', strtotime($b['booking_date']));
    $isToday = date('m-d') === date('m-d', strtotime($b['booking_date']));
    $isTomorrow = date('m-d') === date('m-d', strtotime($b['booking_date'] . ' +1 day'));
    $dayLabel = $isToday ? 'Today' : ($isTomorrow ? 'Tomorrow' : date('D', strtotime($b['booking_date'])));
    if ($b['status'] === 'cancelled') {
        $statusText = 'Cancelled';
        $statusIcon = 'fa-circle-xmark';
        $statusClass = 'sm-cancelled';
    } elseif ($b['status'] === 'pending') {
        $statusText = 'Awaiting approval';
        $statusIcon = 'fa-clock';
        $statusClass = 'sm-unpaid';
    } elseif ($b['payment_status'] === 'paid') {
        $statusText = $needsPayment ? 'Confirmed' : 'Confirmed · Paid';
        $statusIcon = $needsPayment ? 'fa-clock' : 'fa-circle-check';
        $statusClass = $needsPayment ? 'sm-partial' : 'sm-paid';
    } elseif ($b['payment_status'] === 'partial') {
        $statusText = 'Awaiting payment';
        $statusIcon = 'fa-circle-half-stroke';
        $statusClass = 'sm-partial';
    } else {
        $statusText = 'Payment pending';
        $statusIcon = 'fa-clock';
        $statusClass = 'sm-unpaid';
    }
    ?>
    <div class="mbooking mbooking--card">
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
            <div class="mb-price-inline"><?php booking_price_html($b); ?></div>
        </div>
        <span class="mb-st <?php echo $statusClass; ?>"><i class="fa-solid <?php echo $statusIcon; ?>"></i> <?php echo $statusText; ?></span>
        <div class="mb-side">
            <div class="mb-actions">
                <?php if ($needsPayment): ?>
                    <a href="<?php echo base_url('pages/payment.php?booking_id=' . (int)$b['id']); ?>" class="mb-cta mb-cta-pay"><i class="fa-solid fa-wallet"></i> <?php echo $payLabel; ?></a>
                <?php endif; ?>
                <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="mb-cta mb-cta-more">Details <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <?php
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
    if ($b['status'] === 'cancelled') {
        $statusText = 'Cancelled';
        $statusIcon = 'fa-circle-xmark';
        $statusClass = 'sm-cancelled';
    } elseif ($b['status'] === 'pending') {
        $statusText = 'Awaiting approval';
        $statusIcon = 'fa-clock';
        $statusClass = 'sm-unpaid';
    } elseif ($b['payment_status'] === 'paid') {
        $statusText = 'Confirmed · Paid';
        $statusIcon = 'fa-circle-check';
        $statusClass = 'sm-paid';
    } elseif ($b['payment_status'] === 'partial') {
        $statusText = 'Awaiting payment';
        $statusIcon = 'fa-circle-half-stroke';
        $statusClass = 'sm-partial';
    } else {
        $statusText = $b['status'] === 'confirmed' ? 'Payment pending' : 'Pending';
        $statusIcon = 'fa-clock';
        $statusClass = 'sm-unpaid';
    }
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
        </div>
        <span class="mb-st <?php echo $statusClass; ?>"><i class="fa-solid <?php echo $statusIcon; ?>"></i> <?php echo $statusText; ?></span>
        <div class="mb-side">
            <div class="mb-actions">
                <?php if ($actions_html !== null): ?><?php echo $actions_html; ?><?php endif; ?>
                <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="mb-cta mb-cta-more">Details <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <?php
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

/**
 * Convert an uploaded image to WebP and write it to $dest.
 * Returns true on success (file saved as WebP) or false on failure.
 * GIFs are saved as-is (no frame support in GD WebP), and already-WebP
 * sources are copied through untouched.
 */
function convert_image_to_webp(string $src, string $dest, int $quality = 82, ?int $maxWidth = null): bool
{
    $src = realpath($src);
    if ($src === false || !is_file($src)) {
        return false;
    }
    $info = @getimagesize($src);
    if ($info === false) {
        return false;
    }
    $mime = $info['mime'];
    if ($mime === 'image/webp') {
        return copy($src, $dest);
    }
    if ($mime === 'image/gif') {
        return copy($src, $dest);
    }
    switch ($mime) {
        case 'image/jpeg':
            $img = @imagecreatefromjpeg($src);
            break;
        case 'image/png':
            $img = @imagecreatefrompng($src);
            break;
        default:
            return false;
    }
    if (!$img) {
        return false;
    }
    if ($maxWidth !== null) {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > $maxWidth) {
            $nh = (int)round($h * ($maxWidth / $w));
            $resized = imagecreatetruecolor($maxWidth, $nh);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $maxWidth, $nh, $w, $h);
            imagedestroy($img);
            $img = $resized;
        }
    }
    if (function_exists('imagepalettetotruecolor')) {
        imagepalettetotruecolor($img);
    }
    $ok = imagewebp($img, $dest, $quality);
    imagedestroy($img);
    return $ok;
}

function save_ground_photos(int $ground_id): array
{
    global $conn;
    $files = $_FILES['photos'] ?? [];
    $names = is_array($files['name'] ?? null) ? $files['name'] : [];
    $tmps = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [];
    $errs = is_array($files['error'] ?? null) ? $files['error'] : [];
    $uploaded = 0;
    $failed = 0;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    for ($i = 0; $i < count($names); $i++) {
        if (($errs[$i] ?? 1) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (($errs[$i] ?? 1) !== UPLOAD_ERR_OK) {
            $failed++;
            continue;
        }
        $mime = finfo_file($finfo, $tmps[$i]);
        $allowed = ['image/jpeg' => true, 'image/png' => true, 'image/webp' => true, 'image/gif' => true];
        if (!isset($allowed[$mime])) {
            $failed++;
            continue;
        }
        $filename = 'ground_' . $ground_id . '_' . bin2hex(random_bytes(6)) . '.webp';
        $dest = __DIR__ . '/../uploads/grounds/' . $filename;
        if (!convert_image_to_webp($tmps[$i], $dest, 82, 1600)) {
            @unlink($dest);
            $failed++;
            continue;
        }
        $stmt = $conn->prepare('INSERT INTO ground_images (ground_id, image) VALUES (?, ?)');
        $stmt->bind_param('is', $ground_id, $filename);
        if ($stmt->execute()) {
            $uploaded++;
        } else {
            @unlink($dest);
            $failed++;
        }
    }
    finfo_close($finfo);
    return [$uploaded, $failed];
}

function setting(string $key, string $default = ''): string
{
    global $conn;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach ($conn->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return isset($cache[$key]) ? (string)$cache[$key] : $default;
}

function manager_setup_fee(): float
{
    return max(0, (float)setting('manager_setup_fee', '2500'));
}

function manager_monthly_fee(): float
{
    return max(0, (float)setting('manager_monthly_fee', '800'));
}

/**
 * Platform commission model (configurable in `settings`).
 * Managers keep (100 - fee)% of each booking's gross; the platform keeps the rest.
 */
function platform_fee_percent(): float
{
    return max(0.0, min(100.0, (float)setting('platform_fee_percent', '10')));
}

function platform_fee_amount(float $gross): float
{
    return round($gross * (platform_fee_percent() / 100.0), 2);
}

function manager_payout(float $gross): float
{
    return round($gross - platform_fee_amount($gross), 2);
}

function manager_subscription(int $manager_id): ?array
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM manager_subscriptions WHERE manager_id = ?');
    $stmt->bind_param('i', $manager_id);
    $stmt->execute();
    $sub = $stmt->get_result()->fetch_assoc();
    return $sub ?: null;
}

function create_manager_subscription(int $manager_id, bool $setup_prepaid = false): void
{
    global $conn;
    $setup = manager_setup_fee();
    $monthly = manager_monthly_fee();
    $setupDate = $setup_prepaid ? date('Y-m-d') : null;
    $setupPaidDate = $setup_prepaid ? date('Y-m-d') : null;
    $periodEnd = date('Y-m-d', strtotime('+30 days'));
    $stmt = $conn->prepare(
        'INSERT IGNORE INTO manager_subscriptions (manager_id, setup_fee, setup_paid_at, monthly_fee, period_start, period_end)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isssss', $manager_id, $setup, $setupPaidDate, $monthly, $setupDate, $periodEnd);
    $stmt->execute();
}

function subscription_status(int $manager_id): array
{
    $sub = manager_subscription($manager_id);
    if (!$sub) {
        return ['key' => 'none', 'label' => 'No subscription', 'active' => false, 'sub' => null];
    }
    $active = true;
    $key = 'active';
    $label = 'Active';
    if ($sub['setup_paid_at'] === null) {
        $active = false;
        $key = 'setup_pending';
        $label = 'Setup fee due';
    } elseif ($sub['period_end'] && $sub['period_end'] < date('Y-m-d')) {
        $active = false;
        $key = 'overdue';
        $label = 'Overdue';
    }
    return ['key' => $key, 'label' => $label, 'active' => $active, 'sub' => $sub];
}

function is_subscription_active(int $manager_id): bool
{
    return subscription_status($manager_id)['active'];
}

function sync_subscription_grounds(): void
{
    global $conn;
    $conn->query(
        'UPDATE grounds g
         JOIN manager_subscriptions s ON s.manager_id = g.manager_id
         SET g.is_active = 0
         WHERE NOT (s.setup_paid_at IS NOT NULL AND (s.period_end IS NULL OR s.period_end >= CURDATE()))'
    );
}

function save_setting(string $key, string $value): void
{
    global $conn;
    $stmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
}

function notify_user(int $user_id, string $title, string $body = '', string $icon = 'fa-bell', string $link = ''): void
{
    global $conn;
    $stmt = $conn->prepare('INSERT INTO notifications (user_id, title, body, icon, link) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('issss', $user_id, $title, $body, $icon, $link);
    $stmt->execute();
}

/**
 * Small HTML email shell shared by all transactional messages.
 * Inline styles only so it renders in every client. No em dashes.
 *
 * @param array $rows key => value pairs shown as a summary table
 */
function booking_email_html(string $heading, array $rows = [], string $note = ''): string
{
    $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $table = '';
    if ($rows) {
        $cells = '';
        foreach ($rows as $k => $v) {
            $cells .= '<tr><td style="padding:7px 0;color:#425349;width:130px;font-size:14px;vertical-align:top;">'
                . $esc($k) . '</td><td style="padding:7px 0;color:#101814;font-size:14px;font-weight:600;">'
                . $esc($v) . '</td></tr>';
        }
        $table = '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:20px 0;">' . $cells . '</table>';
    }
    $noteBlock = $note !== '' ? '<p style="margin:18px 0 0;color:#6e8175;font-size:13px;line-height:1.6;">'
        . $esc($note) . '</p>' : '';

    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f4f7f5;">
<table role="presentation" width="100%" style="background:#f4f7f5;padding:28px 12px;">
<tr><td align="center">
<table role="presentation" width="560" style="max-width:560px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e1e5e1;">
    <tr><td style="background:#0a120e;padding:22px 28px;">
        <span style="color:#6ee7a8;font-size:15px;font-weight:800;">GoalSpace</span>
    </td></tr>
    <tr><td style="padding:28px;">
        <h1 style="margin:0 0 6px;color:#101814;font-size:20px;font-family:Arial,sans-serif;">' . $esc($heading) . '</h1>
        ' . $table . $noteBlock . '
        <p style="margin:22px 0 0;color:#6e8175;font-size:12px;line-height:1.5;">You got this email because it relates to your GoalSpace account.
        <br><a href="' . base_url('index.php') . '" style="color:#059669;">goalspace.com</a></p>
    </td></tr>
</table></td></tr></table></body></html>';
}

/**
 * Send a transactional booking email. Uses in-app style, safe when SMTP is offline.
 *
 * @return bool true if a mail was actually sent (SMTP configured + ack)
 */
function send_booking_email(string $to, string $subject, string $heading, array $rows = [], string $note = ''): bool
{
    return send_mail($to, $subject, booking_email_html($heading, $rows, $note), true);
}

/**
 * Announce legal page updates to users.
 * Sends ONE combined email per user (every selected page in a single message)
 * and ONE in-app notification per user (shown in the notifications tab).
 * No em dashes are used in the messages. Human-friendly tone.
 *
 * @param string[] $slugs  legal page slugs (privacy, terms, about, help, contact)
 * @param string   $date   humanized date, e.g. "August 7, 2026"
 * @param string   $scope  "all", "admins", "managers" or "users"
 * @return int  number of users notified
 */
function notify_policy_update(array $slugs, string $date = '', string $scope = 'all'): int
{
    global $conn;
    $allowed = [
        'privacy' => 'Privacy Policy',
        'terms'   => 'Terms of Service',
        'about'   => 'About GoalSpace',
        'help'    => 'Help and Support',
        'contact' => 'Contact',
    ];
    $chosen = [];
    foreach ($slugs as $s) {
        if (isset($allowed[$s]) && !in_array($s, $chosen, true)) {
            $chosen[$s] = $allowed[$s];
        }
    }
    if (empty($chosen)) {
        return 0;
    }
    if ($date === '') {
        $date = date('F j, Y');
    }

    $labels = [];
    $lines  = [];
    foreach ($chosen as $slug => $label) {
        $labels[] = $label;
        $lines[]  = '- ' . $label . ': ' . absolute_url('pages/page.php?slug=' . $slug);
    }
    $labelText = implode(', ', $labels);
    $summary   = 'GoalSpace updated: ' . $labelText . ' on ' . $date;
    $firstSlug = array_keys($chosen)[0];

    $where = '';
    if ($scope === 'admins')      { $where = "WHERE role = 'admin'"; }
    elseif ($scope === 'managers'){ $where = "WHERE role = 'manager'"; }
    elseif ($scope === 'users')  { $where = "WHERE role = 'user'"; }
    // scope === 'all' applies no filter

    $res = $conn->query('SELECT id,email,name FROM users ' . $where);
    if ($res === false) {
        return 0;
    }

    $sent = 0;
    while ($u = $res->fetch_assoc()) {
        // 1) in-app notification -> appears in the notifications tab (bell)
        notify_user((int)$u['id'], 'Legal pages updated', $summary, 'fa-circle-info', 'pages/page.php?slug=' . $firstSlug);

        // 2) ONE combined email per user (both policies in the same message)
        $subject = 'GoalSpace update: ' . $labelText;
        $msg  = "Hi " . ($u['name'] ?: 'there') . ",\r\n\r\n";
        $msg .= "We have updated the following on GoalSpace:\r\n" . implode("\r\n", $lines) . "\r\n\r\n";
        $msg .= "Last reviewed: " . $date . "\r\n";
        $msg .= "Take a look when you have a moment so you know what changed. Thanks for being part of GoalSpace.\r\n\r\n";
        $msg .= "See you on the court!\r\nThe GoalSpace team";
        @send_mail($u['email'], $subject, $msg);
        $sent++;
    }
    return $sent;
}

function notify_announcement(string $subject, string $message, string $scope = 'all', bool $sendMail = true): int
{
    global $conn;
    $where = '';
    if ($scope === 'admins')      { $where = "WHERE role = 'admin'"; }
    elseif ($scope === 'managers'){ $where = "WHERE role = 'manager'"; }
    elseif ($scope === 'users')  { $where = "WHERE role = 'user'"; }

    $res = $conn->query('SELECT id,email,name FROM users ' . $where);
    if ($res === false) {
        return 0;
    }

    $sent = 0;
    while ($u = $res->fetch_assoc()) {
        notify_user((int)$u['id'], $subject, $message, 'fa-bullhorn');
        if ($sendMail) {
            $body = "Hi " . ($u['name'] ?: 'there') . ",\r\n\r\n" . $message . "\r\n\r\n" .
                "See you on the court!\r\nThe GoalSpace team";
            @send_mail($u['email'], $subject, $body);
        }
        $sent++;
    }
    return $sent;
}

function user_notifications(int $user_id, int $limit = 20): array
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->bind_param('ii', $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function unread_notification_count(int $user_id): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}

function notification_time(?string $created_at): string
{
    if (!$created_at) {
        return '';
    }
    $ts = strtotime($created_at);
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 172800) {
        return 'yesterday';
    }
    return date('M j', $ts);
}

function notification_icon_color(string $icon): string
{
    $map = [
        'fa-calendar-check' => 'green',
        'fa-store' => 'blue',
        'fa-sack-dollar' => 'gold',
        'fa-circle-xmark' => 'red',
        'fa-bell' => 'brand',
        'fa-calendar-xmark' => 'red',
    ];
    return $map[$icon] ?? 'brand';
}

function mark_notifications_read(int $user_id): void
{
    global $conn;
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}

/**
 * Login attempt throttling.
 * Tracks failures per identifier (email) + IP.
 * Lockout base: 60s after 5 fails, +30s per extra failure (max 10min).
 * After 10+ fails the identifier is marked suspended.
 */
function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

function login_attempt_key(string $email): string
{
    return strtolower(trim($email)) . '|' . client_ip();
}

function login_lock_seconds(int $failures): int
{
    if ($failures < 5) {
        return 0;
    }
    $extra = $failures - 5;
    return min(600, 60 + $extra * 30);
}

function get_login_attempt(string $key): ?array
{
    global $conn;
    $stmt = $conn->prepare('SELECT attempts, locked_until, suspended FROM login_attempts WHERE identifier = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }
    if ($row['locked_until'] && strtotime($row['locked_until']) <= time()) {
        $stmt = $conn->prepare('UPDATE login_attempts SET locked_until = NULL WHERE identifier = ?');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row['locked_until'] = null;
    }
    return $row;
}

function record_login_failure(string $key): void
{
    global $conn;
    $stmt = $conn->prepare('INSERT INTO login_attempts (identifier, ip, attempts, locked_until) VALUES (?, ?, 1, NULL) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt_at = NOW()');
    $ip = client_ip();
    $stmt->bind_param('ss', $key, $ip);
    $stmt->execute();

    $row = get_login_attempt($key);
    $failures = (int)($row['attempts'] ?? 0);
    if ($failures >= 5) {
        $lockUntil = date('Y-m-d H:i:s', time() + login_lock_seconds($failures));
        $stmt = $conn->prepare('UPDATE login_attempts SET locked_until = ? WHERE identifier = ?');
        $stmt->bind_param('ss', $lockUntil, $key);
        $stmt->execute();
    }
}

function login_attempt_state(string $key): array
{
    $row = get_login_attempt($key);
    $failures = (int)($row['attempts'] ?? 0);
    $state = [
        'failures' => $failures,
        'locked' => false,
        'suspended' => !empty($row['suspended']),
        'lock_seconds' => 0,
        'lock_ends' => null,
    ];
    if (!empty($row['suspended']) || $failures > 10) {
        if (!$row['suspended'] && $failures > 10) {
            suspend_login_identifier($key);
        }
        $state['suspended'] = true;
        $state['locked'] = true;
        return $state;
    }
    if ($failures >= 5) {
        $lockUntil = $row['locked_until'] ? strtotime($row['locked_until']) : time() + login_lock_seconds($failures);
        $remaining = max(0, $lockUntil - time());
        $state['locked'] = true;
        $state['lock_seconds'] = $remaining;
        $state['lock_ends'] = date('c', $lockUntil);
    }
    return $state;
}

function clear_login_attempts(string $key): void
{
    global $conn;
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE identifier = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
}

function suspend_login_identifier(string $key): void
{
    global $conn;
    $stmt = $conn->prepare('UPDATE login_attempts SET suspended = 1, locked_until = NULL WHERE identifier = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
}

function login_attempts_email(string $key): string
{
    return explode('|', $key)[0];
}

function issue_otp(string $identifier, string $purpose, int $ttlSeconds = 300): string
{
    global $conn;
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare('UPDATE otps SET used = 1 WHERE identifier = ? AND purpose = ? AND used = 0');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $stmt = $conn->prepare('INSERT INTO otps (identifier, purpose, code, attempts, expires_at) VALUES (?, ?, ?, 0, ?)');
    $stmt->bind_param('ssss', $identifier, $purpose, $code, $expires);
    $stmt->execute();
    return $code;
}

function verify_otp(string $identifier, string $purpose, string $code): bool
{
    global $conn;
    $stmt = $conn->prepare('SELECT id, code, expires_at, used, attempts FROM otps WHERE identifier = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return false;
    }
    if (strtotime($row['expires_at']) < time()) {
        $stmt = $conn->prepare('UPDATE otps SET used = 1 WHERE id = ?');
        $stmt->bind_param('i', $row['id']);
        $stmt->execute();
        return false;
    }
    if ((int)$row['attempts'] >= 5) {
        return false;
    }
    if (!hash_equals($row['code'], $code)) {
        $stmt = $conn->prepare('UPDATE otps SET attempts = attempts + 1 WHERE id = ?');
        $stmt->bind_param('i', $row['id']);
        $stmt->execute();
        return false;
    }
    $stmt = $conn->prepare('UPDATE otps SET used = 1 WHERE id = ?');
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();
    return true;
}

function otp_attempts_left(string $identifier, string $purpose): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT attempts FROM otps WHERE identifier = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return 5;
    }
    return max(0, 5 - (int)$row['attempts']);
}

function otp_remaining_seconds(string $identifier, string $purpose): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT expires_at FROM otps WHERE identifier = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return 0;
    }
    return max(0, strtotime($row['expires_at']) - time());
}

function otp_send_cooldown(string $identifier, string $purpose, int $cooldown = 30): int
{
    global $conn;
    static $bypassEmails = ['nishantdahal612@gmail.com', 'admin@futsal.com'];
    if (in_array($identifier, $bypassEmails, true)) {
        return 0;
    }
    $stmt = $conn->prepare('SELECT created_at FROM otps WHERE identifier = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $wait = 0;
    if ($row) {
        $wait = max($wait, $cooldown - (time() - strtotime($row['created_at'])));
    }
    $wait = max($wait, otp_send_hour_limit($identifier, $purpose));
    return max(0, $wait);
}

function otp_send_hour_limit(string $identifier, string $purpose, int $maxPerHour = 5): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) AS c, MIN(created_at) AS oldest FROM otps WHERE identifier = ? AND purpose = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ((int)$row['c'] < $maxPerHour) {
        return 0;
    }
    return max(0, (strtotime($row['oldest']) + 3600) - time());
}

function format_otp_wait(int $seconds): string
{
    if ($seconds >= 3600) {
        $hours = intdiv($seconds, 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's');
    }
    if ($seconds >= 60) {
        $minutes = intdiv($seconds, 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }
    return $seconds . 's';
}

function otp_subject(string $purpose): string
{
    switch ($purpose) {
        case 'email_verify':
            return 'Verify your email';
        case 'password_reset':
            return 'Your password reset code';
        case 'login':
            return 'Your login code';
        case 'password_change':
            return 'Your security code';
        case 'email_change':
            return 'Verify your new email';
        default:
            return 'Your GoalSpace security code';
    }
}


function send_otp_mail(string $email, string $code, string $purpose): bool
{
    $title = otp_subject($purpose);
    $body = 'Hi there,' . "\r\n\r\n";
    $body .= 'You asked to ' . otp_purpose_verb($purpose) . '. Here is your code:' . "\r\n\r\n";
    $body .= '<span style="font-size:22px;font-weight:bold;letter-spacing:3px;">' . $code . '</span>' . "\r\n\r\n";
    $body .= 'This code works for the next 5 minutes.' . "\r\n\r\n";
    $body .= "If this wasn't you, just ignore this email. Nothing will change." . "\r\n\r\n";
    $body .= 'See you on the court!' . "\r\n\r\n";
    $body .= 'The GoalSpace team';
    return send_mail($email, $title, $body);
}

function otp_purpose_verb(string $purpose): string
{
    switch ($purpose) {
        case 'email_verify':
            return 'verify your email address';
        case 'password_reset':
            return 'reset your password';
        case 'login':
            return 'sign in to your account';
        case 'password_change':
            return 'change your password';
        case 'email_change':
            return 'verify your new email address';
        default:
            return 'continue with what you were doing';
    }
}

function export_csv(array $rows, string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

/**
 * Export rows as a real .xlsx file without any PHP extension.
 * Builds the OOXML parts and wraps them in a ZIP archive (STORED, no compression).
 */
function export_excel(array $rows, string $filename): void
{
    $escape = function (string $s): string {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };

    // shared strings
    $shared = [];
    $sheetRows = [];
    foreach ($rows as $row) {
        $cells = '';
        foreach ($row as $cell) {
            $v = (string)$cell;
            $idx = array_search($v, $shared, true);
            if ($idx === false) {
                $shared[] = $v;
                $idx = count($shared) - 1;
            }
            $cells .= '<c t="s"><v>' . $idx . '</v></c>';
        }
        $sheetRows[] = '<row>' . $cells . '</row>';
    }
    $sharedXml = '';
    foreach ($shared as $s) {
        $sharedXml .= '<si><t xml:space="preserve">' . $escape($s) . '</t></si>';
    }

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="1"><xf xfId="0"/></cellXfs>'
            . '</styleSheet>',
        'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($shared) . '" uniqueCount="' . count($shared) . '">'
            . $sharedXml . '</sst>',
        'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode('', $sheetRows) . '</sheetData></worksheet>',
    ];

    // Build ZIP (STORED)
    $zipData = '';
    $central = '';
    $offset = 0;
    foreach ($files as $name => $content) {
        $name = str_replace('\\', '/', $name);
        $nameBytes = $content;
        $crc = crc32($nameBytes);
        $size = strlen($nameBytes);
        $nameLen = strlen($name);
        // local file header
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0) . $name . $nameBytes;
        $zipData .= $local;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 0, $offset) . $name;
        $offset += strlen($local);
    }
    $centralSize = strlen($central);
    $eocd = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), $centralSize, $offset, 0);
    $zipData .= $central . $eocd;

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($zipData));
    echo $zipData;
    exit;
}

function star_html($rating): string
{
    $rating = (float)$rating;
    $html = '<span class="stars" aria-label="' . number_format($rating, 1) . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= '<i class="fa-solid fa-star"></i>';
        } elseif ($rating >= $i - 0.5) {
            $html .= '<i class="fa-solid fa-star-half-stroke"></i>';
        } else {
            $html .= '<i class="fa-regular fa-star"></i>';
        }
    }
    return $html . '</span>';
}

function ground_card_html(array $ground, ?array $availability = null): void
{
    $cover = ground_cover((int)$ground['id']);
    $rating = ground_rating((int)$ground['id']);
    $full = $availability !== null && $availability['free'] === 0;
    ?>
    <div class="card reveal <?php echo $full ? 'card-full' : ''; ?>">
        <div class="card-img">
            <?php if ($cover): ?>
                <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($cover)); ?>" alt="<?php echo e($ground['name']); ?>" class="card-cover" loading="lazy" decoding="async">
            <?php else: ?>
                <div class="pitch"></div>
            <?php endif; ?>
            <?php if (is_logged_in()): ?>
                <button type="button"
                        class="fav-toggle card-fav"
                        data-ground-id="<?php echo (int)$ground['id']; ?>"
                        data-url="<?php echo base_url('ajax/favorite.php'); ?>"
                        aria-label="<?php echo favorite_exists((int)$ground['id']) ? 'Remove from saved courts' : 'Save this court'; ?>"
                        title="<?php echo favorite_exists((int)$ground['id']) ? 'Remove from saved courts' : 'Save this court'; ?>"
                        data-saved="<?php echo favorite_exists((int)$ground['id']) ? '1' : '0'; ?>">
                    <i class="fa-heart <?php echo favorite_exists((int)$ground['id']) ? 'fa-solid' : 'fa-regular'; ?>"
                       style="<?php echo favorite_exists((int)$ground['id']) ? 'color:var(--danger);' : ''; ?>" aria-hidden="true"></i>
                    <span class="fav-text"><?php echo favorite_exists((int)$ground['id']) ? 'Saved' : 'Save'; ?></span>
                </button>
            <?php endif; ?>
                <?php if ($availability !== null): ?>
                <span class="thumb-tag availability <?php echo $full ? 'is-full' : 'is-free'; ?>">
                    <?php if ($full): ?>
                        <i class="fa-solid fa-circle-xmark"></i> Fully booked
                    <?php else: ?>
                        <i class="fa-solid fa-circle-check"></i> <?php echo $availability['free']; ?> slot<?php echo $availability['free'] === 1 ? '' : 's'; ?> left
                    <?php endif; ?>
                </span>
            <?php elseif (!empty($ground['distance_km'])): ?>
                <span class="thumb-tag"><i class="fa-solid fa-walkie-talkie"></i> <?php echo number_format((float)$ground['distance_km'], 1); ?> km</span>
            <?php else: ?>
                <span class="thumb-tag"><i class="fa-solid fa-location-dot"></i> <?php echo e($ground['location']); ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <h3 class="card-title"><?php echo e($ground['name']); ?></h3>
            <div class="card-meta">
                <span class="price">
                    <?php
                    $cardPrice = (float)$ground['price_per_hour'];
                    $cardDisc = isset($ground['discount_price']) ? (float)$ground['discount_price'] : 0;
                    $cardSale = $cardDisc > 0 && $cardDisc < $cardPrice;
                    ?>
                    <?php if ($cardSale): ?><span class="price-orig">Rs <?php echo number_format($cardPrice, 0); ?></span><?php endif; ?>
                    <?php echo number_format($cardSale ? $cardDisc : $cardPrice, 0); ?><small> Rs / hour</small>
                </span>
                <?php if ($rating['count'] > 0): ?>
                    <span class="card-rating-inline"><?php echo star_html($rating['avg']); ?> <small><?php echo number_format((float)$rating['avg'], 1); ?></small></span>
                <?php else: ?>
                    <span class="card-rating-inline"><i class="fa-solid fa-star" style="color:var(--ink-3);"></i> <small>No reviews yet</small></span>
                <?php endif; ?>
                <a href="<?php echo base_url('pages/ground.php?id=' . (int)$ground['id']); ?>" class="btn btn-primary btn-sm card-cta-link">View details <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Favorites (saved courts). Requires login. Helpers are safe no-ops when the
 * table is missing (e.g. before running tools/migrate.php).
 */
function favorite_exists(int $ground_id): bool
{
    if (!is_logged_in() || $ground_id <= 0) {
        return false;
    }
    global $conn;
    $stmt = $conn->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND ground_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $_SESSION['user_id'], $ground_id);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_row();
}

function toggle_favorite(int $ground_id): bool
{
    if (!is_logged_in() || $ground_id <= 0) {
        return false;
    }
    global $conn;
    if (favorite_exists($ground_id)) {
        $stmt = $conn->prepare('DELETE FROM favorites WHERE user_id = ? AND ground_id = ?');
        $stmt->bind_param('ii', $_SESSION['user_id'], $ground_id);
    } else {
        $stmt = $conn->prepare('INSERT INTO favorites (user_id, ground_id) VALUES (?, ?)');
        $stmt->bind_param('ii', $_SESSION['user_id'], $ground_id);
    }
    return $stmt ? $stmt->execute() : false;
}

function favorite_ground_ids(): array
{
    if (!is_logged_in()) {
        return [];
    }
    global $conn;
    $stmt = $conn->prepare('SELECT ground_id FROM favorites WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'ground_id');
}

/**
 * Returns ' has-error' when the given field has a validation error,
 * so the .form-group can be highlighted in red alongside the inline message.
 */
function has_error(array $errors, string $field): string
{
    return !empty($errors[$field]) ? ' has-error' : '';
}

/**
 * Prints the inline error message for a field, pointing directly at the mistake.
 * Shows nothing (and echoes nothing) when the field is valid.
 */
function field_error(array $errors, string $field): void
{
    if (!empty($errors[$field])) {
        echo '<p class="field-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i>'
            . e($errors[$field]) . '</p>';
    }
}

/**
 * Renders a persistent inline callout at the top of a form for a form-level
 * error (not tied to one field). Stays until dismissed or the form is resubmitted.
 */
function render_inline(string $message): void
{
    echo '<div class="toast toast-error toast-inline" role="alert">'
        . '<div class="toast-icon"><i class="fa-solid fa-circle-exclamation"></i></div>'
        . '<div class="toast-content"><div class="toast-msg">' . e($message) . '</div></div>'
        . '<button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>'
        . '</div>';
}

/**
 * Prints an always-visible helper hint above/below a field so users know
 * what to enter (and what to avoid) before they even submit.
 */
function field_hint(string $text, string $icon = 'fa-circle-info'): void
{
    echo '<p class="form-hint hint-tip"><i class="fa-solid ' . e($icon) . '"></i>'
        . e($text) . '</p>';
}

/**
 * Convenience wrapper that renders the hint and then the inline error for a field.
 */
function hint_and_error(array $errors, string $field, string $hint, string $icon = 'fa-circle-info'): void
{
    field_hint($hint, $icon);
    field_error($errors, $field);
}

/**
 * Stores per-field errors + submitted values across a redirect (POST -> GET)
 * so the receiving form can re-render inline errors and keep user input.
 */
function flash_form(array $errors, array $old = []): void
{
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old'] = $old;
}

function form_errors(): array
{
    $e = $_SESSION['form_errors'] ?? [];
    unset($_SESSION['form_errors']);
    return is_array($e) ? $e : [];
}

function form_old(): array
{
    $o = $_SESSION['form_old'] ?? [];
    unset($_SESSION['form_old']);
    return is_array($o) ? $o : [];
}

function old_value(array $old, string $field, string $default = ''): string
{
    return isset($old[$field]) ? (string)$old[$field] : $default;
}

/**
 * Static fallback definitions for legal / static pages.
 * The live editor (admin/pages.php) writes into the `pages` table;
 * page_content() merges DB rows over these defaults, so the site
 * keeps working even if the table has not been created yet.
 */
function legal_pages_defaults(): array
{
    return [
        'about'   => [
            'title'   => 'About GoalSpace',
            'summary' => 'GoalSpace helps players find and book futsal courts, and helps owners keep their grounds full.',
            'body'    => '
            <p><strong>GoalSpace</strong> is a booking platform made for futsal. It connects players who want a court with owners who have spare hours to fill.</p>
            <p>We started GoalSpace because booking a court usually meant calling three venues and hoping someone answered. We wanted something quicker: see what\'s free, pick a slot, done.</p>

            <h2>Who it\'s for</h2>
            <ul>
                <li><strong>Players</strong> can find nearby courts, see real-time availability, and book a slot in a couple of minutes.</li>
                <li><strong>Managers</strong> can list their courts, keep the calendar full, and always know who has paid.</li>
                <li><strong>Admins</strong> keep the platform running smoothly and manage users and listings.</li>
            </ul>

            <h2>What we care about</h2>
            <ul>
                <li><strong>Bookings that stick.</strong> When a slot is confirmed, it is locked. No double-booking, no surprises.</li>
                <li><strong>Simple tools for owners.</strong> Managing bookings and payments should not be a second job.</li>
                <li><strong>Honest information.</strong> Prices, hours and availability are shown straight, so you know what you are getting.</li>
            </ul>

            <h2>Contact</h2>
            <p>We read everything that comes in, whether it is a question, some feedback, or just to say hi. Write to us at <a href="mailto:hello@goalspace.com">hello@goalspace.com</a>.</p>
        ',
        ],
        'privacy' => [
            'title'   => 'Privacy Policy',
            'summary' => 'What data we collect, why we collect it, and how we keep it safe.',
            'body'    => '
            <p class="updated-note">Last updated: August 9, 2026</p>

            <h2>Information we collect</h2>
            <ul>
                <li><strong>Account details</strong>: your name, email address and phone number, provided when you sign up.</li>
                <li><strong>Booking data</strong>: the courts, dates and time slots you reserve.</li>
                <li><strong>Usage data</strong>: pages you visit and actions you take, used to improve the platform.</li>
                <li><strong>Location data</strong>: when you choose "Use my location" on the courts page, we request your browser&rsquo;s geolocation to show courts sorted by distance. This is only used during that session to sort and display distances; we do not store your precise location on our servers.</li>
            </ul>

            <h2>Why we use it</h2>
            <ul>
                <li>To create and manage your account.</li>
                <li>To process and manage bookings including sharing booking details with the manager of the court.</li>
                <li>To show nearby courts and sort by distance when you opt in.</li>
                <li>To keep the platform secure and prevent misuse.</li>
                <li>To improve performance and user experience.</li>
            </ul>

            <h2>Who we share it with</h2>
            <p>We do not sell your personal data. Your details are shared only with:</p>
            <ul>
                <li>The <strong>court manager</strong> when you book one of their grounds (so they can confirm your slot).</li>
                <li>Service providers that host and operate the platform, bound by confidentiality.</li>
                <li>Authorities, only where required by law.</li>
            </ul>
            <p><strong>Your precise location is never shared with managers, third parties, or stored in our database.</strong> It is only used client-side in your browser to calculate distances for sorting.</p>

            <h2>Cookies and sessions</h2>
            <p>We use session cookies to keep you logged in. You can clear these at any time in your browser; you\'ll just need to log in again.</p>

            <h2>Data security</h2>
            <p>Passwords are stored as strong, one-way hashes and are never readable by staff. Access to dashboards is limited by role so each person sees only the information they need.</p>

            <h2>Your rights</h2>
            <p>You may request a copy of your data, ask us to correct it, or ask us to delete your account and bookings. Contact <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> and we will act on your request within 30 days.</p>

            <h2>Changes to this policy</h2>
            <p>If we change this policy, we will update the date above and, where practical, notify you by email.</p>
        ',
        ],
        'terms'  => [
            'title'   => 'Terms of Service',
            'summary' => 'The rules for using GoalSpace as a player, manager or admin.',
            'body'    => '
            <p class="updated-note">Last updated: August 9, 2026</p>
            <p>By creating an account or using GoalSpace, you agree to these terms.</p>

            <h2>Your account</h2>
            <ul>
                <li>You must provide accurate information and keep your login details secure.</li>
                <li>One account per person. You may not share accounts or credentials.</li>
                <li>You are responsible for activity that happens under your account.</li>
            </ul>

            <h2>Booking courts</h2>
            <ul>
                <li>A booking is <strong>confirmed immediately</strong> the moment you reserve a slot. No approval needed.</li>
                <li>Once booked, the slot is locked and cannot be taken by anyone else.</li>
                <li>Confirmed bookings can be cancelled from "My Bookings" at any time before the game.</li>
                <li>Managers and admins may cancel any booking on their grounds or across the platform.</li>
                <li>Misusing the booking system (fake bookings, spam or harassment) may lead to account suspension.</li>
            </ul>

            <h2 id="for-managers">For managers</h2>
            <p>Managers are court owners who list their grounds and accept bookings. By becoming a manager you agree to the following:</p>
            <ul>
                <li><strong>Your courts only.</strong> You manage only the grounds assigned to your account. You cannot edit or cancel bookings for courts you do not own.</li>
                <li><strong>Listings must be accurate.</strong> Prices, opening hours and court details should be truthful and kept up to date. Misleading listings may be removed.</li>
                <li><strong>Honour confirmed slots.</strong> Once a booking is confirmed, keep the court available for that slot, or work with the player if a change is unavoidable.</li>
                <li><strong>Respect player data.</strong> You may see players\' names and contact details only to manage their bookings. Do not use them for marketing without permission.</li>
                <li><strong>No double-selling.</strong> A slot that is booked through GoalSpace must not also be sold elsewhere.</li>
            </ul>

            <h2>Admin responsibilities</h2>
            <ul>
                <li>Admins administer the platform: users, grounds and system settings.</li>
                <li>Admins may remove content or accounts that violate these terms.</li>
            </ul>

            <h2>Acceptable use</h2>
            <ul>
                <li>Do not attempt to access other users\' data or restricted areas.</li>
                <li>Do not disrupt, overload or attempt to break the platform.</li>
                <li>Do not use the platform for any unlawful purpose.</li>
            </ul>

            <h2>Limitation of liability</h2>
            <p>GoalSpace is a booking platform; court quality, availability and gameplay are the responsibility of each court owner. We are not liable for issues arising at a court, such as cancellations or facilities.</p>

            <h2>Location services</h2>
            <p>When you use the "Use my location" feature, your browser may request access to your device&rsquo;s geolocation. You can deny or revoke this permission at any time in your browser settings. GoalSpace does not store your precise location; it is used only to sort and display nearby courts during your session. You can use GoalSpace fully without enabling location access by searching by city or area instead.</p>

            <h2>Changes and termination</h2>
            <p>We may update these terms or suspend accounts that breach them. Continued use after a change means you accept the updated terms.</p>
        ',
        ],
        'contact' => [
            'title'   => 'Contact Us',
            'summary' => 'We are happy to help players and court owners.',
            'body'    => '
            <p>Need help with a booking, your account or a court? Message us below, and we respond within 1 day.</p>
        ',
        ],
        'help'    => [
            'title'   => 'Help & Support',
            'summary' => 'Answers to common questions for players, managers and admins.',
            'body'    => '
            <h2 id="for-players">For players</h2>
            <h3>How do I book a court?</h3>
            <p>Find a ground on the homepage, pick a date, choose an available time slot and confirm. Your slot locks in immediately, then you choose a payment option.</p>
            <h3>How does payment work?</h3>
            <p>After booking you can either pay a 20% advance online and the rest at the court, or pay the full amount online in one go. Both options are shown at checkout.</p>
            <h3>Can I cancel a booking?</h3>
            <p>Yes, confirmed bookings can be cancelled from "My Bookings" at any time.</p>

            <h2 id="for-managers">For managers</h2>
            <h3>How do I become a manager?</h3>
            <p>Sign up and choose the "Manager" option, or click <a href="%MANAGER_URL%">Become a manager</a>. You will land on your manager dashboard where you can add your first court.</p>
            <h3>How do I list a court?</h3>
            <p>From "My Grounds", click "Add Ground" and enter the name, location, capacity and hourly price. Once saved, the court is visible on the site for players to book.</p>
            <h3>How do I edit or remove a court?</h3>
            <p>Go to "My Grounds", find the court and use Edit or Delete. You can also mark a court inactive so it stops accepting bookings without deleting it.</p>
            <h3>How do bookings work?</h3>
            <p>When a player reserves a slot on your court, it is confirmed instantly. No approval needed. You can cancel any booking from the bookings page if you can no longer host.</p>
            <h3>Can I track payments and revenue?</h3>
            <p>Yes. Your dashboard shows revenue from your courts, plus a payment badge (Paid, Partial or Unpaid) on every booking.</p>
            <h3>What should I do if a player cancels?</h3>
            <p>When a player cancels from "My Bookings", the slot becomes free again automatically for others to book.</p>

            <h2 id="for-admins">For admins</h2>
            <h3>How do I change a user\'s role?</h3>
            <p>Go to Users in the admin dashboard and choose a new role from the dropdown.</p>
            <h3>How do I assign a ground to a manager?</h3>
            <p>Edit the ground in the admin dashboard and select the owning manager.</p>

            <h2>Still stuck?</h2>
            <p>Contact us at <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> and we will help.</p>
        ',
        ],
    ];
}

/**
 * Resolve a legal/static page.
 * Falls back to the static defaults in legal_pages_defaults()
 * when the `pages` table is missing or the slug is not present.
 */
function page_content(string $slug): ?array
{
    global $conn;
    $defaults = legal_pages_defaults();
    $base = isset($defaults[$slug]) ? $defaults[$slug] : null;
    if ($base === null) {
        return null;
    }
    // Try the live DB first (table may not exist on a fresh install).
    try {
        $stmt = $conn->prepare('SELECT title, summary, body, updated_at FROM pages WHERE slug = ?');
        if ($stmt) {
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            $stmt->bind_result($title, $summary, $body, $updated_at);
            if ($stmt->fetch()) {
                $stmt->close();
                return [
                    'title'       => (string)$title,
                    'summary'     => (string)$summary,
                    'body'        => (string)$body,
                    'updated_at'  => $updated_at ? (string)$updated_at : '',
                ];
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // table missing or other DB issue: keep the static fallback
    }
    return $base;
}

/**
 * Persist an edited legal/static page. Inserts or updates the `pages` row.
 * @return bool true on success
 */
function save_page(string $slug, string $title, string $summary, string $body): bool
{
    global $conn;
    $defaults = legal_pages_defaults();
    if (!isset($defaults[$slug])) {
        return false;
    }
    $stmt = $conn->prepare(
        'INSERT INTO pages (slug, title, summary, body) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE title = VALUES(title), summary = VALUES(summary), body = VALUES(body), updated_at = CURRENT_TIMESTAMP'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssss', $slug, $title, $summary, $body);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function render_json_ld(array $data): string
{
    return '<script type="application/ld+json">' . "\n" . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n" . '</script>';
}

function ground_seo_meta(array $ground): void
{
    global $page_title, $page_description, $page_image, $page_url, $og_type;
    $title = $ground['name'];
    $loc  = isset($ground['address']) && $ground['address'] !== ''
        ? $ground['address'] : (isset($ground['city']) && $ground['city'] !== '' ? $ground['city'] : 'GoalSpace');
    $page_title       = $title . ' - Book futsal court';
    $page_description = 'Book the ' . $ground['name'] . ' futsal court'
        . ($loc !== 'GoalSpace' ? ' in ' . $loc : '')
        . '. Check real-time availability, prices, and pay securely with GoalSpace.';
    $img = ground_cover($ground['id']);
    $page_image = $img ? absolute_url($img) : absolute_url('assets/img/social-og.png');
    $page_url     = absolute_url('pages/ground.php?id=' . (int) $ground['id'] . (isset($ground['slug']) && $ground['slug'] !== '' ? '&slug=' . $ground['slug'] : ''));
    $og_type      = 'article';
}

function ground_detail_url(int $ground_id): string
{
    $slug = ground_slug($ground_id);
    if ($slug !== '') {
        return base_url('pages/ground.php?id=' . $ground_id . '&slug=' . $slug);
    }
    return base_url('pages/ground.php?id=' . $ground_id);
}

function ground_slug(int $ground_id): string
{
    global $conn;
    $slug = '';
    $stmt = $conn->prepare('SELECT slug FROM grounds WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $ground_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $slug = (string) ($row['slug'] ?? '');
        }
        $stmt->close();
    }
    return $slug;
}

function ground_json_ld(array $ground): string
{
    $rating = ground_rating($ground['id']);
    $img    = ground_cover($ground['id']);
    $price  = isset($ground['price']) ? (float) $ground['price'] : 0.0;
    $data   = [
        '@context' => 'https://schema.org',
        '@type'    => 'SportsActivityLocation',
        'name'     => $ground['name'],
        'image'    => $img ? [absolute_url($img)] : [absolute_url('assets/img/social-og.png')],
        'address'  => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => $ground['address'] ?? '',
            'addressLocality' => $ground['city'] ?? '',
            'addressRegion'   => $ground['state'] ?? '',
            'postalCode'      => $ground['zip'] ?? '',
        ],
        'geo' => [
            '@type'      => 'GeoCoordinates',
            'latitude'   => $ground['lat'] ?? null,
            'longitude'  => $ground['lng'] ?? null,
        ],
        'priceRange'  => $price > 0 ? '$' . number_format($price, 2) : 'Ask',
        'sport'       => 'Futsal',
        'aggregateRating' => [
            '@type'         => 'AggregateRating',
            'ratingValue'   => $rating['avg'] ?? null,
            'reviewCount'   => $rating['count'] ?? 0,
            'bestRating'    => 5,
            'worstRating'   => 1,
        ],
    ];
    return render_json_ld($data);
}

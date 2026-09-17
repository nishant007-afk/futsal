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
    // Cache per request to avoid N+1 queries
    if (isset($GLOBALS['__current_user'])) {
        return $GLOBALS['__current_user'];
    }
    global $conn;
    $stmt = $conn->prepare('SELECT id, name, email, phone, avatar, role, email_verified, created_at, notify_bookings, notify_promo, notify_expiry, notify_sms FROM users WHERE id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $GLOBALS['__current_user'] = $result->fetch_assoc();
    return $GLOBALS['__current_user'];
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

    $configured = trim((string)env('ADMIN_IPS', ''));
    if ($configured !== '') {
        $allowed = array_filter(array_map('trim', explode(',', $configured)));
        $current = $_SERVER['REMOTE_ADDR'] ?? '';
        $isLocal = in_array($current, ['127.0.0.1', '::1'], true) && (in_array('127.0.0.1', $allowed, true) || in_array('::1', $allowed, true));
        if (!$isLocal && !in_array($current, $allowed, true)) {
            header('Location: ' . base_url('index.php'));
            exit;
        }
    }
}

function require_manager(): void
{
    if (!is_manager() && !is_admin()) {
        header('Location: ' . base_url('index.php'));
        exit;
    }

    $configured = trim((string)env('MANAGER_IPS', ''));
    if ($configured !== '') {
        $allowed = array_filter(array_map('trim', explode(',', $configured)));
        $current = $_SERVER['REMOTE_ADDR'] ?? '';
        $isLocal = in_array($current, ['127.0.0.1', '::1'], true) && (in_array('127.0.0.1', $allowed, true) || in_array('::1', $allowed, true));
        if (!$isLocal && !in_array($current, $allowed, true)) {
            header('Location: ' . base_url('index.php'));
            exit;
        }
    }
}

function require_player(): void
{
    if (!is_player() && !is_admin()) {
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

function base_url(string $path = ''): string
{
    // Works at any install location: computes the app's URL subdirectory from
    // its folder on disk relative to the web root ('' at the domain root,
    // '/futsal' under htdocs/futsal, etc.). An explicit BASE_PATH in .env wins.
    $root = '';
    $override = env('BASE_PATH');
    if ($override !== null) {
        $root = '/' . trim($override, '/');
    } else {
        $docRoot = str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appDir  = str_replace('\\', '/', (string)realpath(dirname(__DIR__)));
        if ($docRoot !== '' && $appDir !== '' && strpos($appDir . '/', $docRoot . '/') === 0) {
            $root = rtrim(substr($appDir, strlen($docRoot)), '/');
        }
    }
    return rtrim($root, '/') . '/' . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    $scheme = env('APP_SCHEME');
    if ($scheme === null) {
        $fwd = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $scheme = ($fwd === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . base_url($path);
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

/**
 * Render a small inline POST form for a destructive action (vanilla, no framework).
 * Replaces state-changing GET links (?delete=, ?cancel=, ...) to avoid CSRF token
 * in URL / logs / Referer. $confirm adds data-confirm modal support (core.js).
 */
function post_action_form(string $actionUrl, string $field, string $value, string $labelHtml, string $btnClass = 'btn btn-outline btn-sm', string $confirm = '', string $ariaLabel = '', array $extra = []): string
{
    $h = '<form method="post" action="' . e($actionUrl) . '" class="d-inline">';
    $h .= csrf_field();
    $h .= '<input type="hidden" name="' . e($field) . '" value="' . e($value) . '">';
    foreach ($extra as $k => $v) {
        $h .= '<input type="hidden" name="' . e((string)$k) . '" value="' . e((string)$v) . '">';
    }
    $h .= '<button type="submit" class="' . e($btnClass) . '"';
    if ($confirm !== '') { $h .= ' data-confirm="' . e($confirm) . '"'; }
    if ($ariaLabel !== '') { $h .= ' aria-label="' . e($ariaLabel) . '"'; }
    $h .= '>' . $labelHtml . '</button></form>';
    return $h;
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
    // Use the same setting as the cron (default 60) so web + CLI agree.
    $timeout = 60;
    try {
        $s = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'unpaid_cancel_timeout_minutes' LIMIT 1");
        if ($s && ($row = $s->fetch_assoc()) && is_numeric($row['setting_value'])) {
            $timeout = max(5, min(1440, (int)$row['setting_value']));
        }
    } catch (Exception $e) {
        // fall back to 60
    }
    $stmt = $conn->prepare(
        "UPDATE bookings
         SET status = 'cancelled'
         WHERE status = 'confirmed'
           AND payment_status = 'unpaid'
           AND TIMESTAMP(booking_date, start_time) < DATE_SUB(NOW(), INTERVAL ? MINUTE)"
    );
    if ($stmt) {
        $stmt->bind_param('i', $timeout);
        $stmt->execute();
        $stmt->close();
    }
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
    // Atomic + capped: only increment when under max_uses (prevents overshoot on race).
    $stmt = $conn->prepare('UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ? AND (max_uses <= 0 OR used_count < max_uses)');
    $stmt->bind_param('i', $promo_id);
    $stmt->execute();
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
         ORDER BY id ASC LIMIT 50'
    );
    $stmt->bind_param('iss', $ground_id, $booking_date, $start_time);
    $stmt->execute();
    $rows = $stmt->get_result();
    while ($row = $rows->fetch_assoc()) {
        notify_user((int)$row['user_id'], 'A slot opened up!', $groundName . ' is free on ' . $label . '. Book before someone else does.', 'fa-bell', 'pages/ground.php?id=' . (int)$ground_id . '&date=' . urlencode($booking_date));
        $uStmt = $conn->prepare('SELECT email, name FROM users WHERE id = ?');
        $uStmt->bind_param('i', $row['user_id']);
        $uStmt->execute();
        $uRow = $uStmt->get_result()->fetch_assoc();
        if ($uRow) {
            $bookUrl = absolute_url('pages/ground.php?id=' . (int)$ground_id . '&date=' . urlencode($booking_date));
            send_booking_email(
                $uRow['email'],
                'A slot opened at ' . $groundName,
                'Your waitlist spot just freed up',
                [
                    'Court'      => $groundName,
                    'Date'       => date('D, M j, Y', strtotime($booking_date)),
                    'Time'       => substr($start_time, 0, 5) . ' onwards',
                    'Book now'   => $bookUrl,
                ],
                'This slot is now available on a first-come, first-served basis. Tap the link above to reserve it before someone else books it.',
                $uRow['name'] ?? ''
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
    $hasDiscount = ($discounted !== null && (float)$discounted > 0 && (float)$discounted < (float)$base_price);
    if ($hasDiscount) {
        $effective = (float)$discounted;
    }
    $day = (int)date('N', strtotime($date)); // 1=Mon .. 7=Sun
    if ($day >= 6 && $row['price_weekend'] !== null && (float)$row['price_weekend'] > 0) {
        $weekend = (float)$row['price_weekend'];
        // Discount still applies on weekends: charge the lower of the two.
        if ($hasDiscount) {
            return min($effective, $weekend);
        }
        return $weekend;
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
    // Batch preload hit (set by preload_ground_cards() on listing pages).
    if (isset($GLOBALS['__ground_covers'][$ground_id])) {
        return $GLOBALS['__ground_covers'][$ground_id];
    }
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
    if (isset($GLOBALS['__ground_ratings'][$ground_id])) {
        return $GLOBALS['__ground_ratings'][$ground_id];
    }
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

/**
 * Batch preload covers/ratings/favorites for a list of grounds (1 query each
 * instead of N per card). Call once in courts/player_home before the loop.
 */
function preload_ground_cards(array $groundIds): void
{
    global $conn;
    $ids = array_values(array_unique(array_map('intval', $groundIds)));
    $ids = array_filter($ids, fn($i) => $i > 0);
    if (!$ids) { return; }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    // Covers: earliest image per ground
    $stmt = $conn->prepare("SELECT ground_id, MIN(id) mid FROM ground_images WHERE ground_id IN ($ph) GROUP BY ground_id");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $midRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $covers = [];
    if ($midRows) {
        $mids = array_column($midRows, 'mid');
        $ph2 = implode(',', array_fill(0, count($mids), '?'));
        $t2 = str_repeat('i', count($mids));
        $s2 = $conn->prepare("SELECT ground_id, image FROM ground_images WHERE id IN ($ph2)");
        $s2->bind_param($t2, ...$mids);
        $s2->execute();
        foreach ($s2->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $covers[(int)$r['ground_id']] = $r['image'];
        }
        $s2->close();
    }
    $GLOBALS['__ground_covers'] = $covers;
    // Ratings
    $stmt = $conn->prepare("SELECT ground_id, AVG(rating) avg, COUNT(*) cnt FROM reviews WHERE ground_id IN ($ph) GROUP BY ground_id");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $ratings = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $ratings[(int)$r['ground_id']] = ['avg' => $r['avg'] !== null ? round((float)$r['avg'], 1) : null, 'count' => (int)$r['cnt']];
    }
    $stmt->close();
    foreach ($ids as $gid) {
        if (!isset($ratings[$gid])) { $ratings[$gid] = ['avg' => null, 'count' => 0]; }
    }
    $GLOBALS['__ground_ratings'] = $ratings;
    // Favorites for current user
    $favs = [];
    if (is_logged_in()) {
        $uid = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT ground_id FROM favorites WHERE user_id = ? AND ground_id IN ($ph)");
        $stmt->bind_param('i' . $types, $uid, ...$ids);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $favs[(int)$r['ground_id']] = true;
        }
        $stmt->close();
    }
    $GLOBALS['__ground_favs'] = $favs;
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
            <?php echo booking_payment_method_html($b); ?>
        </div>
        <span class="mb-st <?php echo $statusClass; ?>"><i class="fa-solid <?php echo $statusIcon; ?>"></i> <?php echo e($statusText); ?></span>
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
    // Whitelist status rendering (defense if $b is tampered).
    $allowedClasses = ['sm-cancelled' => true, 'sm-unpaid' => true, 'sm-paid' => true, 'sm-partial' => true];
    $allowedIcons = ['fa-circle-xmark' => true, 'fa-clock' => true, 'fa-circle-check' => true, 'fa-circle-half-stroke' => true];
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
    // Enforce whitelist before output.
    if (!isset($allowedClasses[$statusClass])) { $statusClass = 'sm-unpaid'; }
    if (!isset($allowedIcons[$statusIcon])) { $statusIcon = 'fa-clock'; }
    $statusText = in_array($statusText, ['Cancelled', 'Awaiting approval', 'Confirmed · Paid', 'Awaiting payment', 'Payment pending', 'Pending'], true) ? $statusText : 'Pending';
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

/**
 * Convert an uploaded image to WebP and write it to $dest.
 * Returns true on success (file saved as WebP) or false on failure.
 * GIFs are re-encoded to static WebP (first frame) so no polyglot/executable
 * payload survives. Includes decompression-bomb guard via max pixels.
 */
function convert_image_to_webp(string $src, string $dest, int $quality = 82, ?int $maxWidth = null): bool
{
    $real = realpath($src);
    if ($real === false || !is_file($real)) {
        return false;
    }
    $info = @getimagesize($real);
    if ($info === false) {
        return false;
    }
    // Bomb guard: reject absurd dimensions (e.g. 10000x10000).
    if (($info[0] * $info[1]) > 16000000 || $info[0] > 6000 || $info[1] > 6000) {
        return false;
    }
    $mime = $info['mime'];
    if ($mime === 'image/webp') {
        return copy($real, $dest);
    }
    switch ($mime) {
        case 'image/jpeg':
            $img = @imagecreatefromjpeg($real);
            break;
        case 'image/png':
            $img = @imagecreatefrompng($real);
            break;
        case 'image/gif':
            $img = @imagecreatefromgif($real);
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

/**
 * Download a user's Google profile picture and save it as a local WebP avatar.
 * Returns the stored filename on success, or '' on failure (caller should
 * fall back to the letter avatar).
 */
function save_google_avatar(string $url, int $userId): string
{
    if ($url === '' || $userId < 1) {
        return '';
    }
    // Ask Google for a 512px version instead of the tiny default thumbnail.
    $url = preg_replace('/=s\d+-c$/', '=s512-c', $url);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'GoalSpace-Avatar/1.0',
    ]);
    $data = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($data === false || $code !== 200 || $data === '') {
        return '';
    }

    $tmp = tempnam(sys_get_temp_dir(), 'gav');
    if ($tmp === false || @file_put_contents($tmp, $data) === false) {
        @unlink($tmp);
        return '';
    }

    $dest = dirname(__DIR__) . '/uploads/avatars/user_' . $userId . '_' . bin2hex(random_bytes(8)) . '.webp';
    $ok = convert_image_to_webp($tmp, $dest, 85, 512);
    @unlink($tmp);
    if (!$ok || !is_file($dest)) {
        @unlink($dest);
        return '';
    }
    return basename($dest);
}

function save_ground_photos(int $ground_id): array
{
    global $conn;
    $files = $_FILES['photos'] ?? [];
    $names = is_array($files['name'] ?? null) ? $files['name'] : [];
    $tmps = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [];
    $errs = is_array($files['error'] ?? null) ? $files['error'] : [];
    $sizes = is_array($files['size'] ?? null) ? $files['size'] : [];
    $uploaded = 0;
    $failed = 0;
    // Limits: max 10 files per request, 5MB each – prevents GD/disk DoS.
    $maxFiles = 10;
    $maxBytes = 5 * 1024 * 1024;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $count = min(count($names), $maxFiles);
    for ($i = 0; $i < $count; $i++) {
        if (($errs[$i] ?? 1) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (($errs[$i] ?? 1) !== UPLOAD_ERR_OK) {
            $failed++;
            continue;
        }
        if ((int)($sizes[$i] ?? 0) > $maxBytes) {
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

/**
 * Get the stored payment QR filename for a ground ('' when not set).
 */
function ground_qr(int $ground_id): string
{
    global $conn;
    $stmt = $conn->prepare('SELECT payment_qr FROM grounds WHERE id = ?');
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (string)($row['payment_qr'] ?? '');
}

/**
 * Save an uploaded QR image for a ground's payment account.
 * Returns ['ok' => bool, 'filename' => string, 'error' => ?string].
 */
function save_ground_qr(int $ground_id): array
{
    global $conn;
    if (empty($_FILES['payment_qr']['name'])) {
        return ['ok' => false, 'filename' => '', 'error' => null];
    }
    $file = $_FILES['payment_qr'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'filename' => '', 'error' => 'Upload failed. Try again.'];
    }
    if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['ok' => false, 'filename' => '', 'error' => 'QR image must be under 2MB.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = ['image/jpeg' => true, 'image/png' => true, 'image/webp' => true, 'image/gif' => true];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'filename' => '', 'error' => 'Only JPG, PNG, WebP or GIF images are accepted.'];
    }
    $filename = 'qr_' . $ground_id . '_' . bin2hex(random_bytes(6)) . '.webp';
    $dest = __DIR__ . '/../uploads/grounds/' . $filename;
    if (!convert_image_to_webp($file['tmp_name'], $dest, 90, 800)) {
        @unlink($dest);
        return ['ok' => false, 'filename' => '', 'error' => 'Could not process that image. Try another one.'];
    }
    $stmt = $conn->prepare('UPDATE grounds SET payment_qr = ? WHERE id = ?');
    $stmt->bind_param('si', $filename, $ground_id);
    if (!$stmt->execute()) {
        @unlink($dest);
        return ['ok' => false, 'filename' => '', 'error' => 'Could not save the QR code.'];
    }
    return ['ok' => true, 'filename' => $filename, 'error' => null];
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
    $now = time();
    if (isset($_SESSION['last_sub_sync']) && $now - (int)$_SESSION['last_sub_sync'] < 300) {
        return;
    }
    $_SESSION['last_sub_sync'] = $now;

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
 * Professional, modern HTML email template for GoalSpace.
 * Bulletproof inline CSS compatible with all email clients (Gmail, Apple Mail, Outlook).
 * Zero em dashes, clean typography, responsive max-width.
 */
function goalspace_email_html(string $title, string $greeting, string $bodyHtml, string $footerNote = '', string $previewText = ''): string
{
    $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $contactUrl = absolute_url('pages/page.php?slug=contact');
    $siteUrl = absolute_url('');
    $preview = $previewText !== '' ? $previewText : $title;

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . $esc($title) . '</title>
<style>
@media only screen and (max-width: 600px) {
    .email-container { width: 100% !important; border-radius: 8px !important; }
    .email-outer { padding: 16px 8px !important; }
    .email-body { padding: 24px 18px !important; }
    .email-header { padding: 20px 18px !important; }
    .email-footer { padding: 16px 18px !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#0e1712;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
<!-- Hidden preview snippet for inbox list -->
<div style="display:none;font-size:1px;color:#0e1712;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
    ' . $esc($preview) . '
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="email-outer" style="background-color:#0e1712;padding:36px 12px;">
<tr>
<td align="center">
    <table role="presentation" width="560" cellpadding="0" cellspacing="0" class="email-container" style="max-width:560px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 14px 36px rgba(0,0,0,0.4);border:1px solid #1e3327;">
        <!-- Header -->
        <tr>
            <td class="email-header" style="background-color:#0b130e;padding:26px 32px;border-bottom:3px solid #16a34a;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>
                            <a href="' . $siteUrl . '" style="text-decoration:none;">
                                <span style="color:#ffffff;font-size:20px;font-weight:800;letter-spacing:-0.4px;">GoalSpace</span>
                                <span style="display:inline-block;margin-left:8px;padding:3px 8px;background-color:rgba(34,197,94,0.18);color:#4ade80;font-size:11px;font-weight:700;border-radius:6px;letter-spacing:0.5px;text-transform:uppercase;">Futsal</span>
                            </a>
                        </td>
                        <td align="right">
                            <span style="color:#7d9487;font-size:12px;">Kathmandu, Nepal</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Main Body -->
        <tr>
            <td class="email-body" style="padding:34px 32px 28px;">
                <p style="margin:0 0 14px;color:#607368;font-size:15px;font-weight:600;">' . $esc($greeting) . '</p>
                <h1 style="margin:0 0 20px;color:#0d1812;font-size:22px;font-weight:800;line-height:1.3;letter-spacing:-0.4px;">' . $esc($title) . '</h1>
                
                ' . $bodyHtml . '
                
                <p style="margin:28px 0 0;color:#0d1812;font-size:14px;line-height:1.6;">
                    See you on the pitch,<br>
                    <strong style="color:#15803d;">The GoalSpace Team</strong>
                </p>
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td class="email-footer" style="background-color:#f6faf7;padding:18px 32px;border-top:1px solid #e7eee9;">
                ' . ($footerNote !== '' ? '<p style="margin:0 0 8px;color:#7a8a81;font-size:12px;line-height:1.5;">' . $footerNote . '</p>' : '') . '
                <p style="margin:0;color:#8f9f96;font-size:12px;line-height:1.5;">
                    GoalSpace Platform &middot; Fast futsal reservations &middot; 
                    <a href="' . $contactUrl . '" style="color:#16a34a;text-decoration:none;font-weight:600;">Contact Support</a> &middot; 
                    <a href="' . $siteUrl . '" style="color:#16a34a;text-decoration:none;font-weight:600;">Visit GoalSpace</a>
                </p>
            </td>
        </tr>
    </table>
</td>
</tr>
</table>
</body>
</html>';
}

/**
 * Revamped transactional booking email with high aesthetic appeal and clarity.
 */
function booking_email_html(string $heading, array $rows = [], string $note = '', string $name = ''): string
{
    $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $greeting = $name !== '' ? 'Hi ' . $esc($name) . ',' : 'Hello,';

    $table = '';
    if ($rows) {
        $cells = '';
        $i = 0;
        foreach ($rows as $k => $v) {
            $bg = ($i % 2 === 0) ? '#fbfdfc' : '#ffffff';
            $valStr = (string)$v;
            if (filter_var($valStr, FILTER_VALIDATE_URL)) {
                $valHtml = '<a href="' . $esc($valStr) . '" style="display:inline-block;padding:7px 16px;background-color:#16a34a;color:#ffffff;text-decoration:none;border-radius:7px;font-size:13px;font-weight:700;">' . ($esc($k) === 'Book now' ? 'Book slot now &rarr;' : 'Open &rarr;') . '</a>';
            } elseif (stripos($k, 'ref') !== false) {
                $valHtml = '<code style="background-color:#edf7f0;color:#166534;font-family:Consolas,monospace;font-size:13px;font-weight:700;padding:3px 7px;border-radius:5px;letter-spacing:0.5px;">' . $esc($valStr) . '</code>';
            } else {
                $valHtml = $esc($valStr);
            }

            $cells .= '<tr style="background-color:' . $bg . ';">'
                . '<td style="padding:11px 14px;color:#617369;width:140px;font-size:13.5px;border-bottom:1px solid #edf2ee;vertical-align:middle;">' . $esc($k) . '</td>'
                . '<td style="padding:11px 14px;color:#0f1d15;font-size:14px;font-weight:700;border-bottom:1px solid #edf2ee;vertical-align:middle;">' . $valHtml . '</td>'
                . '</tr>';
            $i++;
        }
        $table = '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;margin:16px 0;border:1px solid #e2eae4;border-radius:12px;overflow:hidden;">'
            . $cells
            . '</table>';
    }

    $noteBlock = $note !== '' ? '<div style="margin:20px 0 0;padding:14px 18px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;"><p style="margin:0;color:#166534;font-size:13.5px;line-height:1.6;">'
        . $esc($note) . '</p></div>' : '';

    $bodyHtml = '<p style="margin:0 0 12px;color:#35493d;font-size:15px;line-height:1.6;">'
        . 'Here are the details for your booking:'
        . '</p>'
        . $table
        . $noteBlock;

    $footerNote = 'If you have any questions about your booking, feel free to reply or reach out to our team.';
    return goalspace_email_html($heading, $greeting, $bodyHtml, $footerNote);
}

/**
 * Send a transactional booking email. Uses in-app style, safe when SMTP is offline.
 *
 * @return bool true if a mail was actually sent (SMTP configured + ack)
 */
function send_booking_email(string $to, string $subject, string $heading, array $rows = [], string $note = '', string $name = ''): bool
{
    return send_mail($to, $subject, booking_email_html($heading, $rows, $note, $name), true);
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
        $lines[]  = '• ' . $label . ': ' . absolute_url('pages/page.php?slug=' . $slug);
    }
    $labelText = implode(', ', $labels);
    $summary   = 'GoalSpace has updated the following: ' . $labelText . ' on ' . $date;
    $firstSlug = array_keys($chosen)[0];

    $where = '';
    if ($scope === 'admins')      { $where = "WHERE role = 'admin'"; }
    elseif ($scope === 'managers'){ $where = "WHERE role = 'manager'"; }
    elseif ($scope === 'users')  { $where = "WHERE role = 'user'"; }
    // scope === 'all' applies no filter

    $res = $conn->query('SELECT id,email,name FROM users ' . $where . ' LIMIT 500');
    if ($res === false) {
        return 0;
    }

    $sent = 0;
    while ($u = $res->fetch_assoc()) {
        // 1) in-app notification -> appears in the notifications tab (bell)
        notify_user((int)$u['id'], 'Legal pages updated', $summary, 'fa-circle-info', 'pages/page.php?slug=' . $firstSlug);

        // 2) ONE combined email per user (both policies in the same message)
        // Capped at 500 recipients per run.
        $subject = 'GoalSpace update: ' . $labelText;
        $userName = $u['name'] ?: 'there';
        $greeting = 'Hi ' . $userName . ',';
        
        $policyLinksHtml = '';
        foreach ($chosen as $slug => $label) {
            $policyLinksHtml .= '<li style="margin-bottom:8px;"><a href="' . absolute_url('pages/page.php?slug=' . $slug) . '" style="color:#15803d;font-weight:700;text-decoration:none;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
        }

        $bodyHtml = '<p style="margin:0 0 14px;color:#2c3e34;font-size:15px;line-height:1.6;">GoalSpace has updated the following information:</p>'
            . '<ul style="margin:0 0 18px;padding-left:20px;color:#15803d;font-size:14px;line-height:1.8;">'
            . $policyLinksHtml
            . '</ul>'
            . '<p style="margin:0 0 14px;color:#55685d;font-size:13.5px;line-height:1.6;">These updates take effect on <strong>' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '</strong>. We believe in being transparent about how our platform operates, so please take a moment to review them when you can.</p>';

        $footerNote = 'If you have any questions, our support team is always here to help.';
        $htmlMsg = goalspace_email_html($subject, $greeting, $bodyHtml, $footerNote);
        queue_email($u['email'], $subject, $htmlMsg);
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

    // Capped per run to avoid SMTP timeouts on large bases.
    $res = $conn->query('SELECT id,email,name FROM users ' . $where . ' LIMIT 500');
    if ($res === false) {
        return 0;
    }

    $sent = 0;
    while ($u = $res->fetch_assoc()) {
        notify_user((int)$u['id'], $subject, $message, 'fa-bullhorn');
        if ($sendMail) {
            $greeting = 'Hello, ' . ($u['name'] ?: 'valued member') . ',';
            $bodyHtml = '<p style="margin:0 0 16px;color:#2c3e34;font-size:15px;line-height:1.65;">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>'
                . '<p style="margin:0;color:#55685d;font-size:13.5px;line-height:1.6;">Thank you for being part of the GoalSpace community. We look forward to seeing you on the pitch soon.</p>';
            $footerNote = 'If you have any questions, our support team is always here to help.';
            $htmlBody = goalspace_email_html($subject, $greeting, $bodyHtml, $footerNote);
            queue_email($u['email'], $subject, $htmlBody);
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

/**
 * Standard empty-state block: soft icon chip + title + optional text and action.
 * Used across bookings, favorites, notifications, search results and dashboards.
 */
function empty_state(string $icon, string $title, string $text = '', ?string $action_url = null, ?string $action_label = null, string $action_class = 'btn btn-outline btn-sm'): void
{
    echo '<div class="empty reveal">';
    echo '<span class="big"><i class="' . e($icon) . '"></i></span>';
    echo '<h3>' . e($title) . '</h3>';
    if ($text !== '') {
        echo '<p>' . e($text) . '</p>';
    }
    if ($action_url !== null && $action_label !== null) {
        $resolvedUrl = preg_match('#^(https?://|/)#', $action_url) ? $action_url : base_url($action_url);
        echo '<a href="' . e($resolvedUrl) . '" class="' . e($action_class) . '">' . e($action_label) . '</a>';
    }
    echo '</div>';
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
        'fa-qrcode' => 'green',
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
    // Include IP to prevent brute-force from single IP across many emails
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
    $stmt = $conn->prepare('INSERT INTO login_attempts (identifier, attempts, locked_until) VALUES (?, 1, NULL) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt_at = NOW()');
    $stmt->bind_param('s', $key);
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

/**
 * Generic IP-based rate limiting using login_attempts table.
 * @param string $prefix  Namespace prefix (e.g. 'reg', 'emailchk')
 * @param int    $max     Max attempts allowed in the window
 * @param int    $window  Window in seconds
 * @return bool true if rate limit exceeded
 */
function rate_limit_exceeded(string $prefix, int $max, int $window): bool
{
    global $conn;
    $key = $prefix . '|' . client_ip();
    $stmt = $conn->prepare('SELECT attempts, last_attempt_at FROM login_attempts WHERE identifier = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $last = strtotime($row['last_attempt_at']);
        if (time() - $last > $window) {
            $stmt = $conn->prepare('UPDATE login_attempts SET attempts = 1, last_attempt_at = NOW() WHERE identifier = ?');
            $stmt->bind_param('s', $key);
            $stmt->execute();
            return false;
        }
        if ((int)$row['attempts'] >= $max) {
            return true;
        }
        $stmt = $conn->prepare('UPDATE login_attempts SET attempts = attempts + 1, last_attempt_at = NOW() WHERE identifier = ?');
        $stmt->bind_param('s', $key);
        $stmt->execute();
    } else {
        $ip = client_ip();
        $stmt = $conn->prepare('INSERT INTO login_attempts (identifier, ip, attempts, last_attempt_at) VALUES (?, ?, 1, NOW())');
        $stmt->bind_param('ss', $key, $ip);
        $stmt->execute();
    }
    return false;
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
    // No bypass list: all addresses share the same cooldown + hourly limit.
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
            return 'Confirm your email address';
        case 'password_reset':
            return 'Reset your password';
        case 'login':
            return 'Your GoalSpace sign-in code';
        case 'password_change':
            return 'Your GoalSpace security code';
        case 'email_change':
            return 'Confirm your new email';
        case 'delete_account':
            return 'Confirm account deletion';
        default:
            return 'Your GoalSpace security code';
    }
}


function send_otp_mail(string $email, string $code, string $purpose): bool
{
    global $conn;
    // Personalize the greeting when the email belongs to an existing account.
    $firstName = '';
    if (!empty($conn) && $email !== '') {
        $stmt = $conn->prepare('SELECT name FROM users WHERE email = ?');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row && !empty($row['name'])) {
                $firstName = preg_split('/\s+/', trim($row['name']))[0] ?? '';
            }
        }
    }
    $greeting = $firstName !== '' ? 'Hello, ' . $firstName : 'Hello,';

    $title = otp_subject($purpose);
    $actionVerb = otp_purpose_verb($purpose);

    $bodyHtml = '
    <p style="margin:0 0 16px;color:#2c3e34;font-size:15px;line-height:1.6;">
        We received a request to ' . htmlspecialchars($actionVerb, ENT_QUOTES, 'UTF-8') . '. Enter this verification code on GoalSpace to continue:
    </p>
    
    <div style="margin:26px 0;text-align:center;background:#f0fdf4;border:2px solid #86efac;border-radius:14px;padding:24px 16px;">
        <span style="font-family:\'SF Pro Mono\',Consolas,\'Courier New\',monospace;font-size:38px;font-weight:800;letter-spacing:10px;color:#15803d;display:inline-block;padding-left:10px;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</span>
        <div style="margin-top:10px;font-size:12.5px;color:#166534;font-weight:600;">
            <span style="display:inline-block;width:8px;height:8px;background:#22c55e;border-radius:50%;margin-right:6px;vertical-align:middle;"></span>
            Valid for 5 minutes
        </div>
    </div>

    <div style="margin:24px 0 0;padding:14px 18px;background:#f8faf9;border-radius:10px;border-left:3px solid #15803d;">
        <p style="margin:0;color:#53665c;font-size:13px;line-height:1.55;">
            <strong>Security Notice:</strong> Never share this code with anyone. If you did not make this request, you can safely ignore this email. Your GoalSpace account credentials remain secure.
        </p>
    </div>';

    $footerNote = 'If you need any help, our support team is always ready to assist you.';
    $htmlContent = goalspace_email_html($title, $greeting, $bodyHtml, $footerNote);

    return send_mail($email, $title, $htmlContent, true);
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
        case 'delete_account':
            return 'delete your GoalSpace account';
        default:
            return 'continue with what you were doing';
    }
}

/**
 * Permanently delete a user account (self-serve, OTP-confirmed).
 * Grounds owned by the user are hidden so orphaned courts don't keep
 * accepting bookings; identifier-keyed rows are removed explicitly;
 * everything else cascades via the schema's ON DELETE CASCADE.
 */
function delete_user_account(int $user_id, string $email): bool
{
    global $conn;
    // A manager leaving the platform shouldn't leave live, unowned courts.
    $stmt = $conn->prepare('UPDATE grounds SET is_active = 0 WHERE manager_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    // Clean up identifier-keyed tables (no FK to users).
    $key = login_attempt_key($email);
    $stmt = $conn->prepare('DELETE FROM otps WHERE identifier = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE identifier = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt = $conn->prepare('DELETE FROM contact_messages WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    return $stmt->execute() && $stmt->affected_rows > 0;
}

function export_csv(array $rows, string $filename): void
{
    $filename = preg_replace('/[^a-z0-9_.\-]/i', '', $filename);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        $safe = [];
        foreach ($row as $cell) {
            $cell = (string)$cell;
            // CSV formula injection: cells starting with = + - @ (or tab/CR)
            // would execute as formulas when opened in Excel/LibreOffice.
            if ($cell !== '' && strpbrk($cell[0], "=+-@\t\r") !== false) {
                $cell = "'" . $cell;
            }
            $safe[] = $cell;
        }
        fputcsv($out, $safe);
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
    $filename = preg_replace('/[^a-z0-9_.\-]/i', '', $filename);
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

function ground_card_html(array $ground, array|string|null $availability = null, string $extraClass = ''): void
{
    if (is_string($availability)) {
        $extraClass = $availability;
        $availability = null;
    }
    $cover = ground_cover((int)$ground['id']);
    $rating = ground_rating((int)$ground['id']);
    $full = $availability !== null && $availability['free'] === 0;
    ?>
    <div class="card reveal <?php echo $full ? 'card-full' : ''; ?><?php echo $extraClass !== '' ? ' ' . e($extraClass) : ''; ?>">
        <div class="card-img">
            <?php if ($cover): ?>
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 10'%3E%3C/svg%3E" data-src="<?php echo base_url('uploads/grounds/' . rawurlencode($cover)); ?>" alt="<?php echo e($ground['name']); ?>" class="card-cover lazy-load" loading="lazy" decoding="async">
                <noscript><img src="<?php echo base_url('uploads/grounds/' . rawurlencode($cover)); ?>" alt="<?php echo e($ground['name']); ?>" class="card-cover"></noscript>
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
                    <i class="fa-heart <?php echo favorite_exists((int)$ground['id']) ? 'fa-solid' : 'fa-regular'; ?>" aria-hidden="true"></i>
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
                <span class="thumb-tag"><i class="fa-solid fa-location-arrow"></i> <?php echo number_format((float)$ground['distance_km'], 1); ?> km</span>
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
                    <span class="card-rating-inline"><i class="fa-solid fa-star star-muted"></i> <small>No reviews yet</small></span>
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
    if (isset($GLOBALS['__ground_favs'])) {
        return !empty($GLOBALS['__ground_favs'][$ground_id]);
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
        . '<div class="toast-content">'
        . '<div class="toast-msg"><span>' . e($message) . '</span></div>'
        . '</div>'
        . '<button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>'
        . '</div>';
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

/**
 * Renders the settings sidebar navigation. Called from all stg-layout pages.
 * @param string $active  One of: settings, account, security, notifications, appearance
 */
function settings_sidebar(string $active): void
{
    $user = current_user();
    $links = [
        ['section' => 'Account', 'items' => [
            ['page' => 'settings.php',           'key' => 'settings',      'icon' => 'fa-sliders',      'label' => 'General'],
            ['page' => 'settings_account.php',   'key' => 'account',       'icon' => 'fa-user-pen',     'label' => 'Edit profile'],
            ['page' => 'security.php',           'key' => 'security',      'icon' => 'fa-lock',         'label' => 'Password'],
        ]],
        ['section' => 'Preferences', 'items' => [
            ['page' => 'settings_notifications.php', 'key' => 'notifications', 'icon' => 'fa-bell',       'label' => 'Notifications'],
            ['page' => 'settings_preferences.php',   'key' => 'appearance',    'icon' => 'fa-palette',    'label' => 'Appearance'],
        ]],
        ['section' => 'Support', 'items' => [
            ['page' => 'faq.php',                    'key' => '',  'icon' => 'fa-circle-question', 'label' => 'Help & FAQ'],
            ['page' => 'page.php?slug=terms',        'key' => '',  'icon' => 'fa-file-lines',     'label' => 'Terms'],
            ['page' => 'page.php?slug=privacy',      'key' => '',  'icon' => 'fa-shield-halved',  'label' => 'Privacy'],
        ]],
    ];
    ?>
    <nav class="stg-sidebar" aria-label="Settings navigation">
        <div class="stg-sidebar-head">
            <a href="<?php echo base_url('pages/profile.php'); ?>" class="stg-sidebar-back" aria-label="Back to profile"><i class="fa-solid fa-arrow-left"></i></a>
            <h1>Settings</h1>
        </div>
        <a href="<?php echo base_url('pages/profile.php'); ?>" class="stg-sidebar-user">
            <span class="stg-sidebar-avatar">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?php echo base_url('uploads/avatars/' . rawurlencode($user['avatar'])); ?>" alt="" loading="lazy" decoding="async">
                <?php else: ?>
                    <?php echo e(strtoupper(substr($user['name'], 0, 1))); ?>
                <?php endif; ?>
            </span>
            <span class="stg-sidebar-user-info">
                <strong><?php echo e($user['name']); ?></strong>
                <span><?php echo e($user['email']); ?></span>
            </span>
        </a>
        <?php foreach ($links as $group): ?>
        <div class="stg-sidebar-section">
            <span class="stg-sidebar-label"><?php echo e($group['section']); ?></span>
            <?php foreach ($group['items'] as $link): ?>
            <a href="<?php echo base_url('pages/' . $link['page']); ?>" class="stg-sidebar-link<?php echo $link['key'] === $active ? ' active' : ''; ?>"><i class="fa-solid <?php echo $link['icon']; ?>"></i> <?php echo $link['label']; ?></a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <div class="stg-sidebar-section">
            <form method="post" action="<?php echo base_url('pages/logout.php'); ?>" class="m-0">
                <?php echo csrf_field(); ?>
                <button type="submit" class="stg-sidebar-link stg-sidebar-danger" data-confirm="Log out of your account?" data-confirm-ok="Yes, log out" data-confirm-cancel="Cancel"><i class="fa-solid fa-right-from-bracket"></i> Log out</button>
            </form>
        </div>
    </nav>
    <?php
}

/**
 * Renders a hidden honeypot field for bot trapping. Include inside <form>.
 */
function honeypot_field(): void
{
    echo '<div style="position:absolute;left:-9999px;top:-9999px" aria-hidden="true">'
        . '<label for="website_url">Leave this empty</label>'
        . '<input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off"></div>';
}

/**
 * Returns true if the honeypot field was filled (likely a bot).
 */
function is_honeypot_filled(): bool
{
    return !empty($_POST['website_url']);
}

/**
 * Renders the Google sign-in SVG icon.
 */
function google_svg_icon(): void
{
    echo '<svg class="g-icon" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.1 18.9 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.1 5.7l6.2 5.2C36.9 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z"/></svg>';
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
            'summary' => 'Connecting passionate futsal players with verified courts across Nepal.',
            'body'    => '
            <p><strong>GoalSpace</strong> is Nepal\'s dedicated futsal discovery and court reservation platform. We make booking a court as quick and effortless as scoring a tap-in, connecting players directly with venue managers in real time.</p>
            
            <p>Before GoalSpace, organizing a friendly match meant making multiple phone calls, checking availability through busy signals, and hoping your court slot was actually held when you arrived. We built GoalSpace to replace guesswork with clarity: see open slots live, lock in your game instantly, and hit the turf with confidence.</p>

            <h2>What We Believe In</h2>
            <ul>
                <li><strong>No more double bookings.</strong> Every confirmed reservation is locked in our database instantly. There are no verbal holds or lost time slots.</li>
                <li><strong>Full transparency.</strong> Clear pricing, court dimensions, surface details, parking info, changing rooms, and customer reviews are openly displayed for every ground.</li>
                <li><strong>Empowering venue managers.</strong> Court owners get dedicated dashboard tools to automate reservations, verify digital QR payments, manage custom rates, and run off-peak promotions.</li>
            </ul>

            <h2>Who Uses GoalSpace</h2>
            <ul>
                <li><strong>Players and Teams:</strong> Explore local courts by location or amenities, check live free slots, reserve in seconds, and track match histories.</li>
                <li><strong>Court Managers:</strong> Streamline front-desk operations, replace paper registers, accept cashless payments, and fill off-peak hours with automated promos.</li>
                <li><strong>Tournament Organizers:</strong> Discover verified venues with multi-court capacity, floodlights, and spectator seating.</li>
            </ul>

            <h2>Get in Touch</h2>
            <p>Have ideas to make GoalSpace better, or want to partner with us? Our Kathmandu-based team is always here to listen. Email us anytime at <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> or visit our <a href="' . base_url('pages/page.php?slug=contact') . '">Contact page</a>.</p>
        ',
        ],
        'privacy' => [
            'title'   => 'Privacy Policy',
            'summary' => 'How we collect, protect, and handle your data with complete transparency.',
            'body'    => '
            <p class="updated-note">Last updated: September 2026</p>

            <p>At GoalSpace, your trust is fundamental to our service. This Privacy Policy explains what personal information we collect, why we collect it, how it is secured, and your control over your data.</p>

            <h2>1. What We Collect</h2>
            <ul>
                <li><strong>Account Information:</strong> When you register, we collect your full name, email address, contact phone number, and account password (stored securely as a one-way cryptographic hash).</li>
                <li><strong>Court Reservation Data:</strong> We store details of your futsal bookings, including chosen court, date, time slot, payment status (e.g. Paid, Advance, or Venue Pay), and cancellation history.</li>
                <li><strong>Ephemeral Location Data:</strong> When you tap "Use my location", your browser requests your coordinates. We use this strictly within your current session to calculate distances to nearby futsal courts. Your exact GPS coordinates are never stored on our servers or shared with third parties.</li>
                <li><strong>Technical and Device Logs:</strong> Basic server logs such as browser type, operating system, and IP address are maintained temporarily for security monitoring, DDoS prevention, and crash diagnostics.</li>
            </ul>

            <h2>2. How We Use Your Information</h2>
            <ul>
                <li>To create and authenticate your account across web and mobile devices.</li>
                <li>To instantly confirm, schedule, and maintain court reservations.</li>
                <li>To send crucial transactional notifications, such as login OTP codes, booking receipts, and schedule changes.</li>
                <li>To provide venue managers with necessary player contact details so they can welcome your team at the court.</li>
                <li>To maintain system integrity, detect fraudulent actions, and prevent unauthorized account access.</li>
            </ul>

            <h2>3. Information Sharing and Disclosure</h2>
            <p>GoalSpace does not sell, rent, or trade your personal information to third-party advertisers or data brokers. We disclose your data only in the following limited circumstances:</p>
            <ul>
                <li><strong>Court Managers:</strong> When you make a booking, the manager of that specific futsal venue receives your name and contact phone number to coordinate entry, pitch access, and ball allocation.</li>
                <li><strong>Infrastructure Service Providers:</strong> Trusted technical partners who assist with email delivery (such as Brevo/SMTP) and secure database hosting, governed by strict confidentiality terms.</li>
                <li><strong>Legal Requirements:</strong> If compelled by applicable law, court order, or governmental regulation in Nepal.</li>
            </ul>

            <h2>4. Data Security Standards</h2>
            <p>We implement comprehensive security measures to safeguard your personal data:</p>
            <ul>
                <li>Passwords are hashed with industry-standard bcrypt algorithms with salted rounds. Plaintext passwords are never accessible to any staff member.</li>
                <li>All network communications are transmitted over secure HTTPS with TLS encryption.</li>
                <li>Role-based access restrictions guarantee that players, venue managers, and platform administrators can only access authorized system records.</li>
            </ul>

            <h2>5. Your Privacy Rights</h2>
            <p>You have full autonomy over your personal information on GoalSpace:</p>
            <ul>
                <li><strong>Access and Correction:</strong> You can view and edit your profile name, contact phone, and avatar at any time in your account settings.</li>
                <li><strong>Data Portability and Deletion:</strong> You can request a copy of your booking history or permanently delete your account through your profile settings or by emailing <a href="mailto:hello@goalspace.com">hello@goalspace.com</a>.</li>
            </ul>

            <h2>6. Cookies and Session Storage</h2>
            <p>We use essential session cookies and local storage to keep you authenticated, remember your theme preference (dark or light mode), and retain active navigation state. We do not use third-party tracking cookies.</p>

            <h2>7. Contact Our Privacy Team</h2>
            <p>If you have questions or concerns regarding our privacy practices, please contact us at <a href="mailto:privacy@goalspace.com">privacy@goalspace.com</a>.</p>
        ',
        ],
        'terms'  => [
            'title'   => 'Terms of Service',
            'summary' => 'Clear terms governing the use of GoalSpace for players, managers, and visitors.',
            'body'    => '
            <p class="updated-note">Last updated: September 2026</p>

            <p>Welcome to GoalSpace. By accessing our platform, creating an account, or making a court booking, you agree to these Terms of Service. Please review them carefully.</p>

            <h2>1. User Accounts and Eligibility</h2>
            <ul>
                <li>You must be at least 16 years of age or have parent/guardian consent to create an account.</li>
                <li>You agree to provide accurate, up-to-date registration information and keep your credentials confidential.</li>
                <li>You are responsible for all activities and bookings made under your account credentials.</li>
                <li>GoalSpace reserves the right to suspend or terminate accounts that provide falsified details or misuse the reservation system.</li>
            </ul>

            <h2>2. Court Reservations and Instant Confirmation</h2>
            <ul>
                <li><strong>Instant Booking:</strong> When you select a time slot and complete checkout, your reservation is confirmed immediately. The court calendar updates in real time to prevent duplicate bookings.</li>
                <li><strong>Punctuality:</strong> Players are expected to arrive at the venue at least 10 minutes prior to their reserved kickoff time. Game time ends precisely when the booked slot concludes.</li>
                <li><strong>Venue Rules:</strong> Players agree to abide by the specific ground rules of the futsal facility, including proper turf footwear, equipment care, and courteous sportsmanship.</li>
            </ul>

            <h2>3. Pricing, Payments, and Advance Deposits</h2>
            <ul>
                <li><strong>Clear Rates:</strong> Court prices are established directly by venue managers and clearly displayed per 60-minute or 90-minute time slot.</li>
                <li><strong>Payment Methods:</strong> Depending on the court\'s configuration, players may pay the full amount online, pay a 20% advance online with the balance due upon arrival, or pay the entire fee at the venue.</li>
                <li><strong>Direct QR Transfers:</strong> Digital QR payments (e.g. Khalti, eSewa, IME Pay) are transferred directly to the venue manager\'s verified merchant account.</li>
            </ul>

            <h2>4. Cancellation and Refund Policy</h2>
            <ul>
                <li><strong>Standard Notice (24+ hours before kickoff):</strong> Cancellations submitted at least 24 hours prior to game start qualify for a 100% refund or platform credit.</li>
                <li><strong>Late Cancellation (within 24 hours):</strong> For cancellations made less than 24 hours before kickoff, the advance deposit is retained as credit for future bookings or paid to the court to cover idle turf loss.</li>
                <li><strong>No-Shows:</strong> Failing to attend a reserved slot without cancellation forfeits the advance deposit. Continued no-shows may lead to booking restrictions on your account.</li>
            </ul>

            <h2>5. Manager and Court Owner Responsibilities</h2>
            <p>Futsal court operators who register as Managers on GoalSpace agree to uphold the following standards:</p>
            <ul>
                <li><strong>Listing Accuracy:</strong> Court dimensions, amenities, grass type, rates, and working hours must remain truthful and up to date.</li>
                <li><strong>Guaranteed Availability:</strong> A slot confirmed on GoalSpace must be honored. Double-selling slots across phone or third-party platforms is strictly prohibited.</li>
                <li><strong>Player Privacy:</strong> Customer contact details may be used solely for reservation coordination and never for unsolicited commercial messaging.</li>
            </ul>

            <h2>6. Platform Availability and Liability</h2>
            <p>GoalSpace provides the digital booking infrastructure connecting players and courts. Physical venue conditions, weather disruptions, pitch maintenance, and player conduct remain the direct responsibility of the respective venue managers and participants. To the maximum extent permitted by law, GoalSpace is not liable for injuries or property loss occurring at partner futsal facilities.</p>

            <h2>7. Amendments to Terms</h2>
            <p>We may update these terms periodically to reflect new platform capabilities or legal guidelines. Continued use of GoalSpace following published updates constitutes acceptance of the modified terms.</p>
        ',
        ],
        'contact' => [
            'title'   => 'Contact Us',
            'summary' => 'Get in touch with the GoalSpace team for support, court onboarding, and inquiries.',
            'body'    => '
            <p>Whether you need assistance with a current booking, want to register your futsal court on our platform, or simply have feedback to share, we are here to help.</p>

            <h2>Support Channels</h2>
            <ul>
                <li><strong>Email Support:</strong> <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> (typically answered within 4 hours during business days)</li>
                <li><strong>Manager Venue Onboarding:</strong> <a href="mailto:partners@goalspace.com">partners@goalspace.com</a></li>
                <li><strong>Headquarters:</strong> GoalSpace Technologies, Kathmandu, Nepal</li>
                <li><strong>Operating Hours:</strong> Sunday through Friday, 8:00 AM to 8:00 PM NPT</li>
            </ul>

            <p>You can also send a direct inquiry using the contact form below, and our team will get back to you promptly.</p>
        ',
        ],
        'help'    => [
            'title'   => 'Help and Support',
            'summary' => 'Comprehensive answers and tutorials for players, court managers, and administrators.',
            'body'    => '
            <h2 id="for-players">Player Guide</h2>
            <h3>How do I find and book an open futsal court?</h3>
            <p>Browse courts on the <a href="' . base_url('pages/courts.php') . '">Courts page</a> or search by city and neighborhood. Tap any court to view available dates, then click on your preferred open time slot to begin checkout.</p>

            <h3>How does payment work?</h3>
            <p>GoalSpace supports flexible payment options. You can pay a 20% advance online and settle the balance when you arrive, pay 100% upfront via the court\'s official QR code (Khalti, eSewa, or IME Pay), or pay directly at the venue counter.</p>

            <h3>Can I reschedule or cancel my match?</h3>
            <p>Yes. Go to <a href="' . base_url('pages/my_bookings.php') . '">My Bookings</a>, choose your upcoming game, and tap Cancel or Reschedule. Cancellations made 24 hours or more before kickoff qualify for a full refund.</p>

            <h2 id="for-managers">Court Manager Guide</h2>
            <h3>How do I list my futsal ground on GoalSpace?</h3>
            <p>Sign up and select the <strong>Manager</strong> account role, or visit <a href="%MANAGER_URL%">Become a Manager</a>. From your manager dashboard, navigate to "My Grounds" and click "Add Ground" to set your rates, photos, and ground specifications.</p>

            <h3>How do I configure digital QR payments?</h3>
            <p>In "My Grounds", click "Edit" on your court and locate the Payment QR Code section. Upload a clear photo or screenshot of your Khalti, eSewa, or mobile banking QR code so players can scan and transfer fees directly to you.</p>

            <h3>How do I mark payments collected in cash?</h3>
            <p>In your manager Bookings tab, locate the player\'s reservation and click "Mark Paid". The booking status will immediately update to reflect full settlement.</p>

            <h3>Can I block courts for tournaments or private maintenance?</h3>
            <p>Yes. Under court settings, open "Blocked Dates and Times" to temporarily close specific slots from public availability without taking down your entire court profile.</p>

            <h2 id="general-questions">General Questions</h2>
            <h3>What should I do if a venue is closed upon arrival?</h3>
            <p>Please contact our support team immediately at <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> with your booking reference code. We will verify the incident with the manager and promptly issue a full refund or credit.</p>
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
 * Body HTML is sanitized via allowlist (vanilla, no framework).
 * @return bool true on success
 */
function sanitize_page_body(string $html): string
{
    // Allow only safe formatting tags; strip scripts, iframes, objects, forms, event handlers.
    $allowed = '<p><br><h2><h3><h4><ul><ol><li><strong><em><b><i><u><a><blockquote><code><pre><hr>';
    $html = strip_tags($html, $allowed);
    // Remove event-handler attributes (onclick= etc.)
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    // Remove style attributes that could hide content or exfiltrate.
    $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    // For <a> tags: validate href is a safe protocol (http, https, mailto, #)
    // This defeats encoded javascript: URIs (unicode, percent-encoding, newlines, etc.)
    $html = preg_replace_callback('/<a\s[^>]*href\s*=\s*(["\'])(.*?)\1/i', function ($m) {
        $val = rawurldecode(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
        // Strip whitespace/control chars that could bypass protocol check
        $clean = preg_replace('/[\s\x00-\x1f\x7f]+/', '', $val);
        if (!preg_match('#^(https?://|mailto:|#)#i', $clean)) {
            return str_replace($m[0], '<a href="#">', $m[0]);
        }
        return $m[0];
    }, $html);
    return trim($html);
}

function save_page(string $slug, string $title, string $summary, string $body): bool
{
    global $conn;
    $defaults = legal_pages_defaults();
    if (!isset($defaults[$slug])) {
        return false;
    }
    $title = mb_substr(trim($title), 0, 150);
    $summary = mb_substr(trim($summary), 0, 255);
    $body = sanitize_page_body($body);
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
    return '<script type="application/ld+json">' . "\n" . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . "\n" . '</script>';
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
    $page_image = $img ? absolute_url($img) : absolute_url('assets/img/icon-512.png');
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
        'image'    => $img ? [absolute_url($img)] : [absolute_url('assets/img/icon-512.png')],
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

/**
 * True when the court is the dedicated demo/practice court.
 */
function is_demo_ground(array $ground): bool
{
    return isset($ground['slug']) && $ground['slug'] === 'demo-court';
}

/**
 * Human-friendly owner label. The demo court is presented as GoalSpace itself.
 */
function ground_owner_label(array $ground): string
{
    if (is_demo_ground($ground)) {
        return 'GoalSpace';
    }
    return (string) ($ground['owner_name'] ?? '');
}

/**
 * Slot Hold / Concurrency Protection (5-minute lease)
 */
function cleanup_expired_slot_holds(): void
{
    global $conn;
    $conn->query('DELETE FROM slot_holds WHERE expires_at <= NOW()');
}

function is_slot_held(int $ground_id, string $booking_date, string $start_time, ?int $ignore_user_id = null): bool
{
    global $conn;
    cleanup_expired_slot_holds();
    if ($ignore_user_id !== null) {
        $stmt = $conn->prepare('SELECT id FROM slot_holds WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND user_id != ? AND expires_at > NOW()');
        $stmt->bind_param('issi', $ground_id, $booking_date, $start_time, $ignore_user_id);
    } else {
        $stmt = $conn->prepare('SELECT id FROM slot_holds WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND expires_at > NOW()');
        $stmt->bind_param('iss', $ground_id, $booking_date, $start_time);
    }
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function acquire_slot_hold(int $ground_id, string $booking_date, string $start_time, int $user_id, int $hold_seconds = 300): array
{
    global $conn;
    cleanup_expired_slot_holds();

    if (slot_is_taken($ground_id, $booking_date, $start_time)) {
        return ['ok' => false, 'error' => 'That time slot is already booked.'];
    }

    if (is_slot_held($ground_id, $booking_date, $start_time, $user_id)) {
        return ['ok' => false, 'error' => 'Someone is currently checking out this slot. Try again in a couple of minutes.'];
    }

    $token = bin2hex(random_bytes(24));
    $expires = date('Y-m-d H:i:s', time() + $hold_seconds);

    $stmt = $conn->prepare(
        'INSERT INTO slot_holds (ground_id, booking_date, start_time, user_id, hold_token, expires_at)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), hold_token = VALUES(hold_token), expires_at = VALUES(expires_at)'
    );
    $stmt->bind_param('ississ', $ground_id, $booking_date, $start_time, $user_id, $token, $expires);
    if ($stmt->execute()) {
        return ['ok' => true, 'token' => $token, 'expires_at' => $expires];
    }
    return ['ok' => false, 'error' => 'Could not hold slot. Please try again.'];
}

function release_slot_hold(int $ground_id, string $booking_date, string $start_time, int $user_id): void
{
    global $conn;
    $stmt = $conn->prepare('DELETE FROM slot_holds WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND user_id = ?');
    $stmt->bind_param('issi', $ground_id, $booking_date, $start_time, $user_id);
    $stmt->execute();
}

/**
 * Asynchronous Background Email Queue
 */
function queue_email(string $recipient, string $subject, string $body_html): bool
{
    global $conn;
    $recipient = strtolower(trim($recipient));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $stmt = $conn->prepare('INSERT INTO email_queue (recipient, subject, body_html, status) VALUES (?, ?, ?, "pending")');
    $stmt->bind_param('sss', $recipient, $subject, $body_html);
    return $stmt->execute();
}



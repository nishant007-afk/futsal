<?php

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

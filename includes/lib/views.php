<?php

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

function ground_card_features(array $ground): array
{
    $desc = strtolower((string)($ground['description'] ?? ''));
    $name = strtolower((string)($ground['name'] ?? ''));
    $loc = (string)($ground['location'] ?? '');

    // Surface detection
    $surface = 'FIFA Turf';
    if (str_contains($desc, 'wooden') || str_contains($desc, 'parquet') || str_contains($name, 'wooden')) {
        $surface = 'Parquet Wood';
    } elseif (str_contains($desc, 'rubber') || str_contains($desc, 'rubberized')) {
        $surface = 'Rubberized Turf';
    } elseif (str_contains($desc, 'dome') || str_contains($desc, 'covered')) {
        $surface = 'Covered Dome';
    } elseif (str_contains($desc, 'outdoor')) {
        $surface = 'Outdoor Turf';
    } elseif (str_contains($desc, 'indoor')) {
        $surface = 'Indoor Turf';
    }

    // Amenities detection
    $amenities = [];
    if (str_contains($desc, 'changing') || str_contains($desc, 'shower')) {
        $amenities[] = ['icon' => 'fa-solid fa-shower', 'label' => 'Changing Room'];
    }
    if (str_contains($desc, 'parking')) {
        $amenities[] = ['icon' => 'fa-solid fa-square-parking', 'label' => 'Parking'];
    }
    if (str_contains($desc, 'floodlight') || str_contains($desc, 'night') || str_contains($desc, 'lighting')) {
        $amenities[] = ['icon' => 'fa-solid fa-lightbulb', 'label' => 'Floodlights'];
    }
    if (str_contains($desc, 'cafe') || str_contains($desc, 'canteen')) {
        $amenities[] = ['icon' => 'fa-solid fa-mug-saucer', 'label' => 'Cafe'];
    }
    if (empty($amenities)) {
        $amenities[] = ['icon' => 'fa-solid fa-shirt', 'label' => 'Bibs & Balls'];
        $amenities[] = ['icon' => 'fa-solid fa-shower', 'label' => 'Changing Room'];
    } elseif (count($amenities) === 1) {
        $amenities[] = ['icon' => 'fa-solid fa-shirt', 'label' => 'Bibs & Balls'];
    }

    // Format detection: the card only surfaces this when it isn't the default 5-a-side
    $sides = 5;
    if (preg_match('/\b(6|7|8)[\s\-]?(?:a[\s\-]?side|aside|sides|players)\b/', $desc . ' ' . $name, $m)) {
        $sides = (int)$m[1];
    }

    // City normalization for header quick filter
    $city = 'Kathmandu';
    if (stripos($loc, 'Lalitpur') !== false || stripos($loc, 'Patan') !== false || stripos($loc, 'Jawalakhel') !== false || stripos($loc, 'Balkumari') !== false) {
        $city = 'Lalitpur';
    } elseif (stripos($loc, 'Bhaktapur') !== false) {
        $city = 'Bhaktapur';
    }

    return [
        'surface' => $surface,
        'amenities' => $amenities,
        'sides' => $sides,
        'city' => $city,
    ];
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
    $features = ground_card_features($ground);

    // Rating representation: no fabricated review counts - a court with little
    // or no feedback simply shows no rating badge instead of a made-up score.
    $reviewCount = (int)$rating['count'];
    $score = $reviewCount > 0 ? (float)$rating['avg'] : 0.0;
    $cardPrice = (float)$ground['price_per_hour'];
    $cardDisc = isset($ground['discount_price']) ? (float)$ground['discount_price'] : 0;
    $cardSale = $cardDisc > 0 && $cardDisc < $cardPrice;
    $cardOff = $cardSale ? (int)round((1 - $cardDisc / $cardPrice) * 100) : 0;
    ?>
    <div class="card <?php echo $full ? 'card-full' : ''; ?><?php echo $extraClass !== '' ? ' ' . e($extraClass) : ''; ?>" data-city="<?php echo e($features['city']); ?>" data-href="<?php echo base_url('pages/ground.php?id=' . (int)$ground['id']); ?>">
        <div class="card-img">
            <?php if ($cover): ?>
                <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($cover)); ?>" alt="<?php echo e($ground['name']); ?>" class="card-cover" loading="lazy" decoding="async" draggable="false">
            <?php else: ?>
                <img src="<?php echo base_url('uploads/grounds/court_brastad_arena.jpg'); ?>" alt="<?php echo e($ground['name']); ?>" class="card-cover" loading="lazy" decoding="async" draggable="false">
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
            <?php if ($reviewCount > 0): ?>
                <span class="card-rating-badge" title="<?php echo number_format($score, 1); ?> stars based on <?php echo $reviewCount; ?> review<?php echo $reviewCount === 1 ? '' : 's'; ?>">
                    <i class="fa-solid fa-star"></i> <?php echo number_format($score, 1); ?> <span class="rating-num">(<?php echo $reviewCount; ?>)</span>
                </span>
            <?php endif; ?>
            <?php $gallery = ground_gallery((int)$ground['id']); ?>
            <?php if (count($gallery) > 1): ?>
                <?php $galUrls = []; foreach ($gallery as $gimg) { $galUrls[] = base_url('uploads/grounds/' . rawurlencode($gimg)); } ?>
                <div class="card-gallery" role="group" aria-label="Photos of <?php echo e($ground['name']); ?>" data-images="<?php echo e(json_encode($galUrls)); ?>">
                    <button type="button" class="card-gnav card-gprev" aria-label="Previous photo" title="Previous photo" hidden><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                    <button type="button" class="card-gnav card-gnext" aria-label="Next photo" title="Next photo"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                    <span class="gallery-counter"><span class="gallery-counter-current">1</span>&nbsp;/&nbsp;<span class="gallery-counter-total"><?php echo count($galUrls); ?></span></span>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <h3 class="card-title">
                <a href="<?php echo base_url('pages/ground.php?id=' . (int)$ground['id']); ?>"><?php echo e($ground['name']); ?></a>
            </h3>
            <?php
            $subs = [
                '<span class="cs-loc"><i class="fa-solid fa-location-dot"></i> ' . e($ground['location']) . '</span>',
                '<span class="cs-surface">' . e($features['surface']) . '</span>',
            ];
            if ((int)$features['sides'] !== 5) {
                $subs[] = '<span class="cs-format">' . (int)$features['sides'] . 'A-Side</span>';
            }
            if ($availability !== null) {
                $subs[] = $full
                    ? '<span class="cs-slots cs-full"><i class="fa-solid fa-circle-xmark"></i> Fully booked</span>'
                    : '<span class="cs-slots cs-free"><i class="fa-solid fa-circle-check"></i> ' . (int)$availability['free'] . ' slot' . ((int)$availability['free'] === 1 ? '' : 's') . ' left</span>';
            }
            echo '<p class="card-sub">' . implode('<span class="cs-dot" aria-hidden="true">•</span>', $subs) . '</p>';
            ?>
            <div class="card-meta">
                <div class="price-block">
                    <div class="price-main-row">
                        <strong class="price-current">Rs <?php echo number_format($cardSale ? $cardDisc : $cardPrice, 0); ?></strong>
                        <span class="price-unit">/ hr</span>
                        <?php if ($cardSale): ?>
                            <span class="price-orig">Rs <?php echo number_format($cardPrice, 0); ?></span>
                            <span class="price-off">-<?php echo $cardOff; ?>%</span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?php echo base_url('pages/ground.php?id=' . (int)$ground['id']); ?>" class="btn btn-primary btn-sm card-action-btn">
                    <span>Book Slot</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
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
                <button type="submit" class="stg-sidebar-link stg-sidebar-danger" data-confirm="Log out of your account?" data-confirm-title="Log out" data-confirm-ok="Yes, log out" data-confirm-cancel="Cancel"><i class="fa-solid fa-right-from-bracket"></i> Log out</button>
            </form>
        </div>
    </nav>
    <?php
}

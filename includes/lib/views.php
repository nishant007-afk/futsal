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

<?php
require_once __DIR__ . '/../config/db.php';
require_manager();

$subStatus = subscription_status((int)$_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ground'])) {
    verify_csrf();
    $id = (int)$_POST['delete_ground'];
    if (user_owns_ground($id)) {
        $stmt = $conn->prepare('DELETE FROM grounds WHERE id = ? AND manager_id = ?');
        $stmt->bind_param('ii', $id, $_SESSION['user_id']);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            set_flash('success', 'Ground deleted.');
        } else {
            set_flash('error', 'Could not delete ground.');
        }
    } else {
        set_flash_error(
            'You can only manage your own grounds.',
            'This action was for a court that isn\'t linked to your account.',
            'Use your dashboard to manage the courts assigned to you.',
            'manager/dashboard.php'
        );
    }
    redirect('manager/grounds.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duplicate_ground'])) {
    verify_csrf();
    $id = (int)$_POST['duplicate_ground'];
    if (user_owns_ground($id)) {
        $stmt = $conn->prepare('SELECT * FROM grounds WHERE id = ? AND manager_id = ?');
        $stmt->bind_param('ii', $id, $_SESSION['user_id']);
        $stmt->execute();
        $g = $stmt->get_result()->fetch_assoc();
        if ($g) {
            $newName = $g['name'] . ' (copy)';
            $stmt = $conn->prepare(
                'INSERT INTO grounds (name, location, description, price_per_hour, discount_price, capacity, manager_id, is_active, open_time, close_time, slot_interval, price_weekend, address, court_number, latitude, longitude)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sssddiiissidssdd', $newName, $g['location'], $g['description'], $g['price_per_hour'], $g['discount_price'], $g['capacity'], $g['manager_id'], $g['is_active'], $g['open_time'], $g['close_time'], $g['slot_interval'], $g['price_weekend'], $g['address'], $g['court_number'], $g['latitude'], $g['longitude']);
            if ($stmt->execute()) {
                $newId = (int)$stmt->insert_id;
                // Copy images
                $imgStmt = $conn->prepare('SELECT * FROM ground_images WHERE ground_id = ?');
                $imgStmt->bind_param('i', $id);
                $imgStmt->execute();
                $images = $imgStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $imgStmt->close();
                if ($images) {
                    $ins = $conn->prepare('INSERT INTO ground_images (ground_id, image) VALUES (?, ?)');
                    foreach ($images as $img) {
                        $ins->bind_param('is', $newId, $img['image']);
                        $ins->execute();
                    }
                    $ins->close();
                }
                set_flash('success', 'Ground duplicated. You can now edit the copy.');
                redirect('manager/grounds.php?edit=' . $newId);
            } else {
                set_flash_error('Could not duplicate ground.', 'Database error.', 'Try again.', 'manager/grounds.php');
            }
        } else {
            set_flash_error('Ground not found.', 'It may have been deleted.', 'Check your grounds list.', 'manager/grounds.php');
        }
    } else {
        set_flash_error('You can only duplicate your own grounds.', 'This action was for a court that isn\'t linked to your account.', 'Use your dashboard to manage the courts assigned to you.', 'manager/dashboard.php');
    }
    redirect('manager/grounds.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
    verify_csrf();
    $photo_id = (int)$_POST['delete_photo'];
    $ground_id = (int)($_POST['ground_id'] ?? 0);
    if (user_owns_ground($ground_id)) {
        $stmt = $conn->prepare('SELECT image FROM ground_images WHERE id = ? AND ground_id = ?');
        $stmt->bind_param('ii', $photo_id, $ground_id);
        $stmt->execute();
        $img = $stmt->get_result()->fetch_assoc();
        if ($img) {
            $stmt = $conn->prepare('DELETE FROM ground_images WHERE id = ?');
            $stmt->bind_param('i', $photo_id);
            $stmt->execute();
            $path = __DIR__ . '/../uploads/grounds/' . basename($img['image']);
            if (is_file($path)) {
                unlink($path);
            }
            set_flash('success', 'Photo removed.');
        }
    }
    redirect('manager/grounds.php?edit=' . $ground_id . '#media');
}

$editing = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare('SELECT * FROM grounds WHERE id = ? AND manager_id = ?');
    $stmt->bind_param('ii', $id, $_SESSION['user_id']);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    if (!$editing) {
        set_flash_error(
            'We couldn\'t find that court.',
            'It may have been removed, or it isn\'t linked to your account.',
            'Refresh your courts list to see what you can manage.',
            'manager/grounds.php'
        );
        redirect('manager/grounds.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ground_core'])) {
    verify_csrf();
    if (!$subStatus['active']) {
        set_flash_error(
            'You can\'t add or edit courts right now.',
            'Your subscription is ' . strtolower($subStatus['label']) . '.',
            'Renew your subscription with the platform to keep managing courts.',
            'manager/dashboard.php'
        );
        redirect('manager/grounds.php');
    }
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price_per_hour'] ?? 0);
    $discount_price = $_POST['discount_price'] !== '' ? (float)$_POST['discount_price'] : null;
    $capacity = (int)($_POST['capacity'] ?? 10);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $open_time = trim($_POST['open_time'] ?? '08:00');
    $close_time = trim($_POST['close_time'] ?? '22:00');
    $slot_interval = (int)($_POST['slot_interval'] ?? 60);
    $price_weekend = $_POST['price_weekend'] !== '' ? (float)$_POST['price_weekend'] : null;
    $address = trim($_POST['address'] ?? '');
    $court_number = trim($_POST['court_number'] ?? '') !== '' ? trim($_POST['court_number']) : null;
    $latitude = ($_POST['latitude'] ?? '') !== '' ? (float)$_POST['latitude'] : null;
    $longitude = ($_POST['longitude'] ?? '') !== '' ? (float)$_POST['longitude'] : null;
    $id = (int)($_POST['id'] ?? 0);

    if ($name === '') {
        $errors['name'] = 'Enter a name for this ground.';
    }
    if ($location === '') {
        $errors['location'] = 'Enter where this court is located.';
    }
    if ($price <= 0) {
        $errors['price_per_hour'] = 'Price must be greater than zero.';
    }
    if ($discount_price !== null && ($discount_price <= 0 || $discount_price >= $price)) {
        $errors['discount_price'] = 'Discount price must be between 0 and the regular price.';
    }
    if ($capacity < 1) {
        $errors['capacity'] = 'Capacity must be at least 1 player.';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $open_time) || !preg_match('/^\d{2}:\d{2}$/', $close_time)) {
        $errors['open_time'] = 'Opening and closing times must use HH:MM format.';
    } else {
        $openM = (int)substr($open_time, 0, 2) * 60 + (int)substr($open_time, 3, 2);
        $closeM = (int)substr($close_time, 0, 2) * 60 + (int)substr($close_time, 3, 2);
        if ($openM >= $closeM) {
            $errors['close_time'] = 'Closing time must be after opening time.';
        }
    }
    if ($price_weekend !== null && $price_weekend <= 0) {
        $errors['price_weekend'] = 'Weekend price must be greater than zero.';
    }
    if (!in_array($slot_interval, [30, 60], true)) {
        $slot_interval = 60;
    }

    if (!$errors) {
        if ($id > 0 && user_owns_ground($id)) {
            $stmt = $conn->prepare(
                'UPDATE grounds SET name = ?, location = ?, description = ?, price_per_hour = ?, discount_price = ?, capacity = ?, is_active = ?, open_time = ?, close_time = ?, slot_interval = ?, price_weekend = ?, address = ?, court_number = ?, latitude = ?, longitude = ? WHERE id = ? AND manager_id = ?'
            );
            $stmt->bind_param('sssddiissidssddii', $name, $location, $description, $price, $discount_price, $capacity, $is_active, $open_time, $close_time, $slot_interval, $price_weekend, $address, $court_number, $latitude, $longitude, $id, $_SESSION['user_id']);
            $msg = 'Ground updated.';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO grounds (name, location, description, price_per_hour, discount_price, capacity, manager_id, is_active, open_time, close_time, slot_interval, price_weekend, address, court_number, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sssddiiissidssdd', $name, $location, $description, $price, $discount_price, $capacity, $_SESSION['user_id'], $is_active, $open_time, $close_time, $slot_interval, $price_weekend, $address, $court_number, $latitude, $longitude);
            $msg = 'Ground added.';
        }
        if ($stmt->execute()) {
            if ($id === 0) {
                $new_id = (int)$conn->insert_id;
                [$uploaded, $failed] = save_ground_photos($new_id);
                $res = save_ground_qr($new_id);

                $parts = [];
                if ($uploaded > 0 && $failed > 0) {
                    $parts[] = $uploaded . ' photo(s) uploaded. ' . $failed . ' could not be saved.';
                } elseif ($uploaded > 0) {
                    $parts[] = $uploaded . ' photo(s) uploaded.';
                }
                if ($res['ok']) {
                    $parts[] = 'Payment QR code saved.';
                }
                if ($parts) {
                    set_flash('success', $msg . ' ' . implode(', ', $parts));
                }
                if (!$res['ok'] && $res['error'] !== null) {
                    set_flash_error(
                        'Could not save the payment QR code.',
                        $res['error'],
                        'Try a clear image of your payment QR code.',
                        'manager/grounds.php?edit=' . $new_id
                    );
                }
                redirect('manager/grounds.php?edit=' . $new_id);
            }
            set_flash('success', $msg);
            redirect('manager/grounds.php');
        } else {
            $errors['general'] = 'Could not save ground.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photos']) && $editing) {
    verify_csrf();
    $ground_id = (int)$editing['id'];
    [$uploaded, $failed] = save_ground_photos($ground_id);
    if ($uploaded > 0) {
        set_flash('success', $uploaded . ' photo(s) uploaded.');
    }
    if ($failed > 0) {
        set_flash_error(
            $failed . ' photo(s) could not be uploaded.',
            'Only JPG, PNG, WebP or GIF images are accepted.',
            'Convert the images to one of those formats and try again.',
            'manager/grounds.php?edit=' . $ground_id . '#media'
        );
    }
    redirect('manager/grounds.php?edit=' . $ground_id . '#media');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_qr']) && $editing) {
    verify_csrf();
    $ground_id = (int)$editing['id'];
    $res = save_ground_qr($ground_id);
    if ($res['ok']) {
        set_flash('success', 'Payment QR code saved.');
    } elseif ($res['error'] !== null) {
        set_flash_error(
            'Could not save the payment QR code.',
            $res['error'],
            'Try a clear image of your payment QR code.',
            'manager/grounds.php?edit=' . $ground_id . '#media'
        );
    }
    redirect('manager/grounds.php?edit=' . $ground_id . '#media');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_qr_ground'])) {
    verify_csrf();
    $ground_id = (int)$_POST['delete_qr_ground'];
    if (!user_owns_ground($ground_id)) {
        set_flash('error', 'You can only manage your own grounds.');
        redirect('manager/grounds.php');
    }
    $oldQr = ground_qr($ground_id);
    $stmt = $conn->prepare('UPDATE grounds SET payment_qr = \'\' WHERE id = ? AND manager_id = ?');
    $stmt->bind_param('ii', $ground_id, $_SESSION['user_id']);
    $stmt->execute();
    if ($oldQr !== '') {
        $path = __DIR__ . '/../uploads/grounds/' . basename($oldQr);
        if (is_file($path)) {
            unlink($path);
        }
    }
    set_flash('success', 'Payment QR code removed.');
    redirect('manager/grounds.php?edit=' . $ground_id . '#media');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_blocked_date'])) {
    verify_csrf();
    $ground_id = (int)($_POST['ground_id'] ?? 0);
    if (!user_owns_ground($ground_id)) {
        set_flash('error', 'You can only manage your own grounds.');
        redirect('manager/grounds.php');
    }
    $block_date = trim($_POST['block_date'] ?? '');
    $note = trim($_POST['block_note'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $block_date)) {
        set_flash_error(
            'That date doesn\'t look valid.',
            'We need a real calendar date to block it.',
            'Pick the date from the calendar and try again.',
            'manager/grounds.php?edit=' . $ground_id . '#schedule'
        );
    } else {
        $stmt = $conn->prepare('INSERT INTO blocked_dates (ground_id, block_date, note) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $ground_id, $block_date, $note);
        if ($stmt->execute()) {
            set_flash('success', 'Court blocked for ' . date('M j, Y', strtotime($block_date)) . '.');
        } else {
            set_flash_error(
                'That date is already blocked.',
                'The court already has this date on its closed list.',
                'Pick a different date to block.',
                'manager/grounds.php?edit=' . $ground_id . '#schedule'
            );
        }
    }
    redirect('manager/grounds.php?edit=' . $ground_id . '#schedule');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unblock_date'])) {
    verify_csrf();
    $unblock_id = (int)$_POST['unblock_date'];
    $unblock_ground = (int)($_POST['ground_id'] ?? 0);
    if (!user_owns_ground($unblock_ground)) {
        set_flash('error', 'You can only manage your own grounds.');
        redirect('manager/grounds.php');
    }
    $stmt = $conn->prepare('DELETE FROM blocked_dates WHERE id = ? AND ground_id = ?');
    $stmt->bind_param('ii', $unblock_id, $unblock_ground);
    if ($stmt->execute()) {
        set_flash('success', 'Date unblocked.');
    }
    redirect('manager/grounds.php?edit=' . $unblock_ground . '#schedule');
}

$grounds = $conn->query(
    'SELECT * FROM grounds WHERE manager_id = ' . (int)$_SESSION['user_id'] . ' ORDER BY id'
)->fetch_all(MYSQLI_ASSOC);

$blockedDates = [];
$photos = [];
if ($editing) {
    $stmt = $conn->prepare('SELECT * FROM blocked_dates WHERE ground_id = ? ORDER BY block_date');
    $stmt->bind_param('i', $editing['id']);
    $stmt->execute();
    $blockedDates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $photos = ground_images((int)$editing['id']);
}

// Determine default active tab based on any errors or context
$defaultTab = 'details';
if (!empty($errors['price_per_hour']) || !empty($errors['discount_price']) || !empty($errors['open_time']) || !empty($errors['close_time']) || !empty($errors['price_weekend'])) {
    $defaultTab = 'pricing';
} elseif (!empty($errors['latitude']) || !empty($errors['longitude'])) {
    $defaultTab = 'location';
}

$page_title = $editing ? 'Edit Court: ' . $editing['name'] : 'Manage Courts';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head dash-page-head">
    <a href="<?php echo base_url('manager/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <div>
            <h2><?php echo $editing ? 'Edit Court: ' . e($editing['name']) : 'Add New Court'; ?></h2>
            <p class="muted" style="margin: 2px 0 0 0; font-size: 0.88rem;">Configure court details, pricing, location pin, media, and blackout schedules.</p>
        </div>
        <div class="actions">
            <?php if ($editing): ?>
                <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-plus"></i> Add New Court</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$subStatus['active']): ?>
    <div class="toast toast-warning toast-inline reveal" role="status">
        <div class="toast-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
        <div class="toast-content">
            <div class="toast-msg"><?php echo e($subStatus['label']); ?>: your courts are currently hidden from players. Renew your subscription to go live again.</div>
        </div>
    </div>
<?php endif; ?>

<div class="court-editor-container">
    <div class="editor-main-pane">
        <!-- Tab Navigation Header -->
        <div class="editor-tabs-bar" role="tablist" aria-label="Court Configuration Sections">
            <button type="button" class="editor-tab-btn active" data-tab="details" role="tab" aria-selected="true" aria-controls="panel-details">
                <i class="fa-solid fa-circle-info"></i> Details
            </button>
            <button type="button" class="editor-tab-btn" data-tab="pricing" role="tab" aria-selected="false" aria-controls="panel-pricing">
                <i class="fa-solid fa-tag"></i> Pricing & Hours
            </button>
            <button type="button" class="editor-tab-btn" data-tab="location" role="tab" aria-selected="false" aria-controls="panel-location">
                <i class="fa-solid fa-map-location-dot"></i> Location & Pin
            </button>
            <button type="button" class="editor-tab-btn" data-tab="media" role="tab" aria-selected="false" aria-controls="panel-media">
                <i class="fa-solid fa-images"></i> Media & QR
                <?php if ($editing && count($photos) > 0): ?>
                    <span class="tab-chip"><?php echo count($photos); ?></span>
                <?php endif; ?>
            </button>
            <?php if ($editing): ?>
                <button type="button" class="editor-tab-btn" data-tab="schedule" role="tab" aria-selected="false" aria-controls="panel-schedule">
                    <i class="fa-solid fa-calendar-xmark"></i> Blackout Schedule
                    <?php if (count($blockedDates) > 0): ?>
                        <span class="tab-chip chip-warn"><?php echo count($blockedDates); ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>
        </div>

        <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

        <!-- Primary Form for Ground Core Data -->
        <form method="post" action="" id="groundForm" enctype="multipart/form-data" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="save_ground_core" value="1">
            <input type="hidden" name="id" value="<?php echo $editing ? (int)$editing['id'] : 0; ?>">

            <!-- TAB 1: BASIC DETAILS -->
            <div class="editor-panel-card" id="panel-details" role="tabpanel">
                <div class="panel-intro">
                    <h3>Basic Court Information</h3>
                    <p class="muted">Set your court name, venue capacity, and player-facing description.</p>
                </div>
                <div class="grid grid-2">
                    <div class="form-group<?php echo has_error($errors, 'name'); ?>">
                        <label for="name">Ground Name <span class="req">*</span></label>
                        <input type="text" id="name" name="name" value="<?php echo e($editing['name'] ?? ($name ?? '')); ?>" maxlength="100" placeholder="e.g. KickOff Arena Court 1" required>
                        <?php field_error($errors, 'name'); ?>
                    </div>
                    <div class="form-group<?php echo has_error($errors, 'location'); ?>">
                        <label for="location">Neighborhood / Area <span class="req">*</span></label>
                        <input type="text" id="location" name="location" value="<?php echo e($editing['location'] ?? ($location ?? '')); ?>" maxlength="255" placeholder="e.g. Baneshwor, Kathmandu" required>
                        <?php field_error($errors, 'location'); ?>
                    </div>
                    <div class="form-group">
                        <label for="address">Full Street Address</label>
                        <input type="text" id="address" name="address" value="<?php echo e($editing['address'] ?? ($address ?? '')); ?>" maxlength="255" placeholder="e.g. Madan Bhandari Path, New Baneshwor">
                    </div>
                    <div class="form-group<?php echo has_error($errors, 'capacity'); ?>">
                        <label for="capacity">Player Capacity <span class="req">*</span></label>
                        <input type="number" min="1" id="capacity" name="capacity" value="<?php echo e($editing['capacity'] ?? ($capacity ?? 10)); ?>" placeholder="10 (for 5v5)" required>
                        <?php field_error($errors, 'capacity'); ?>
                    </div>
                    <div class="form-group">
                        <label for="court_number">Court Number or Label <span class="muted">(optional)</span></label>
                        <input type="text" id="court_number" name="court_number" value="<?php echo e($editing['court_number'] ?? ($court_number ?? '')); ?>" maxlength="20" placeholder="e.g. Pitch A">
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Court Overview & Highlights</label>
                    <textarea id="description" name="description" rows="3" placeholder="Describe your turf quality, lighting, parking, changing rooms, and amenities..."><?php echo e($editing['description'] ?? ($description ?? '')); ?></textarea>
                </div>
                <div class="form-group" style="margin-top: 14px;">
                    <label class="check-line">
                        <input type="checkbox" name="is_active" <?php echo !isset($editing) || $editing['is_active'] ? 'checked' : ''; ?>>
                        <span class="check-box"><i class="fa-solid fa-check"></i></span>
                        <span><strong>Active status:</strong> Make this court visible and bookable for players</span>
                    </label>
                </div>
            </div>

            <!-- TAB 2: PRICING & OPERATING HOURS -->
            <div class="editor-panel-card" id="panel-pricing" role="tabpanel" hidden>
                <div class="panel-intro">
                    <h3>Pricing, Rates & Hours</h3>
                    <p class="muted">Set your regular hourly pricing, sales discounts, and daily opening windows.</p>
                </div>
                <div class="grid grid-2">
                    <div class="form-group<?php echo has_error($errors, 'price_per_hour'); ?>">
                        <label for="price_per_hour">Regular Price / Hour (Rs.) <span class="req">*</span></label>
                        <input type="number" step="0.01" min="0" id="price_per_hour" name="price_per_hour" value="<?php echo e($editing['price_per_hour'] ?? ($price ?? '')); ?>" placeholder="e.g. 1500" required>
                        <?php field_error($errors, 'price_per_hour'); ?>
                    </div>
                    <div class="form-group<?php echo has_error($errors, 'discount_price'); ?>">
                        <label for="discount_price">Special Promotional Rate (Rs.) <span class="muted">(optional)</span></label>
                        <input type="number" step="0.01" min="0" id="discount_price" name="discount_price" value="<?php echo e($editing['discount_price'] ?? ''); ?>" placeholder="Discounted price per hour">
                        <?php field_error($errors, 'discount_price'); ?>
                    </div>
                    <div class="form-group<?php echo has_error($errors, 'price_weekend'); ?>">
                        <label for="price_weekend">Weekend Rate / Hour (Rs.) <span class="muted">(optional)</span></label>
                        <input type="number" step="0.01" min="0" id="price_weekend" name="price_weekend" value="<?php echo e($editing['price_weekend'] ?? ''); ?>" placeholder="Defaults to regular price if empty">
                        <?php field_error($errors, 'price_weekend'); ?>
                    </div>
                    <div class="form-group">
                        <label for="slot_interval">Booking Slot Duration</label>
                        <select id="slot_interval" name="slot_interval">
                            <option value="60" <?php echo (int)($editing['slot_interval'] ?? 60) === 60 ? 'selected' : ''; ?>>60 minutes (Standard)</option>
                            <option value="30" <?php echo (int)($editing['slot_interval'] ?? 60) === 30 ? 'selected' : ''; ?>>30 minutes (Fast match)</option>
                        </select>
                    </div>
                    <div class="form-group<?php echo has_error($errors, 'open_time'); ?>">
                        <label for="open_time">Opening Time</label>
                        <input type="time" id="open_time" name="open_time" value="<?php echo e($editing['open_time'] ?? '08:00'); ?>">
                        <?php field_error($errors, 'open_time'); ?>
                    </div>
                    <div class="form-group<?php echo has_error($errors, 'close_time'); ?>">
                        <label for="close_time">Closing Time</label>
                        <input type="time" id="close_time" name="close_time" value="<?php echo e($editing['close_time'] ?? '22:00'); ?>">
                        <?php field_error($errors, 'close_time'); ?>
                    </div>
                </div>
            </div>

            <!-- TAB 3: LOCATION & PIN -->
            <div class="editor-panel-card" id="panel-location" role="tabpanel" hidden>
                <div class="panel-intro">
                    <h3>Court Geolocation & Map Pin</h3>
                    <p class="muted">Drag the map or type your address to pin the exact entrance for player directions.</p>
                </div>
                <?php include __DIR__ . '/../includes/views/ground_location_picker.php'; ?>
            </div>

            <?php if (!$editing): ?>
                <!-- NEW GROUND: MEDIA TAB DIRECTLY IN FORM -->
                <div class="editor-panel-card" id="panel-media" role="tabpanel" hidden>
                    <div class="panel-intro">
                        <h3>Court Photos & Payment QR</h3>
                        <p class="muted">Upload showcase photos and your payment QR code directly with this new court.</p>
                    </div>
                    <div class="form-card sub" style="margin-bottom: 20px;">
                        <h4><i class="fa-solid fa-camera"></i> Court Photos</h4>
                        <p class="muted" style="font-size: 0.85rem;">Select photos of your turf, changing rooms, and venue. JPG, PNG, WebP or GIF up to 5 MB.</p>
                        <div class="form-group file-pick">
                            <label class="file-btn" for="photoInput"><i class="fa-solid fa-image"></i> Choose files</label>
                            <input type="file" id="photoInput" name="photos[]" accept="image/*" multiple>
                            <span class="file-name" id="fileNames">No files selected</span>
                        </div>
                        <div class="photo-preview-grid" id="photoPreviewGrid"></div>
                    </div>

                    <div class="form-card sub">
                        <h4><i class="fa-solid fa-qrcode"></i> Payment QR Code</h4>
                        <p class="muted" style="font-size: 0.85rem;">Upload your Fonepay/eSewa/Khalti QR so players can scan and pay you directly.</p>
                        <div class="form-group file-pick">
                            <label class="file-btn" for="qrInput"><i class="fa-solid fa-qrcode"></i> Choose QR image</label>
                            <input type="file" id="qrInput" name="payment_qr" accept="image/*">
                            <span class="file-name" id="qrFileName">No file selected</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </form>

        <?php if ($editing): ?>
            <!-- TAB 4: MEDIA & QR FOR EXISTING GROUND -->
            <div class="editor-panel-card" id="panel-media" role="tabpanel" hidden>
                <div class="panel-intro">
                    <h3>Court Media & Payment Methods</h3>
                    <p class="muted">Manage your photo gallery and player scan-to-pay QR code.</p>
                </div>

                <div class="form-card sub" style="margin-bottom: 24px;">
                    <div class="media-section-head">
                        <div>
                            <h4><i class="fa-solid fa-camera"></i> Photo Gallery</h4>
                            <p class="muted" style="font-size: 0.85rem; margin: 0;">Show off your turf quality, lighting, and amenities.</p>
                        </div>
                        <span class="media-count-tag"><?php echo count($photos); ?> photos</span>
                    </div>

                    <div class="photo-grid mb">
                        <?php foreach ($photos as $ph): ?>
                            <div class="photo-item">
                                <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($ph['image'])); ?>" alt="<?php echo e($editing['name']); ?> photo" loading="lazy" decoding="async">
                                <form method="post" action="" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="delete_photo" value="<?php echo (int)$ph['id']; ?>">
                                    <input type="hidden" name="ground_id" value="<?php echo (int)$editing['id']; ?>">
                                    <button type="submit" class="photo-remove" data-confirm="Remove this photo from your court?" title="Remove" aria-label="Remove photo"><i class="fa-solid fa-xmark"></i></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$photos): ?>
                            <div class="empty-media-msg"><i class="fa-solid fa-image"></i> No photos uploaded yet.</div>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="" enctype="multipart/form-data" id="photoUploadForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="upload_photos" value="1">
                        <div class="form-group file-pick">
                            <label class="file-btn" for="photoInput"><i class="fa-solid fa-cloud-arrow-up"></i> Select New Photos</label>
                            <input type="file" id="photoInput" name="photos[]" accept="image/*" multiple>
                            <span class="file-name" id="fileNames">No files selected</span>
                        </div>
                        <div class="photo-preview-grid" id="photoPreviewGrid"></div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-upload"></i> Upload selected photos</button>
                    </form>
                </div>

                <div class="form-card sub">
                    <div class="media-section-head">
                        <div>
                            <h4><i class="fa-solid fa-qrcode"></i> Payment QR Code</h4>
                            <p class="muted" style="font-size: 0.85rem; margin: 0;">Displayed on the checkout screen so players pay your account directly.</p>
                        </div>
                    </div>

                    <?php $groundQr = ground_qr((int)$editing['id']); ?>
                    <?php if ($groundQr !== ''): ?>
                        <div class="qr-preview-box mb">
                            <div class="photo-item qr-item">
                                <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($groundQr)); ?>" alt="Payment QR code for <?php echo e($editing['name']); ?>" loading="lazy" decoding="async">
                                <form method="post" action="" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="delete_qr_ground" value="<?php echo (int)$editing['id']; ?>">
                                    <button type="submit" class="photo-remove" data-confirm="Remove this payment QR code?" title="Remove" aria-label="Remove QR code"><i class="fa-solid fa-xmark"></i></button>
                                </form>
                            </div>
                            <div class="qr-info-note">
                                <span class="badge badge-confirmed"><i class="fa-solid fa-check"></i> Active QR</span>
                                <p class="muted" style="font-size: 0.85rem; margin: 6px 0 0 0;">Upload a replacement below if your payment QR or merchant details change.</p>
                            </div>
                        </div>
                        <form method="post" action="" enctype="multipart/form-data" id="qrUploadForm">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="upload_qr" value="1">
                            <div class="form-group file-pick">
                                <label class="file-btn" for="qrInputEdit"><i class="fa-solid fa-arrows-rotate"></i> Replace QR image</label>
                                <input type="file" id="qrInputEdit" name="payment_qr" accept="image/*">
                                <span class="file-name" id="qrFileNameEdit">No file selected</span>
                            </div>
                            <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-upload"></i> Save replacement QR</button>
                        </form>
                    <?php else: ?>
                        <p class="muted" style="font-size: 0.88rem;">No payment QR set yet.</p>
                        <form method="post" action="" enctype="multipart/form-data" id="qrUploadForm">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="upload_qr" value="1">
                            <div class="form-group file-pick">
                                <label class="file-btn" for="qrInput"><i class="fa-solid fa-qrcode"></i> Choose QR image</label>
                                <input type="file" id="qrInput" name="payment_qr" accept="image/*">
                                <span class="file-name" id="qrFileName">No file selected</span>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-upload"></i> Upload QR Code</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 5: BLACKOUT DATES (EDIT ONLY) -->
            <div class="editor-panel-card" id="panel-schedule" role="tabpanel" hidden>
                <div class="panel-intro">
                    <h3>Court Blackouts & Maintenance Lock</h3>
                    <p class="muted">Tap any calendar date to block it for tournaments, holidays, or field maintenance.</p>
                </div>

                <?php
                $blockedSet = [];
                foreach ($blockedDates as $bd) {
                    $blockedSet[$bd['block_date']] = true;
                }
                ?>
                <div class="block-cal" data-picked="">
                    <?php for ($mOff = 0; $mOff < 2; $mOff++):
                        $cy = (int)date('Y');
                        $cm = (int)date('n') + $mOff;
                        while ($cm > 12) { $cm -= 12; $cy++; }
                        $firstDow = (int)date('w', mktime(0, 0, 0, $cm, 1, $cy));
                        $daysInMonth = (int)date('t', mktime(0, 0, 0, $cm, 1, $cy));
                    ?>
                        <div class="block-cal-month">
                            <div class="block-cal-head"><?php echo e(date('F Y', mktime(0, 0, 0, $cm, 1, $cy))); ?></div>
                            <div class="block-cal-dow"><span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span></div>
                            <div class="block-cal-grid">
                                <?php for ($i = 0; $i < $firstDow; $i++): ?><span class="block-cal-empty"></span><?php endfor; ?>
                                <?php for ($d = 1; $d <= $daysInMonth; $d++):
                                    $ds = sprintf('%04d-%02d-%02d', $cy, $cm, $d);
                                    $isBlocked = isset($blockedSet[$ds]);
                                    $isPast = $ds < date('Y-m-d');
                                ?>
                                    <button type="button" class="block-cal-day<?php echo $isBlocked ? ' blocked' : ''; ?><?php echo $isPast ? ' past' : ''; ?>" data-date="<?php echo $ds; ?>" <?php echo $isPast ? 'disabled' : ''; ?> title="<?php echo $isBlocked ? 'Already blocked' : 'Block this date'; ?>"><?php echo $d; ?></button>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="form-card sub" style="margin-top: 18px;">
                    <h4><i class="fa-solid fa-lock"></i> Add Blackout Date</h4>
                    <form method="post" action="" id="blockDateForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="add_blocked_date" value="1">
                        <input type="hidden" name="ground_id" value="<?php echo (int)$editing['id']; ?>">
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="blockDate">Selected Date <span class="req">*</span></label>
                                <input type="date" id="blockDate" name="block_date" min="<?php echo e(date('Y-m-d')); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="blockNote">Reason / Note <span class="muted">(optional)</span></label>
                                <input type="text" id="blockNote" name="block_note" maxlength="255" placeholder="e.g. Private corporate tournament">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline btn-block"><i class="fa-solid fa-ban"></i> Block this date</button>
                    </form>
                </div>

                <div class="blocked-list" style="margin-top: 20px;">
                    <h4>Currently Closed Dates</h4>
                    <?php if (!$blockedDates): ?>
                        <p class="muted">No dates blocked. Your court is open on regular schedule.</p>
                    <?php else: ?>
                        <?php foreach ($blockedDates as $bd): ?>
                            <div class="blocked-item">
                                <span><i class="fa-solid fa-calendar-xmark"></i> <strong><?php echo e(date('D, M j, Y', strtotime($bd['block_date']))); ?></strong><?php echo $bd['note'] !== '' ? ' &mdash; ' . e($bd['note']) : ''; ?></span>
                                <form method="post" action="" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="unblock_date" value="<?php echo (int)$bd['id']; ?>">
                                    <input type="hidden" name="ground_id" value="<?php echo (int)$editing['id']; ?>">
                                    <button type="submit" class="photo-remove" data-confirm="Unblock this date and make slots available again?" title="Unblock" aria-label="Unblock date"><i class="fa-solid fa-xmark"></i></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Persistent Bottom Action Bar -->
        <div class="editor-action-footer">
            <div class="action-footer-nav">
                <button type="button" class="btn btn-outline btn-sm" id="prevTabBtn" style="display: none;"><i class="fa-solid fa-arrow-left"></i> Back</button>
                <button type="button" class="btn btn-outline btn-sm" id="nextTabBtn">Next: Pricing <i class="fa-solid fa-arrow-right"></i></button>
            </div>
            <div class="action-footer-save">
                <?php if ($editing): ?>
                    <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-ghost btn-sm">Cancel</a>
                <?php endif; ?>
                <button type="submit" form="groundForm" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?php echo $editing ? 'Save Changes' : 'Create Ground'; ?></button>
            </div>
        </div>
    </div>

    <!-- Right Side Column: Sticky Live Card Preview & Tips -->
    <div class="editor-side-pane">
        <div class="preview-sticky-card">
            <div class="preview-card-header">
                <span><i class="fa-solid fa-eye"></i> Live Card Preview</span>
                <span class="preview-status-pill" id="previewStatusPill">Live</span>
            </div>
            <div class="ground-preview">
                <div class="card-img">
                    <?php if ($editing): $cover = ground_cover((int)$editing['id']); endif; ?>
                    <?php if (!empty($cover)): ?>
                        <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($cover)); ?>" id="previewImg" alt="Cover photo of <?php echo e($editing['name']); ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <div class="pitch" id="previewPitch"></div>
                    <?php endif; ?>
                </div>
                <div class="preview-body">
                    <h3 id="previewName"><?php echo e($editing['name'] ?? 'Your court name'); ?></h3>
                    <span class="preview-loc"><i class="fa-solid fa-location-dot"></i> <span id="previewLoc"><?php echo e($editing['location'] ?? 'Kathmandu, Nepal'); ?></span></span>
                    <span class="preview-price">Rs <span id="previewPrice"><?php echo number_format((float)($editing['price_per_hour'] ?? 0), 0); ?></span> <small>/ hour</small></span>
                    <p class="preview-desc" id="previewDesc"><?php echo e($editing['description'] ?? 'A short overview of your turf and venue features.'); ?></p>
                    <?php if ($editing): ?>
                        <a href="<?php echo base_url('pages/ground.php?id=' . (int)$editing['id']); ?>" class="btn btn-primary btn-sm btn-block" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i> Open Public View</a>
                    <?php else: ?>
                        <span class="btn btn-outline btn-sm btn-block disabled"><i class="fa-solid fa-arrow-up-from-bracket"></i> Save to publish preview</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="manager-tip-box">
                <div class="tip-ico"><i class="fa-solid fa-lightbulb"></i></div>
                <div class="tip-text">
                    <strong>Manager Tip:</strong> Adding photos and a payment QR code helps players book with confidence and complete checkout faster.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- List of All Manager's Grounds -->
<div class="managed-courts-section">
    <div class="courts-section-header">
        <div>
            <h3>Your Managed Grounds (<?php echo count($grounds); ?>)</h3>
            <p class="muted" style="margin: 2px 0 0 0; font-size: 0.85rem;">All futsal venues assigned to your manager account.</p>
        </div>
    </div>

    <div class="mbookings reveal">
        <?php foreach ($grounds as $g): ?>
            <div class="mbooking<?php echo $editing && (int)$editing['id'] === (int)$g['id'] ? ' is-current-editing' : ''; ?>">
                <div class="mbooking-thumb">
                    <?php $gcover = ground_cover((int)$g['id']); ?>
                    <?php if ($gcover): ?>
                        <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($gcover)); ?>" alt="<?php echo e($g['name']); ?> cover" loading="lazy" decoding="async">
                    <?php else: ?>
                        <i class="fa-solid fa-store"></i>
                    <?php endif; ?>
                </div>
                <div class="mbooking-main">
                    <div class="mbooking-head">
                        <h3><?php echo e($g['name']); ?></h3>
                        <span class="mbooking-status">
                            <?php if ($g['is_active']): ?>
                                <span class="badge badge-confirmed"><i class="fa-solid fa-circle-check"></i> Active</span>
                            <?php else: ?>
                                <span class="badge badge-cancelled"><i class="fa-solid fa-circle-xmark"></i> Inactive</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="mbooking-meta">
                        <span><i class="fa-solid fa-location-dot"></i> <?php echo e($g['location']); ?></span>
                        <span class="mprice"><i class="fa-solid fa-tag"></i> <?php echo format_price($g['price_per_hour']); ?>/hr</span>
                    </div>
                </div>
                <div class="mbooking-side">
                    <div class="actions tight">
                        <a href="<?php echo base_url('manager/grounds.php?edit=' . (int)$g['id']); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen"></i> Edit</a>
                        <?php echo post_action_form(base_url('manager/grounds.php'), 'duplicate_ground', (string)(int)$g['id'], '<i class="fa-solid fa-copy"></i> Duplicate', 'btn btn-outline btn-sm', 'Create a copy of this ground?', 'Duplicate ground'); ?>
                        <?php echo post_action_form(base_url('manager/grounds.php'), 'delete_ground', (string)(int)$g['id'], '<i class="fa-solid fa-trash"></i>', 'btn btn-danger btn-sm', 'Delete this ground?', 'Delete ground'); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$grounds): ?>
            <div class="empty reveal"><span class="big"><i class="fa-solid fa-store"></i></span><h3>No grounds yet</h3><p>Use the form above to add your first futsal court.</p></div>
        <?php endif; ?>
    </div>
</div>

<style>
.court-editor-container {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 24px;
    align-items: start;
    margin-top: 14px;
    margin-bottom: 40px;
}
.editor-tabs-bar {
    display: flex;
    gap: 6px;
    border-bottom: 2px solid var(--border, #e5e7eb);
    padding-bottom: 4px;
    margin-bottom: 20px;
    overflow-x: auto;
    scrollbar-width: thin;
}
.editor-tab-btn {
    background: transparent;
    border: none;
    padding: 10px 16px;
    border-radius: var(--radius-md, 8px) var(--radius-md, 8px) 0 0;
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    position: relative;
    transition: all 0.15s ease;
}
.editor-tab-btn:hover {
    color: var(--text, #1f2937);
    background: var(--surface-subtle, #f8fafc);
}
.editor-tab-btn.active {
    color: var(--primary, #10b981);
    background: var(--surface, #ffffff);
}
.editor-tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: -6px;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--primary, #10b981);
    border-radius: 3px 3px 0 0;
}
.tab-chip {
    font-size: 0.72rem;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--surface-subtle, #e2e8f0);
    color: var(--text, #334155);
    font-weight: 700;
}
.tab-chip.chip-warn {
    background: #fef3c7;
    color: #92400e;
}
.editor-panel-card {
    background: var(--surface, #ffffff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: var(--radius-lg, 12px);
    padding: 24px;
    animation: fadeInTab 0.18s ease;
}
@keyframes fadeInTab {
    from { opacity: 0; transform: translateY(3px); }
    to { opacity: 1; transform: translateY(0); }
}
.panel-intro {
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border-light, #f1f5f9);
    padding-bottom: 12px;
}
.panel-intro h3 {
    margin: 0 0 4px 0;
    font-size: 1.15rem;
    color: var(--text, #0f172a);
}
.panel-intro p {
    margin: 0;
    font-size: 0.88rem;
}
.editor-action-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--surface, #ffffff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: var(--radius-lg, 12px);
    padding: 16px 20px;
    margin-top: 20px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}
.action-footer-nav {
    display: flex;
    gap: 8px;
}
.action-footer-save {
    display: flex;
    align-items: center;
    gap: 10px;
}
.editor-side-pane {
    position: sticky;
    top: 24px;
}
.preview-sticky-card {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.preview-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--muted, #6b7280);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0 4px;
}
.preview-status-pill {
    background: var(--primary-subtle, #ecfdf5);
    color: var(--primary-dark, #065f46);
    border: 1px solid var(--primary, #10b981);
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 0.72rem;
}
.media-section-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}
.media-count-tag {
    font-size: 0.82rem;
    color: var(--muted, #6b7280);
    background: var(--surface-subtle, #f1f5f9);
    padding: 3px 8px;
    border-radius: 6px;
}
.empty-media-msg {
    padding: 24px;
    text-align: center;
    color: var(--muted, #94a3b8);
    font-size: 0.9rem;
    border: 1px dashed var(--border, #cbd5e1);
    border-radius: var(--radius-md, 8px);
    margin-bottom: 14px;
}
.qr-preview-box {
    display: flex;
    align-items: center;
    gap: 18px;
    background: var(--surface-subtle, #f8fafc);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: var(--radius-md, 8px);
    padding: 12px;
}
.qr-preview-box .qr-item {
    margin-bottom: 0;
}
.manager-tip-box {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 12px 14px;
    background: var(--surface-subtle, #f8fafc);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: var(--radius-md, 8px);
    font-size: 0.82rem;
    color: var(--text-muted, #475569);
    line-height: 1.45;
}
.manager-tip-box .tip-ico {
    color: #f59e0b;
    font-size: 1rem;
    flex-shrink: 0;
    margin-top: 1px;
}
.managed-courts-section {
    margin-top: 48px;
    border-top: 2px solid var(--border, #e5e7eb);
    padding-top: 28px;
}
.courts-section-header {
    margin-bottom: 16px;
}
.mbooking.is-current-editing {
    border-color: var(--primary, #10b981);
    background: var(--surface-subtle, #f0fdf4);
}

@media (max-width: 960px) {
    .court-editor-container {
        grid-template-columns: 1fr;
    }
    .editor-side-pane {
        position: static;
        order: 2;
    }
}
</style>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo base_url('assets/js/leaflet/leaflet.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/map-picker.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Tab switching system
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.editor-tab-btn'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('.editor-panel-card'));
    var prevBtn = document.getElementById('prevTabBtn');
    var nextBtn = document.getElementById('nextTabBtn');

    var tabKeys = tabs.map(function (b) { return b.getAttribute('data-tab'); });
    var tabLabels = {
        'details': 'Details',
        'pricing': 'Pricing & Hours',
        'location': 'Location & Pin',
        'media': 'Media & QR',
        'schedule': 'Blackout Schedule'
    };

    function switchTab(targetKey) {
        var idx = tabKeys.indexOf(targetKey);
        if (idx === -1) {
            targetKey = 'details';
            idx = 0;
        }

        tabs.forEach(function (b) {
            var isCurrent = b.getAttribute('data-tab') === targetKey;
            b.classList.toggle('active', isCurrent);
            b.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
        });

        panels.forEach(function (p) {
            var match = p.id === 'panel-' + targetKey;
            if (match) {
                p.removeAttribute('hidden');
                p.style.display = 'block';
            } else {
                p.setAttribute('hidden', '');
                p.style.display = 'none';
            }
        });

        if (history.replaceState) {
            history.replaceState(null, null, '#' + targetKey);
        }

        // Navigation footer state
        if (prevBtn && nextBtn) {
            if (idx === 0) {
                prevBtn.style.display = 'none';
            } else {
                prevBtn.style.display = 'inline-flex';
                var prevKey = tabKeys[idx - 1];
                prevBtn.innerHTML = '<i class="fa-solid fa-arrow-left"></i> ' + (tabLabels[prevKey] || 'Back');
                prevBtn.setAttribute('data-target', prevKey);
            }

            if (idx === tabKeys.length - 1) {
                nextBtn.style.display = 'none';
            } else {
                nextBtn.style.display = 'inline-flex';
                var nextKey = tabKeys[idx + 1];
                nextBtn.innerHTML = (tabLabels[nextKey] || 'Next') + ' <i class="fa-solid fa-arrow-right"></i>';
                nextBtn.setAttribute('data-target', nextKey);
            }
        }

        // Leaflet map refresh when location tab is opened
        if (targetKey === 'location') {
            setTimeout(function () {
                if (window.groundMap && typeof window.groundMap.invalidateSize === 'function') {
                    window.groundMap.invalidateSize();
                }
                window.dispatchEvent(new Event('resize'));
            }, 100);
        }
    }

    tabs.forEach(function (b) {
        b.addEventListener('click', function () {
            switchTab(this.getAttribute('data-tab'));
        });
    });

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            var target = this.getAttribute('data-target');
            if (target) { switchTab(target); }
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            var target = this.getAttribute('data-target');
            if (target) { switchTab(target); }
        });
    }

    // Initial tab setup: hash check -> error check -> default
    var initialTab = '<?php echo $defaultTab; ?>';
    var hash = window.location.hash.replace('#', '');
    if (hash && tabKeys.indexOf(hash) !== -1) {
        initialTab = hash;
    }
    switchTab(initialTab);

    // 2. Real-time Live Preview synchronizer
    var nameInput = document.getElementById('name');
    var locInput = document.getElementById('location');
    var priceInput = document.getElementById('price_per_hour');
    var descInput = document.getElementById('description');

    var prevName = document.getElementById('previewName');
    var prevLoc = document.getElementById('previewLoc');
    var prevPrice = document.getElementById('previewPrice');
    var prevDesc = document.getElementById('previewDesc');

    function syncPreview() {
        if (nameInput && prevName) {
            prevName.textContent = nameInput.value.trim() || 'Your court name';
        }
        if (locInput && prevLoc) {
            prevLoc.textContent = locInput.value.trim() || 'Kathmandu, Nepal';
        }
        if (priceInput && prevPrice) {
            var val = parseFloat(priceInput.value);
            prevPrice.textContent = isNaN(val) ? '0' : val.toLocaleString();
        }
        if (descInput && prevDesc) {
            prevDesc.textContent = descInput.value.trim() || 'A short overview of your turf and venue features.';
        }
    }

    if (nameInput) { nameInput.addEventListener('input', syncPreview); }
    if (locInput) { locInput.addEventListener('input', syncPreview); }
    if (priceInput) { priceInput.addEventListener('input', syncPreview); }
    if (descInput) { descInput.addEventListener('input', syncPreview); }

    // 3. Calendar Blackout picker
    var cal = document.querySelector('.block-cal');
    var dateInput = document.getElementById('blockDate');
    if (cal && dateInput) {
        cal.addEventListener('click', function (e) {
            var day = e.target.closest('.block-cal-day');
            if (!day || day.disabled || day.classList.contains('blocked')) { return; }
            cal.querySelectorAll('.block-cal-day').forEach(function (el) { el.classList.remove('picked'); });
            day.classList.add('picked');
            dateInput.value = day.getAttribute('data-date');
            dateInput.focus();
        });
        dateInput.addEventListener('change', function () {
            cal.querySelectorAll('.block-cal-day').forEach(function (el) {
                el.classList.toggle('picked', el.getAttribute('data-date') === dateInput.value);
            });
        });
    }

    // 4. Client file names preview for photo uploads
    var photoInput = document.getElementById('photoInput');
    var fileNames = document.getElementById('fileNames');
    var photoGrid = document.getElementById('photoPreviewGrid');

    if (photoInput && fileNames) {
        photoInput.addEventListener('change', function () {
            var files = this.files;
            if (!files || files.length === 0) {
                fileNames.textContent = 'No files selected';
                if (photoGrid) { photoGrid.innerHTML = ''; }
                return;
            }
            fileNames.textContent = files.length === 1 ? files[0].name : files.length + ' files selected';
            if (photoGrid) {
                photoGrid.innerHTML = '';
                Array.prototype.slice.call(files).forEach(function (f) {
                    if (!f.type.match('image.*')) { return; }
                    var reader = new FileReader();
                    reader.onload = function (evt) {
                        var wrap = document.createElement('div');
                        wrap.className = 'photo-preview-item';
                        var img = document.createElement('img');
                        img.src = evt.target.result;
                        img.alt = f.name;
                        wrap.appendChild(img);
                        photoGrid.appendChild(wrap);
                    };
                    reader.readAsDataURL(f);
                });
            }
        });
    }

    // QR filename preview
    var qrInput = document.getElementById('qrInput') || document.getElementById('qrInputEdit');
    var qrFileName = document.getElementById('qrFileName') || document.getElementById('qrFileNameEdit');
    if (qrInput && qrFileName) {
        qrInput.addEventListener('change', function () {
            qrFileName.textContent = (this.files && this.files.length > 0) ? this.files[0].name : 'No file selected';
        });
    }
});
</script>

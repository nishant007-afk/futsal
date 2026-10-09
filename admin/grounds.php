<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ground'])) {
    verify_csrf();
    $id = (int)$_POST['delete_ground'];
    $stmt = $conn->prepare('DELETE FROM grounds WHERE id = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        set_flash('success', 'Ground deleted.');
    } else {
        set_flash('error', 'Could not delete ground.');
    }
    redirect('admin/grounds.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
    verify_csrf();
    $photo_id = (int)$_POST['delete_photo'];
    $ground_id = (int)($_POST['ground_id'] ?? 0);
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
    redirect('admin/grounds.php?edit=' . $ground_id);
}

$editing = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare('SELECT * FROM grounds WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
}

$managers = $conn->query('SELECT id, name FROM users WHERE role = "manager" ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price_per_hour'] ?? 0);
    $discount_price = ($_POST['discount_price'] ?? '') !== '' ? (float)$_POST['discount_price'] : null;
    $capacity = (int)($_POST['capacity'] ?? 10);
    $manager_id = (int)($_POST['manager_id'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $open_time = trim($_POST['open_time'] ?? '08:00');
    $close_time = trim($_POST['close_time'] ?? '22:00');
    $slot_interval = (int)($_POST['slot_interval'] ?? 60);
    $price_weekend = ($_POST['price_weekend'] ?? '') !== '' ? (float)$_POST['price_weekend'] : null;
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
    if ($manager_id > 0 && !in_array($manager_id, array_column($managers, 'id'), true)) {
        $errors['manager_id'] = 'Selected owner is not a manager account.';
    }
    $manager_id = $manager_id > 0 ? $manager_id : null;

    if (!$errors) {
        if ($id > 0) {
            $stmt = $conn->prepare(
                'UPDATE grounds SET name = ?, location = ?, description = ?, price_per_hour = ?, discount_price = ?, capacity = ?, manager_id = ?, is_active = ?, open_time = ?, close_time = ?, slot_interval = ?, price_weekend = ?, address = ?, court_number = ?, latitude = ?, longitude = ? WHERE id = ?'
            );
            $stmt->bind_param('sssddiiissidssddi', $name, $location, $description, $price, $discount_price, $capacity, $manager_id, $is_active, $open_time, $close_time, $slot_interval, $price_weekend, $address, $court_number, $latitude, $longitude, $id);
            $msg = 'Ground updated.';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO grounds (name, location, description, price_per_hour, discount_price, capacity, manager_id, is_active, open_time, close_time, slot_interval, price_weekend, address, court_number, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sssddiiissidssdd', $name, $location, $description, $price, $discount_price, $capacity, $manager_id, $is_active, $open_time, $close_time, $slot_interval, $price_weekend, $address, $court_number, $latitude, $longitude);
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
                        'admin/grounds.php?edit=' . $new_id
                    );
                }
                redirect('admin/grounds.php?edit=' . $new_id);
            }
            set_flash('success', $msg);
            redirect('admin/grounds.php');
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
        set_flash('error', $failed . ' photo(s) could not be uploaded. Use JPG, PNG, WebP or GIF.');
    }
    redirect('admin/grounds.php?edit=' . $ground_id);
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
            'admin/grounds.php?edit=' . $ground_id
        );
    }
    redirect('admin/grounds.php?edit=' . $ground_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_qr'])) {
    verify_csrf();
    $ground_id = (int)$_POST['delete_qr'];
    $oldQr = ground_qr($ground_id);
    $stmt = $conn->prepare('UPDATE grounds SET payment_qr = \'\' WHERE id = ?');
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    if ($oldQr !== '') {
        $path = __DIR__ . '/../uploads/grounds/' . basename($oldQr);
        if (is_file($path)) {
            unlink($path);
        }
    }
    set_flash('success', 'Payment QR code removed.');
    redirect('admin/grounds.php?edit=' . $ground_id);
}

$groundsAll = $conn->query(
    'SELECT g.*, u.name AS owner_name
     FROM grounds g
     LEFT JOIN users u ON u.id = g.manager_id
     ORDER BY g.id'
)->fetch_all(MYSQLI_ASSOC);

$f_g_search = trim($_GET['g_search'] ?? '');
if (mb_strlen($f_g_search) > 60) { $f_g_search = mb_substr($f_g_search, 0, 60); }
$f_g_active = trim($_GET['g_active'] ?? '');
if (!in_array($f_g_active, ['', 'active', 'inactive'], true)) { $f_g_active = ''; }

$grounds = $groundsAll;
if ($f_g_search !== '') {
    $needle = mb_strtolower($f_g_search);
    $grounds = array_values(array_filter($grounds, function ($g) use ($needle) {
        $hay = mb_strtolower(($g['name'] ?? '') . ' ' . ($g['location'] ?? '') . ' ' . ($g['owner_name'] ?? ''));
        return mb_strpos($hay, $needle) !== false;
    }));
}
if ($f_g_active !== '') {
    $grounds = array_values(array_filter($grounds, function ($g) use ($f_g_active) {
        return $f_g_active === 'active'
            ? !empty($g['is_active'])
            : empty($g['is_active']);
    }));
}

if (isset($_GET['export']) || isset($_GET['export_excel'])) {
    $rows = [['ID', 'Ground', 'Location', 'Manager', 'Price/hr (Rs)', 'Capacity', 'Open', 'Close', 'Slot (min)', 'Weekend Price (Rs)', 'Active']];
    foreach ($groundsAll as $g) {
        $rows[] = [
            $g['id'],
            $g['name'],
            $g['location'],
            $g['owner_name'] ?? '',
            $g['price_per_hour'],
            $g['capacity'],
            $g['open_time'],
            $g['close_time'],
            $g['slot_interval'],
            $g['price_weekend'] ?? '',
            $g['is_active'] ? 'Yes' : 'No',
        ];
    }
    if (isset($_GET['export_excel'])) {
        export_excel($rows, 'grounds.xlsx');
    }
    export_csv($rows, 'grounds.csv');
}

function build_grounds_query(array $overrides = []): string {
    $params = array_merge(
        [
            'g_search' => trim($_GET['g_search'] ?? ''),
            'g_active' => trim($_GET['g_active'] ?? ''),
        ],
        $overrides
    );
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}

$perPage = 10;
$totalGrounds = count($grounds);
$totalPages = max(1, (int)ceil($totalGrounds / $perPage));
$page = max(1, (int)($_GET['page'] ?? 1));
if ($page > $totalPages) {
    $page = $totalPages;
}
$pageGrounds = array_slice($grounds, ($page - 1) * $perPage, $perPage);
$showFrom = $totalGrounds > 0 ? ($page - 1) * $perPage + 1 : 0;
$showTo = min($totalGrounds, $page * $perPage);

$previewImages = [];
$photos = [];
if ($editing) {
    $photos = ground_images((int)$editing['id']);
    foreach ($photos as $ph) {
        $previewImages[] = base_url('uploads/grounds/' . rawurlencode($ph['image']));
    }
}
$firstImage = !empty($previewImages) ? $previewImages[0] : base_url('assets/images/pitch-pattern.svg');

$defaultTab = 'details';
if (!empty($errors['open_time']) || !empty($errors['close_time']) || !empty($errors['price_per_hour']) || !empty($errors['discount_price']) || !empty($errors['price_weekend'])) {
    $defaultTab = 'pricing';
} elseif (!empty($errors['latitude']) || !empty($errors['longitude'])) {
    $defaultTab = 'location';
}

$page_title = $editing ? 'Edit Court: ' . $editing['name'] : 'Add New Court';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/leaflet/leaflet.css'); ?>">

<div class="page-head dash-page-head">
    <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <h1 class="page-title"><?php echo $editing ? 'Edit Court: ' . e($editing['name']) : 'Add New Court'; ?></h1>
        <div class="actions">
            <?php if ($editing): ?>
                <a href="<?php echo base_url('admin/grounds.php'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add New Court</a>
            <?php endif; ?>
            <a href="<?php echo base_url('admin/grounds.php?export_excel=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-excel"></i> Export Excel</a>
            <a href="<?php echo base_url('admin/grounds.php?export=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        </div>
    </div>
</div>

<div class="court-editor-container">
    <div class="editor-main-pane">
        <div class="editor-card-unified">
            <div class="editor-tabs-bar" role="tablist" aria-label="Court Configuration Sections">
                <button type="button" class="editor-tab-btn active" data-tab="details" role="tab" aria-selected="true" aria-controls="panel-details">
                    <i class="fa-solid fa-circle-info"></i> Details
                </button>
                <button type="button" class="editor-tab-btn" data-tab="pricing" role="tab" aria-selected="false" aria-controls="panel-pricing">
                    <i class="fa-solid fa-tag"></i> Pricing &amp; Hours
                </button>
                <button type="button" class="editor-tab-btn" data-tab="location" role="tab" aria-selected="false" aria-controls="panel-location">
                    <i class="fa-solid fa-map-location-dot"></i> Location &amp; Pin
                </button>
                <button type="button" class="editor-tab-btn" data-tab="media" role="tab" aria-selected="false" aria-controls="panel-media">
                    <i class="fa-solid fa-images"></i> Media &amp; QR
                </button>
            </div>

            <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

            <!-- Primary Form for Ground Core Data -->
            <form method="post" action="" id="groundForm" enctype="multipart/form-data" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $editing ? (int)$editing['id'] : 0; ?>">

                <!-- TAB 1: BASIC DETAILS -->
                <div class="editor-panel-card" id="panel-details" role="tabpanel">
                    <div class="panel-intro">
                        <h3>Basic Court Information</h3>
                    </div>
                    <div class="grid grid-2">
                        <div class="form-group<?php echo has_error($errors, 'name'); ?>">
                            <div class="input-group floating">
                                <input type="text" id="name" name="name" value="<?php echo e($editing['name'] ?? ($name ?? '')); ?>" maxlength="100" placeholder=" " required>
                                <label for="name">Ground Name <span class="req">*</span></label>
                            </div>
                            <?php field_error($errors, 'name'); ?>
                        </div>
                        <div class="form-group">
                            <div class="input-group floating">
                                <input type="text" id="court_number" name="court_number" value="<?php echo e($editing['court_number'] ?? ($court_number ?? '')); ?>" maxlength="20" placeholder=" ">
                                <label for="court_number">Court Number or Label <span class="muted">(optional)</span></label>
                            </div>
                        </div>
                        <div class="form-group<?php echo has_error($errors, 'location'); ?>">
                            <div class="input-group floating">
                                <input type="text" id="location" name="location" value="<?php echo e($editing['location'] ?? ($location ?? '')); ?>" maxlength="255" placeholder=" " required>
                                <label for="location">Neighborhood / Area <span class="req">*</span></label>
                            </div>
                            <?php field_error($errors, 'location'); ?>
                        </div>
                        <div class="form-group<?php echo has_error($errors, 'capacity'); ?>">
                            <div class="input-group floating">
                                <input type="number" min="1" id="capacity" name="capacity" value="<?php echo e($editing['capacity'] ?? ($capacity ?? 10)); ?>" placeholder=" " required>
                                <label for="capacity">Player Capacity <span class="req">*</span></label>
                            </div>
                            <?php field_error($errors, 'capacity'); ?>
                        </div>
                        <div class="form-group span-full">
                            <div class="input-group floating">
                                <input type="text" id="address" name="address" value="<?php echo e($editing['address'] ?? ($address ?? '')); ?>" maxlength="255" placeholder=" ">
                                <label for="address">Full Street Address</label>
                            </div>
                        </div>
                        <div class="form-group span-full<?php echo has_error($errors, 'manager_id'); ?>">
                            <label for="manager_id">Assigned Manager (Owner)</label>
                            <select id="manager_id" name="manager_id">
                                <option value="0">-- Unassigned --</option>
                                <?php foreach ($managers as $m): ?>
                                    <option value="<?php echo (int)$m['id']; ?>" <?php echo (int)($editing['manager_id'] ?? ($manager_id ?? 0)) === (int)$m['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($m['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php field_error($errors, 'manager_id'); ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <textarea id="description" name="description" rows="3" aria-label="Court Overview &amp; Highlights" placeholder="Describe your turf quality, lighting, parking, changing rooms, and amenities..."><?php echo e($editing['description'] ?? ($description ?? '')); ?></textarea>
                    </div>
                    <div class="form-group" style="margin-top: 14px;">
                        <label class="check-line" for="is_active">
                            <input type="checkbox" id="is_active" name="is_active" aria-label="Make this court visible and bookable for players" <?php echo !isset($editing) || $editing['is_active'] ? 'checked' : ''; ?>>
                            <span class="check-box"><i class="fa-solid fa-check"></i></span>
                            <span><strong>Active status:</strong> Make this court visible and bookable for players</span>
                        </label>
                    </div>
                </div>

                <!-- TAB 2: PRICING & OPERATING HOURS -->
                <div class="editor-panel-card" id="panel-pricing" role="tabpanel" hidden>
                    <span id="pricing" class="anchor-target" aria-hidden="true"></span>
                    <div class="panel-intro">
                        <h3>Pricing &amp; Operating Hours</h3>
                    </div>
                    <div class="grid grid-2">
                        <div class="form-group<?php echo has_error($errors, 'price_per_hour'); ?>">
                            <div class="input-group floating">
                                <input type="number" step="0.01" min="0" id="price_per_hour" name="price_per_hour" value="<?php echo e($editing['price_per_hour'] ?? ($price ?? '')); ?>" placeholder=" " required>
                                <label for="price_per_hour">Regular Price / Hour (Rs.) <span class="req">*</span></label>
                            </div>
                            <?php field_error($errors, 'price_per_hour'); ?>
                        </div>
                        <div class="form-group<?php echo has_error($errors, 'discount_price'); ?>">
                            <div class="input-group floating">
                                <input type="number" step="0.01" min="0" id="discount_price" name="discount_price" value="<?php echo e($editing['discount_price'] ?? ''); ?>" placeholder=" ">
                                <label for="discount_price">Special Promotional Rate (Rs.) <span class="muted">(optional)</span></label>
                            </div>
                            <?php field_error($errors, 'discount_price'); ?>
                        </div>
                        <div class="form-group<?php echo has_error($errors, 'price_weekend'); ?>">
                            <div class="input-group floating">
                                <input type="number" step="0.01" min="0" id="price_weekend" name="price_weekend" value="<?php echo e($editing['price_weekend'] ?? ''); ?>" placeholder=" ">
                                <label for="price_weekend">Weekend Rate / Hour (Rs.) <span class="muted">(optional)</span></label>
                            </div>
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
                            <div class="input-group floating">
                                <input type="time" id="open_time" name="open_time" value="<?php echo e($editing['open_time'] ?? '08:00'); ?>">
                                <label for="open_time">Opening Time</label>
                            </div>
                            <?php field_error($errors, 'open_time'); ?>
                        </div>
                        <div class="form-group<?php echo has_error($errors, 'close_time'); ?>">
                            <div class="input-group floating">
                                <input type="time" id="close_time" name="close_time" value="<?php echo e($editing['close_time'] ?? '22:00'); ?>">
                                <label for="close_time">Closing Time</label>
                            </div>
                            <?php field_error($errors, 'close_time'); ?>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: LOCATION & PIN -->
                <div class="editor-panel-card" id="panel-location" role="tabpanel" hidden>
                    <div class="panel-intro">
                        <h3>Location &amp; Pin</h3>
                    </div>
                    <?php include __DIR__ . '/../includes/views/ground_location_picker.php'; ?>
                </div>

                <?php if (!$editing): ?>
                    <!-- NEW GROUND: MEDIA TAB DIRECTLY IN FORM -->
                    <div class="editor-panel-card" id="panel-media" role="tabpanel" hidden>
                        <div class="panel-intro">
                            <h3>Photos &amp; Payment QR</h3>
                        </div>
                        <div class="media-block">
                            <h4><i class="fa-solid fa-camera"></i> Court Photos</h4>
                            <div class="form-group file-pick">
                                <div class="dropzone" id="photoDropzone" data-for="photoInput">
                                    <input type="file" id="photoInput" name="photos[]" accept="image/*" multiple>
                                    <div class="dropzone-inner">
                                        <i class="fa-solid fa-cloud-arrow-up dropzone-icon"></i>
                                        <strong>Drag photos here</strong>
                                        <span class="muted">or</span>
                                        <label class="file-btn" for="photoInput"><i class="fa-solid fa-upload"></i> Choose files</label>
                                        <span class="file-name" id="fileNames">No files selected</span>
                                    </div>
                                </div>
                            </div>
                            <div class="photo-preview-grid" id="photoPreviewGrid"></div>
                        </div>

                        <div class="media-block">
                            <h4><i class="fa-solid fa-qrcode"></i> Payment QR Code</h4>
                            <div class="form-group file-pick">
                                <div class="dropzone" id="qrDropzone" data-for="qrInput">
                                    <input type="file" id="qrInput" name="payment_qr" accept="image/*">
                                    <div class="dropzone-inner">
                                        <i class="fa-solid fa-cloud-arrow-up dropzone-icon"></i>
                                        <strong>Drag QR image here</strong>
                                        <span class="muted">or</span>
                                        <label class="file-btn" for="qrInput"><i class="fa-solid fa-upload"></i> Choose QR image</label>
                                        <span class="file-name" id="qrFileName">No file selected</span>
                                    </div>
                                </div>
                            </div>
                            <div class="photo-preview-grid" id="qrPreviewGrid"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </form>

            <?php if ($editing): ?>
                <!-- TAB 4: MEDIA & QR FOR EXISTING GROUND -->
                <div class="editor-panel-card" id="panel-media" role="tabpanel" hidden>
                    <div class="panel-intro">
                        <h3>Photos &amp; Payment QR</h3>
                    </div>

                    <div class="media-block">
                        <div class="media-section-head">
                            <div>
                                <h4><i class="fa-solid fa-camera"></i> Photo Gallery</h4>
                            </div>
                            <span class="media-count-tag"><?php echo count($photos); ?> photos</span>
                        </div>

                        <div class="photo-grid mb">
                            <?php foreach ($photos as $ph): ?>
                                <div class="photo-item">
                                    <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($ph['image'])); ?>" alt="<?php echo e($editing['name']); ?> photo" loading="lazy" decoding="async">
                                    <form method="post" action="" style="display:inline;" novalidate>
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_photo" value="<?php echo (int)$ph['id']; ?>">
                                        <input type="hidden" name="ground_id" value="<?php echo (int)$editing['id']; ?>">
                                        <button type="submit" class="photo-remove" data-confirm="Remove this photo from your court?" data-confirm-title="Remove photo" title="Remove" aria-label="Remove photo"><i class="fa-solid fa-xmark"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$photos): ?>
                                <div class="empty-media-msg"><i class="fa-solid fa-image"></i> No photos uploaded yet.</div>
                            <?php endif; ?>
                        </div>

                        <form method="post" action="" enctype="multipart/form-data" id="photoUploadForm" novalidate>
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="upload_photos" value="1">
                            <div class="form-group file-pick">
                                <div class="dropzone" id="photoDropzone" data-for="photoInput">
                                    <input type="file" id="photoInput" name="photos[]" accept="image/*" multiple>
                                    <div class="dropzone-inner">
                                        <i class="fa-solid fa-cloud-arrow-up dropzone-icon"></i>
                                        <strong>Drag photos here</strong>
                                        <span class="muted">or</span>
                                        <label class="file-btn" for="photoInput"><i class="fa-solid fa-upload"></i> Select New Photos</label>
                                        <span class="file-name" id="fileNames">No files selected</span>
                                    </div>
                                </div>
                            </div>
                            <div class="photo-preview-grid" id="photoPreviewGrid"></div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-upload"></i> Upload selected photos</button>
                        </form>
                    </div>

                    <div class="media-block">
                        <div class="media-section-head">
                            <div>
                                <h4><i class="fa-solid fa-qrcode"></i> Payment QR Code</h4>
                            </div>
                        </div>

                        <?php $groundQr = ground_qr((int)$editing['id']); ?>
                        <?php if ($groundQr !== ''): ?>
                            <div class="qr-preview-box mb">
                                <div class="photo-item qr-item">
                                    <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($groundQr)); ?>" alt="Payment QR code for <?php echo e($editing['name']); ?>" loading="lazy" decoding="async">
                                    <form method="post" action="" style="display:inline;" novalidate>
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_qr" value="<?php echo (int)$editing['id']; ?>">
                                        <button type="submit" class="photo-remove" data-confirm="Remove this payment QR code?" data-confirm-title="Remove QR code" title="Remove" aria-label="Remove QR code"><i class="fa-solid fa-xmark"></i></button>
                                    </form>
                                </div>
                                <div class="qr-info-note">
                                    <span class="badge badge-confirmed"><i class="fa-solid fa-check"></i> Active QR</span>
                                </div>
                            </div>
                            <form method="post" action="" enctype="multipart/form-data" id="qrUploadForm" novalidate>
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="upload_qr" value="1">
                                <div class="form-group file-pick">
                                    <div class="dropzone" id="qrDropzone" data-for="qrInputEdit">
                                        <input type="file" id="qrInputEdit" name="payment_qr" accept="image/*">
                                        <div class="dropzone-inner">
                                            <i class="fa-solid fa-cloud-arrow-up dropzone-icon"></i>
                                            <strong>Drag a new QR image here</strong>
                                            <span class="muted">or</span>
                                            <label class="file-btn" for="qrInputEdit"><i class="fa-solid fa-upload"></i> Replace QR image</label>
                                            <span class="file-name" id="qrFileNameEdit">No file selected</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="photo-preview-grid" id="qrPreviewGrid"></div>
                                <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-upload"></i> Save replacement QR</button>
                            </form>
                        <?php else: ?>
                            <p class="muted" style="font-size: 0.88rem;">No payment QR set yet.</p>
                            <form method="post" action="" enctype="multipart/form-data" id="qrUploadForm" novalidate>
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="upload_qr" value="1">
                                <div class="form-group file-pick">
                                    <div class="dropzone" id="qrDropzone" data-for="qrInput">
                                        <input type="file" id="qrInput" name="payment_qr" accept="image/*">
                                        <div class="dropzone-inner">
                                            <i class="fa-solid fa-cloud-arrow-up dropzone-icon"></i>
                                            <strong>Drag QR image here</strong>
                                            <span class="muted">or</span>
                                            <label class="file-btn" for="qrInput"><i class="fa-solid fa-upload"></i> Choose QR image</label>
                                            <span class="file-name" id="qrFileName">No file selected</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="photo-preview-grid" id="qrPreviewGrid"></div>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-upload"></i> Upload QR Code</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Card Bottom Action Bar -->
            <div class="editor-card-footer">
                <div class="action-footer-left">
                    <button type="button" class="btn btn-outline btn-sm" id="prevTabBtn" style="display: none;"><i class="fa-solid fa-arrow-left"></i> Back</button>
                    <?php if ($editing): ?>
                        <a href="<?php echo base_url('admin/grounds.php'); ?>" class="btn btn-ghost btn-sm">Cancel</a>
                    <?php endif; ?>
                </div>
                <div class="action-footer-right">
                    <button type="button" class="btn btn-outline btn-sm" id="nextTabBtn">Next: Pricing &amp; Hours <i class="fa-solid fa-arrow-right"></i></button>
                    <button type="submit" form="groundForm" class="btn btn-primary" id="saveGroundBtn"><i class="fa-solid fa-floppy-disk"></i> <?php echo $editing ? 'Save Changes' : 'Create Ground'; ?></button>
                </div>
            </div>
        </div><!-- /.editor-card-unified -->
    </div><!-- /.editor-main-pane -->

    <!-- Right Side Column: Sticky Live Card Preview -->
    <div class="editor-side-pane">
        <div class="preview-sticky-card">
            <div class="preview-card-header">
                <span><i class="fa-solid fa-eye"></i> Live Card Preview</span>
            </div>
            <div class="card ground-preview-card" data-href="#">
                <div class="card-img">
                    <img src="<?php echo $firstImage; ?>" id="previewCoverImg" alt="Preview cover" class="card-cover" loading="lazy" decoding="async" draggable="false">
                    <div class="card-gallery" id="previewGallery" role="group" aria-label="Photos" data-images="<?php echo e(json_encode($previewImages)); ?>">
                        <button type="button" class="card-gnav card-gprev" aria-label="Previous photo" title="Previous photo" <?php echo count($previewImages) <= 1 ? 'hidden' : ''; ?>><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                        <button type="button" class="card-gnav card-gnext" aria-label="Next photo" title="Next photo" <?php echo count($previewImages) <= 1 ? 'hidden' : ''; ?>><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                        <span class="gallery-counter"><span class="gallery-counter-current">1</span>&nbsp;/&nbsp;<span class="gallery-counter-total" id="previewTotalCount"><?php echo max(1, count($previewImages)); ?></span></span>
                    </div>
                </div>
                <div class="card-body">
                    <h3 class="card-title">
                        <span id="previewName"><?php echo e($editing['name'] ?? 'Your Court Name'); ?></span>
                    </h3>
                    <p class="card-sub">
                        <span class="cs-loc"><i class="fa-solid fa-location-dot"></i> <span id="previewLoc"><?php echo e($editing['location'] ?? 'Kathmandu, Nepal'); ?></span></span>
                        <span class="cs-dot" aria-hidden="true">&bull;</span>
                        <span class="cs-surface"><span id="previewCapacity"><?php echo (int)($editing['capacity'] ?? 10) === 10 ? '5A-Side' : ((int)($editing['capacity'] ?? 10) . ' Players'); ?></span></span>
                        <span class="cs-dot" aria-hidden="true">&bull;</span>
                        <span class="cs-slots cs-free"><i class="fa-solid fa-circle-check"></i> Available</span>
                    </p>
                    <div class="card-meta">
                        <div class="price-block">
                            <div class="price-main-row">
                                <strong class="price-current">Rs <span id="previewPrice"><?php echo number_format((float)($editing['price_per_hour'] ?? 1500), 0); ?></span></strong>
                                <span class="price-unit">/ hr</span>
                            </div>
                        </div>
                        <?php if ($editing): ?>
                            <a href="<?php echo base_url('pages/ground.php?id=' . (int)$editing['id']); ?>" class="btn btn-outline btn-sm" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> View</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="groundsResults">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 40px; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
        <h3 class="dash-section reveal" style="margin: 0;">All Listed Courts (<?php echo count($grounds); ?>)</h3>
        <?php if ($totalGrounds > 0): ?>
            <span class="muted reveal" style="font-size: 0.85rem;">Showing <?php echo $showFrom; ?>&ndash;<?php echo $showTo; ?> of <?php echo $totalGrounds; ?> courts</span>
        <?php endif; ?>
    </div>

    <div class="courts-toolbar reveal">
        <form method="get" action="<?php echo base_url('admin/grounds.php'); ?>" class="courts-search" data-ajax-results="groundsResults">
            <div class="courts-search-main courts-search-main--4">
                <div class="search-field sf-grow">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="text" id="groundsSearch" name="g_search" placeholder="Search court, location or manager..." value="<?php echo e($f_g_search); ?>" autocomplete="off" aria-label="Search courts">
                </div>
                <div class="search-field">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <select id="groundsActive" name="g_active" aria-label="Filter by status">
                        <option value="">Any status</option>
                        <option value="active" <?php echo $f_g_active === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $f_g_active === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="toolbar-actions">
                    <?php if ($f_g_search !== '' || $f_g_active !== ''): ?>
                        <a href="<?php echo base_url('admin/grounds.php'); ?>" class="btn btn-outline btn-sm">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <?php if (!$groundsAll): ?>
        <div class="empty reveal"><span class="big"><i class="fa-solid fa-store"></i></span><h3>No grounds yet</h3><p>Add your first futsal court above.</p></div>
    <?php elseif (!$grounds): ?>
        <div class="empty reveal"><span class="big"><i class="fa-solid fa-magnifying-glass"></i></span><h3>No courts match your search</h3><p>Try a different name, area or status.</p></div>
    <?php else: ?>
        <div class="table-wrap reveal">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="min-width: 250px;">Court</th>
                        <th style="min-width: 140px;">Manager</th>
                        <th style="min-width: 160px;">Price / hr</th>
                        <th style="min-width: 150px;">Hours &amp; Slots</th>
                        <th style="width: 100px; text-align: center;">Status</th>
                        <th style="width: 140px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pageGrounds as $g): ?>
                        <?php
                        $gcover = ground_cover((int)$g['id']);
                        $gPrice = (float)$g['price_per_hour'];
                        $gDisc = !empty($g['discount_price']) ? (float)$g['discount_price'] : 0;
                        $gSale = $gDisc > 0 && $gDisc < $gPrice;
                        $gOff = $gSale ? (int)round((1 - $gDisc / $gPrice) * 100) : 0;
                        ?>
                        <tr>
                            <td data-label="Court">
                                <div class="court-table-cell">
                                    <div class="court-table-thumb-wrap">
                                        <?php if ($gcover): ?>
                                            <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($gcover)); ?>" alt="<?php echo e($g['name']); ?> cover" class="court-table-thumb" loading="lazy" decoding="async">
                                        <?php else: ?>
                                            <div class="court-table-thumb-placeholder"><i class="fa-solid fa-futbol"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="court-table-details">
                                        <strong class="court-table-name"><?php echo e($g['name']); ?></strong>
                                        <span class="court-table-loc"><i class="fa-solid fa-location-dot"></i> <?php echo e($g['location']); ?><?php if (!empty($g['court_number'])): ?> &middot; <?php echo e($g['court_number']); ?><?php endif; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Manager">
                                <?php if (!empty($g['owner_name'])): ?>
                                    <span class="court-table-manager"><i class="fa-solid fa-user-tie"></i> <?php echo e($g['owner_name']); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-unassigned">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Price / hr">
                                <div class="table-court-price">
                                    <?php if ($gSale): ?>
                                        <span class="price-orig">Rs <?php echo number_format($gPrice, 0); ?></span>
                                        <strong class="price-current">Rs <?php echo number_format($gDisc, 0); ?></strong>
                                        <span class="price-unit">/hr</span>
                                        <span class="price-off">-<?php echo $gOff; ?>%</span>
                                    <?php else: ?>
                                        <strong class="price-current">Rs <?php echo number_format($gPrice, 0); ?></strong>
                                        <span class="price-unit">/hr</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Hours & Slots">
                                <div class="court-table-schedule">
                                    <span class="court-table-hours"><i class="fa-regular fa-clock"></i> <?php echo substr($g['open_time'], 0, 5); ?> &ndash; <?php echo substr($g['close_time'], 0, 5); ?></span>
                                    <span class="court-table-specs"><?php echo (int)$g['slot_interval']; ?>m slots &middot; <?php echo (int)$g['capacity']; ?> players</span>
                                </div>
                            </td>
                            <td data-label="Status" class="cell-status" style="text-align: center;">
                                <?php if ($g['is_active']): ?>
                                    <span class="badge badge-confirmed"><i class="fa-solid fa-circle-check"></i> Active</span>
                                <?php else: ?>
                                    <span class="badge badge-cancelled"><i class="fa-solid fa-circle-xmark"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions" class="cell-actions" style="text-align: right;">
                                <div class="court-table-actions">
                                    <a href="<?php echo base_url('admin/grounds.php?edit=' . (int)$g['id']); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen"></i> Edit</a>
                                    <a href="<?php echo base_url('pages/ground.php?id=' . (int)$g['id']); ?>" class="btn btn-outline btn-sm" target="_blank" rel="noopener" title="View live court page"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                                    <?php echo post_action_form(base_url('admin/grounds.php'), 'delete_ground', (string)(int)$g['id'], '<i class="fa-solid fa-trash"></i>', 'btn btn-danger btn-sm', 'Delete this ground?', 'Delete ground', [], 'Delete ground?'); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Grounds pages" data-ajax-link="groundsResults" style="margin-top: 20px;">
                <?php if ($page > 1): ?>
                    <a class="page-link" href="<?php echo base_url('admin/grounds.php?' . build_grounds_query(['page' => $page - 1])); ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="page-link <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo base_url('admin/grounds.php?' . build_grounds_query(['page' => $i])); ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="page-link" href="<?php echo base_url('admin/grounds.php?' . build_grounds_query(['page' => $page + 1])); ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo base_url('assets/js/leaflet/leaflet.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/map-picker.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Tab switching system
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.editor-tab-btn[data-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('.editor-panel-card'));
    var prevBtn = document.getElementById('prevTabBtn');
    var nextBtn = document.getElementById('nextTabBtn');
    var saveBtn = document.getElementById('saveGroundBtn');
    var isEditing = <?php echo $editing ? 'true' : 'false'; ?>;

    var tabKeys = tabs.map(function (b) { return b.getAttribute('data-tab'); });
    var tabLabels = {
        'details': 'Details',
        'pricing': 'Pricing & Hours',
        'location': 'Location & Pin',
        'media': 'Media & QR'
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

        if (saveBtn) {
            if (!isEditing) {
                saveBtn.style.display = (idx === tabKeys.length - 1) ? 'inline-flex' : 'none';
            } else {
                saveBtn.style.display = 'inline-flex';
            }
        }

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
            var currentTarget = document.querySelector('.editor-tab-btn.active');
            var currentTab = currentTarget ? currentTarget.getAttribute('data-tab') : null;

            if (currentTab === 'details') {
                var name = document.getElementById('name') ? document.getElementById('name').value.trim() : '';
                var location = document.getElementById('location') ? document.getElementById('location').value.trim() : '';
                var capacity = document.getElementById('capacity') ? document.getElementById('capacity').value : '';

                var hasErrors = false;
                if (!name) { alert('Please enter a ground name.'); hasErrors = true; }
                else if (!location) { alert('Please enter a location.'); hasErrors = true; }
                else if (!capacity || capacity < 1) { alert('Please enter a valid capacity.'); hasErrors = true; }

                if (hasErrors) { return; }
            }

            var target = this.getAttribute('data-target');
            if (target) { switchTab(target); }
        });
    }

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
    var capacityInput = document.getElementById('capacity');

    var prevName = document.getElementById('previewName');
    var prevLoc = document.getElementById('previewLoc');
    var prevPrice = document.getElementById('previewPrice');
    var prevCapacity = document.getElementById('previewCapacity');

    function syncPreview() {
        if (nameInput && prevName) {
            prevName.textContent = nameInput.value.trim() || 'Your Court Name';
        }
        if (locInput && prevLoc) {
            prevLoc.textContent = locInput.value.trim() || 'Kathmandu, Nepal';
        }
        if (priceInput && prevPrice) {
            var val = parseFloat(priceInput.value);
            prevPrice.textContent = isNaN(val) ? '0' : val.toLocaleString();
        }
        if (capacityInput && prevCapacity) {
            var cap = parseInt(capacityInput.value, 10);
            prevCapacity.textContent = cap === 10 ? '5A-Side' : (cap ? cap + ' Players' : '5A-Side');
        }
    }

    if (nameInput) { nameInput.addEventListener('input', syncPreview); }
    if (locInput) { locInput.addEventListener('input', syncPreview); }
    if (priceInput) { priceInput.addEventListener('input', syncPreview); }
    if (capacityInput) { capacityInput.addEventListener('input', syncPreview); }

    // 3. Client file names preview & live gallery update for photo uploads
    var photoInput = document.getElementById('photoInput');
    var fileNames = document.getElementById('fileNames');
    var photoGrid = document.getElementById('photoPreviewGrid');

    function handlePhotoFiles(files) {
        if (!files || files.length === 0) {
            if (fileNames) { fileNames.textContent = 'No files selected'; }
            if (photoGrid) { photoGrid.innerHTML = ''; }
            return;
        }
        if (fileNames) {
            fileNames.textContent = files.length === 1 ? files[0].name : files.length + ' files selected';
        }

        var newUrls = [];
        if (photoGrid) { photoGrid.innerHTML = ''; }
        Array.prototype.slice.call(files).forEach(function (f) {
            if (!f.type.match('image.*')) { return; }
            var url = URL.createObjectURL(f);
            newUrls.push(url);
            if (photoGrid) {
                var wrap = document.createElement('div');
                wrap.className = 'photo-preview-item';
                var img = document.createElement('img');
                img.src = url;
                img.alt = f.name;
                wrap.appendChild(img);
                photoGrid.appendChild(wrap);
            }
        });

        if (newUrls.length > 0) {
            var gal = document.getElementById('previewGallery');
            var cover = document.getElementById('previewCoverImg');
            var tot = document.getElementById('previewTotalCount');
            var cur = gal ? gal.querySelector('.gallery-counter-current') : null;
            var prevBtnEl = gal ? gal.querySelector('.card-gprev') : null;
            var nextBtnEl = gal ? gal.querySelector('.card-gnext') : null;
            if (gal) {
                gal.setAttribute('data-images', JSON.stringify(newUrls));
                gal._imgs = newUrls;
                gal._idx = 0;
                if (cur) cur.textContent = '1';
                if (tot) tot.textContent = String(newUrls.length);
                if (prevBtnEl) prevBtnEl.hidden = true;
                if (nextBtnEl) nextBtnEl.hidden = newUrls.length <= 1;
            }
            if (cover) {
                cover.src = newUrls[0];
            }
        }
    }

    if (photoInput) {
        photoInput.addEventListener('change', function () {
            handlePhotoFiles(this.files);
        });
    }

    function wireDropzone(zone, input, onFiles) {
        if (!zone || !input || !window.DataTransfer) { return; }
        var dzInput = zone.querySelector('input[type="file"]') || input;
        var dzDepth = 0;

        ['dragenter', 'dragover'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (evt === 'dragenter') { dzDepth++; }
                zone.classList.add('is-dragover');
            });
        });

        zone.addEventListener('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dzDepth = Math.max(0, dzDepth - 1);
            if (dzDepth === 0) { zone.classList.remove('is-dragover'); }
        });

        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dzDepth = 0;
            zone.classList.remove('is-dragover');

            var dt = e.dataTransfer;
            if (!dt || !dt.files || dt.files.length === 0) { return; }

            var images = [];
            Array.prototype.slice.call(dt.files).forEach(function (f) {
                if (f.type && f.type.match('image.*')) { images.push(f); }
            });
            if (images.length === 0) { return; }

            try {
                var transfer = new DataTransfer();
                images.forEach(function (f) { transfer.items.add(f); });
                dzInput.files = transfer.files;
            } catch (err) {
                if (window.console) { console.warn('Could not attach dropped files to the input', err); }
            }

            onFiles(images);
        });
    }

    wireDropzone(document.getElementById('photoDropzone'), photoInput, handlePhotoFiles);

    var qrInput = document.getElementById('qrInput') || document.getElementById('qrInputEdit');
    var qrFileName = document.getElementById('qrFileName') || document.getElementById('qrFileNameEdit');
    var qrGrid = document.getElementById('qrPreviewGrid');

    function handleQrFile(files) {
        if (qrFileName) {
            qrFileName.textContent = (files && files.length > 0) ? files[0].name : 'No file selected';
        }
        if (!qrGrid) { return; }
        qrGrid.innerHTML = '';
        if (!files || files.length === 0 || !files[0].type.match('image.*')) { return; }
        var wrap = document.createElement('div');
        wrap.className = 'photo-preview-item';
        var img = document.createElement('img');
        img.src = URL.createObjectURL(files[0]);
        img.alt = files[0].name;
        wrap.appendChild(img);
        qrGrid.appendChild(wrap);
    }

    if (qrInput) {
        qrInput.addEventListener('change', function () {
            handleQrFile(this.files);
        });
    }
    wireDropzone(document.getElementById('qrDropzone'), qrInput, handleQrFile);
});
</script>


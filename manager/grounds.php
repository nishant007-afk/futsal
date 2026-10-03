<?php
require_once __DIR__ . '/../config/db.php';
require_manager();

$subStatus = subscription_status((int)$_SESSION['user_id']);

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
require __DIR__ . '/grounds_actions.php';

$grounds = $conn->query(
    'SELECT * FROM grounds WHERE manager_id = ' . (int)$_SESSION['user_id'] . ' ORDER BY id'
)->fetch_all(MYSQLI_ASSOC);

$isAdding = isset($_GET['add']) || (isset($_GET['action']) && $_GET['action'] === 'add');
$isEditing = $editing !== null;
$showEditor = $isAdding || $isEditing || !empty($errors);

$blockedDates = [];
$photos = [];
if ($editing) {
    $stmt = $conn->prepare('SELECT * FROM blocked_dates WHERE ground_id = ? ORDER BY block_date');
    $stmt->bind_param('i', $editing['id']);
    $stmt->execute();
    $blockedDates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $photos = ground_images((int)$editing['id']);
}

$previewImages = [];
if ($editing) {
    $gallery = ground_gallery((int)$editing['id']);
    foreach ($gallery as $gimg) {
        $previewImages[] = base_url('uploads/grounds/' . rawurlencode($gimg));
    }
}
if (empty($previewImages)) {
    $previewImages = [
        base_url('uploads/grounds/court_brastad_arena.jpg'),
        base_url('uploads/grounds/court_night_floodlights.jpg'),
        base_url('uploads/grounds/court_match_action.jpg')
    ];
}
$firstImage = $previewImages[0];

// Determine default active tab based on any errors or context
$defaultTab = 'details';
if (!empty($errors['price_per_hour']) || !empty($errors['discount_price']) || !empty($errors['open_time']) || !empty($errors['close_time']) || !empty($errors['price_weekend'])) {
    $defaultTab = 'pricing';
} elseif (!empty($errors['latitude']) || !empty($errors['longitude'])) {
    $defaultTab = 'location';
}

$page_title = $showEditor ? ($editing ? 'Edit Court: ' . $editing['name'] : 'Add New Court') : 'My Courts';
require __DIR__ . '/../includes/header.php';
?>

<?php if (!$showEditor): ?>
<!-- ======================= DEFAULT VIEW: MY COURTS ======================= -->
<div class="page-head dash-page-head">
    <a href="<?php echo base_url('manager/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <div>
            <h1 class="page-title">My Courts</h1>
            <p class="page-sub">Manage your futsal venues, live availability, pricing, and court details</p>
        </div>
        <div class="actions">
            <a href="<?php echo base_url('manager/promos.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-tags"></i> Promo Codes</a>
            <a href="<?php echo base_url('manager/grounds.php?add=1'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Court</a>
        </div>
    </div>
</div>

<?php if (!$subStatus['active']): ?>
    <div class="toast toast-warning toast-inline reveal" role="status" style="margin-bottom: 24px;">
        <div class="toast-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
        <div class="toast-content">
            <div class="toast-msg"><strong><?php echo e($subStatus['label']); ?>:</strong> Your courts are currently hidden from players. Renew your subscription in <a href="<?php echo base_url('manager/subscription.php'); ?>" style="text-decoration: underline; font-weight: 700;">Subscription & Billing</a> to go live again.</div>
        </div>
    </div>
<?php endif; ?>

<?php
$totalCourts = count($grounds);
$activeCourts = 0;
foreach ($grounds as $g) {
    if (!empty($g['is_active'])) $activeCourts++;
}
$inactiveCourts = $totalCourts - $activeCourts;
?>

<!-- Quick Overview Stats Bar -->
<div class="courts-stat-strip reveal">
    <div class="cs-stat-item">
        <span class="cs-stat-val"><?php echo $totalCourts; ?></span>
        <span class="cs-stat-lbl"><i class="fa-solid fa-store"></i> Total Courts</span>
    </div>
    <div class="cs-stat-item">
        <span class="cs-stat-val cs-stat-active"><?php echo $activeCourts; ?></span>
        <span class="cs-stat-lbl"><i class="fa-solid fa-circle-check"></i> Active & Live</span>
    </div>
    <div class="cs-stat-item">
        <span class="cs-stat-val"><?php echo $inactiveCourts; ?></span>
        <span class="cs-stat-lbl"><i class="fa-solid fa-circle-pause"></i> Inactive</span>
    </div>
</div>

<!-- Manager Courts Cards Grid -->
<?php if ($grounds): ?>
    <div class="mc-grid reveal">
        <?php foreach ($grounds as $g): ?>
            <?php
            $gcover = ground_cover((int)$g['id']);
            $coverUrl = $gcover ? base_url('uploads/grounds/' . rawurlencode($gcover)) : base_url('uploads/grounds/court_brastad_arena.jpg');
            $galleryCount = count(ground_images((int)$g['id']));
            ?>
            <div class="mc-card">
                <div class="mc-card-media">
                    <img src="<?php echo $coverUrl; ?>" alt="<?php echo e($g['name']); ?> cover" class="mc-card-cover" loading="lazy" decoding="async">
                    <div class="mc-card-badges">
                        <?php if ($g['is_active']): ?>
                            <span class="badge badge-confirmed"><i class="fa-solid fa-circle-check"></i> Active</span>
                        <?php else: ?>
                            <span class="badge badge-cancelled"><i class="fa-solid fa-circle-pause"></i> Inactive</span>
                        <?php endif; ?>

                        <?php if (!empty($g['court_number'])): ?>
                            <span class="mc-badge-pill"><?php echo e($g['court_number']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($galleryCount > 0): ?>
                        <div class="mc-photo-pill"><i class="fa-solid fa-camera"></i> <?php echo $galleryCount; ?></div>
                    <?php endif; ?>
                </div>

                <div class="mc-card-content">
                    <div class="mc-card-head-row">
                        <h3 class="mc-card-title">
                            <a href="<?php echo base_url('manager/grounds.php?edit=' . (int)$g['id']); ?>"><?php echo e($g['name']); ?></a>
                        </h3>
                    </div>

                    <div class="mc-card-loc">
                        <i class="fa-solid fa-location-dot"></i>
                        <span><?php echo e($g['location']); ?></span>
                    </div>

                    <div class="mc-card-features">
                        <span class="mc-feat-item"><i class="fa-solid fa-users"></i> <?php echo (int)$g['capacity'] === 10 ? '5A-Side' : ((int)$g['capacity'] . ' Players'); ?></span>
                        <span class="mc-feat-item"><i class="fa-solid fa-clock"></i> <?php echo e(substr($g['open_time'], 0, 5)); ?> – <?php echo e(substr($g['close_time'], 0, 5)); ?></span>
                        <span class="mc-feat-item"><i class="fa-solid fa-stopwatch"></i> <?php echo (int)$g['slot_interval']; ?>m slots</span>
                    </div>

                    <div class="mc-card-price-row">
                        <div class="mc-price-group">
                            <strong class="mc-price-val"><?php echo format_price((float)$g['price_per_hour']); ?></strong>
                            <span class="mc-price-unit">/ hour</span>
                        </div>
                        <?php if ($g['discount_price']): ?>
                            <span class="badge badge-confirmed">Promo: <?php echo format_price((float)$g['discount_price']); ?>/hr</span>
                        <?php endif; ?>
                    </div>

                    <div class="mc-card-actions">
                        <a href="<?php echo base_url('manager/grounds.php?edit=' . (int)$g['id']); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen"></i> Edit</a>
                        <a href="<?php echo base_url('pages/ground.php?id=' . (int)$g['id']); ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm" title="View Public Listing"><i class="fa-solid fa-arrow-up-right-from-square"></i> View</a>
                        <?php echo post_action_form(base_url('manager/grounds.php'), 'duplicate_ground', (string)(int)$g['id'], '<i class="fa-solid fa-copy"></i>', 'btn btn-outline btn-sm', 'Duplicate this ground?', 'Duplicate ground', ['title' => 'Duplicate court'], 'Duplicate ground?'); ?>
                        <?php echo post_action_form(base_url('manager/grounds.php'), 'delete_ground', (string)(int)$g['id'], '<i class="fa-solid fa-trash"></i>', 'btn btn-danger btn-sm', 'Delete this ground permanently?', 'Delete ground', ['title' => 'Delete court'], 'Delete court?'); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty reveal" style="padding: 56px 24px; background: var(--bg); border: 1px solid var(--line); border-radius: var(--r-lg); text-align: center; margin-top: 14px;">
        <span class="big" style="font-size: 3.2rem; color: var(--ink-4); margin-bottom: 14px; display: inline-block;"><i class="fa-solid fa-store"></i></span>
        <h3 style="margin: 0 0 8px 0; font-size: 1.3rem;">No courts added yet</h3>
        <p class="muted" style="max-width: 460px; margin: 0 auto 24px; font-size: 0.95rem;">You haven't listed any futsal courts yet. Add your court details, set prices, and upload photos to start receiving player bookings.</p>
        <a href="<?php echo base_url('manager/grounds.php?add=1'); ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Your First Court</a>
    </div>
<?php endif; ?>

<?php else: ?>
<!-- ======================= COURT EDITOR VIEW (ADD / EDIT) ======================= -->
<div class="page-head dash-page-head">
    <a href="<?php echo base_url('manager/grounds.php'); ?>" class="page-back-arrow" aria-label="Back to courts"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <div>
            <h1 class="page-title"><?php echo $editing ? 'Edit Court: ' . e($editing['name']) : 'Add New Court'; ?></h1>
            <p class="page-sub">Configure your futsal pitch specifications, pricing, and live availability</p>
        </div>
        <div class="actions">
            <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to My Courts</a>
            <a href="<?php echo base_url('manager/promos.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-tags"></i> Promo Codes</a>
        </div>
    </div>
</div>

<?php if (!$subStatus['active']): ?>
    <div class="toast toast-warning toast-inline reveal" role="status" style="margin-bottom: 20px;">
        <div class="toast-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
        <div class="toast-content">
            <div class="toast-msg"><strong><?php echo e($subStatus['label']); ?>:</strong> Your courts are currently hidden from players. Renew your subscription to go live again.</div>
        </div>
    </div>
<?php endif; ?>

<div class="court-editor-container">
    <div class="editor-main-pane">
        <div class="editor-card-unified">
            <!-- Card Header: Title + Action Links In Same Card (TOP CREATE GROUND BUTTON REMOVED) -->
            <div class="editor-card-head">
                <div class="ech-title-wrap">
                    <h2 class="ech-title"><?php echo $editing ? 'Edit Court: ' . e($editing['name']) : 'Add New Court'; ?></h2>
                    <p class="ech-sub">Configure your futsal pitch specifications, pricing, and live availability</p>
                </div>
                <div class="ech-actions">
                    <?php if ($editing): ?>
                        <a href="<?php echo base_url('manager/grounds.php?add=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-plus"></i> Add New Court</a>
                    <?php endif; ?>
                    <a href="<?php echo base_url('manager/promos.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-tags"></i> Promo Codes</a>
                </div>
            </div>

            <!-- Tab Navigation Header (clean, without pills) -->
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
                </button>
                <?php if ($editing): ?>
                    <button type="button" class="editor-tab-btn" data-tab="schedule" role="tab" aria-selected="false" aria-controls="panel-schedule">
                        <i class="fa-solid fa-calendar-xmark"></i> Blackout Schedule
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
                    </div>
                    <div class="grid grid-2">
                        <div class="form-group<?php echo has_error($errors, 'name'); ?>">
                            <label for="name">Ground Name <span class="req">*</span></label>
                            <input type="text" id="name" name="name" value="<?php echo e($editing['name'] ?? ($name ?? '')); ?>" maxlength="100" placeholder="e.g. KickOff Arena Court 1" required>
                            <?php field_error($errors, 'name'); ?>
                        </div>
                        <div class="form-group">
                            <label for="court_number">Court Number or Label <span class="muted">(optional)</span></label>
                            <input type="text" id="court_number" name="court_number" value="<?php echo e($editing['court_number'] ?? ($court_number ?? '')); ?>" maxlength="20" placeholder="e.g. Pitch A">
                        </div>
                        <div class="form-group<?php echo has_error($errors, 'location'); ?>">
                            <label for="location">Neighborhood / Area <span class="req">*</span></label>
                            <input type="text" id="location" name="location" value="<?php echo e($editing['location'] ?? ($location ?? '')); ?>" maxlength="255" placeholder="e.g. Baneshwor, Kathmandu" required>
                            <?php field_error($errors, 'location'); ?>
                        </div>
                        <div class="form-group<?php echo has_error($errors, 'capacity'); ?>">
                            <label for="capacity">Player Capacity <span class="req">*</span></label>
                            <input type="number" min="1" id="capacity" name="capacity" value="<?php echo e($editing['capacity'] ?? ($capacity ?? 10)); ?>" placeholder="10 (for 5v5)" required>
                            <?php field_error($errors, 'capacity'); ?>
                        </div>
                        <div class="form-group span-full">
                            <label for="address">Full Street Address</label>
                            <input type="text" id="address" name="address" value="<?php echo e($editing['address'] ?? ($address ?? '')); ?>" maxlength="255" placeholder="e.g. Madan Bhandari Path, New Baneshwor">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Court Overview & Highlights</label>
                        <textarea id="description" name="description" rows="3" placeholder="Describe your turf quality, lighting, parking, changing rooms, and amenities..."><?php echo e($editing['description'] ?? ($description ?? '')); ?></textarea>
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
                    <div class="panel-intro">
                        <h3>Pricing & Operating Hours</h3>
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
                        <h3>Location & Pin</h3>
                    </div>
                    <?php include __DIR__ . '/../includes/views/ground_location_picker.php'; ?>
                </div>

                <?php if (!$editing): ?>
                    <!-- NEW GROUND: MEDIA TAB DIRECTLY IN FORM -->
                    <div class="editor-panel-card" id="panel-media" role="tabpanel" hidden>
                        <div class="panel-intro">
                            <h3>Photos & Payment QR</h3>
                        </div>
                        <div class="form-card sub" style="margin-bottom: 20px;">
                            <h4><i class="fa-solid fa-camera"></i> Court Photos</h4>
                            <div class="form-group file-pick">
                                <label class="file-btn" for="photoInput"><i class="fa-solid fa-image"></i> Choose files</label>
                                <input type="file" id="photoInput" name="photos[]" accept="image/*" multiple>
                                <span class="file-name" id="fileNames">No files selected</span>
                            </div>
                            <div class="photo-preview-grid" id="photoPreviewGrid"></div>
                        </div>

                        <div class="form-card sub">
                            <h4><i class="fa-solid fa-qrcode"></i> Payment QR Code</h4>
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
                        <h3>Photos & Payment QR</h3>
                    </div>

                    <div class="form-card sub" style="margin-bottom: 24px;">
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
                                    <form method="post" action="" style="display:inline;">
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
                                        <button type="submit" class="photo-remove" data-confirm="Remove this payment QR code?" data-confirm-title="Remove QR code" title="Remove" aria-label="Remove QR code"><i class="fa-solid fa-xmark"></i></button>
                                    </form>
                                </div>
                                <div class="qr-info-note">
                                    <span class="badge badge-confirmed"><i class="fa-solid fa-check"></i> Active QR</span>
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
                        <h3>Blackout Schedule</h3>
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
                                        <button type="submit" class="photo-remove" data-confirm="Unblock this date and make slots available again?" data-confirm-title="Unblock date" title="Unblock" aria-label="Unblock date"><i class="fa-solid fa-xmark"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Card Bottom Action Bar (Next link on right side beside Create Ground / Save Changes) -->
            <div class="editor-card-footer">
                <div class="action-footer-left">
                    <button type="button" class="btn btn-outline btn-sm" id="prevTabBtn" style="display: none;"><i class="fa-solid fa-arrow-left"></i> Back</button>
                    <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-ghost btn-sm">Cancel</a>
                </div>
                <div class="action-footer-right">
                    <button type="button" class="btn btn-outline btn-sm" id="nextTabBtn">Next: Pricing & Hours <i class="fa-solid fa-arrow-right"></i></button>
                    <button type="submit" form="groundForm" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?php echo $editing ? 'Save Changes' : 'Create Ground'; ?></button>
                </div>
            </div>
        </div><!-- /.editor-card-unified -->
    </div><!-- /.editor-main-pane -->

    <!-- Right Side Column: Sticky Live Card Preview Only (No Tip, Authentic Card With Slider) -->
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
                        <span class="cs-dot" aria-hidden="true">•</span>
                        <span class="cs-surface"><span id="previewCapacity"><?php echo (int)($editing['capacity'] ?? 10) === 10 ? '5A-Side' : ((int)($editing['capacity'] ?? 10) . ' Players'); ?></span></span>
                        <span class="cs-dot" aria-hidden="true">•</span>
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
<?php endif; ?>

<style>
/* Stats bar in My Courts */
.courts-stat-strip {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.cs-stat-item {
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: var(--r-md);
    padding: 14px 20px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 140px;
    flex: 1;
}
.cs-stat-val {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -0.02em;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}
.cs-stat-active {
    color: var(--brand-700);
}
.cs-stat-lbl {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--ink-3);
    display: flex;
    align-items: center;
    gap: 6px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* My Courts Responsive Grid */
.mc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 22px;
    margin-bottom: 40px;
}
.mc-card {
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: var(--r-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: var(--s1);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.mc-card:hover {
    border-color: var(--line-2);
    box-shadow: 0 10px 24px -4px rgba(10, 24, 16, 0.08);
}
.mc-card-media {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 10;
    background: #092617;
    overflow: hidden;
}
.mc-card-cover {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transform: none !important;
}
.mc-card:hover .mc-card-cover {
    transform: none !important;
}
.mc-card-badges {
    position: absolute;
    top: 12px;
    left: 12px;
    display: flex;
    gap: 6px;
    z-index: 2;
    align-items: center;
}
.mc-badge-pill {
    background: rgba(10, 24, 16, 0.78);
    backdrop-filter: blur(4px);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: var(--r-sm);
    letter-spacing: 0.02em;
}
.mc-photo-pill {
    position: absolute;
    bottom: 10px;
    right: 12px;
    z-index: 2;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(4px);
    color: #ffffff;
    font-size: 0.74rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: var(--r-sm);
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.mc-card-content {
    padding: 18px 20px 20px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.mc-card-head-row {
    margin-bottom: 6px;
}
.mc-card-title {
    font-size: 1.15rem;
    font-weight: 800;
    margin: 0;
    line-height: 1.3;
}
.mc-card-title a {
    color: var(--ink);
    text-decoration: none;
    transition: color 0.15s ease;
}
.mc-card-title a:hover {
    color: var(--brand-700);
}
.mc-card-loc {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    color: var(--ink-3);
    margin-bottom: 12px;
}
.mc-card-features {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 14px;
}
.mc-feat-item {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--ink-2);
    background: var(--bg-soft);
    border: 1px solid var(--line);
    padding: 4px 9px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.mc-card-price-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 0;
    border-top: 1px solid var(--line);
    margin-top: auto;
    margin-bottom: 14px;
}
.mc-price-group {
    display: inline-flex;
    align-items: baseline;
    gap: 4px;
}
.mc-price-val {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--ink);
}
.mc-price-unit {
    font-size: 0.8rem;
    color: var(--ink-3);
}
.mc-card-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.mc-card-actions .btn {
    white-space: nowrap;
}
.mc-card-actions .btn-primary {
    flex: 1;
    justify-content: center;
}

/* Court Editor Layout */
.court-editor-container {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 400px;
    gap: 24px;
    align-items: start;
    margin-top: 14px;
    margin-bottom: 40px;
    min-width: 0;
    max-width: 100%;
}
.editor-main-pane {
    min-width: 0;
    max-width: 100%;
}
.editor-card-unified {
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: var(--r-lg);
    box-shadow: var(--s1);
    overflow: hidden;
}
.editor-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    padding: 20px 24px;
    border-bottom: 1px solid var(--line);
    background: var(--bg);
}
.ech-title-wrap {
    min-width: 0;
}
.ech-title {
    margin: 0 0 4px 0;
    font-size: 1.25rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--ink);
}
.ech-sub {
    margin: 0;
    font-size: 0.84rem;
    color: var(--ink-3);
}
.ech-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.editor-tabs-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 14px 24px;
    border-bottom: 1px solid var(--line);
    background: var(--bg-soft);
    max-width: 100%;
    overflow-x: visible;
}
.editor-tab-btn {
    background: var(--bg);
    border: 1px solid var(--line);
    padding: 7px 14px;
    border-radius: var(--r-md);
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ink-2);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    position: relative;
    transition: all 0.15s ease;
}
.editor-tab-btn:hover {
    color: var(--ink);
    background: var(--bg-soft);
    border-color: var(--line-2);
}
.editor-tab-btn.active {
    color: var(--brand-700);
    background: var(--brand-soft);
    border-color: var(--brand);
    font-weight: 700;
}
.editor-panel-card {
    background: transparent;
    border: none;
    border-radius: 0;
    padding: 24px 24px 28px;
    animation: fadeInTab 0.18s ease;
    box-sizing: border-box;
}
.editor-panel-card .grid-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px 20px;
}
.editor-panel-card .form-group {
    margin-bottom: 18px;
    min-width: 0;
}
.editor-panel-card .span-full {
    grid-column: 1 / -1;
}
.editor-panel-card .form-group label {
    display: block;
    font-size: 13.5px;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--ink);
    letter-spacing: -0.01em;
}
.editor-panel-card input:not([type="checkbox"]):not([type="radio"]):not([type="file"]),
.editor-panel-card select,
.editor-panel-card textarea {
    width: 100% !important;
    max-width: 100% !important;
    min-height: 44px;
    padding: 10px 14px !important;
    font-size: 14.5px !important;
    font-family: inherit;
    color: var(--ink) !important;
    background: var(--bg) !important;
    border: 1px solid var(--line-2) !important;
    border-radius: 8px !important;
    box-sizing: border-box !important;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.editor-panel-card input:focus,
.editor-panel-card select:focus,
.editor-panel-card textarea:focus {
    border-color: var(--brand) !important;
    box-shadow: 0 0 0 3px var(--brand-soft) !important;
    background: #ffffff !important;
    outline: none !important;
}
.editor-panel-card textarea {
    min-height: 96px;
    line-height: 1.5;
}

@keyframes fadeInTab {
    from { opacity: 0; transform: translateY(3px); }
    to { opacity: 1; transform: translateY(0); }
}
.panel-intro {
    margin-bottom: 18px;
    border-bottom: 1px solid var(--line);
    padding-bottom: 10px;
}
.panel-intro h3 {
    margin: 0 0 4px 0;
    font-size: 1.1rem;
    color: var(--ink);
}
.panel-intro p {
    margin: 0;
    font-size: 0.85rem;
    color: var(--ink-3);
}
.editor-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding: 16px 24px;
    border-top: 1px solid var(--line);
    background: var(--bg-soft);
}
.action-footer-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.action-footer-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-left: auto;
}
.editor-side-pane {
    position: sticky;
    top: 24px;
}
.preview-sticky-card {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.preview-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--ink-3);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0 2px;
}

/* Ground Preview Card - No zoom, clean */
.ground-preview-card {
    width: 100%;
    margin: 0;
    box-shadow: var(--s2);
    border-radius: var(--r-lg);
    overflow: hidden;
}
.ground-preview-card:hover {
    transform: none !important;
    box-shadow: var(--s2) !important;
}
.ground-preview-card .card-img {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 10;
    background: var(--dark-2, #18221c);
    overflow: hidden;
}
.ground-preview-card .card-cover,
.ground-preview-card:hover .card-cover,
.card:hover .card-cover {
    transform: none !important;
}
.ground-preview-card .card-cover {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.media-section-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}
.media-count-tag {
    font-size: 0.82rem;
    color: var(--ink-3);
    background: var(--bg-soft);
    padding: 3px 8px;
    border-radius: var(--r-sm);
}
.empty-media-msg {
    padding: 24px;
    text-align: center;
    color: var(--ink-3);
    font-size: 0.9rem;
    border: 1px dashed var(--line-2);
    border-radius: var(--r-md);
    margin-bottom: 14px;
}
.qr-preview-box {
    display: flex;
    align-items: center;
    gap: 18px;
    background: var(--bg-soft);
    border: 1px solid var(--line);
    border-radius: var(--r-md);
    padding: 12px;
}
.qr-preview-box .qr-item {
    margin-bottom: 0;
}

@media (max-width: 1180px) {
    .court-editor-container {
        grid-template-columns: minmax(0, 1fr);
    }
    .editor-main-pane {
        min-width: 0;
        max-width: 100%;
    }
    .editor-side-pane {
        position: static;
        order: 2;
        min-width: 0;
        max-width: 100%;
    }
}
@media (max-width: 768px) {
    .editor-panel-card .grid-2 {
        grid-template-columns: 1fr;
        gap: 14px;
    }
    .editor-panel-card {
        padding: 18px 16px;
    }
    .mc-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 600px) {
    .editor-card-footer {
        flex-direction: column;
        align-items: stretch;
    }
    .action-footer-left,
    .action-footer-right {
        width: 100%;
        display: flex;
        gap: 8px;
        margin-left: 0;
    }
    .action-footer-left .btn,
    .action-footer-right .btn {
        flex: 1;
        text-align: center;
        justify-content: center;
    }
}
</style>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php if ($showEditor): ?>
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

        // Navigation footer state: Back on left, Next on right beside Create Ground
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

    // 4. Client file names preview & live gallery update for photo uploads
    var photoInput = document.getElementById('photoInput');
    var fileNames = document.getElementById('fileNames');
    var photoGrid = document.getElementById('photoPreviewGrid');

    if (photoInput) {
        photoInput.addEventListener('change', function () {
            var files = this.files;
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

            // Update live card preview gallery in right pane
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
<?php endif; ?>

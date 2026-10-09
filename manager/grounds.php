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

$photos = [];

$previewImages = [];
if ($editing) {
    $photos = ground_images((int)$editing['id']);
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
if (!empty($_GET['tab']) && in_array($_GET['tab'], ['details', 'location', 'pricing', 'media'], true)) {
    $defaultTab = $_GET['tab'];
} elseif (!empty($errors['price_per_hour']) || !empty($errors['discount_price']) || !empty($errors['open_time']) || !empty($errors['close_time']) || !empty($errors['price_weekend'])) {
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
                        <?php $gRating = ground_rating((int)$g['id']); ?>
                        <?php if ($gRating['count'] > 0): ?>
                            <a href="<?php echo base_url('pages/ground.php?id=' . (int)$g['id'] . '#reviews'); ?>" target="_blank" rel="noopener" class="badge badge-warning" title="<?php echo $gRating['count']; ?> review(s)" style="display:inline-flex; align-items:center; gap:4px; text-decoration:none;">
                                <i class="fa-solid fa-star" style="color:#f59e0b; font-size:0.75rem;"></i> <?php echo number_format($gRating['avg'], 1); ?> (<?php echo $gRating['count']; ?>)
                            </a>
                        <?php endif; ?>
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
        <h1 class="page-title"><?php echo $editing ? 'Edit Court: ' . e($editing['name']) : 'Add New Court'; ?></h1>
        <div class="actions">
            <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to My Courts</a>
            <?php if ($editing): ?>
                <a href="<?php echo base_url('manager/grounds.php?add=1'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add New Court</a>
            <?php endif; ?>
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
            <!-- Tab Navigation Header (clean, without pills)
                 REVERTED to the plain tab labels on request: the numbered steps with completion
                 ticks were not wanted, so the chips, the "n of 4 complete" readout and the divider
                 are all retired. They are kept below, commented out, in case they are wanted again.
            <div class="editor-tabs-bar" role="tablist" aria-label="Court Configuration Steps">
                <button type="button" class="editor-tab-btn active" data-tab="details" role="tab" aria-selected="true" aria-controls="panel-details">
                    <span class="step-chip" aria-hidden="true"><span class="step-num">1</span><i class="fa-solid fa-check step-done"></i></span>
                    <i class="fa-solid fa-circle-info step-icon"></i> Details
                </button>
                <button type="button" class="editor-tab-btn" data-tab="pricing" role="tab" aria-selected="false" aria-controls="panel-pricing">
                    <span class="step-chip" aria-hidden="true"><span class="step-num">2</span><i class="fa-solid fa-check step-done"></i></span>
                    <i class="fa-solid fa-tag step-icon"></i> Pricing &amp; Hours
                </button>
                <button type="button" class="editor-tab-btn" data-tab="location" role="tab" aria-selected="false" aria-controls="panel-location">
                    <span class="step-chip" aria-hidden="true"><span class="step-num">3</span><i class="fa-solid fa-check step-done"></i></span>
                    <i class="fa-solid fa-map-location-dot step-icon"></i> Location &amp; Pin
                </button>
                <button type="button" class="editor-tab-btn" data-tab="media" role="tab" aria-selected="false" aria-controls="panel-media">
                    <span class="step-chip" aria-hidden="true"><span class="step-num">4</span><i class="fa-solid fa-check step-done"></i></span>
                    <i class="fa-solid fa-images step-icon"></i> Media &amp; QR
                </button>
                <span class="editor-tabs-divider" aria-hidden="true"></span>
                <a href="<?php echo base_url('manager/promos.php'); ?>" class="editor-tab-btn editor-tab-link" target="_blank" rel="noopener">
                    <i class="fa-solid fa-tags"></i> Promo Codes
                </a>
            </div>
            <p class="editor-steps-progress" id="editorStepsProgress" role="status" aria-live="polite"></p>
            -->

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
                <a href="<?php echo base_url('manager/promos.php'); ?>" class="editor-tab-btn editor-tab-link" target="_blank" rel="noopener">
                    <i class="fa-solid fa-tags"></i> Promo Codes
                </a>

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
                    </div>
                    <div class="form-group">
                        <!-- KEPT (removed per request): the visible heading for this field was not
                             wanted - the placeholder already says what to write. Kept here, commented
                             out. The textarea carries aria-label with the same text so screen readers
                             and the shared validation still have a field name to work with.
                        <label for="description">Court Overview &amp; Highlights</label>
                        -->
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
                    <div class="panel-intro">
                        <h3>Pricing & Operating Hours</h3>
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
                        <div class="media-block">
                            <h4><i class="fa-solid fa-camera"></i> Court Photos</h4>
                            <!-- KEPT (removed per request): plain file input with a "Choose files"
                                 label, so photos could only be added through the file browser and
                                 there was no visual target to drop onto. Now a drop zone; the
                                 <input> is still the real form control and stays keyboard reachable.
                            <div class="form-group file-pick">
                                <label class="file-btn" for="photoInput"><i class="fa-solid fa-image"></i> Choose files</label>
                                <input type="file" id="photoInput" name="photos[]" accept="image/*" multiple>
                                <span class="file-name" id="fileNames">No files selected</span>
                            </div>
                            -->
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
                        <h3>Photos & Payment QR</h3>
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
                            <!-- KEPT (removed per request): same plain file input as the add form,
                                 replaced here by the drag-and-drop zone for consistency.
                            <div class="form-group file-pick">
                                <label class="file-btn" for="photoInput"><i class="fa-solid fa-cloud-arrow-up"></i> Select New Photos</label>
                                <input type="file" id="photoInput" name="photos[]" accept="image/*" multiple>
                                <span class="file-name" id="fileNames">No files selected</span>
                            </div>
                            -->
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
                                        <input type="hidden" name="delete_qr_ground" value="<?php echo (int)$editing['id']; ?>">
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

            <!-- Card Bottom Action Bar (Next link on right side beside Create Ground / Save Changes) -->
            <div class="editor-card-footer">
                <div class="action-footer-left">
                    <button type="button" class="btn btn-outline btn-sm" id="prevTabBtn" style="display: none;"><i class="fa-solid fa-arrow-left"></i> Back</button>
                    <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-ghost btn-sm">Cancel</a>
                </div>
                <div class="action-footer-right">
                    <button type="button" class="btn btn-outline btn-sm" id="nextTabBtn">Next: Pricing & Hours <i class="fa-solid fa-arrow-right"></i></button>
                    <button type="submit" form="groundForm" class="btn btn-primary" id="saveGroundBtn"><i class="fa-solid fa-floppy-disk"></i> <?php echo $editing ? 'Save Changes' : 'Create Ground'; ?></button>
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
}
.mc-card:hover .mc-card-cover {
    transform: none;
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
.editor-tab-btn.editor-tab-link {
    color: var(--ink-2);
    text-decoration: none !important;
}
.editor-tab-btn.editor-tab-link:hover {
    color: var(--brand-700);
    border-color: var(--brand);
    background: var(--brand-soft);
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
.editor-panel-card .form-group label:not(.file-btn) {
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
    /* KEPT (removed per request): a tall preview (long gallery, long description)
       made the sticky pane taller than the viewport, so it scrolled away instead of
       staying put. It now scrolls internally and never exceeds the viewport.
       Previous values were simply `position: sticky; top: 24px;` with no cap. */
    max-height: calc(100vh - 48px);
    overflow-y: auto;
    overscroll-behavior: contain;
    /* Room for the scrollbar so it does not sit on top of the card. */
    padding-right: 4px;
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
    transform: none;
    box-shadow: var(--s2);
}
.ground-preview-card .card-img {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 10;
    background: var(--dark-2, #18221c);
    overflow: hidden;
}
.ground-preview-card .card-cover {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.ground-preview-card:hover .card-cover {
    transform: none;
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
    /* Mobile keeps the desktop arrangement: Back/Cancel left, Next/Create Ground right */
    .editor-card-footer {
        flex-direction: row;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 10px 12px;
    }
    .action-footer-left,
    .action-footer-right {
        width: auto;
        flex: 0 1 auto;
        min-width: 0;
        display: flex;
        flex-wrap: nowrap;
        gap: 6px;
        margin-left: 0;
        overflow: hidden;
    }
    .action-footer-right {
        margin-left: auto;
        flex: 0 0 auto;
        overflow: visible;
    }
    .action-footer-left .btn,
    .action-footer-right .btn {
        flex: 0 1 auto;
        min-width: 0;
        text-align: center;
        justify-content: center;
        white-space: nowrap;
        overflow: hidden;
    }
}

/* ============================================================
   ADD / EDIT COURT: floating label clearance + tighter field spacing
   Added after review of this page. Two real problems were measured here.

   1. THE LABEL OVERLAPPED THE TEXT. The rule above at "line 934" sets
      `padding: 10px 14px !important` on every panel input, which overrode the
      global floating-label padding (`padding: 31px 14px 3px 16px !important`
      in assets/css/style.css). The floated label sits at top 4px with an 11px
      font and line-height 1, so it occupies 4px-15px, while the text started at
      10px: a -5px gap, i.e. the label was drawn on top of the typed value.
      Scoped to `.input-group.floating input` so the plain labelled controls in
      these panels - the "Court Overview" textarea and the Active status checkbox -
      keep their normal 10px padding.
      30px of top padding puts the text 15px below the label; min-height rises to
      60px so the 14.5px text still gets its full 20px line box (was 44px tall).

   2. THE FIELD SPACING WAS DOUBLE-COUNTED AND TOO LOOSE. `.grid-2` had an 18px
      row gap *and* every `.form-group` added another 18px margin-bottom, so
      stacked rows were really 36px apart, with a 20px column gutter.

   Previous values, kept for reference:
     .editor-panel-card input/select/textarea   padding: 10px 14px; min-height: 44px
     .editor-panel-card .grid-2                gap: 18px 20px   (14px on <=768px)
     .editor-panel-card .form-group            margin-bottom: 18px
   ============================================================ */

/* 1. Restore room for the floated label (floating inputs only).
      KEPT (removed per request): min-height: 60px; padding: 30px 14px 8px;
      and on mobile min-height: 56px; padding: 27px 13px 7px.  */
.editor-panel-card .input-group.floating input:not([type="checkbox"]):not([type="radio"]):not([type="file"]) {
    min-height: 46px;
    padding: 21px 14px 2px !important;
}

/* 2. Tighter, single-source spacing: the grid gap owns the rhythm, the
      form-group margin no longer doubles it up. */
.editor-panel-card .grid-2 {
    gap: 10px 12px;
    /* Zeroing the grid children's margin-bottom (below) removed the only space
       that used to sit between the grid and whatever followed it. In the Details
       panel that collapsed the gap between "Full Street Address" and the Court
       Overview textarea to 0. The grid carries its own trailing space instead,
       matching its 10px row gap. */
    margin-bottom: 10px;
}
.editor-panel-card .grid-2 > .form-group {
    margin-bottom: 0;
}
.editor-panel-card > .form-group,
.editor-panel-card .form-card > .form-group {
    margin-bottom: 12px;
}

@media (max-width: 768px) {
    .editor-panel-card .grid-2 {
        gap: 10px;
    }
    .editor-panel-card .input-group.floating input:not([type="checkbox"]):not([type="radio"]):not([type="file"]) {
        min-height: 46px;
        padding: 21px 13px 2px !important;
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

        /* RETIRED on request: per-step completion tracking (green ticks + the "n of 4 complete"
           readout). The tabs are plain labels again, so this no longer runs. Kept for reference:
        var stepDone = {
            details: function (p) {
                return p.querySelector('[name="name"]') &&
                       p.querySelector('[name="name"]').value.trim() !== '';
            },
            pricing: function (p) {
                var price = p.querySelector('[name="price_per_hour"]');
                return price && parseFloat(price.value) > 0;
            },
            location: function (p) {
                var loc = p.querySelector('[name="location"]');
                return loc && loc.value.trim() !== '';
            },
            media: function () { return true; }
        };

        tabs.forEach(function (b) {
            var key = b.getAttribute('data-tab');
            var panel = document.getElementById('panel-' + key);
            var done = !!(stepDone[key] && panel && stepDone[key](panel));
            b.classList.toggle('is-complete', done);
            b.setAttribute('data-step-state', done ? 'complete' : 'todo');
        });

        var progress = document.getElementById('editorStepsProgress');
        if (progress) {
            var completeCount = tabs.filter(function (b) { return b.classList.contains('is-complete'); }).length;
            progress.textContent = completeCount + ' of ' + tabs.length + ' steps complete';
        }
        */

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

        // Create Ground button visibility: do not show until user reaches the last part
        if (saveBtn) {
            if (!isEditing) {
                saveBtn.style.display = (idx === tabKeys.length - 1) ? 'inline-flex' : 'none';
            } else {
                saveBtn.style.display = 'inline-flex';
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
            // When switching to details tab from another tab, validate required fields
            var currentTarget = document.querySelector('.editor-tab-btn.active');
            var currentTab = currentTarget ? currentTarget.getAttribute('data-tab') : null;

            // If we're on or moving to details tab, validate required fields
            if (!target || target === 'details' || currentTab === 'details') {
                var name = document.getElementById('name')?.value.trim();
                var location = document.getElementById('location')?.value.trim();
                var capacity = document.getElementById('capacity')?.value;
                var price = document.getElementById('price_per_hour')?.value;

                var hasErrors = false;
                if (!name) { alert('Please enter a ground name.'); hasErrors = true; }
                if (!location) { alert('Please enter a location.'); hasErrors = true; }
                if (!capacity || capacity < 1) { alert('Please enter a valid capacity.'); hasErrors = true; }
                if (!price || isNaN(parseFloat(price)) || parseFloat(price) <= 0) { alert('Please enter a valid price per hour.'); hasErrors = true; }

                if (hasErrors) { return; }
            }

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

    // 3. Client file names preview & live gallery update for photo uploads
    //    KEPT (removed per request): this was bound only to the <input>'s change event, so the
    //    preview could not be produced any other way. The body below is unchanged apart from
    //    being extracted into handlePhotoFiles() so the drop zone can reuse it verbatim.
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
    }

    if (photoInput) {
        photoInput.addEventListener('change', function () {
            handlePhotoFiles(this.files);
        });
    }

    // 3b. Drag-and-drop onto a drop zone. The real <input> still receives the
    //     files (via DataTransfer) so the normal multipart upload is untouched.
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

            // Only images are accepted, matching accept="image/*" on the input.
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
                // Older browsers refuse assignment to input.files; the preview still works.
                if (window.console) { console.warn('Could not attach dropped files to the input', err); }
            }

            onFiles(images);
        });
    }

    wireDropzone(document.getElementById('photoDropzone'), photoInput, handlePhotoFiles);

    // QR: filename preview + thumbnail, wired through the same drop zone helper
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
<?php endif; ?>

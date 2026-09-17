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
    redirect('manager/grounds.php?edit=' . $ground_id);
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            'manager/grounds.php?edit=' . $ground_id
        );
    }
    redirect('manager/grounds.php?edit=' . $ground_id);
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
            'manager/grounds.php?edit=' . $ground_id
        );
    }
    redirect('manager/grounds.php?edit=' . $ground_id);
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
    redirect('manager/grounds.php?edit=' . $ground_id);
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
            'manager/grounds.php?edit=' . $ground_id
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
                'manager/grounds.php?edit=' . $ground_id
            );
        }
    }
    redirect('manager/grounds.php?edit=' . $ground_id);
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
    redirect('manager/grounds.php?edit=' . $unblock_ground);
}

$grounds = $conn->query(
    'SELECT * FROM grounds WHERE manager_id = ' . (int)$_SESSION['user_id'] . ' ORDER BY id'
)->fetch_all(MYSQLI_ASSOC);

$blockedDates = [];
if ($editing) {
    $stmt = $conn->prepare('SELECT * FROM blocked_dates WHERE ground_id = ? ORDER BY block_date');
    $stmt->bind_param('i', $editing['id']);
    $stmt->execute();
    $blockedDates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$page_title = 'My Grounds';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head dash-page-head">
    <a href="<?php echo base_url('manager/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <h2><?php echo $editing ? 'Edit Ground' : 'Add Ground'; ?></h2>
        <div class="actions">
            <?php if ($editing): ?>
                <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Ground</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$subStatus['active']): ?>
    <div class="toast toast-warning toast-inline reveal" role="status">
        <div class="toast-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
        <div class="toast-content">
            <div class="toast-msg"><?php echo e($subStatus['label']); ?> - your courts are hidden from players. Renew your subscription to go live again.</div>
        </div>
    </div>
<?php endif; ?>


<div class="ground-form-grid">
    <div class="form-card reveal flat">
        <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>
        <form method="post" action="" id="groundForm" enctype="multipart/form-data" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $editing ? (int)$editing['id'] : 0; ?>">
            <div class="grid grid-2">
                <div class="form-group<?php echo has_error($errors, 'name'); ?>">
                    <label for="name">Ground Name <span class="req">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo e($editing['name'] ?? ($name ?? '')); ?>" maxlength="100" required>
                    <?php field_error($errors, 'name'); ?>
                </div>
                <div class="form-group<?php echo has_error($errors, 'location'); ?>">
                    <label for="location">Location <span class="req">*</span></label>
                    <input type="text" id="location" name="location" value="<?php echo e($editing['location'] ?? ($location ?? '')); ?>" maxlength="255" required>
                    <?php field_error($errors, 'location'); ?>
                </div>
                <div class="form-group<?php echo has_error($errors, 'price_per_hour'); ?>">
                    <label for="price_per_hour">Price per Hour (Rs.) <span class="req">*</span></label>
                    <input type="number" step="0.01" min="0" id="price_per_hour" name="price_per_hour" value="<?php echo e($editing['price_per_hour'] ?? ($price ?? '')); ?>" required>
                    <?php field_error($errors, 'price_per_hour'); ?>
                </div>
                <div class="form-group<?php echo has_error($errors, 'discount_price'); ?>">
                    <label for="discount_price">Discounted price/hr (Rs.) <span class="muted">(optional)</span></label>
                    <input type="number" step="0.01" min="0" id="discount_price" name="discount_price" value="<?php echo e($editing['discount_price'] ?? ''); ?>" placeholder="Lower than regular price for a sale">
                    <?php field_error($errors, 'discount_price'); ?>
                </div>
                <div class="form-group<?php echo has_error($errors, 'capacity'); ?>">
                    <label for="capacity">Capacity <span class="req">*</span></label>
                    <input type="number" min="1" id="capacity" name="capacity" value="<?php echo e($editing['capacity'] ?? ($capacity ?? 10)); ?>" required>
                    <?php field_error($errors, 'capacity'); ?>
                </div>
                <div class="form-group<?php echo has_error($errors, 'open_time'); ?>">
                    <label for="open_time">Opens at</label>
                    <input type="time" id="open_time" name="open_time" value="<?php echo e($editing['open_time'] ?? '08:00'); ?>">
                    <?php field_error($errors, 'open_time'); ?>
                </div>
                <div class="form-group<?php echo has_error($errors, 'close_time'); ?>">
                    <label for="close_time">Closes at</label>
                    <input type="time" id="close_time" name="close_time" value="<?php echo e($editing['close_time'] ?? '22:00'); ?>">
                    <?php field_error($errors, 'close_time'); ?>
                </div>
                <div class="form-group">
                    <label for="slot_interval">Slot length</label>
                    <select id="slot_interval" name="slot_interval">
                        <option value="60" <?php echo (int)($editing['slot_interval'] ?? 60) === 60 ? 'selected' : ''; ?>>60 minutes</option>
                        <option value="30" <?php echo (int)($editing['slot_interval'] ?? 60) === 30 ? 'selected' : ''; ?>>30 minutes</option>
                    </select>
                </div>
                <div class="form-group<?php echo has_error($errors, 'price_weekend'); ?>">
                    <label for="price_weekend">Weekend price/hr (Rs.) <span class="muted">(optional)</span></label>
                    <input type="number" step="0.01" min="0" id="price_weekend" name="price_weekend" value="<?php echo e($editing['price_weekend'] ?? ''); ?>" placeholder="Uses weekday price">
                    <?php field_error($errors, 'price_weekend'); ?>
                 </div>
                 <div class="form-group">
                     <label for="address">Full address (for maps)</label>
                     <input type="text" id="address" name="address" value="<?php echo e($editing['address'] ?? ($address ?? '')); ?>" maxlength="255" placeholder="e.g. New Road, Kathmandu 44600, Nepal">
                 </div>
<div class="form-group">
                     <label for="court_number">Court number/name (optional)</label>
                     <input type="text" id="court_number" name="court_number" value="<?php echo e($editing['court_number'] ?? ($court_number ?? '')); ?>" maxlength="20" placeholder="e.g. Court 1">
                 </div>
             </div>
             <?php include __DIR__ . '/../includes/views/ground_location_picker.php'; ?>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?php echo e($editing['description'] ?? ($description ?? '')); ?></textarea>
            </div>
            <div class="form-group">
                <label class="check-line">
                    <input type="checkbox" name="is_active" <?php echo !isset($editing) || $editing['is_active'] ? 'checked' : ''; ?>>
                    <span class="check-box"><i class="fa-solid fa-check"></i></span>
                    <span>Active (visible on homepage)</span>
                </label>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?php echo $editing ? 'Save Changes' : 'Add Ground'; ?></button>
                <?php if ($editing): ?>
                    <a href="<?php echo base_url('manager/grounds.php'); ?>" class="btn btn-outline">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="ground-form-side">
        <div class="form-card sub">
            <h3>Photos</h3>
            <p class="muted">Upload photos to showcase your court.
            JPG, PNG, WebP, GIF • Max 5 MB each</p>
            <?php if ($editing): ?>
                <div class="photo-grid mb">
                    <?php $photos = ground_images((int)$editing['id']); ?>
                    <?php foreach ($photos as $ph): ?>
                        <div class="photo-item">
                            <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($ph['image'])); ?>" alt="<?php echo e($editing['name']); ?> photo" loading="lazy" decoding="async">
                            <form method="post" action="" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="delete_photo" value="<?php echo (int)$ph['id']; ?>">
                                <input type="hidden" name="ground_id" value="<?php echo (int)$editing['id']; ?>">
                                <button type="submit" class="photo-remove" data-confirm="Remove this photo?" title="Remove" aria-label="Remove photo"><i class="fa-solid fa-xmark"></i></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$photos): ?>
                        <p class="muted">No photos yet.</p>
                    <?php endif; ?>
                </div>
                <form method="post" action="" enctype="multipart/form-data" id="photoUploadForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="upload_photos" value="1">
                    <div class="form-group file-pick">
                        <label class="file-btn" for="photoInput"><i class="fa-solid fa-image"></i> Choose files</label>
                        <input type="file" id="photoInput" name="photos[]" accept="image/*" multiple>
                        <span class="file-name" id="fileNames">No files selected</span>
                    </div>
                    <div class="photo-preview-grid" id="photoPreviewGrid"></div>
                    <button type="submit" class="btn btn-outline btn-block"><i class="fa-solid fa-upload"></i> Upload photos</button>
                </form>
            <?php else: ?>
                <div class="form-group file-pick">
                    <label class="file-btn" for="photoInput"><i class="fa-solid fa-image"></i> Choose files</label>
                    <input type="file" id="photoInput" name="photos[]" accept="image/*" multiple form="groundForm">
                    <span class="file-name" id="fileNames">No files selected</span>
                </div>
                <div class="photo-preview-grid" id="photoPreviewGrid"></div>
            <?php endif; ?>
        </div>

        <div class="form-card sub">
            <h3>Payment QR code</h3>
            <p class="muted">Upload your payment QR so players can scan and pay you directly.</p>
            <?php if ($editing): ?>
                <?php $groundQr = ground_qr((int)$editing['id']); ?>
                <?php if ($groundQr !== ''): ?>
                    <div class="photo-item qr-item mb">
                        <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($groundQr)); ?>" alt="Payment QR code for <?php echo e($editing['name']); ?>" loading="lazy" decoding="async">
                        <form method="post" action="" style="display:inline;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="delete_qr_ground" value="<?php echo (int)$editing['id']; ?>">
                            <button type="submit" class="photo-remove" data-confirm="Remove this payment QR code?" title="Remove" aria-label="Remove QR code"><i class="fa-solid fa-xmark"></i></button>
                        </form>
                    </div>
                    <form method="post" action="" enctype="multipart/form-data" id="qrUploadForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="upload_qr" value="1">
                        <div class="form-group file-pick">
                            <label class="file-btn" for="qrInput"><i class="fa-solid fa-qrcode"></i> Replace QR image</label>
                            <input type="file" id="qrInputEdit" name="payment_qr" accept="image/*">
                            <span class="file-name" id="qrFileNameEdit">No file selected</span>
                        </div>
                        <button type="submit" class="btn btn-outline btn-block"><i class="fa-solid fa-upload"></i> Save QR</button>
                    </form>
                <?php else: ?>
                    <p class="muted">No payment QR yet.</p>
                    <form method="post" action="" enctype="multipart/form-data" id="qrUploadForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="upload_qr" value="1">
                        <div class="form-group file-pick">
                            <label class="file-btn" for="qrInput"><i class="fa-solid fa-qrcode"></i> Choose QR image</label>
                            <input type="file" id="qrInput" name="payment_qr" accept="image/*">
                            <span class="file-name" id="qrFileName">No file selected</span>
                        </div>
                        <button type="submit" class="btn btn-outline btn-block"><i class="fa-solid fa-upload"></i> Upload QR</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <p class="muted">No payment QR yet.</p>
                <div class="form-group file-pick">
                    <label class="file-btn" for="qrInput"><i class="fa-solid fa-qrcode"></i> Choose QR image</label>
                    <input type="file" id="qrInput" name="payment_qr" accept="image/*" form="groundForm">
                    <span class="file-name" id="qrFileName">No file selected</span>
                </div>
                <p class="muted">The QR will be saved when you add the ground below.</p>
            <?php endif; ?>
        </div>

        <?php if ($editing): ?>
            <div class="form-card sub">
                <h3>Blocked dates</h3>
                <p class="muted">Tap a day below to block it for private events, maintenance or holidays.</p>
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
                <div class="blocked-list">
                    <?php if (!$blockedDates): ?>
                        <p class="muted">No blocked dates.</p>
                    <?php else: ?>
                        <?php foreach ($blockedDates as $bd): ?>
                            <div class="blocked-item">
                                <span><i class="fa-solid fa-calendar-xmark"></i> <?php echo e(date('D, M j, Y', strtotime($bd['block_date']))); ?><?php echo $bd['note'] !== '' ? ' - ' . e($bd['note']) : ''; ?></span>
                                 <form method="post" action="" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="unblock_date" value="<?php echo (int)$bd['id']; ?>">
                                    <input type="hidden" name="ground_id" value="<?php echo (int)$editing['id']; ?>">
                                    <button type="submit" class="photo-remove" data-confirm="Unblock this date?" title="Unblock" aria-label="Unblock date"><i class="fa-solid fa-xmark"></i></button>
                                 </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <form method="post" action="" id="blockDateForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="add_blocked_date" value="1">
                    <input type="hidden" name="ground_id" value="<?php echo (int)$editing['id']; ?>">
                    <div class="form-group">
                        <label for="blockDate">Date</label>
                        <input type="date" id="blockDate" name="block_date" min="<?php echo e(date('Y-m-d')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="blockNote">Note <span class="muted">(optional)</span></label>
                        <input type="text" id="blockNote" name="block_note" maxlength="255" placeholder="e.g. Private tournament">
                    </div>
                    <button type="submit" class="btn btn-outline btn-block"><i class="fa-solid fa-ban"></i> Block this date</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="ground-preview">
            <div class="card-img">
                <?php if ($editing): $cover = ground_cover((int)$editing['id']); endif; ?>
                <?php if (!empty($cover)): ?>
                    <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($cover)); ?>" alt="Cover photo of <?php echo e($editing['name']); ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <div class="pitch"></div>
                <?php endif; ?>
            </div>
            <div class="preview-body">
                <h3 id="previewName"><?php echo e($editing['name'] ?? 'Your court name'); ?></h3>
                <span class="preview-loc"><i class="fa-solid fa-location-dot"></i> <span id="previewLoc"><?php echo e($editing['location'] ?? 'Kathmandu, Nepal'); ?></span></span>
                <span class="preview-price">Rs <?php echo number_format((float)($editing['price_per_hour'] ?? 0), 0); ?> <small>/ hour</small></span>
                <p class="preview-desc" id="previewDesc"><?php echo e($editing['description'] ?? 'A short description of your court.'); ?></p>
                <?php if ($editing): ?>
                    <a href="<?php echo base_url('pages/ground.php?id=' . (int)$editing['id']); ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener"><i class="fa-solid fa-eye"></i> Live preview</a>
                <?php else: ?>
                    <span class="btn btn-primary btn-sm btn-preview"><i class="fa-solid fa-eye"></i> Save to preview</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="mbookings reveal">
    <?php foreach ($grounds as $g): ?>
        <div class="mbooking">
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
        <div class="empty reveal"><span class="big"><i class="fa-solid fa-store"></i></span><h3>No grounds yet</h3><p>Add your first futsal court above.</p></div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo base_url('assets/js/leaflet/leaflet.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/map-picker.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var cal = document.querySelector('.block-cal');
    var dateInput = document.getElementById('blockDate');
    if (!cal || !dateInput) { return; }
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
});
</script>


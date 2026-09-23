<?php
// POST handlers for manager/grounds.php — keeps the main page view-focused.
// Paths use __DIR__ relative to manager/ (this file lives beside grounds.php).

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photos']) && !empty($editing)) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_qr']) && !empty($editing)) {
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

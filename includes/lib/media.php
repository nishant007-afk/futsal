<?php

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

    $dest = dirname(__DIR__, 2) . '/uploads/avatars/user_' . $userId . '_' . bin2hex(random_bytes(8)) . '.webp';
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
        $dest = __DIR__ . '/../../uploads/grounds/' . $filename;
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
    $dest = __DIR__ . '/../../uploads/grounds/' . $filename;
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

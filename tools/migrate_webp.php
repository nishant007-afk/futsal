<?php
/**
 * One-time CLI migration: convert legacy JPG/PNG uploads to WebP.
 *
 * Usage:  php tools/migrate_webp.php
 *
 * Converts ground_images, ground covers (grounds.image) and user avatars
 * located under uploads/, updates the DB filenames, and moves each original
 * into uploads/_legacy_backup/ so nothing is destroyed.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

require __DIR__ . '/../config/env.php';
require __DIR__ . '/../includes/functions.php';

$conn = new mysqli(env('DB_HOST', 'localhost'), env('DB_USER', 'root'), (string)env('DB_PASS', ''), env('DB_NAME', 'futsal_booking'), (int)env('DB_PORT', '3306'));
if ($conn->connect_errno) {
    fwrite(STDERR, 'DB connection failed: ' . $conn->connect_error . PHP_EOL);
    exit(1);
}
$conn->set_charset('utf8mb4');

$base   = dirname(__DIR__);
$legacy = $base . '/uploads/_legacy_backup';

$JPEG_PNG = '/\.(jpe?g|png)$/i';

function to_webp(string $base, string $uploadDir, string $oldName, string $legacy, int $quality, ?int $maxWidth): ?string
{
    $src  = $base . '/' . $uploadDir . '/' . $oldName;
    if (!is_file($src)) {
        fwrite(STDERR, '  MISSING ' . $uploadDir . '/' . $oldName . PHP_EOL);
        return null;
    }
    $newName = preg_replace('/\.(jpe?g|png)$/i', '.webp', $oldName);
    $dest    = $base . '/' . $uploadDir . '/' . $newName;

    if (is_file($dest)) {
        fwrite(STDOUT, '  EXISTS ' . $newName . PHP_EOL);
    } elseif (!convert_image_to_webp($src, $dest, $quality, $maxWidth)) {
        fwrite(STDERR, '  CONVERT FAILED ' . $uploadDir . '/' . $oldName . PHP_EOL);
        return null;
    }

    $backup = $legacy . '/' . $uploadDir . '/' . $oldName;
    $bkDir  = dirname($backup);
    if (!is_dir($bkDir)) {
        mkdir($bkDir, 0777, true);
    }
    if (!rename($src, $backup)) {
        fwrite(STDERR, '  BACKUP MOVE FAILED ' . $oldName . PHP_EOL);
        return null;
    }
    fwrite(STDOUT, '  OK ' . $oldName . ' -> ' . $newName . ' (' . round(filesize($dest) / 1024, 1) . ' KB)' . PHP_EOL);
    return $newName;
}

$total = 0;

fwrite(STDOUT, "== ground_images ==\n");
$res = $conn->query("SELECT id, ground_id, image FROM ground_images ORDER BY id");
$upd = $conn->prepare("UPDATE ground_images SET image = ? WHERE id = ?");
while ($row = $res->fetch_assoc()) {
    if (!preg_match($JPEG_PNG, $row['image'])) {
        fwrite(STDOUT, '  (skip already webp/empty) ' . $row['image'] . PHP_EOL);
        continue;
    }
    $new = to_webp($base, 'uploads/grounds', $row['image'], $legacy, 82, 1600);
    if ($new === null) {
        continue;
    }
    $upd->bind_param('si', $new, $row['id']);
    if ($upd->execute()) {
        $total++;
    }
}
$res->close();
$upd->close();

fwrite(STDOUT, "== grounds.image (covers) ==\n");
$res = $conn->query("SELECT id, image FROM grounds WHERE image IS NOT NULL AND image <> ''");
$upd = $conn->prepare("UPDATE grounds SET image = ? WHERE id = ?");
while ($row = $res->fetch_assoc()) {
    if (!preg_match($JPEG_PNG, $row['image'])) {
        continue;
    }
    $new = to_webp($base, 'uploads/grounds', $row['image'], $legacy, 82, 1600);
    if ($new === null) {
        continue;
    }
    $upd->bind_param('ss', $new, $row['id']);
    if ($upd->execute()) {
        $total++;
    }
}
$res->close();
$upd->close();

fwrite(STDOUT, "== users.avatar ==\n");
$res = $conn->query("SELECT id, avatar FROM users WHERE avatar <> ''");
$upd = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
while ($row = $res->fetch_assoc()) {
    if (!preg_match($JPEG_PNG, $row['avatar'])) {
        continue;
    }
    $new = to_webp($base, 'uploads/avatars', $row['avatar'], $legacy, 85, 512);
    if ($new === null) {
        continue;
    }
    $upd->bind_param('si', $new, $row['id']);
    if ($upd->execute()) {
        $total++;
    }
}
$res->close();
$upd->close();

fwrite(STDOUT, "\n== done: $total files migrated. Originals under uploads/_legacy_backup/\n");
$conn->close();
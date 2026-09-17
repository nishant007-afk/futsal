#!/usr/bin/env php
<?php
/**
 * Idempotent schema migration runner.
 * Applies any CREATE TABLE IF NOT EXISTS statements that are not yet present.
 *
 * Usage:
 *   php tools/migrate.php            # create any missing tables
 *   php tools/system_check.php       # full audit (see README)
 *
 * Each migration is a standalone function guarded by table_exists() so it is
 * safe to re-run. Keep one migration per new table/major change.
 */
require_once __DIR__ . '/../config/db.php';

// CLI-only: never allow schema changes via HTTP (blocked in .htaccess too).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: run via CLI only (php tools/migrate.php).');
}

function table_exists(string $name): bool
{
    global $conn;
    $pattern = $conn->real_escape_string($name);
    $res = $conn->query("SHOW TABLES LIKE '" . $pattern . "'");
    return $res && $res->num_rows > 0;
}

$applied = 0;

if (!table_exists('favorites')) {
    $conn->query("CREATE TABLE favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        ground_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY favorites_user_ground (user_id, ground_id)
    ) ENGINE=InnoDB");
    $applied++;
    echo "Applied: added `favorites` table.\n";
}

// Backfill coordinates for seeded Kathmandu grounds so near-me sorting works.
$needsCoords = (int)$conn->query("SELECT COUNT(*) c FROM grounds WHERE latitude IS NULL OR longitude IS NULL")->fetch_assoc()['c'];
if ($needsCoords > 0) {
    $conn->query(
        "UPDATE grounds SET latitude = 27.7025, longitude = 85.3116 WHERE name = 'Downtown Futsal Arena' AND (latitude IS NULL OR longitude IS NULL)"
    );
    $conn->query(
        "UPDATE grounds SET latitude = 27.6719, longitude = 85.3124 WHERE name = 'Golden City Futsal' AND (latitude IS NULL OR longitude IS NULL)"
    );
    $conn->query(
        "UPDATE grounds SET latitude = 27.6817, longitude = 85.3253 WHERE name = 'Riverside Sports Hub' AND (latitude IS NULL OR longitude IS NULL)"
    );
    $conn->query(
        "UPDATE grounds SET latitude = 27.7033, longitude = 85.3146 WHERE name = 'Thamel Sports Complex' AND (latitude IS NULL OR longitude IS NULL)"
    );
    $conn->query(
        "UPDATE grounds SET latitude = 27.6645, longitude = 85.3190 WHERE name = 'Patan Futsal Dome' AND (latitude IS NULL OR longitude IS NULL)"
    );
    $conn->query(
        "UPDATE grounds SET latitude = 27.6590, longitude = 85.3200 WHERE name = 'Balkumari Arena' AND (latitude IS NULL OR longitude IS NULL)"
    );
    $conn->query(
        "UPDATE grounds SET latitude = 27.6800, longitude = 85.3350 WHERE name = 'Koteshwor Kickoff' AND (latitude IS NULL OR longitude IS NULL)"
    );
    $conn->query(
        "UPDATE grounds SET latitude = 27.7010, longitude = 85.3159 WHERE name = 'Bouddha Sports House' AND (latitude IS NULL OR longitude IS NULL)"
    );
    $conn->query(
        "UPDATE grounds SET latitude = 27.6820, longitude = 85.3180 WHERE name = 'Newar Street Court' AND (latitude IS NULL OR longitude IS NULL)"
    );
    $conn->query(
        "UPDATE grounds SET latitude = 27.6817, longitude = 85.3253 WHERE name = 'Baneshwor Dome 2' AND (latitude IS NULL OR longitude IS NULL)"
    );
    $conn->query(
        "UPDATE grounds SET latitude = 27.7106, longitude = 85.3118 WHERE name = 'Gyaneshwor Grid' AND (latitude IS NULL OR longitude IS NULL)"
    );
    $applied++;
    echo "Applied: backfilled coordinates for seeded Kathmandu grounds.\n";
}

// Add payment_method column for the pay-at-court flow.
if ((int)$conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'payment_method'")->fetch_assoc()['c'] === 0) {
    $conn->query("ALTER TABLE bookings ADD COLUMN payment_method ENUM('online','at_court','qr') NOT NULL DEFAULT 'online' AFTER payment_type");
    $applied++;
    echo "Applied: added `bookings.payment_method` column.\n";
}

// Add the `qr` value to the payments_method enum (QR-code / scan-to-pay flow).
$pmType = (string)($conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'payment_method'")->fetch_assoc()['COLUMN_TYPE'] ?? '');
if (strpos($pmType, "'qr'") === false) {
    $conn->query("ALTER TABLE bookings MODIFY COLUMN payment_method ENUM('online','at_court','qr') NOT NULL DEFAULT 'online' AFTER payment_type");
    $applied++;
    echo "Applied: added `qr` value to `bookings.payment_method` enum.\n";
}

// Add slug column to grounds for SEO-friendly URLs (sitemap + ground detail links).
if ((int)$conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grounds' AND COLUMN_NAME = 'slug'")->fetch_assoc()['c'] === 0) {
    $conn->query("ALTER TABLE grounds ADD COLUMN slug VARCHAR(120) NULL AFTER is_active");
    // Backfill unique slugs from ground names.
    $r = $conn->query("SELECT id, name FROM grounds WHERE slug IS NULL");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $base      = preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $row['name']));
            $base      = trim($base, '-');
            $candidate = $base;
            $i = 2;
            while ((int)$conn->query("SELECT COUNT(*) c FROM grounds WHERE slug = '" . $conn->real_escape_string($candidate) . "'")->fetch_assoc()['c'] > 0) {
                $candidate = $base . '-' . $i;
                $i++;
            }
            $candidate = $candidate !== '' ? $candidate : 'court-' . $row['id'];
            $conn->query("UPDATE grounds SET slug = '" . $conn->real_escape_string($candidate) . "' WHERE id = " . (int) $row['id']);
        }
    }
    $conn->query("ALTER TABLE grounds ADD UNIQUE KEY uq_grounds_slug (slug)");
    $applied++;
    echo "Applied: added `grounds.slug` column and backfilled slugs.\n";
}

// Add updated_at to grounds so sitemap can report fresh ground listings.
if ((int)$conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grounds' AND COLUMN_NAME = 'updated_at'")->fetch_assoc()['c'] === 0) {
    $conn->query("ALTER TABLE grounds ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at");
    $conn->query("UPDATE grounds SET updated_at = created_at");
    $applied++;
    echo "Applied: added `grounds.updated_at` column.\n";
}

// Add reminder_sent to bookings to track 24h reminder emails.
if ((int)$conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'reminder_sent'")->fetch_assoc()['c'] === 0) {
    $conn->query("ALTER TABLE bookings ADD COLUMN reminder_sent TINYINT(1) NOT NULL DEFAULT 0 AFTER paid_at");
    $applied++;
    echo "Applied: added `bookings.reminder_sent` column.\n";
}

// Add notification preferences to users.
if ((int)$conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'notify_email'")->fetch_assoc()['c'] === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN notify_email TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
    $conn->query("ALTER TABLE users ADD COLUMN notify_sms TINYINT(1) NOT NULL DEFAULT 0 AFTER notify_email");
    $applied++;
    echo "Applied: added `users.notify_email` and `users.notify_sms` columns.\n";
}

// Split email notifications into per-type preferences.
if ((int)$conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'notify_bookings'")->fetch_assoc()['c'] === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN notify_bookings TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_sms");
    $conn->query("ALTER TABLE users ADD COLUMN notify_promo TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_bookings");
    $conn->query("ALTER TABLE users ADD COLUMN notify_expiry TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_promo");
    $conn->query("UPDATE users SET notify_bookings = notify_email, notify_promo = notify_email, notify_expiry = notify_email");
    $applied++;
    echo "Applied: split `users.notify_email` into `notify_bookings`, `notify_promo`, `notify_expiry`.\n";
}

// Add helpful_count to reviews.
if ((int)$conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'helpful_count'")->fetch_assoc()['c'] === 0) {
    $conn->query("ALTER TABLE reviews ADD COLUMN helpful_count INT NOT NULL DEFAULT 0 AFTER comment");
    $applied++;
    echo "Applied: added `reviews.helpful_count` column.\n";
}

// Create review_votes table for helpful voting.
if (!table_exists('review_votes')) {
    $conn->query("CREATE TABLE review_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        review_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY review_votes_unique (review_id, user_id)
    ) ENGINE=InnoDB");
    $applied++;
    echo "Applied: added `review_votes` table.\n";
}

// Add payment QR for owner's payment account to grounds.
if ((int)$conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grounds' AND COLUMN_NAME = 'payment_qr'")->fetch_assoc()['c'] === 0) {
    $conn->query("ALTER TABLE grounds ADD COLUMN payment_qr VARCHAR(255) DEFAULT '' AFTER image");
    $applied++;
    echo "Applied: added `grounds.payment_qr` column.\n";
}

// Create slot_holds table for checkout concurrency protection (5-minute lease)
if (!table_exists('slot_holds')) {
    $conn->query("CREATE TABLE slot_holds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ground_id INT NOT NULL,
        booking_date DATE NOT NULL,
        start_time TIME NOT NULL,
        user_id INT NOT NULL,
        hold_token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_slot_hold (ground_id, booking_date, start_time),
        KEY idx_expires (expires_at),
        FOREIGN KEY (ground_id) REFERENCES grounds(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $applied++;
    echo "Applied: added `slot_holds` table for concurrency protection.\n";
}

// Create email_queue table for background asynchronous email delivery
if (!table_exists('email_queue')) {
    $conn->query("CREATE TABLE email_queue (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        recipient VARCHAR(150) NOT NULL,
        subject VARCHAR(200) NOT NULL,
        body_html LONGTEXT NOT NULL,
        status ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
        attempts TINYINT NOT NULL DEFAULT 0,
        max_attempts TINYINT NOT NULL DEFAULT 3,
        last_error TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at DATETIME NULL,
        KEY idx_status_created (status, created_at)
    ) ENGINE=InnoDB");
    $applied++;
    echo "Applied: added `email_queue` table for background processing.\n";
}

echo $applied . " migration(s) applied. Done.\n";

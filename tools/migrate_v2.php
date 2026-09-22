<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/db.php';
global $conn;

echo "Running v2 migrations...\n";

// Add missing indexes
$migrations = [
    // Index on bookings.user_id (frequently queried)
    "ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_bookings_user_id (user_id)",
    // Index on reviews.user_id
    "ALTER TABLE reviews ADD INDEX IF NOT EXISTS idx_reviews_user_id (user_id)",
    // Index on favorites.user_id
    "ALTER TABLE favorites ADD INDEX IF NOT EXISTS idx_favorites_user_id (user_id)",
    // Index on notifications.user_id for faster unread counts
    "ALTER TABLE notifications ADD INDEX IF NOT EXISTS idx_notifications_user_unread (user_id, is_read, created_at)",
];

foreach ($migrations as $sql) {
    try {
        $conn->query($sql);
        echo "OK: " . substr($sql, 0, 60) . "...\n";
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate') !== false || strpos($msg, 'already exists') !== false) {
            echo "SKIP (already exists): " . substr($sql, 0, 60) . "...\n";
        } else {
            echo "FAIL: " . substr($sql, 0, 60) . "... => $msg\n";
        }
    }
}

echo "Migration complete.\n";

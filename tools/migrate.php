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

echo $applied . " migration(s) applied. Done.\n";

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

echo $applied . " migration(s) applied. Done.\n";

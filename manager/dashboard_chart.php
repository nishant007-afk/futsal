<?php
/**
 * JSON endpoint for the manager dashboard booking-activity chart.
 *
 * Backs the 7 / 14 / 30 day toggle so switching range never reloads the page.
 * Same helper as the server-rendered first paint, so the two can't drift.
 *
 * GET manager/dashboard_chart.php?days=14
 *   -> { days, max, axis:[...], total_count, total_rev, points:[...] }
 */
require_once __DIR__ . '/../config/db.php';
require_manager();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$days = manager_chart_days($_GET['days'] ?? 7);
$data = manager_chart_data((int)$_SESSION['user_id'], $days);
$data['axis'] = manager_chart_axis((int)$data['max']);

echo json_encode([
    'ok' => true,
    'days' => $data['days'],
    'max' => $data['max'],
    'axis' => $data['axis'],
    'total_count' => $data['total_count'],
    'total_rev' => $data['total_rev'],
    'points' => $data['points'],
]);

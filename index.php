<?php
require_once __DIR__ . '/config/db.php';

$view = 'landing';

if (is_logged_in()) {
    $user = current_user();
    if ($user['role'] === 'manager') {
        redirect('manager/dashboard.php');
    }
    if ($user['role'] === 'admin') {
        redirect('admin/dashboard.php');
    }
    $view = 'player';
}

$page_title = $view === 'landing' ? 'Book Futsal Courts Online' : 'My Home';
$page_description = $view === 'landing'
    ? 'Book futsal courts online in Kathmandu, Nepal. Find a free court near you, pick your slot, and pay securely on GoalSpace.'
    : 'Your GoalSpace dashboard - view your upcoming bookings, browse courts, and manage your schedule.';

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/views/' . $view . '_home.php';
require __DIR__ . '/includes/footer.php';

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

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/views/' . $view . '_home.php';
require __DIR__ . '/includes/footer.php';

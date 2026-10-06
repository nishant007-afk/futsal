<?php
require 'config/db.php';
$tables = ['users', 'grounds', 'bookings', 'promo_codes', 'favorites'];
foreach ($tables as $t) {
    $idx = $conn->query('SHOW INDEX FROM ' . $t)->fetch_all(MYSQLI_ASSOC);
    echo $t . ': ' . count($idx) . ' indexes' . PHP_EOL;
}
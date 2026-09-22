<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

if (rate_limit_exceeded('groundsjson', 20, 60)) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare('SELECT id, name, location, price_per_hour, discount_price, latitude, longitude FROM grounds WHERE is_active = 1 AND latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY id');
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$out = [];
foreach ($rows as $g) {
    $price = (float)$g['discount_price'] > 0 && (float)$g['discount_price'] < (float)$g['price_per_hour']
        ? (float)$g['discount_price']
        : (float)$g['price_per_hour'];
    $out[] = [
        'id' => (int)$g['id'],
        'name' => $g['name'],
        'location' => $g['location'],
        'price' => $price,
        'lat' => (float)$g['latitude'],
        'lng' => (float)$g['longitude'],
        'url' => base_url('pages/ground.php?id=' . (int)$g['id']),
    ];
}
echo json_encode($out);

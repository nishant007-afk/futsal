<?php
require_once __DIR__ . '/../config/db.php';
global $conn;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !empty($_GET['x'])) {
    echo json_encode(['error' => 'invalid request']);
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') {
    echo json_encode(['results' => []]);
    exit;
}
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}

$like = '%' . $q . '%';
$prefix = $q . '%';
$stmt = $conn->prepare(
    'SELECT id, name, location, address, price_per_hour
     FROM grounds
     WHERE is_active = 1 AND (name LIKE ? OR location LIKE ? OR address LIKE ?)
     ORDER BY (name = ?) DESC, (name LIKE ?) DESC, name ASC
     LIMIT 5'
);
$stmt->bind_param('sssss', $like, $like, $like, $q, $prefix);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$results = [];
if ($rows) {
    $ids = array_column($rows, 'id');
    $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $imgStmt = $conn->prepare(
        'SELECT ground_id, image FROM ground_images WHERE ground_id IN (' . $idPlaceholders . ') ORDER BY id ASC'
    );
    $imgStmt->bind_param($types, ...$ids);
    $imgStmt->execute();
    $imgRows = $imgStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $imgStmt->close();

    $firstImage = [];
    foreach ($imgRows as $im) {
        if (!isset($firstImage[$im['ground_id']])) {
            $firstImage[$im['ground_id']] = $im['image'];
        }
    }

    foreach ($rows as $g) {
        $results[] = [
            'id' => (int)$g['id'],
            'name' => $g['name'],
            'location' => $g['location'],
            'price' => $g['price_per_hour'],
            'img' => $firstImage[$g['id']] ?? '',
        ];
    }
}

echo json_encode(['results' => $results]);
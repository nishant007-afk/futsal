<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !empty($_GET['x'])) {
    echo json_encode(['error' => 'invalid request']);
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' && !isset($_GET['lat'])) {
    echo json_encode(['results' => []]);
    exit;
}

function http_get_json(string $url): ?array
{
    // SSRF protection: only allow requests to known external geocoding APIs
    $host = parse_url($url, PHP_URL_HOST);
    $allowed = ['photon.komoot.io', 'nominatim.openstreetmap.org'];
    if (!$host || !in_array($host, $allowed, true)) {
        return null;
    }
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "User-Agent: GoalSpace/1.0 (futsal booking app; localhost)\r\n"
                . "Accept: application/json\r\n",
        ],
        // Keep TLS verification on to prevent MITM on the geocode proxy.
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

$url = 'https://photon.komoot.io/api/?q=' . rawurlencode($q) . '&limit=12&countrycode=NP';
$photon = http_get_json($url);

$nomUrl = 'https://nominatim.openstreetmap.org/search?format=jsonv2&countrycodes=NP&limit=5&q=' . rawurlencode($q);
$nom = http_get_json($nomUrl);

if (isset($_GET['lat']) && isset($_GET['lng'])) {
    $lat = (float)$_GET['lat'];
    $lng = (float)$_GET['lng'];
    $revUrl = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' . $lat . '&lon=' . $lng . '&zoom=18&addressdetails=1';
    $rev = http_get_json($revUrl);
    if ($rev && !empty($rev['display_name'])) {
        echo json_encode([
            'results' => [[
                'lat' => $lat,
                'lng' => $lng,
                'name' => $rev['name'] ?? $rev['display_name'],
                'sub' => $rev['display_name'],
                'src' => 'Map pin',
            ]],
            'address' => $rev['display_name'],
        ]);
        exit;
    }
    echo json_encode(['results' => []]);
    exit;
}

$results = [];
$seen = [];

if ($photon && !empty($photon['features'])) {
    foreach ($photon['features'] as $f) {
        $c = $f['geometry']['coordinates'] ?? null;
        if (!is_array($c) || count($c) < 2) {
            continue;
        }
        $p = $f['properties'] ?? [];
        $key = round((float)$c[1], 4) . ',' . round((float)$c[0], 4);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $results[] = [
            'lat' => $c[1],
            'lng' => $c[0],
            'name' => $p['name'] ?? $p['street'] ?? $p['housenumber'] ?? 'Unknown place',
            'sub' => implode(', ', array_filter([$p['street'] ?? '', $p['city'] ?? '', $p['county'] ?? '', $p['country'] ?? ''])),
            'src' => $p['osm_value'] ?? 'Place',
        ];
    }
}

if ($nom) {
    foreach ($nom as $n) {
        $key = round((float)$n['lat'], 4) . ',' . round((float)$n['lon'], 4);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $results[] = [
            'lat' => $n['lat'],
            'lng' => $n['lon'],
            'name' => $n['name'] ?? $n['display_name'] ?? 'Unknown place',
            'sub' => $n['display_name'] ?? '',
            'src' => 'Address',
        ];
    }
}

echo json_encode(['results' => array_slice($results, 0, 15)]);

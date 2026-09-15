<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$base = rtrim(base_url(), '/');
$last = date('c');

$urls = [];

$urls[] = ['loc' => absolute_url('/'), 'changefreq' => 'daily', 'priority' => '1.0'];
$urls[] = ['loc' => absolute_url('pages/courts.php'), 'changefreq' => 'daily', 'priority' => '0.9'];
$urls[] = ['loc' => absolute_url('pages/favorites.php'), 'changefreq' => 'weekly', 'priority' => '0.5'];
$urls[] = ['loc' => absolute_url('pages/my_bookings.php'), 'changefreq' => 'weekly', 'priority' => '0.6'];
$urls[] = ['loc' => absolute_url('pages/login.php'), 'changefreq' => 'monthly', 'priority' => '0.4'];
$urls[] = ['loc' => absolute_url('pages/register.php'), 'changefreq' => 'monthly', 'priority' => '0.4'];
$urls[] = ['loc' => absolute_url('pages/page.php?slug=contact'), 'changefreq' => 'monthly', 'priority' => '0.5'];
$legal = legal_pages_defaults();
foreach ($legal as $slug => $info) {
    $urls[] = ['loc' => absolute_url('pages/page.php?slug=' . $slug), 'changefreq' => 'monthly', 'priority' => '0.3'];
}

$res = $conn->query('SELECT id, slug, updated_at FROM grounds WHERE is_active = 1 ORDER BY id');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $urls[] = [
            'loc'        => absolute_url('pages/ground.php?id=' . (int) $row['id'] . '&slug=' . $row['slug']),
            'lastmod'    => isset($row['updated_at']) ? substr($row['updated_at'], 0, 10) : '',
            'changefreq' => 'weekly',
            'priority'   => '0.8',
        ];
    }
}

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    $xml .= '  <url>' . "\n";
    $xml .= '    <loc>' . e($u['loc']) . '</loc>' . "\n";
    if (!empty($u['lastmod'])) {
        $xml .= '    <lastmod>' . e($u['lastmod']) . '</lastmod>' . "\n";
    }
    $xml .= '    <changefreq>' . e($u['changefreq']) . '</changefreq>' . "\n";
    $xml .= '    <priority>' . e((string) $u['priority']) . '</priority>' . "\n";
    $xml .= '  </url>' . "\n";
}
$xml .= '</urlset>';

header('Content-Type: application/xml; charset=utf-8');
echo $xml;

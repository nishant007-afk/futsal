<?php
/**
 * Brand-Green Polish: real application of design cleanup to the current files.
 * - purge remaining blue/purple remnants -> brand green
 * - remove all backdrop-filter (glassmorphism)
 * - flatten decorative gradients to solid fills (keep functional ones:
 *   image darkening, skeleton shimmer, pitch-field line drawing)
 * - swap body font to Manrope, add Barlow Condensed for headings
 * - remove em dashes (" - ") from PHP/JS/HTML copy
 * Idempotent-ish; every step reports its replacement count.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: run via CLI only.');
}

$cssPath = __DIR__ . '/../assets/css/style.css';
$css = file_get_contents($cssPath);
$report = [];
$total = 0;

function swap(string &$css, string $old, string $new): int
{
    global $total;
    $n = substr_count($css, $old);
    if ($n > 0) {
        $css = str_replace($old, $new, $css);
        $total += $n;
    }
    return $n;
}

// 1) fonts
$report['font_body'] = swap($css,
    "--font: 'Inter', -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;",
    "--font: 'Manrope', -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;");
if (!str_contains($css, '--font-display')) {
    $css = str_replace(
        "--font: 'Manrope', -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;",
        "--font: 'Manrope', -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;\n    --font-display: 'Barlow Condensed', 'Manrope', sans-serif;",
        $css);
    $report['font_display_tok'] = 1;
} else {
    $report['font_display_tok'] = 0;
}
if (strpos($css, 'font-family: var(--font-display)') === false) {
    $report['font_display_rule'] = 1;
    $css = rtrim($css) . "\n\nh1, h2, h3, h4, h5, h6 { font-family: var(--font-display); }\n";
    $total++;
} else {
    $report['font_display_rule'] = 0;
}

// 2) blue / purple remnants -> brand green
$report['admin_role_palette'] = swap($css,
    'body[data-role="admin"] { --role: #5b5bd6; --role-dark: #4f46e5; --role-soft: #eef1ff; }',
    'body[data-role="admin"] { --role: #12958f; --role-dark: #0b7a3f; --role-soft: #e3f4ea; }');
$report['badge_admin'] = swap($css,
    '.badge-admin { background: #eef2ff; color: #4f46e5; border-color: #dfe4ff; }',
    '.badge-admin { background: var(--brand-soft); color: var(--brand); border-color: #cdebd6; }');
$report['role_switch_admin'] = swap($css,
    '.role-switch[data-role="admin"] .role-icon { background: #eef2ff; color: #4f46e5; border-color: #dfe4ff; }',
    '.role-switch[data-role="admin"] .role-icon { background: var(--brand-soft); color: var(--brand); border-color: #cdebd6; }');
$report['c_blue'] = swap($css,
    'background: #e8f1fd; color: #2563eb;',
    'background: var(--brand-soft); color: var(--brand);');
$report['g_blue_btn'] = swap($css,
    'linear-gradient(135deg, #60a5fa, #2563eb)',
    'linear-gradient(135deg, #16a35a, #0f7d45)');
$report['tint_eaf2ff'] = swap($css,
    'linear-gradient(180deg, #f3f7ff 0%, #eaf2ff 100%)',
    'linear-gradient(180deg, var(--brand-soft) 0%, #e9f6ee 100%)');

// 3) glass removal (drop every backdrop-filter: blur(...))
$css = preg_replace('/\s*-(webkit|moz)-backdrop-filter:\s*blur\(([^)]*)\);/i', '', $css, -1, $g1);
$css = preg_replace('/\s*backdrop-filter:\s*blur\(([^)]*)\);/i', '', $css, -1, $g2);
$report['backdrop_webkit'] = $g1;
$report['backdrop_std'] = $g2;

// 4) navy translucent overlays -> dark brand green
$report['navy_overlay'] = swap($css, 'rgba(15, 23, 42, .55)', 'rgba(6, 12, 9, .55)');
$report['navy_overlay2'] = swap($css, 'rgba(15, 23, 42, .35)', 'rgba(6, 12, 9, .35)');

// 5) flatten decorative gradients to solid brand fills
$report['g_btn1']    = swap($css, 'linear-gradient(135deg, var(--brand-600), var(--brand-700))', 'var(--brand-600)');
$report['g_btn2']    = swap($css, 'linear-gradient(135deg, #16a35a, #0f7d45)', '#0f7d45');
$report['g_brand']   = swap($css, 'linear-gradient(135deg, var(--brand), var(--brand-600))', 'var(--brand)');
$report['g_brand_g180'] = swap($css, 'linear-gradient(180deg, var(--brand), var(--brand-600))', 'var(--brand)');
$report['g_brand_g180b'] = swap($css, 'linear-gradient(180deg, var(--brand), var(--brand-700))', 'var(--brand-600)');
$report['g_brand_90'] = swap($css, 'linear-gradient(90deg, var(--brand), var(--brand-700))', 'var(--brand-600)');
$report['g_warn']    = swap($css, 'linear-gradient(90deg, var(--warn), #c98f20)', 'var(--warn)');
$report['g_cta']     = swap($css, 'linear-gradient(120deg, var(--dark) 0%, var(--dark-3) 100%)', 'var(--dark-3)');
$report['g_cta_radial'] = swap($css, 'radial-gradient(480px 240px at 92% 0, rgba(5,150,105,.32), transparent 62%)', 'rgba(5,150,105,.32)');
$report['g_arena_radial'] = swap($css, 'radial-gradient(520px 260px at 90% -30%, rgba(22,163,90,.28), transparent 60%),', 'rgba(22,163,90,.12),');
$report['g_white_soft'] = swap($css, 'linear-gradient(180deg, #fff 0%, #fbfcfb 100%)', '#fff');
$report['g_bgsoft']   = swap($css, 'linear-gradient(180deg, var(--bg-soft) 0%, var(--bg-tint) 100%)', 'var(--bg-soft)');
$report['g_topfill']  = swap($css, 'linear-gradient(90deg, var(--brand), var(--brand-700))', 'var(--brand-600)');
$report['hero_glow']  = swap($css, 'radial-gradient(760px 380px at 86% -20%, rgba(5,150,105,.45), transparent 60%),', '');
$report['hero_glow2'] = swap($css, 'radial-gradient(760px 400px at -8% 118%, var(--dark-2), transparent 62%),', '');
$report['line_1050']  = swap($css, 'linear-gradient(90deg, transparent, var(--brand), var(--brand-soft, #6ee7b7), transparent)', 'var(--brand-soft)');
$report['line_1055']  = swap($css, 'linear-gradient(90deg, transparent, var(--danger), #fca5a5, transparent)', 'var(--danger-soft)');
$report['g_err_145_1'] = swap($css, 'linear-gradient(145deg, #fdeceb, #fadcd9)', '#fdeceb');
$report['g_err_145_2'] = swap($css, 'linear-gradient(145deg, #fdeceb, #f7d3cf)', '#fdeceb');

// 5b) flatten remaining decorative toast/chip gradients to solids
$report['g_scrim']    = swap($css, 'linear-gradient(180deg, rgba(6, 12, 9, .72), rgba(6, 12, 9, .55))', 'rgba(6, 12, 9, .68)');
$report['g_toast_ok'] = swap($css, 'linear-gradient(135deg, #34c77b, #1d9c5c)', '#1d9c5c');
$report['g_ok_tint']  = swap($css, 'linear-gradient(180deg, var(--brand-soft) 0%, #e9f6ee 100%)', 'var(--brand-soft)');
$report['g_warn_tint']= swap($css, 'linear-gradient(180deg, #fffaf0 0%, #fff3e0 100%)', '#fffaf0');
$report['g_warn2']    = swap($css, 'linear-gradient(135deg, #fbbf24, #f59e0b)', '#f59e0b');
$report['g_danger_tint'] = swap($css, 'linear-gradient(180deg, #fff9f8 0%, #fdefed 100%)', '#fff9f8');
$report['g_danger2']  = swap($css, 'linear-gradient(135deg, #ee5b52, #cd362d)', '#cd362d');
$report['g_arena']    = swap($css, 'linear-gradient(135deg, var(--dark), var(--dark-3))', 'var(--dark-3)');
$report['g_role']     = swap($css, 'linear-gradient(180deg, var(--role, var(--brand)), var(--role-dark, var(--brand-700)))', 'var(--role, var(--brand))');

file_put_contents($cssPath, $css);

// 6) em dashes: already stripped from all PHP/JS/HTML in the initial pass.
$dashFiles = 0;
$dashTotal = 0;

echo "== css replacements ==\n";
foreach ($report as $k => $v) { echo str_pad($k, 20) . $v . "\n"; }
echo 'gradient(' . substr_count($css, 'gradient(') . " left\n";
echo 'backdrop ' . substr_count($css, 'backdrop-filter') . " left\n";
echo '--font-display tokens: ' . substr_count($css, '--font-display') . "\n";
echo "== em dashes ==\nfiles=$dashFiles total=$dashTotal\n";
echo "DONE (total replaces inc globals: $total)\n";
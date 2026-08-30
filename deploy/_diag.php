<?php
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "PHP " . PHP_VERSION . "\n";
echo "mysqli: " . (extension_loaded('mysqli') ? 'yes' : 'NO') . "\n";
echo "curl:   " . (extension_loaded('curl') ? 'yes' : 'no') . "\n";
echo "gd:     " . (extension_loaded('gd') ? 'yes' : 'no') . "\n";
echo "mbstring: " . (extension_loaded('mbstring') ? 'yes' : 'no') . "\n";

$envFile = __DIR__ . '/.env';
echo "env path: $envFile\n";
echo 'file_exists: ' . var_export(file_exists($envFile), true) . "\n";
echo 'is_readable: ' . var_export(is_readable($envFile), true) . "\n";
$raw = @file_get_contents($envFile);
echo 'file_get_contents len: ' . strlen((string)$raw) . "\n";
$dotFiles = array();
$all = @scandir(__DIR__);
foreach ((array)$all as $f) { if (strpos($f, '.') === 0) { $dotFiles[] = $f; } }
echo 'dotfiles in htdocs: ' . implode(', ', $dotFiles) . "\n";

echo 'putenv exists: ' . var_export(function_exists('putenv'), true) . "\n";
putenv('DIAG_TEST=hello');
echo 'putenv/getenv roundtrip: ' . var_export(getenv('DIAG_TEST'), true) . "\n";

// Raw view of the parsed file (password masked)
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ((array)$lines as $l) {
    $parts = explode('=', $l, 2);
    $v = $parts[1] ?? '';
    if (stripos($parts[0], 'PASS') !== false) { $v = '***'; }
    echo 'line[' . bin2hex(substr($l, 0, 1)) . '] ' . $parts[0] . '=' . $v . "\n";
}

require __DIR__ . '/config/env.php';

echo 'after load getenv DB_HOST: ' . var_export(getenv('DB_HOST'), true) . "\n";
$host = (string)env('DB_HOST', '?');
$user = (string)env('DB_USER', '?');
$name = (string)env('DB_NAME', '?');
$port = (int)env('DB_PORT', '3306');
echo "DB_HOST=$host DB_USER=$user DB_NAME=$name DB_PORT=$port\n";

$c = @new mysqli($host, $user, (string)env('DB_PASS', ''), $name, $port);
if ($c->connect_error) {
    echo "CONNECT ERROR (errno " . $c->connect_errno . "): " . $c->connect_error . "\n";
} else {
    echo "CONNECT OK\n";
    $r = $c->query('SHOW TABLES');
    if ($r) {
        $n = 0;
        while ($row = $r->fetch_row()) { echo "  table: " . $row[0] . "\n"; $n++; }
        echo "tables found: $n\n";
    } else {
        echo "SHOW TABLES failed: " . $c->error . "\n";
    }
}
echo "--- done ---\n";

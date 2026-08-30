<?php
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/config/env.php';

$conn = new mysqli(
    (string)env('DB_HOST', 'localhost'),
    (string)env('DB_USER', 'root'),
    (string)env('DB_PASS', ''),
    (string)env('DB_NAME', ''),
    (int)env('DB_PORT', '3306')
);
if ($conn->connect_error) {
    die('CONNECT ERROR: ' . $conn->connect_error);
}

$sql = @file_get_contents(__DIR__ . '/database_infinityfree.sql');
if ($sql === false) {
    die('cannot read database_infinityfree.sql');
}

$ok = true;
if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
}
if ($conn->errno) {
    $ok = false;
    echo 'QUERY ERROR (errno ' . $conn->errno . '): ' . $conn->error . "\n";
}

echo $ok ? "IMPORT DONE\n" : "IMPORT FINISHED WITH ERRORS\n";

$r = $conn->query('SHOW TABLES');
$n = 0;
if ($r) {
    while ($row = $r->fetch_row()) {
        echo '  table: ' . $row[0] . "\n";
        $n++;
    }
} else {
    echo 'SHOW TABLES failed: ' . $conn->error . "\n";
}
echo "tables: $n\n";
echo "--- done ---\n";

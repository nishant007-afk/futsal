<?php
header('Content-Type: text/plain; charset=utf-8');
foreach (['_base_probe.php', '_setup_db.php'] as $f) {
    $p = __DIR__ . '/' . $f;
    if (is_file($p)) {
        echo unlink($p) ? "deleted $f\n" : "FAILED $f\n";
    } else {
        echo "not found: $f\n";
    }
}
@unlink(__FILE__);
echo "done\n";

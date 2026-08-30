<?php
header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/config/env.php';
require __DIR__ . '/includes/functions.php';

echo 'DOCUMENT_ROOT=' . ($_SERVER['DOCUMENT_ROOT'] ?? '?') . "\n";
echo 'SCRIPT_NAME=' . ($_SERVER['SCRIPT_NAME'] ?? '?') . "\n";
echo 'appdir=' . str_replace('\\', '/', (string)realpath(dirname(__DIR__))) . "\n";
echo 'BASE_PATH env=' . var_export(env('BASE_PATH'), true) . "\n";
echo 'base_url(pages/login.php)=' . base_url('pages/login.php') . "\n";
echo 'base_url()=' . base_url('') . "\n";
echo 'absolute_url(pages/login.php)=' . absolute_url('pages/login.php') . "\n";
echo '--- done ---' . "\n";

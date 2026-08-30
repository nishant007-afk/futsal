<?php

if (defined('GOALSPACE_ENV_LOADED')) {
    return;
}
define('GOALSPACE_ENV_LOADED', true);

function _goalspace_load_env(): void
{
    $file = dirname(__DIR__) . '/.env';
    if (!is_file($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        $parts = explode('=', $line, 2);
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === '') {
            continue;
        }
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false) {
        // Some hosts (e.g. InfinityFree/LiteSpeed) ignore putenv(), so getenv()
        // never sees values set in-process. Fall back to $_ENV, which
        // _goalspace_load_env() populates directly as it parses the file.
        $value = $_ENV[$key] ?? null;
    }
    return ($value === false || $value === null) ? $default : $value;
}

_goalspace_load_env();

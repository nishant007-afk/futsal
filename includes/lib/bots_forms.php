<?php

/**
 * Renders a hidden honeypot field for bot trapping. Include inside <form>.
 */
function honeypot_field(): void
{
    echo '<div style="position:absolute;left:-9999px;top:-9999px" aria-hidden="true">'
        . '<label for="website_url">Leave this empty</label>'
        . '<input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off"></div>';
}

/**
 * Returns true if the honeypot field was filled (likely a bot).
 */
function is_honeypot_filled(): bool
{
    return !empty($_POST['website_url']);
}

/**
 * Returns true if the current request's User-Agent matches known bad bots,
 * scrapers, or vulnerability scanners.
 * Does NOT block legitimate crawlers (Googlebot, Bingbot, etc.).
 */
function is_bot_ua(): bool
{
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') {
        // No UA at all is a strong bot signal (every real browser sends one)
        return true;
    }
    // Known bad bots, scanners, scrapers
    $bad = [
        'sqlmap', 'nikto', 'masscan', 'zgrab', 'nmap', 'nessus',
        'openvas', 'acunetix', 'burpsuite', 'w3af', 'nuclei',
        'python-requests', 'python-urllib', 'libwww-perl', 'lwp-trivial',
        'curl/', 'go-http-client', 'java/', 'jakarta',
        'scrapy', 'wget/', 'mechanize', 'httpclient',
        'dirbuster', 'gobuster', 'feroxbuster', 'wfuzz', 'ffuf',
        'zgrab', 'masscan', 'zgrab2', 'headlesschrome',
        'phantomjs', 'slimerjs', 'selenium', 'puppeteer',
        'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot',
        'petalbot', 'bytespider', 'gptbot', 'ccbot',
        'claudebot', 'anthropic-ai', 'cohere-ai', 'facebookbot',
        'twitterbot', 'discordbot', 'rogerbot', 'exabot',
        'sistrix', 'seokicks', 'blexbot', 'istellabot',
    ];
    foreach ($bad as $sig) {
        if (str_contains($ua, $sig)) {
            return true;
        }
    }
    return false;
}

/**
 * Hard-block the worst offending bots immediately (scanners, exploit tools).
 * Call this in db.php AFTER session_start() and functions are loaded.
 * Legitimate search crawlers (GET-only, read-only) are never blocked here.
 */
function block_bots(): void
{
    // Only act on POST requests  -  search crawlers never POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $scanners = [
        'sqlmap', 'nikto', 'masscan', 'zgrab', 'nessus', 'openvas',
        'acunetix', 'burpsuite', 'w3af', 'nuclei', 'dirbuster', 'gobuster',
        'feroxbuster', 'wfuzz', 'ffuf', 'python-requests', 'libwww-perl',
        'scrapy', 'mechanize',
    ];
    foreach ($scanners as $sig) {
        if (str_contains($ua, $sig)) {
            error_log('[SECURITY] Bot UA blocked on POST: ' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . ' IP=' . ($_SERVER['REMOTE_ADDR'] ?? ''));
            http_response_code(403);
            exit('Forbidden');
        }
    }
    // Block empty UA on all POST endpoints
    if ($ua === '') {
        error_log('[SECURITY] Empty UA blocked on POST IP=' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        http_response_code(403);
        exit('Forbidden');
    }
}

/**
 * Renders the Google sign-in SVG icon.
 */
function google_svg_icon(): void
{
    echo '<svg class="g-icon" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.1 18.9 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.1 5.7l6.2 5.2C36.9 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z"/></svg>';
}

function form_errors(): array
{
    $e = $_SESSION['form_errors'] ?? [];
    unset($_SESSION['form_errors']);
    return is_array($e) ? $e : [];
}

function form_old(): array
{
    $o = $_SESSION['form_old'] ?? [];
    unset($_SESSION['form_old']);
    return is_array($o) ? $o : [];
}

function old_value(array $old, string $field, string $default = ''): string
{
    return isset($old[$field]) ? (string)$old[$field] : $default;
}

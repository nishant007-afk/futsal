<?php

/**
 * Login attempt throttling.
 * Tracks failures per identifier (email) + IP.
 * Lockout base: 60s after 5 fails, +30s per extra failure (max 10min).
 * After 10+ fails the identifier is marked suspended.
 */
function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

function login_attempt_key(string $email): string
{
    // Include IP to prevent brute-force from single IP across many emails
    return strtolower(trim($email)) . '|' . client_ip();
}

function login_lock_seconds(int $failures): int
{
    if ($failures < 5) {
        return 0;
    }
    $extra = $failures - 5;
    return min(600, 60 + $extra * 30);
}

function get_login_attempt(string $key): ?array
{
    global $conn;
    $stmt = $conn->prepare('SELECT attempts, locked_until, suspended FROM login_attempts WHERE identifier = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }
    if ($row['locked_until'] && strtotime($row['locked_until']) <= time()) {
        $stmt = $conn->prepare('UPDATE login_attempts SET locked_until = NULL WHERE identifier = ?');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row['locked_until'] = null;
    }
    return $row;
}

function record_login_failure(string $key): void
{
    global $conn;
    $stmt = $conn->prepare('INSERT INTO login_attempts (identifier, attempts, locked_until) VALUES (?, 1, NULL) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt_at = NOW()');
    $stmt->bind_param('s', $key);
    $stmt->execute();

    $row = get_login_attempt($key);
    $failures = (int)($row['attempts'] ?? 0);
    if ($failures >= 5) {
        $lockUntil = date('Y-m-d H:i:s', time() + login_lock_seconds($failures));
        $stmt = $conn->prepare('UPDATE login_attempts SET locked_until = ? WHERE identifier = ?');
        $stmt->bind_param('ss', $lockUntil, $key);
        $stmt->execute();
    }
}

function login_attempt_state(string $key): array
{
    $row = get_login_attempt($key);
    $failures = (int)($row['attempts'] ?? 0);
    $state = [
        'failures' => $failures,
        'locked' => false,
        'suspended' => !empty($row['suspended']),
        'lock_seconds' => 0,
        'lock_ends' => null,
    ];
    if (!empty($row['suspended']) || $failures > 10) {
        if (!$row['suspended'] && $failures > 10) {
            suspend_login_identifier($key);
        }
        $state['suspended'] = true;
        $state['locked'] = true;
        return $state;
    }
    if ($failures >= 5) {
        $lockUntil = $row['locked_until'] ? strtotime($row['locked_until']) : time() + login_lock_seconds($failures);
        $remaining = max(0, $lockUntil - time());
        $state['locked'] = true;
        $state['lock_seconds'] = $remaining;
        $state['lock_ends'] = date('c', $lockUntil);
    }
    return $state;
}

function clear_login_attempts(string $key): void
{
    global $conn;
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE identifier = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
}

function suspend_login_identifier(string $key): void
{
    global $conn;
    $stmt = $conn->prepare('UPDATE login_attempts SET suspended = 1, locked_until = NULL WHERE identifier = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
}

/**
 * Generic IP-based rate limiting using login_attempts table.
 * @param string $prefix  Namespace prefix (e.g. 'reg', 'emailchk')
 * @param int    $max     Max attempts allowed in the window
 * @param int    $window  Window in seconds
 * @return bool true if rate limit exceeded
 */
function rate_limit_exceeded(string $prefix, int $max, int $window): bool
{
    global $conn;
    $key = $prefix . '|' . client_ip();
    $stmt = $conn->prepare('SELECT attempts, last_attempt_at FROM login_attempts WHERE identifier = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $last = strtotime($row['last_attempt_at']);
        if (time() - $last > $window) {
            $stmt = $conn->prepare('UPDATE login_attempts SET attempts = 1, last_attempt_at = NOW() WHERE identifier = ?');
            $stmt->bind_param('s', $key);
            $stmt->execute();
            return false;
        }
        if ((int)$row['attempts'] >= $max) {
            return true;
        }
        $stmt = $conn->prepare('UPDATE login_attempts SET attempts = attempts + 1, last_attempt_at = NOW() WHERE identifier = ?');
        $stmt->bind_param('s', $key);
        $stmt->execute();
    } else {
        $ip = client_ip();
        $stmt = $conn->prepare('INSERT INTO login_attempts (identifier, ip, attempts, last_attempt_at) VALUES (?, ?, 1, NOW())');
        $stmt->bind_param('ss', $key, $ip);
        $stmt->execute();
    }
    return false;
}

function login_attempts_email(string $key): string
{
    return explode('|', $key)[0];
}

function issue_otp(string $identifier, string $purpose, int $ttlSeconds = 300): string
{
    global $conn;
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare('UPDATE otps SET used = 1 WHERE identifier = ? AND purpose = ? AND used = 0');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $stmt = $conn->prepare('INSERT INTO otps (identifier, purpose, code, attempts, expires_at) VALUES (?, ?, ?, 0, ?)');
    $stmt->bind_param('ssss', $identifier, $purpose, $code, $expires);
    $stmt->execute();
    return $code;
}

function verify_otp(string $identifier, string $purpose, string $code): bool
{
    global $conn;
    $stmt = $conn->prepare('SELECT id, code, expires_at, used, attempts FROM otps WHERE identifier = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return false;
    }
    if (strtotime($row['expires_at']) < time()) {
        $stmt = $conn->prepare('UPDATE otps SET used = 1 WHERE id = ?');
        $stmt->bind_param('i', $row['id']);
        $stmt->execute();
        return false;
    }
    if ((int)$row['attempts'] >= 5) {
        return false;
    }
    if (!hash_equals($row['code'], $code)) {
        $stmt = $conn->prepare('UPDATE otps SET attempts = attempts + 1 WHERE id = ?');
        $stmt->bind_param('i', $row['id']);
        $stmt->execute();
        return false;
    }
    $stmt = $conn->prepare('UPDATE otps SET used = 1 WHERE id = ?');
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();
    return true;
}

function otp_attempts_left(string $identifier, string $purpose): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT attempts FROM otps WHERE identifier = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return 5;
    }
    return max(0, 5 - (int)$row['attempts']);
}

function otp_remaining_seconds(string $identifier, string $purpose): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT expires_at FROM otps WHERE identifier = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return 0;
    }
    return max(0, strtotime($row['expires_at']) - time());
}

function otp_send_cooldown(string $identifier, string $purpose, int $cooldown = 30): int
{
    global $conn;
    // No bypass list: all addresses share the same cooldown + hourly limit.
    $stmt = $conn->prepare('SELECT created_at FROM otps WHERE identifier = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $wait = 0;
    if ($row) {
        $wait = max($wait, $cooldown - (time() - strtotime($row['created_at'])));
    }
    $wait = max($wait, otp_send_hour_limit($identifier, $purpose));
    return max(0, $wait);
}

function otp_send_hour_limit(string $identifier, string $purpose, int $maxPerHour = 5): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) AS c, MIN(created_at) AS oldest FROM otps WHERE identifier = ? AND purpose = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ((int)$row['c'] < $maxPerHour) {
        return 0;
    }
    return max(0, (strtotime($row['oldest']) + 3600) - time());
}

function format_otp_wait(int $seconds): string
{
    if ($seconds >= 3600) {
        $hours = intdiv($seconds, 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's');
    }
    if ($seconds >= 60) {
        $minutes = intdiv($seconds, 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }
    return $seconds . 's';
}

function otp_subject(string $purpose): string
{
    switch ($purpose) {
        case 'email_verify':
            return 'Confirm your email address';
        case 'password_reset':
            return 'Reset your password';
        case 'login':
            return 'Your GoalSpace sign-in code';
        case 'password_change':
            return 'Your GoalSpace security code';
        case 'email_change':
            return 'Confirm your new email';
        case 'delete_account':
            return 'Confirm account deletion';
        default:
            return 'Your GoalSpace security code';
    }
}

function send_otp_mail(string $email, string $code, string $purpose): bool
{
    global $conn;
    // Personalize the greeting when the email belongs to an existing account.
    $firstName = '';
    if (!empty($conn) && $email !== '') {
        $stmt = $conn->prepare('SELECT name FROM users WHERE email = ?');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row && !empty($row['name'])) {
                $firstName = preg_split('/\s+/', trim($row['name']))[0] ?? '';
            }
        }
    }
    $greeting = $firstName !== '' ? 'Hi ' . $firstName . ',' : 'Hello,';

    $title = otp_subject($purpose);
    $actionVerb = otp_purpose_verb($purpose);

    $bodyHtml = '
    <p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.6;">
        Here is your 6-digit verification code to ' . htmlspecialchars($actionVerb, ENT_QUOTES, 'UTF-8') . ':
    </p>
    
    <div style="margin:20px 0;text-align:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:18px 12px;">
        <span style="font-family:\'SF Pro Mono\',Consolas,Menlo,Monaco,monospace;font-size:36px;font-weight:800;letter-spacing:8px;color:#0f172a;display:inline-block;padding-left:8px;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</span>
    </div>

    <p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">
        This code is valid for 5 minutes. If you did not request this, you can safely ignore this email.
    </p>';

    $footerNote = '';
    $htmlContent = goalspace_email_html($title, $greeting, $bodyHtml, $footerNote, 'Your GoalSpace verification code is ' . $code);

    return send_mail($email, $title, $htmlContent, true);
}

function otp_purpose_verb(string $purpose): string
{
    switch ($purpose) {
        case 'email_verify':
            return 'verify your email address';
        case 'password_reset':
            return 'reset your password';
        case 'login':
            return 'sign in to your account';
        case 'password_change':
            return 'change your password';
        case 'email_change':
            return 'verify your new email address';
        case 'delete_account':
            return 'delete your GoalSpace account';
        default:
            return 'continue with what you were doing';
    }
}

/**
 * Permanently delete a user account (self-serve, OTP-confirmed).
 * Grounds owned by the user are hidden so orphaned courts don't keep
 * accepting bookings; identifier-keyed rows are removed explicitly;
 * everything else cascades via the schema's ON DELETE CASCADE.
 */
function delete_user_account(int $user_id, string $email): bool
{
    global $conn;
    // A manager leaving the platform shouldn't leave live, unowned courts.
    $stmt = $conn->prepare('UPDATE grounds SET is_active = 0 WHERE manager_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    // Clean up identifier-keyed tables (no FK to users).
    $key = login_attempt_key($email);
    $stmt = $conn->prepare('DELETE FROM otps WHERE identifier = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE identifier = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt = $conn->prepare('DELETE FROM contact_messages WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    return $stmt->execute() && $stmt->affected_rows > 0;
}

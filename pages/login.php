<?php
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    // e.g. Google sign-in popup: the opener (this page) reloads after the
    // session is created - hand the player back to their saved state.
    consume_return_path();
    redirect('index.php');
}

$email = '';
$errors = [];
$lock = null;
$suspended = false;
$suspendedEmail = '';
$failuresLeft = null;
$errorTitle = 'Login failed';


if (isset($_SESSION['login_lock']) && is_array($_SESSION['login_lock'])) {
    $ll = $_SESSION['login_lock'];
    $remaining = max(0, $ll['ends'] - time());
    if ($remaining > 0) {
        $lock = [
            'seconds' => $remaining,
            'ends' => date('c', $ll['ends']),
            'failures' => (int)($ll['failures'] ?? 0),
        ];
        $email = $_SESSION['login_lock_email'] ?? $email;
        $errors['general'] = 'You\'ve had too many failed attempts. Try again in ' . gmdate('i:s', $remaining) . '.';
    } else {
        unset($_SESSION['login_lock'], $_SESSION['login_lock_email']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' && $password === '') {
        $errors['email'] = 'Enter your email address.';
        $errors['password'] = 'Enter your password.';
    } elseif ($email === '') {
        $errors['email'] = 'Enter your email address.';
    } elseif ($password === '') {
        $errors['password'] = 'Enter your password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address, e.g. you@example.com.';
    } else {
        $key = login_attempt_key($email);
        $state = login_attempt_state($key);

        if ($state['suspended']) {
            $suspended = true;
            $suspendedEmail = login_attempts_email($key);
        } elseif (rate_limit_exceeded('login_ip', 20, 300)) {
            // IP-only rate limit: blocks bots cycling many different emails from one IP.
            // 20 attempts per 5 minutes per IP, regardless of email used.
            $errors['general'] = 'Too many login attempts from your location. Please wait a few minutes and try again.';
        } elseif (is_honeypot_filled()) {
            // Honeypot: bots fill this, silently reject
            $errors['general'] = 'Login failed. Please try again.';
        } else {
            $stmt = $conn->prepare('SELECT id, name, password, role, email_verified, login_count FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            $ok = $user && password_verify($password, $user['password']);

            if ($ok && (int)$user['email_verified'] === 1) {
                // Increment login count for legitimate login
                $newCount = (int)($user['login_count'] ?? 0) + 1;
                $updStmt = $conn->prepare('UPDATE users SET login_count = ? WHERE id = ?');
                $updStmt->bind_param('ii', $newCount, $user['id']);
                $updStmt->execute();

                // Clear login failures on successful password authentication
                clear_login_attempts($key);

                // --- 2FA CHECK: Admin always requires 2FA; non-admin periodic check (every 6th login: 6, 12, 18...) ---
                $requires_2fa = ($user['role'] === 'admin');
                $needs_otp_verify = false;

                if ($requires_2fa) {
                    // Admin: always require OTP verification
                    $needs_otp_verify = true;
                } elseif ($newCount > 1 && $newCount % 6 === 0) {
                    // Non-admin: OTP every 6 logins
                    $needs_otp_verify = true;
                }

                if ($needs_otp_verify) {
                    $otp = issue_otp($email, 'login');
                    $otp_sent = send_otp_mail($email, $otp, 'login');
                    if (!$otp_sent) {
                        error_log('OTP email delivery failed for purpose: login');
                    }
                    session_regenerate_id(true);
                    // Never store the OTP in session – verification uses the DB row only.
                    $_SESSION['pending_2fa_login'] = ['user_id' => (int)$user['id'], 'email' => $email, 'role' => $user['role']];
                    redirect('pages/otp_verify.php');
                }

                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                // Back to the court/date/time the player picked before logging in.
                consume_return_path();
                if ($user['role'] === 'admin') {
                    redirect('admin/dashboard.php');
                } elseif ($user['role'] === 'manager') {
                    redirect('manager/dashboard.php');
                } else {
                    redirect('index.php');
                }
            }

            if (!$ok) {
                record_login_failure($key);
            }
            $after = login_attempt_state($key);

            if ($after['suspended']) {
                $suspended = true;
                $suspendedEmail = login_attempts_email($key);
            } elseif ($after['locked']) {
                $lock = [
                    'seconds' => $after['lock_seconds'],
                    'ends' => $after['lock_ends'],
                    'failures' => $after['failures'],
                ];
                $_SESSION['login_lock'] = ['ends' => $after['lock_ends'] ? strtotime($after['lock_ends']) : (time() + $after['lock_seconds']), 'failures' => $after['failures']];
                $_SESSION['login_lock_email'] = $email;
                $errors['general'] = 'Too many failed attempts. Try again in ' . gmdate('i:s', $after['lock_seconds']) . '.';
            } elseif (!$ok) {
                $failuresLeft = 5 - $after['failures'];
                $errorTitle = 'Incorrect password';
                $errors['general'] = 'Attempts remaining: ' . max(0, $failuresLeft);
            } else {
                $errors['general'] = 'Please verify your email before logging in. Check your inbox for the verification link.';
            }
        }
    }
}

$page_title = 'Log In';
$page_description = 'Log in to your GoalSpace account to manage bookings, view open slots, and get back on the court.';
require __DIR__ . '/../includes/header.php';
$active_auth_tab = 'login';
$loginErrors = $errors;
$loginEmail = $email;
require __DIR__ . '/../includes/views/auth_split.php';

require __DIR__ . '/../includes/footer.php';
?>

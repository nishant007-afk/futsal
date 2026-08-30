<?php
require_once __DIR__ . '/../config/db.php';

$oauth = $_SESSION['google_oauth'] ?? null;
$state = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';

$backLogin = 'pages/login.php';
$backSignup = 'pages/register.php';

$popup = is_array($oauth) && !empty($oauth['popup']);

$finish = function ($path) use ($popup) {
    if ($popup) {
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>GoalSpace</title></head><body><script>(function(){try{var o=window.opener;if(o){o.location.reload();}}catch(e){}window.close();})();</script></body></html>';
        exit;
    }
    redirect($path);
};

if (!$oauth || $state === '' || !hash_equals((string)$oauth['state'], (string)$state)) {
    set_flash_error(
        'Google sign-in expired or was tampered with.',
        'The security check did not pass.',
        'Please try signing in with Google again.',
        $backLogin
    );
    $finish($backLogin);
}

$intent = $oauth['intent'] === 'signup' ? 'signup' : 'login';
$role = in_array($oauth['role'] ?? '', ['user', 'manager'], true) ? $oauth['role'] : 'user';
$back = $intent === 'signup' ? $backSignup : $backLogin;

if (isset($_GET['error'])) {
    set_flash_error(
        'Google sign-in was cancelled.',
        'Google did not complete the sign-in.',
        'Try again, or use the form below instead.',
        $back
    );
    $finish($back);
}

$clientId = env('GOOGLE_CLIENT_ID');
$clientSecret = env('GOOGLE_CLIENT_SECRET');
$redirectUri = env('GOOGLE_REDIRECT_URI');

if (!$clientId || !$clientSecret || !$redirectUri) {
    set_flash_error('Google sign-in is not configured yet.', null, null, $back);
    $finish($back);
}

$token = [];
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $code,
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code',
    ]),
    CURLOPT_TIMEOUT => 20,
]);
$raw = curl_exec($ch);
$curlErr = curl_error($ch);
curl_close($ch);
if ($raw !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $token = $decoded;
    }
}

if ($curlErr !== '' || empty($token['access_token'])) {
    set_flash_error(
        'Could not connect to Google.',
        'Google did not return an access token.',
        'Please try again in a moment.',
        $back
    );
    $finish($back);
}

$me = [];
$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token['access_token']],
    CURLOPT_TIMEOUT        => 20,
]);
$raw = curl_exec($ch);
curl_close($ch);
if ($raw !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $me = $decoded;
    }
}

$email = strtolower(trim($me['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash_error(
        'Google did not return a valid email address.',
        'We could not read your Google account email.',
        'Try again, or use the form below instead.',
        $back
    );
    $finish($back);
}

$name = trim($me['name'] ?? '');
if ($name === '') {
    $name = explode('@', $email)[0];
}

$stmt = $conn->prepare('SELECT id, name, avatar FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    $uid = (int)$existing['id'];
    $greeting = $existing['name'];

    // Bring the Google profile picture in as the avatar (only when the
    // account doesn't have one of its own yet).
    if (empty($existing['avatar']) && !empty($me['picture'])) {
        $avatar = save_google_avatar($me['picture'], $uid);
        if ($avatar !== '') {
            $stmt = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ?');
            $stmt->bind_param('si', $avatar, $uid);
            $stmt->execute();
        }
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $uid;
    unset($_SESSION['google_oauth']);
    set_flash('success', 'Signed in with Google. Welcome back, ' . $greeting . '!');

    $returnPath = $_SESSION['return_path'] ?? '';
    unset($_SESSION['return_path']);
    if ($returnPath === 'pages/page.php?slug=contact') {
        $finish($returnPath);
    }

    $finish('index.php');
}

// Brand-new Google account: ask for role + consent on a card before creating it.
$_SESSION['google_pending'] = [
    'name'    => $name,
    'email'   => $email,
    'picture' => $me['picture'] ?? '',
    'role'    => $role,
    'back'    => $back,
    'popup'   => $popup,
];
unset($_SESSION['google_oauth']);

$setupUrl = base_url('pages/google_setup.php');
if ($popup) {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>GoalSpace</title></head><body><script>(function(){try{var o=window.opener;if(o){o.location.href=' . json_encode($setupUrl) . ';}}catch(e){}window.close();})();</script></body></html>';
    exit;
}
redirect('pages/google_setup.php');

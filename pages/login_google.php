<?php
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect('index.php');
}

$clientId = env('GOOGLE_CLIENT_ID');
$clientSecret = env('GOOGLE_CLIENT_SECRET');
$redirectUri = env('GOOGLE_REDIRECT_URI');

if (!$clientId || !$clientSecret || !$redirectUri) {
    set_flash_error(
        'Google sign-in is not configured yet.',
        'Add your Google OAuth credentials to the .env file.',
        'Fill in GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET, then try again.',
        'pages/login.php'
    );
    redirect('pages/login.php');
}

$intent = ($_GET['intent'] ?? '') === 'signup' ? 'signup' : 'login';
$role = in_array($_GET['role'] ?? '', ['user', 'manager'], true) ? $_GET['role'] : 'user';
$popup = ($_GET['popup'] ?? '') === '1';

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth'] = ['state' => $state, 'intent' => $intent, 'role' => $role, 'popup' => $popup];

$params = http_build_query([
    'client_id'     => $clientId,
    'redirect_uri'  => $redirectUri,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'prompt'        => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;

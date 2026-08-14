<?php

if (!function_exists('env')) {
    require_once __DIR__ . '/env.php';
}

define('ESEWA_MODE', env('ESEWA_MODE', 'test')); // 'test' | 'live'
define('ESEWA_PRODUCT_CODE', env('ESEWA_PRODUCT_CODE', 'EPAYTEST'));
define('ESEWA_SECRET_KEY', env('ESEWA_SECRET_KEY', '8gBm/:&EnhH.1/q'));

// Test eSewa user credentials (for testing the checkout on the eSewa side).
define('ESEWA_TEST_EPAY_ID', env('ESEWA_TEST_EPAY_ID', '9711111111'));
define('ESEWA_TEST_PASSWORD', env('ESEWA_TEST_PASSWORD', 'Nepal@123'));
define('ESEWA_TEST_MPIN', env('ESEWA_TEST_MPIN', '1122'));

function esewa_form_url(): string
{
    return ESEWA_MODE === 'live'
        ? 'https://esewa.com.np/api/epay/main/v2/form'
        : 'https://rc-epay.esewa.com.np/api/epay/main/v2/form';
}

function esewa_status_url(): string
{
    return ESEWA_MODE === 'live'
        ? 'https://esewa.com.np/api/epay/transaction/status/'
        : 'https://rc-epay.esewa.com.np/api/epay/transaction/status/';
}

function esewa_success_url(int $booking_id): string
{
    return absolute_url('pages/esewa_callback.php?result=success&booking_id=' . $booking_id);
}

function esewa_failure_url(int $booking_id): string
{
    return absolute_url('pages/esewa_callback.php?result=failure&booking_id=' . $booking_id);
}

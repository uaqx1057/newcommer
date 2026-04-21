<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'reviews-lib.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'site-mailer.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'bookings-lib.php';

$isCli = PHP_SAPI === 'cli';
$token = nc_get_env_value('BOOKING_REMINDER_TOKEN', '');

if (!$isCli) {
    $providedToken = trim((string) ($_GET['token'] ?? ($_SERVER['HTTP_X_REMINDER_TOKEN'] ?? '')));
    $hasValidToken = $token !== '' && hash_equals($token, $providedToken);
    $hasAdminSession = reviews_admin_is_authenticated();

    if (!$hasValidToken && !$hasAdminSession) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Not authorized to run reminders.'
        ]);
        exit;
    }
}

$smtpConfig = nc_get_smtp_config();
$missingConfig = nc_validate_smtp_config($smtpConfig);
if (!empty($missingConfig)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'SMTP is not configured. Missing: ' . implode(', ', $missingConfig) . '.'
    ]);
    exit;
}

$adminEmail = nc_get_env_value('ADMIN_EMAIL', 'humatahir1@gmail.com');
$supportEmail = nc_get_env_value('SUPPORT_EMAIL', (string) ($smtpConfig['from_email'] ?? 'info@codewithusman.com'));
$siteUrl = nc_normalize_public_url(nc_get_env_value('SITE_URL', 'https://newcomerconnect.ca'), 'https://newcomerconnect.ca');

if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Admin email is invalid.'
    ]);
    exit;
}

if (!filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
    $supportEmail = (string) ($smtpConfig['from_email'] ?? 'info@codewithusman.com');
}

$result = bookings_process_due_reminders($smtpConfig, [
    'adminEmail' => $adminEmail,
    'supportEmail' => $supportEmail,
    'siteUrl' => $siteUrl
]);

if ($isCli) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(empty($result['errors']) ? 0 : 1);
}

if (empty($result['success']) && !empty($result['errors'])) {
    http_response_code(500);
}

echo json_encode([
    'success' => empty($result['errors']),
    'result' => $result
]);
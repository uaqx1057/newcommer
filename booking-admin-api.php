<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'reviews-lib.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'site-mailer.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'bookings-lib.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = '';

if ($method === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
} elseif ($method === 'GET') {
    $action = trim((string) ($_GET['action'] ?? ''));
}

if ($action === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing action.'
    ]);
    exit;
}

if ($action === 'login') {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
        exit;
    }

    if (!reviews_admin_attempt_login($username, $password)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid login credentials.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Login successful.']);
    exit;
}

if ($action === 'logout') {
    reviews_admin_logout();
    echo json_encode(['success' => true, 'message' => 'Logged out.']);
    exit;
}

if (!reviews_admin_is_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

if ($action === 'list') {
    $payload = bookings_prepare_dashboard_payload(bookings_load_all());
    $payload['success'] = true;
    echo json_encode($payload);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if ($action === 'status') {
    $bookingId = trim((string) ($_POST['id'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? ''));

    if ($bookingId === '' || $status === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Booking id and status are required.']);
        exit;
    }

    $records = bookings_load_all();
    $update = bookings_update_status($records, $bookingId, $status);
    if (empty($update['success'])) {
        http_response_code(422);
        echo json_encode($update);
        exit;
    }

    if (!bookings_write_all($records)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not update booking status due to file write error.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Booking marked as ' . bookings_status_label($status) . '.',
        'record' => $update['record']
    ]);
    exit;
}

if ($action === 'send-reminders') {
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

    $adminEmail = nc_get_env_value('ADMIN_EMAIL', 'info@newcomerconnect.ca');
    $supportEmail = nc_get_env_value('SUPPORT_EMAIL', (string) ($smtpConfig['from_email'] ?? 'info@newcomerconnect.ca'));
    $siteUrl = nc_normalize_public_url(nc_get_env_value('SITE_URL', 'https://newcomerconnect.ca'), 'https://newcomerconnect.ca');

    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Admin email is invalid.']);
        exit;
    }

    if (!filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
        $supportEmail = (string) ($smtpConfig['from_email'] ?? 'info@newcomerconnect.ca');
    }

    $result = bookings_process_due_reminders($smtpConfig, [
        'adminEmail' => $adminEmail,
        'supportEmail' => $supportEmail,
        'siteUrl' => $siteUrl
    ]);

    if (empty($result['success']) && !empty($result['errors'])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Reminder processing completed with errors.',
            'result' => $result
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Reminder processing completed.',
        'result' => $result
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
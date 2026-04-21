<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'reviews-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

// Honeypot trap. Use a non-obvious field name to avoid browser autofill false positives.
$honeypot = trim((string)($_POST['review_reference_code'] ?? ''));
if ($honeypot !== '') {
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for sharing your feedback.'
    ]);
    exit;
}

$clientIp = reviews_get_client_ip();
$build = reviews_build_record_from_post($_POST, $clientIp);

if (!empty($build['errors'])) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $build['errors'][0]
    ]);
    exit;
}

$pending = reviews_load_list('reviews-pending.json');
$cooldownSeconds = reviews_is_local_ip($clientIp) ? 0 : 900;
if ($cooldownSeconds > 0 && reviews_is_rate_limited($pending, $clientIp, $cooldownSeconds)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Please wait a few minutes before submitting another review.'
    ]);
    exit;
}

$ok = reviews_append_record('reviews-pending.json', $build['record']);
if (!$ok) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not save your review right now. Please try again.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Thank you. Your review has been received and is pending approval.'
]);

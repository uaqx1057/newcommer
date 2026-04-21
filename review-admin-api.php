<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'reviews-lib.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = '';

if ($method === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
} elseif ($method === 'GET') {
    $action = trim((string)($_GET['action'] ?? ''));
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

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

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
    $pending = reviews_load_list('reviews-pending.json');
    $approved = reviews_load_list('reviews-approved.json');

    usort($pending, function ($a, $b) {
        return strtotime((string)($b['created_at'] ?? '1970-01-01')) <=> strtotime((string)($a['created_at'] ?? '1970-01-01'));
    });
    usort($approved, function ($a, $b) {
        return strtotime((string)($b['published_at'] ?? ($b['updated_at'] ?? '1970-01-01'))) <=> strtotime((string)($a['published_at'] ?? ($a['updated_at'] ?? '1970-01-01')));
    });

    echo json_encode([
        'success' => true,
        'pending' => $pending,
        'approved' => array_slice($approved, 0, 50)
    ]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$reviewId = trim((string)($_POST['id'] ?? ''));
if ($reviewId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Review id is required.']);
    exit;
}

$pending = reviews_load_list('reviews-pending.json');
$approved = reviews_load_list('reviews-approved.json');
$rejected = reviews_load_list('reviews-rejected.json');

$targetIndex = -1;
$targetReview = null;
foreach ($pending as $i => $review) {
    if (($review['id'] ?? '') === $reviewId) {
        $targetIndex = (int)$i;
        $targetReview = $review;
        break;
    }
}

if ($targetIndex < 0 || !is_array($targetReview)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Review not found in pending queue.']);
    exit;
}

array_splice($pending, $targetIndex, 1);
$now = reviews_now_iso();

if ($action === 'approve') {
    $targetReview['status'] = 'approved';
    $targetReview['published_at'] = $now;
    $targetReview['updated_at'] = $now;
    $approved[] = $targetReview;

    $okPending = reviews_write_list('reviews-pending.json', $pending);
    $okApproved = reviews_write_list('reviews-approved.json', $approved);

    if (!$okPending || !$okApproved) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not approve review due to file write error.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Review approved.']);
    exit;
}

if ($action === 'reject') {
    $targetReview['status'] = 'rejected';
    $targetReview['rejected_at'] = $now;
    $targetReview['updated_at'] = $now;
    $rejected[] = $targetReview;

    $okPending = reviews_write_list('reviews-pending.json', $pending);
    $okRejected = reviews_write_list('reviews-rejected.json', $rejected);

    if (!$okPending || !$okRejected) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not reject review due to file write error.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Review rejected.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unsupported action.']);

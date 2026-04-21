<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'reviews-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if ($limit <= 0) {
    $limit = 20;
}
if ($limit > 60) {
    $limit = 60;
}

$approved = reviews_load_list('reviews-approved.json');

usort($approved, function ($a, $b) {
    $aTime = strtotime((string)($a['published_at'] ?? ($a['updated_at'] ?? '1970-01-01')));
    $bTime = strtotime((string)($b['published_at'] ?? ($b['updated_at'] ?? '1970-01-01')));
    return $bTime <=> $aTime;
});

$approved = array_slice($approved, 0, $limit);
$publicReviews = array_map('reviews_get_public_payload', $approved);

echo json_encode([
    'success' => true,
    'count' => count($publicReviews),
    'reviews' => $publicReviews
]);

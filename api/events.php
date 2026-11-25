<?php
// GET /api/events.php?q=text&category=ID
// Returns published, upcoming events as JSON (used by the live search).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/events_lib.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_logged_in()) {
    json_out(401, ['error' => 'Please log in first.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_out(405, ['error' => 'Method not allowed.']);
}

$user = current_user();

$q = (isset($_GET['q']) && is_string($_GET['q'])) ? trim($_GET['q']) : '';
if (strlen($q) > 100) {
    json_out(400, ['error' => 'Search text is too long (max 100 characters).']);
}

$categoryId = filter_var($_GET['category'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($categoryId === false) {
    json_out(400, ['error' => 'Invalid category.']);
}

$rows = fetch_public_events($pdo, $user['id'], $q, $categoryId);

$events = array_map(function ($ev) {
    $left         = max(0, (int)$ev['capacity'] - (int)$ev['taken']);
    $isRegistered = in_array($ev['my_status'], ['registered', 'attended'], true);
    return [
        'id'            => (int)$ev['id'],
        'title'         => $ev['title'],
        'description'   => $ev['description'],
        'category'      => $ev['category'],
        'venue'         => $ev['venue'],
        'date_label'    => date('D, d M Y', strtotime($ev['event_date'])),
        'time_label'    => substr($ev['start_time'], 0, 5) . ' - ' . substr($ev['end_time'], 0, 5),
        'capacity'      => (int)$ev['capacity'],
        'taken'         => (int)$ev['taken'],
        'seats_left'    => $left,
        'is_registered' => $isRegistered,
        'can_register'  => !$isRegistered && $left > 0,
    ];
}, $rows);

json_out(200, ['count' => count($events), 'events' => $events]);

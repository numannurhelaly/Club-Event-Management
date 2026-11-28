<?php
// Registrations API (for the logged-in user)
//   GET    /api/registrations.php                 my registrations
//   POST   /api/registrations.php                 body {"event_id": 5}  -> register
//   DELETE /api/registrations.php?event_id=5      cancel my registration
// POST and DELETE need the X-CSRF-Token header.
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/events_lib.php';

allow_methods(['GET', 'POST', 'DELETE']);
$method = $_SERVER['REQUEST_METHOD'];
$user   = api_require_login();

try {

    if ($method === 'GET') {
        $stmt = $pdo->prepare(
            "SELECT r.status AS registration_status, r.registered_at,
                    e.id AS event_id, e.title, e.venue, e.event_date, e.start_time, e.end_time,
                    e.status AS event_status
             FROM registrations r
             JOIN events e ON e.id = r.event_id
             WHERE r.user_id = ?
             ORDER BY e.event_date DESC, e.start_time DESC"
        );
        $stmt->execute([$user['id']]);
        $today = date('Y-m-d');
        $items = array_map(function ($r) use ($today) {
            return [
                'event_id'            => (int)$r['event_id'],
                'title'               => $r['title'],
                'venue'               => $r['venue'],
                'event_date'          => $r['event_date'],
                'start_time'          => substr($r['start_time'], 0, 5),
                'end_time'            => substr($r['end_time'], 0, 5),
                'event_status'        => $r['event_status'],
                'registration_status' => $r['registration_status'],
                'registered_at'       => $r['registered_at'],
                'can_cancel'          => $r['registration_status'] === 'registered'
                                         && $r['event_status'] === 'published'
                                         && $r['event_date'] >= $today,
            ];
        }, $stmt->fetchAll());
        json_out(200, ['count' => count($items), 'registrations' => $items]);
    }

    api_csrf_check();

    if ($method === 'POST') {
        $body    = api_json_body();
        $eventId = api_int($body['event_id'] ?? null);
        if ($eventId === null) {
            api_error(422, 'Validation failed.', ['event_id' => 'event_id must be a positive whole number.']);
        }
        $message = register_user_for_event($pdo, $eventId, (int)$user['id']);
        $ev = fetch_event_detail($pdo, $eventId);
        json_out(201, [
            'message'    => $message,
            'event_id'   => $eventId,
            'seats_left' => max(0, (int)$ev['capacity'] - (int)$ev['taken']),
        ]);
    }

    // DELETE
    $eventId = api_int($_GET['event_id'] ?? null);
    if ($eventId === null) {
        api_error(400, 'event_id is required (?event_id=...).');
    }
    $message = cancel_user_registration($pdo, $eventId, (int)$user['id']);
    json_out(200, ['message' => $message, 'event_id' => $eventId]);

} catch (RuntimeException $ex) {
    $code = in_array($ex->getCode(), [400, 404, 409], true) ? $ex->getCode() : 400;
    api_error($code, $ex->getMessage());
} catch (PDOException $ex) {
    api_error(500, 'Something went wrong. Please try again.');
}

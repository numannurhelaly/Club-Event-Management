<?php
// Events API
//   GET    /api/events.php                      published, upcoming events (?q=, ?category=)
//   GET    /api/events.php?scope=manage         events you manage (admin: all, organizer: own)
//   GET    /api/events.php?id=5                 one event with its schedule
//   POST   /api/events.php                      create event          (admin, organizer)
//   PUT    /api/events.php?id=5                 update event (only the fields you send)
//   DELETE /api/events.php?id=5                 delete event
// POST, PUT and DELETE need the X-CSRF-Token header.
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/events_lib.php';

allow_methods(['GET', 'POST', 'PUT', 'DELETE']);
$method = $_SERVER['REQUEST_METHOD'];
$user   = api_require_login();

$id = null;
if (isset($_GET['id'])) {
    $id = api_int($_GET['id']);
    if ($id === null) {
        api_error(400, 'Invalid event id.');
    }
}

function event_json(array $r): array {
    return [
        'id'          => (int)$r['id'],
        'title'       => $r['title'],
        'description' => $r['description'],
        'category_id' => $r['category_id'] !== null ? (int)$r['category_id'] : null,
        'category'    => $r['category'],
        'venue'       => $r['venue'],
        'event_date'  => $r['event_date'],
        'start_time'  => substr($r['start_time'], 0, 5),
        'end_time'    => substr($r['end_time'], 0, 5),
        'capacity'    => (int)$r['capacity'],
        'taken'       => (int)$r['taken'],
        'seats_left'  => max(0, (int)$r['capacity'] - (int)$r['taken']),
        'status'      => $r['status'],
        'organizer'   => $r['organizer'],
        'created_by'  => (int)$r['created_by'],
    ];
}

function manageable_or_fail(PDO $pdo, int $id, array $user): array {
    $ev = fetch_event_detail($pdo, $id);
    if (!$ev) {
        api_error(404, 'Event not found.');
    }
    if ($user['role'] !== 'admin' && (int)$ev['created_by'] !== (int)$user['id']) {
        api_error(403, 'This is not your event.');
    }
    return $ev;
}

try {

    if ($method === 'GET') {

        if ($id !== null) {
            $ev = fetch_event_detail($pdo, $id);
            $canSee = $ev && ($ev['status'] === 'published'
                || $user['role'] === 'admin' || (int)$ev['created_by'] === (int)$user['id']);
            if (!$canSee) {
                api_error(404, 'Event not found.');   // 404 hides whether an unpublished event exists
            }
            $stmt = $pdo->prepare(
                'SELECT p.id, p.title, p.start_time, p.end_time, p.notes, p.performer_name, u.name AS member_name
                 FROM performances p LEFT JOIN users u ON u.id = p.performer_id
                 WHERE p.event_id = ? ORDER BY p.start_time'
            );
            $stmt->execute([$id]);
            $schedule = array_map(function ($p) {
                return [
                    'id'         => (int)$p['id'],
                    'title'      => $p['title'],
                    'performer'  => $p['member_name'] ?? $p['performer_name'],
                    'start_time' => substr($p['start_time'], 0, 5),
                    'end_time'   => substr($p['end_time'], 0, 5),
                    'notes'      => $p['notes'],
                ];
            }, $stmt->fetchAll());
            $out = event_json($ev);
            $out['schedule'] = $schedule;
            json_out(200, ['event' => $out]);
        }

        if (($_GET['scope'] ?? '') === 'manage') {
            api_require_role(['admin', 'organizer']);
            $sql = "SELECT e.*, c.name AS category, u.name AS organizer,
                           (SELECT COUNT(*) FROM registrations r
                             WHERE r.event_id = e.id AND r.status IN ('registered','attended')) AS taken
                    FROM events e
                    LEFT JOIN categories c ON c.id = e.category_id
                    JOIN users u ON u.id = e.created_by";
            $params = [];
            if ($user['role'] !== 'admin') {
                $sql .= ' WHERE e.created_by = ?';
                $params[] = $user['id'];
            }
            $sql .= ' ORDER BY e.event_date DESC, e.start_time DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $events = array_map('event_json', $stmt->fetchAll());
            json_out(200, ['count' => count($events), 'events' => $events]);
        }

        // Public list: published, upcoming events (used by the live search).
        $q = (isset($_GET['q']) && is_string($_GET['q'])) ? trim($_GET['q']) : '';
        if (mb_strlen($q) > 100) {
            api_error(400, 'Search text is too long (max 100 characters).');
        }
        $categoryId = filter_var($_GET['category'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($categoryId === false) {
            api_error(400, 'Invalid category.');
        }
        $rows = fetch_public_events($pdo, (int)$user['id'], $q, $categoryId);
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
    }

    // ---- Write requests: POST, PUT, DELETE ----
    api_require_role(['admin', 'organizer']);
    api_csrf_check();

    if ($method === 'POST') {
        if ($id !== null) {
            api_error(400, 'Do not send an id when creating an event.');
        }
        $body = api_json_body();
        $body['status']   = $body['status']   ?? 'draft';
        $body['capacity'] = $body['capacity'] ?? 50;

        [$clean, $errors] = validate_event_input($pdo, $body, false);
        if ($errors) {
            api_error(422, 'Validation failed.', $errors);
        }
        $pdo->prepare(
            'INSERT INTO events (title, description, category_id, venue, event_date,
                                 start_time, end_time, capacity, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $clean['title'], $clean['description'], $clean['category_id'], $clean['venue'],
            $clean['event_date'], $clean['start_time'], $clean['end_time'], $clean['capacity'],
            $clean['status'], $user['id'],
        ]);
        $newId = (int)$pdo->lastInsertId();
        header('Location: events.php?id=' . $newId);
        json_out(201, ['event' => event_json(fetch_event_detail($pdo, $newId))]);
    }

    if ($id === null) {
        api_error(400, 'Event id is required (?id=...).');
    }
    $existing = manageable_or_fail($pdo, $id, $user);

    if ($method === 'PUT') {
        $body  = api_json_body();
        $input = [
            'title' => $existing['title'], 'description' => $existing['description'],
            'category_id' => $existing['category_id'], 'venue' => $existing['venue'],
            'event_date' => $existing['event_date'],
            'start_time' => substr($existing['start_time'], 0, 5),
            'end_time' => substr($existing['end_time'], 0, 5),
            'capacity' => (int)$existing['capacity'], 'status' => $existing['status'],
        ];
        foreach (array_keys($input) as $field) {      // only known fields are taken from the body
            if (array_key_exists($field, $body)) {
                $input[$field] = $body[$field];
            }
        }
        [$clean, $errors] = validate_event_input($pdo, $input, true, $id);
        if ($errors) {
            api_error(422, 'Validation failed.', $errors);
        }
        $pdo->prepare(
            'UPDATE events SET title = ?, description = ?, category_id = ?, venue = ?,
                    event_date = ?, start_time = ?, end_time = ?, capacity = ?, status = ?
             WHERE id = ?'
        )->execute([
            $clean['title'], $clean['description'], $clean['category_id'], $clean['venue'],
            $clean['event_date'], $clean['start_time'], $clean['end_time'], $clean['capacity'],
            $clean['status'], $id,
        ]);
        json_out(200, ['event' => event_json(fetch_event_detail($pdo, $id))]);
    }

    // DELETE
    $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
    json_out(204);

} catch (PDOException $ex) {
    api_error(500, 'Something went wrong. Please try again.');
}

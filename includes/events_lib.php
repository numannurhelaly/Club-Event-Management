<?php
// Shared event queries, checks and business rules (used by pages and by the API).

// Published, upcoming events with seat count and this user's registration status.
function fetch_public_events(PDO $pdo, int $userId, string $q = '', int $categoryId = 0): array {
    $sql = "SELECT e.id, e.title, e.description, e.venue, e.event_date, e.start_time,
                   e.end_time, e.capacity, c.name AS category,
                   (SELECT COUNT(*) FROM registrations r
                     WHERE r.event_id = e.id AND r.status IN ('registered','attended')) AS taken,
                   (SELECT r2.status FROM registrations r2
                     WHERE r2.event_id = e.id AND r2.user_id = ?) AS my_status
            FROM events e
            LEFT JOIN categories c ON c.id = e.category_id
            WHERE e.status = 'published' AND e.event_date >= ?";
    $params = [$userId, date('Y-m-d')];

    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $sql .= ' AND (e.title LIKE ? OR e.venue LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }
    if ($categoryId > 0) {
        $sql .= ' AND e.category_id = ?';
        $params[] = $categoryId;
    }
    $sql .= ' ORDER BY e.event_date, e.start_time';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Returns the event if this user may manage it (admin, or the organizer who created it).
// Otherwise stops the request with 404 / 403.
function get_manageable_event(PDO $pdo, int $eventId, array $user): array {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        http_response_code(404);
        exit('Event not found.');
    }
    if ($user['role'] !== 'admin' && (int)$event['created_by'] !== (int)$user['id']) {
        http_response_code(403);
        exit('403 Forbidden: this is not your event.');
    }
    return $event;
}

// One event with category name, organizer name and number of taken seats (or null).
function fetch_event_detail(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare(
        "SELECT e.*, c.name AS category, u.name AS organizer,
                (SELECT COUNT(*) FROM registrations r
                  WHERE r.event_id = e.id AND r.status IN ('registered','attended')) AS taken
         FROM events e
         LEFT JOIN categories c ON c.id = e.category_id
         JOIN users u ON u.id = e.created_by
         WHERE e.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Validates event fields from a form or from JSON.
// Returns [cleanData, errors] where errors is a field => message map (empty when valid).
function validate_event_input(PDO $pdo, array $in, bool $editing, int $eventId = 0): array {
    $errors = [];
    $str = function ($key) use ($in) {
        return (isset($in[$key]) && is_string($in[$key])) ? trim($in[$key]) : '';
    };

    $title = $str('title');
    if ($title === '' || mb_strlen($title) > 150) {
        $errors['title'] = 'title is required (up to 150 characters).';
    }
    $venue = $str('venue');
    if ($venue === '' || mb_strlen($venue) > 150) {
        $errors['venue'] = 'venue is required (up to 150 characters).';
    }
    $description = $str('description');
    if (mb_strlen($description) > 5000) {
        $errors['description'] = 'description is too long (max 5000 characters).';
    }

    $date = $str('event_date');
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        $errors['event_date'] = 'event_date must be a valid date (YYYY-MM-DD).';
    } elseif (!$editing && $date < date('Y-m-d')) {
        $errors['event_date'] = "event_date can't be in the past.";
    }

    $start = $str('start_time');
    $end   = $str('end_time');
    $s = DateTime::createFromFormat('H:i', $start);
    $t = DateTime::createFromFormat('H:i', $end);
    if (!$s || $s->format('H:i') !== $start) {
        $errors['start_time'] = 'start_time must be a valid time (HH:MM).';
    }
    if (!$t || $t->format('H:i') !== $end) {
        $errors['end_time'] = 'end_time must be a valid time (HH:MM).';
    }
    if (!isset($errors['start_time']) && !isset($errors['end_time']) && $end <= $start) {
        $errors['end_time'] = 'end_time must be after start_time.';
    }

    $capRaw   = $in['capacity'] ?? null;
    $capacity = false;
    if (is_int($capRaw)) {
        $capacity = $capRaw;
    } elseif (is_string($capRaw) && preg_match('/^\d{1,5}$/', $capRaw)) {
        $capacity = (int)$capRaw;
    }
    if ($capacity === false || $capacity < 1 || $capacity > 5000) {
        $errors['capacity'] = 'capacity must be a whole number between 1 and 5000.';
        $capacity = null;
    } elseif ($editing) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status IN ('registered','attended')"
        );
        $stmt->execute([$eventId]);
        $taken = (int)$stmt->fetchColumn();
        if ($capacity < $taken) {
            $errors['capacity'] = "capacity can't be lower than the $taken seats already taken.";
        }
    }

    $status = $str('status');
    if (!in_array($status, ['draft', 'published', 'cancelled', 'completed'], true)) {
        $errors['status'] = 'status must be draft, published, cancelled or completed.';
    }

    $categoryId = null;
    $catRaw = $in['category_id'] ?? null;
    if ($catRaw !== null && $catRaw !== '') {
        if (is_int($catRaw) || (is_string($catRaw) && ctype_digit($catRaw))) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
            $stmt->execute([(int)$catRaw]);
            if ($stmt->fetch()) {
                $categoryId = (int)$catRaw;
            } else {
                $errors['category_id'] = 'category_id does not exist.';
            }
        } else {
            $errors['category_id'] = 'category_id must be a number or null.';
        }
    }

    return [[
        'title'       => $title,
        'description' => $description !== '' ? $description : null,
        'category_id' => $categoryId,
        'venue'       => $venue,
        'event_date'  => $date,
        'start_time'  => $start,
        'end_time'    => $end,
        'capacity'    => $capacity,
        'status'      => $status,
    ], $errors];
}

// Registers a user for an event. Returns a success message.
// Throws RuntimeException(message, httpCode) for expected problems (404 / 409).
function register_user_for_event(PDO $pdo, int $eventId, int $userId): string {
    $pdo->beginTransaction();
    try {
        // Lock the event row so two people can't take the last seat at the same time.
        $stmt = $pdo->prepare(
            "SELECT id, capacity FROM events
             WHERE id = ? AND status = 'published' AND event_date >= ? FOR UPDATE"
        );
        $stmt->execute([$eventId, date('Y-m-d')]);
        $event = $stmt->fetch();
        if (!$event) {
            throw new RuntimeException('This event is not open for registration.', 404);
        }

        $stmt = $pdo->prepare('SELECT id, status FROM registrations WHERE event_id = ? AND user_id = ?');
        $stmt->execute([$eventId, $userId]);
        $existing = $stmt->fetch();

        if ($existing && in_array($existing['status'], ['registered', 'attended'], true)) {
            throw new RuntimeException('You are already registered for this event.', 409);
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status IN ('registered','attended')"
        );
        $stmt->execute([$eventId]);
        if ((int)$stmt->fetchColumn() >= (int)$event['capacity']) {
            throw new RuntimeException('Sorry, this event is full.', 409);
        }

        if ($existing) {
            // Re-registering after a cancellation.
            $pdo->prepare(
                "UPDATE registrations SET status = 'registered', registered_at = NOW(), checked_in_at = NULL
                 WHERE id = ?"
            )->execute([$existing['id']]);
        } else {
            $pdo->prepare('INSERT INTO registrations (event_id, user_id) VALUES (?, ?)')
                ->execute([$eventId, $userId]);
        }
        $pdo->commit();
        return 'You are registered for this event.';
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $ex;
    }
}

// Cancels a user's active registration. Returns a success message.
// Throws RuntimeException(message, 404) when there is nothing to cancel.
function cancel_user_registration(PDO $pdo, int $eventId, int $userId): string {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT id FROM events
             WHERE id = ? AND status = 'published' AND event_date >= ? FOR UPDATE"
        );
        $stmt->execute([$eventId, date('Y-m-d')]);
        if (!$stmt->fetch()) {
            throw new RuntimeException('This event is no longer open for changes.', 404);
        }

        $stmt = $pdo->prepare(
            "UPDATE registrations SET status = 'cancelled'
             WHERE event_id = ? AND user_id = ? AND status = 'registered'"
        );
        $stmt->execute([$eventId, $userId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('You have no active registration to cancel.', 404);
        }
        $pdo->commit();
        return 'Registration cancelled.';
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $ex;
    }
}

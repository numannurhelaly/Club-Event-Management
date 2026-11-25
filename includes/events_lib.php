<?php
// Shared event queries (also reused by the AJAX search in the next step).

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

<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'organizer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: events_manage.php');
    exit;
}
csrf_check();

$user = current_user();
$id   = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT created_by FROM events WHERE id = ?');
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    flash_set('error', 'Event not found.');
} elseif ($user['role'] !== 'admin' && (int)$event['created_by'] !== $user['id']) {
    http_response_code(403);
    exit('403 Forbidden: this is not your event.');
} else {
    $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
    flash_set('success', 'Event deleted.');
}

header('Location: events_manage.php');
exit;
